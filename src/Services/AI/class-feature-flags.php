<?php

namespace PCP_AI_Addon\Services\AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles feature flags and future AI capabilities.
 */
class Feature_Flags {

    /**
     * Register hooks.
     */
    public function register_hooks() {
        // Placeholder for future feature flag integrations.
        add_action( 'admin_notices', array( $this, 'render_feature_flag_notice' ) );
    }

    /**
     * Render admin notice highlighting upcoming features when enabled.
     */
    public function render_feature_flag_notice() {
        $screen = get_current_screen();

        if ( empty( $screen ) || 'settings_page_pcp-ai-addon-settings' !== $screen->id ) {
            return;
        }

        echo '<div class="notice notice-info"><p>' . esc_html__( 'Submission history insights and AI developer tooling are feature-flagged. Enable once API access and tooling are available.', 'pcp-ai-addon' ) . '</p></div>';
    }
}



