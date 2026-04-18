<?php

namespace PCP_AI_Addon\Services\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use PCP_AI_Addon\Services\Settings\Settings;

/**
 * Handles admin-side initialization.
 */
class Admin_Bootstrap {

    /**
     * Register hooks for admin functionality.
     */
    public function register_hooks() {
        add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Add settings submenu under Tools > Plugin Check (if available) or under Settings.
     */
    public function register_settings_page() {
        add_options_page(
            __( 'PCP AI Add-on', 'pcp-ai-addon' ),
            __( 'PCP AI Add-on', 'pcp-ai-addon' ),
            'manage_options',
            'pcp-ai-addon-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register plugin settings fields.
     */
    public function register_settings() {
        Settings::register();
    }

    /**
     * Render settings page markup.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        Settings::render_page();
    }
}



