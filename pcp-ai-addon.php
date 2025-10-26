<?php
/**
 * Plugin Name: PCP AI Reviewer Add-on
 * Description: Adds AI-assisted triage, developer packaging guidance, and workflow enhancements to Plugin Check.
 * Version: 0.1.0-dev
 * Author: Copyright.sh / PCP AI Team
 * License: GPL-2.0-or-later
 */

namespace PCP_AI_Addon;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'PCP_AI_ADDON_VERSION', '0.1.0-dev' );
define( 'PCP_AI_ADDON_PLUGIN_FILE', __FILE__ );
define( 'PCP_AI_ADDON_DIR', plugin_dir_path( PCP_AI_ADDON_PLUGIN_FILE ) );
define( 'PCP_AI_ADDON_URL', plugin_dir_url( PCP_AI_ADDON_PLUGIN_FILE ) );

// Register the PSR-4 autoloader for the add-on classes.
require_once PCP_AI_ADDON_DIR . 'src/class-autoloader.php';

Autoloader::register();

/**
 * Bootstrap the plugin once plugins are loaded.
 */
function bootstrap() {
    if ( ! class_exists( '\\WordPress\\Plugin_Check\\Plugin_Main' ) ) {
        // Plugin Check is required; bail early if not present.
        return;
    }

    $container = new Plugin( new Services\Service_Registry() );
    $container->register_services();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap', 20 );



