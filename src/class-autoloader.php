<?php

namespace PCP_AI_Addon;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Simple PSR-4 autoloader for the PCP AI Add-on namespace.
 */
class Autoloader {

    /**
     * Namespace prefix.
     */
    const PREFIX = 'PCP_AI_Addon\\';

    /**
     * Base directory for the namespace prefix.
     *
     * @var string
     */
    protected static $base_dir;

    /**
     * Register the autoloader.
     */
    public static function register() {
        self::$base_dir = rtrim( PCP_AI_ADDON_DIR . 'src/', '/' ) . '/';
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }

    /**
     * Autoload callback.
     *
     * @param string $class Class name.
     */
    protected static function autoload( $class ) {
        if ( 0 !== strpos( $class, self::PREFIX ) ) {
            return;
        }

        $relative_class = substr( $class, strlen( self::PREFIX ) );
        $parts          = explode( '\\', $relative_class );
        $class_name     = array_pop( $parts );
        $class_name     = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
        $sub_path       = '';

        if ( ! empty( $parts ) ) {
            $sub_path = implode( '/', $parts ) . '/';
        }

        $file = self::$base_dir . $sub_path . $class_name;

        if ( file_exists( $file ) ) {
            require $file;
        }
    }
}

