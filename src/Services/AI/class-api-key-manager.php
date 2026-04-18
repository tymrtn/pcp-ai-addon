<?php

namespace PCP_AI_Addon\Services\AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use PCP_AI_Addon\Services\Settings\Settings;

/**
 * Manages API key retrieval for AI services.
 */
class API_Key_Manager {

    /**
     * Get the OpenRouter API key from settings.
     *
     * @return string|null The API key or null if not set.
     */
    public static function get_openrouter_api_key() {
        $settings = Settings::get_settings();
        
        if ( empty( $settings['openrouter_api_key'] ) ) {
            return null;
        }

        // Decrypt the stored API key.
        return self::decrypt_api_key( $settings['openrouter_api_key'] );
    }

    /**
     * Check if API key is configured.
     *
     * @return bool True if API key is available.
     */
    public static function is_api_key_configured() {
        return ! empty( self::get_openrouter_api_key() );
    }

    /**
     * Decrypt API key using WordPress salts.
     *
     * Public so `Settings` can reuse it without duplicating the decrypt logic.
     *
     * @param string $encrypted_api_key The encrypted API key.
     * @return string Decrypted API key.
     */
    public static function decrypt_api_key( $encrypted_api_key ) {
        if ( empty( $encrypted_api_key ) ) {
            return '';
        }

        $key       = wp_salt( 'auth' );
        $iv        = substr( hash( 'sha256', wp_salt( 'secure_auth' ) ), 0, 16 );
        $encrypted = base64_decode( $encrypted_api_key );
        $decrypted = openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );

        return $decrypted ?: '';
    }
}


