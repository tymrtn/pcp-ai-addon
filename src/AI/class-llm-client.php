<?php

namespace PCP_AI_Addon\AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use PCP_AI_Addon\Services\AI\API_Key_Manager;

/**
 * LLM Client for OpenRouter API calls.
 */
class LLM_Client {

    const OPENROUTER_API_URL = 'https://openrouter.ai/api/v1/chat/completions';
    const DEFAULT_MODEL      = 'anthropic/claude-opus-4.7';
    const RATE_LIMIT_WINDOW  = 60;
    const RATE_LIMIT_MAX     = 10;

    /**
     * Make an API call to OpenRouter.
     *
     * @param string $prompt The prompt to send.
     * @param array $options Optional settings (model, temperature, etc.).
     * @return array|\WP_Error Response data or error.
     */
    public static function call( $prompt, $options = array() ) {
        $rate_limit = self::check_rate_limit();
        if ( is_wp_error( $rate_limit ) ) {
            return $rate_limit;
        }

        $api_key = getenv( 'OPENROUTER_API_KEY' );

        if ( empty( $api_key ) ) {
            $api_key = API_Key_Manager::get_openrouter_api_key();
        }

        if ( empty( $api_key ) ) {
            return new \WP_Error( 'no_api_key', __( 'OpenRouter API key is not configured. Please add it in Settings > PCP AI Add-on.', 'pcp-ai-addon' ) );
        }

        $defaults = array(
            'model' => \PCP_AI_Addon\Services\Settings\Settings::get_effective_model(),
            'temperature' => 0.2,
            'max_tokens' => 10000,
        );

        $settings = wp_parse_args( $options, $defaults );

        $body = array(
            'model' => $settings['model'],
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt,
                ),
            ),
            'temperature' => $settings['temperature'],
            'max_tokens' => $settings['max_tokens'],
        );

        $response = wp_remote_post(
            self::OPENROUTER_API_URL,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => home_url(),
                ),
                'body' => wp_json_encode( $body ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );

        if ( $status_code !== 200 ) {
            return new \WP_Error(
                'api_error',
                sprintf(
                    /* translators: %s: Error message from the OpenRouter API */
                    __( 'OpenRouter API returned error: %s', 'pcp-ai-addon' ),
                    isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown error'
                ),
                array( 'status' => $status_code, 'data' => $data )
            );
        }

        if ( empty( $data['choices'][0]['message']['content'] ) ) {
            return new \WP_Error( 'invalid_response', __( 'Invalid response from OpenRouter API.', 'pcp-ai-addon' ) );
        }

        return array(
            'content' => $data['choices'][0]['message']['content'],
            'model' => $data['model'] ?? $settings['model'],
            'usage' => $data['usage'] ?? array(),
        );
    }

    /**
     * Per-user transient rate limit. Skipped in WP-CLI.
     *
     * @return true|\WP_Error
     */
    protected static function check_rate_limit() {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return true;
        }

        $user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
        $bucket  = 'pcp_ai_rl_' . $user_id;
        $count   = (int) get_transient( $bucket );

        if ( $count >= self::RATE_LIMIT_MAX ) {
            return new \WP_Error(
                'rate_limited',
                sprintf(
                    /* translators: 1: max calls, 2: window in seconds */
                    __( 'AI review rate limit reached (%1$d calls per %2$d seconds). Try again shortly.', 'pcp-ai-addon' ),
                    self::RATE_LIMIT_MAX,
                    self::RATE_LIMIT_WINDOW
                )
            );
        }

        set_transient( $bucket, $count + 1, self::RATE_LIMIT_WINDOW );
        return true;
    }

    /**
     * Sanitize untrusted strings before interpolation into prompts.
     *
     * Strips newlines and control chars, truncates, and wraps in
     * <untrusted>...</untrusted> so the model can distinguish data
     * from instructions (prompt-injection defense for plugin metadata).
     *
     * @param string $value Raw value.
     * @param int    $max   Max characters (default 200).
     * @return string Safe string for prompt interpolation.
     */
    public static function sanitize_for_prompt( $value, $max = 200 ) {
        $value = is_scalar( $value ) ? (string) $value : '';
        $value = preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $value );
        $value = preg_replace( '/\s+/u', ' ', $value );
        $value = trim( $value );

        if ( function_exists( 'mb_substr' ) ) {
            $value = mb_substr( $value, 0, $max );
        } else {
            $value = substr( $value, 0, $max );
        }

        if ( '' === $value ) {
            $value = 'Unknown';
        }

        return '<untrusted>' . $value . '</untrusted>';
    }
}

