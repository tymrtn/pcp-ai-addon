<?php

namespace PCP_AI_Addon\Services\Developer;

/**
 * Placeholder developer workflow wizard.
 */
class Packaging_Wizard {

    /**
     * Register hooks.
     */
    public function register_hooks() {
        add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
    }

    /**
     * Add packaging wizard page under Tools.
     */
    public function register_admin_page() {
        add_management_page(
            __( 'PCP Packaging Wizard', 'pcp-ai-addon' ),
            __( 'PCP Packaging Wizard', 'pcp-ai-addon' ),
            'manage_options',
            'pcp-ai-packaging-wizard',
            array( $this, 'render_page' )
        );
    }

    /**
     * Render packaging wizard placeholder.
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Plugin Submission Packaging Wizard', 'pcp-ai-addon' ); ?></h1>
            <p><?php esc_html_e( 'This guided workflow will help plugin authors prepare a compliant submission ZIP. Coming soon.', 'pcp-ai-addon' ); ?></p>

            <h2><?php esc_html_e( 'Planned Steps', 'pcp-ai-addon' ); ?></h2>
            <ol>
                <li><?php esc_html_e( 'Verify code quality using Plugin Check and AI triage.', 'pcp-ai-addon' ); ?></li>
                <li><?php esc_html_e( 'Ensure readme.txt is generated with required sections (short description, FAQ, changelog).', 'pcp-ai-addon' ); ?></li>
                <li><?php esc_html_e( 'Validate screenshot and asset requirements (excluded from initial ZIP).', 'pcp-ai-addon' ); ?></li>
                <li><?php esc_html_e( 'Select included files, exclude hidden/system files, and generate final ZIP.', 'pcp-ai-addon' ); ?></li>
                <li><?php esc_html_e( 'Provide submission instructions with example field values for WordPress.org.', 'pcp-ai-addon' ); ?></li>
            </ol>

            <div class="notice notice-info">
                <p><?php esc_html_e( 'Note: WordPress.org requires plugin assets (banners, screenshots, icons) to be uploaded separately via SVN after approval. The wizard will remind authors to remove asset files from the ZIP.', 'pcp-ai-addon' ); ?></p>
            </div>

            <div class="pcp-ai-coming-soon">
                <h3><?php esc_html_e( 'Coming Soon UI Elements', 'pcp-ai-addon' ); ?></h3>
                <ul>
                    <li><?php esc_html_e( 'AI-generated readme outline with policy citations.', 'pcp-ai-addon' ); ?></li>
                    <li><?php esc_html_e( 'File inclusion checklist with default sensible selections.', 'pcp-ai-addon' ); ?></li>
                    <li><?php esc_html_e( 'Zip preview with warnings for disallowed content.', 'pcp-ai-addon' ); ?></li>
                    <li><?php esc_html_e( 'Submission cheat sheet including instructions for the developer portal fields.', 'pcp-ai-addon' ); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
}



