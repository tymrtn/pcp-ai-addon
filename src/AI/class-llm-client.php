<?php

namespace PCP_AI_Addon\AI;

use PCP_AI_Addon\Services\AI\API_Key_Manager;

/**
 * LLM Client for OpenRouter API calls.
 */
class LLM_Client {

    const OPENROUTER_API_URL = 'https://openrouter.ai/api/v1/chat/completions';
    const DEFAULT_MODEL = 'x-ai/grok-code-fast-1';

    /**
     * Make an API call to OpenRouter.
     *
     * @param string $prompt The prompt to send.
     * @param array $options Optional settings (model, temperature, etc.).
     * @return array|WP_Error Response data or error.
     */
    public static function call( $prompt, $options = array() ) {
        // TODO: Remove hardcoded key - this is for testing only!
        $api_key = getenv( 'OPENROUTER_API_KEY' );
        
        if ( empty( $api_key ) ) {
            $api_key = API_Key_Manager::get_openrouter_api_key();
        }
        
        // TEMPORARY: Use hardcoded key for testing.
        if ( empty( $api_key ) || strlen( $api_key ) > 100 ) {
            $api_key = '***REMOVED-OPENROUTER-KEY***';
        }

        if ( empty( $api_key ) ) {
            return new \WP_Error( 'no_api_key', __( 'OpenRouter API key is not configured. Please add it in Settings > PCP AI Add-on.', 'pcp-ai-addon' ) );
        }

        $defaults = array(
            'model' => self::DEFAULT_MODEL,
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
                'timeout' => 60,
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
}

