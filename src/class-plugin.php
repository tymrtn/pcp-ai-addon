<?php

namespace PCP_AI_Addon;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use PCP_AI_Addon\Services\Service_Registry;

/**
 * Primary plugin orchestrator.
 */
class Plugin {

    /**
     * Service registry instance.
     *
     * @var Service_Registry
     */
    protected $services;

    /**
     * Plugin constructor.
     *
     * @param Service_Registry $services Service registry instance.
     */
    public function __construct( Service_Registry $services ) {
        $this->services = $services;
    }

    /**
     * Register plugin services.
     */
    public function register_services() {
        $this->services->register( new Services\Admin\Admin_Bootstrap() );
        $this->services->register( new Services\Developer\Packaging_Wizard() );
        $this->services->register( new Services\AI\Feature_Flags() );

        // Register AI checks with Plugin Check.
        add_filter( 'wp_plugin_check_checks', array( $this, 'register_ai_checks' ) );

        // Hook to accumulate results per category for AI analysis (for UI).
        add_action( 'wp_ajax_plugin_check_run_checks', array( $this, 'setup_result_accumulation' ), 1 );
    }

    /**
     * Register AI checks with Plugin Check.
     *
     * @param array $checks Existing checks.
     * @return array Modified checks.
     */
    public function register_ai_checks( $checks ) {
        // Require all AI check classes.
        require_once PCP_AI_ADDON_DIR . 'src/Checks/class-ai-review-general.php';
        require_once PCP_AI_ADDON_DIR . 'src/Checks/class-ai-review-security.php';
        require_once PCP_AI_ADDON_DIR . 'src/Checks/class-ai-review-performance.php';
        require_once PCP_AI_ADDON_DIR . 'src/Checks/class-ai-review-plugin-repo.php';
        require_once PCP_AI_ADDON_DIR . 'src/Checks/class-ai-review-accessibility.php';

        // Register one AI check per category.
        $checks['ai_review_general'] = new Checks\AI_Review_General();
        $checks['ai_review_security'] = new Checks\AI_Review_Security();
        $checks['ai_review_performance'] = new Checks\AI_Review_Performance();
        $checks['ai_review_plugin_repo'] = new Checks\AI_Review_Plugin_Repo();
        $checks['ai_review_accessibility'] = new Checks\AI_Review_Accessibility();

        return $checks;
    }

    /**
     * Setup result accumulation for AI analysis per category.
     *
     * This runs early in the AJAX call to setup output buffering.
     */
    public function setup_result_accumulation() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Get the check slug from POST data.
        $checks = filter_input( INPUT_POST, 'checks', FILTER_DEFAULT, FILTER_FORCE_ARRAY );

        // Don't accumulate if an AI check is running (it's the consumer, not producer).
        if ( empty( $checks ) || $this->is_ai_check( $checks[0] ) ) {
            return;
        }

        // Use output buffer to capture the JSON response.
        ob_start( array( $this, 'intercept_and_store_results' ) );
    }

    /**
     * Check if the given check slug is an AI review check.
     *
     * @param string $check_slug Check slug.
     * @return bool True if it's an AI check.
     */
    private function is_ai_check( $check_slug ) {
        return str_starts_with( $check_slug, 'ai_review_' );
    }

    /**
     * Intercept JSON response, store results per category, and return unchanged response.
     *
     * @param string $buffer The output buffer content (JSON response).
     * @return string The unmodified buffer.
     */
    public function intercept_and_store_results( $buffer ) {
        // Decode the JSON response.
        $response = json_decode( $buffer, true );

        // Only process successful responses with data.
        if ( ! is_array( $response ) || ! isset( $response['success'] ) || ! $response['success'] ) {
            return $buffer;
        }

        $data = $response['data'] ?? array();
        if ( empty( $data['errors'] ) && empty( $data['warnings'] ) ) {
            return $buffer;
        }

        $plugin = filter_input( INPUT_POST, 'plugin', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        if ( empty( $plugin ) ) {
            return $buffer;
        }

        // Determine which category this check belongs to.
        $checks = filter_input( INPUT_POST, 'checks', FILTER_DEFAULT, FILTER_FORCE_ARRAY );
        if ( empty( $checks ) ) {
            return $buffer;
        }

        $check_slug = $checks[0];
        $category = $this->get_check_category( $check_slug );
        if ( empty( $category ) ) {
            return $buffer;
        }

        // Store results per category.
        $transient_key = 'pcp_ai_cat_' . $category . '_' . md5( $plugin );
        $accumulated = get_transient( $transient_key );

        if ( ! is_array( $accumulated ) ) {
            $accumulated = array(
                'errors'   => array(),
                'warnings' => array(),
            );
        }

        // Merge new results into accumulated results.
        if ( ! empty( $data['errors'] ) ) {
            $accumulated['errors'] = array_merge_recursive( $accumulated['errors'], $data['errors'] );
        }

        if ( ! empty( $data['warnings'] ) ) {
            $accumulated['warnings'] = array_merge_recursive( $accumulated['warnings'], $data['warnings'] );
        }

        // Store for 5 minutes (enough for UI check sequence).
        set_transient( $transient_key, $accumulated, 300 );

        // Return the unmodified buffer.
        return $buffer;
    }

    /**
     * Get the category for a given check slug.
     *
     * @param string $check_slug Check slug.
     * @return string|null Category name or null.
     */
    private function get_check_category( $check_slug ) {
        // Map common check prefixes to categories.
        $category_map = array(
            'i18n_'                => 'general',
            'enqueued_'            => 'performance',
            'performant_'          => 'performance',
            'non_blocking_'        => 'performance',
            'code_obfuscation'     => 'plugin_repo',
            'file_type'            => 'plugin_repo',
            'localhost'            => 'plugin_repo',
            'no_unfiltered'        => 'plugin_repo',
            'offloading'           => 'plugin_repo',
            'plugin_'              => 'plugin_repo',
            'trademarks'           => 'plugin_repo',
            'setting_sanitization' => 'plugin_repo',
            'prefixing'            => 'plugin_repo',
            'direct_db'            => 'security',
            'late_escaping'        => 'security',
            'safe_redirect'        => 'security',
        );

        foreach ( $category_map as $prefix => $category ) {
            if ( str_starts_with( $check_slug, $prefix ) ) {
                return $category;
            }
        }

        // Default to general if unknown.
        return 'general';
    }
}

