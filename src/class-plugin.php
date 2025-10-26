<?php

namespace PCP_AI_Addon;

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

        // Register AI checks and categories with Plugin Check.
        add_filter( 'wp_plugin_check_categories', array( $this, 'register_ai_categories' ) );
        add_filter( 'wp_plugin_check_checks', array( $this, 'register_ai_checks' ) );

        // Hook to accumulate results from multiple check runs (for UI).
        add_action( 'wp_ajax_plugin_check_run_checks', array( $this, 'setup_result_accumulation' ), 1 );
    }

    /**
     * Register AI categories with Plugin Check.
     *
     * @param array $categories Existing categories.
     * @return array Modified categories.
     */
    public function register_ai_categories( $categories ) {
        $categories['ai_insights'] = __( 'AI Insights', 'pcp-ai-addon' );
        return $categories;
    }

    /**
     * Register AI checks with Plugin Check.
     *
     * @param array $checks Existing checks.
     * @return array Modified checks.
     */
    public function register_ai_checks( $checks ) {
        require_once PCP_AI_ADDON_DIR . 'src/Checks/class-ai-review-check.php';

        $checks['ai_review'] = new Checks\AI_Review_Check();

        return $checks;
    }

    /**
     * Setup result accumulation for AI analysis across multiple UI check runs.
     *
     * This runs early in the AJAX call to setup a shutdown hook.
     */
    public function setup_result_accumulation() {
        // Get the check slug from POST data.
        $checks = filter_input( INPUT_POST, 'checks', FILTER_DEFAULT, FILTER_FORCE_ARRAY );

        // Don't accumulate if AI check is running (it's the consumer, not producer).
        if ( empty( $checks ) || in_array( 'ai_review', $checks, true ) ) {
            return;
        }

        // Use output buffer to capture the JSON response.
        ob_start( array( $this, 'intercept_and_store_results' ) );
    }

    /**
     * Intercept JSON response, store results in transient, and return unchanged response.
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

        $transient_key = 'pcp_ai_accumulated_' . md5( $plugin );
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
}

