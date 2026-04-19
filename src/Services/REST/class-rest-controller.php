<?php

namespace PCP_AI_Addon\Services\REST;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use PCP_AI_Addon\Services\Review\Review_Runner;

/**
 * REST endpoints for agent access:
 *
 * - GET  /pcp-ai/v1/review?plugin=slug[&format=md]
 *     Returns a fresh (or cached) AI review as JSON or Markdown.
 *
 * - POST /pcp-ai/v1/mcp
 *     Minimal JSON-RPC 2.0 MCP server exposing tools/list and tools/call.
 *     Tool: pcp_ai.review — wraps Review_Runner.
 *
 * All routes require the `manage_options` capability; auth uses standard
 * WordPress mechanisms (cookie nonce for the UI, Application Passwords for
 * agents).
 */
class REST_Controller {

    const NAMESPACE_V1 = 'pcp-ai/v1';
    const MCP_PROTOCOL_VERSION = '2025-06-18';

    /**
     * Register hooks.
     */
    public function register_hooks() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST routes.
     */
    public function register_routes() {
        register_rest_route(
            self::NAMESPACE_V1,
            '/review',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'permission_callback' => array( $this, 'require_manage_options' ),
                'callback'            => array( $this, 'handle_review' ),
                'args'                => array(
                    'plugin' => array(
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'format' => array(
                        'type'    => 'string',
                        'enum'    => array( 'json', 'md' ),
                        'default' => 'json',
                    ),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE_V1,
            '/mcp',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'permission_callback' => array( $this, 'require_manage_options' ),
                'callback'            => array( $this, 'handle_mcp' ),
            )
        );
    }

    /**
     * Permission callback — require site-admin capability.
     *
     * @return bool|\WP_Error
     */
    public function require_manage_options() {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        return new \WP_Error(
            'rest_forbidden',
            __( 'You need the manage_options capability to use this endpoint.', 'pcp-ai-addon' ),
            array( 'status' => rest_authorization_required_code() )
        );
    }

    /**
     * GET /review — returns an AI review for a single plugin.
     */
    public function handle_review( \WP_REST_Request $request ) {
        $plugin = (string) $request->get_param( 'plugin' );
        $format = (string) $request->get_param( 'format' );

        $result = Review_Runner::run( $plugin );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( 'md' === $format ) {
            $response = new \WP_REST_Response( Review_Runner::to_markdown( $result ) );
            $response->header( 'Content-Type', 'text/markdown; charset=utf-8' );
            return $response;
        }

        return rest_ensure_response( $result );
    }

    /**
     * POST /mcp — JSON-RPC 2.0 handler for MCP.
     */
    public function handle_mcp( \WP_REST_Request $request ) {
        $body = json_decode( $request->get_body(), true );
        if ( ! is_array( $body ) ) {
            return $this->mcp_error( null, -32700, 'Parse error' );
        }

        $id     = $body['id'] ?? null;
        $method = isset( $body['method'] ) ? (string) $body['method'] : '';
        $params = isset( $body['params'] ) && is_array( $body['params'] ) ? $body['params'] : array();

        switch ( $method ) {
            case 'initialize':
                return $this->mcp_result( $id, array(
                    'protocolVersion' => self::MCP_PROTOCOL_VERSION,
                    'serverInfo'      => array(
                        'name'    => 'pcp-ai-addon',
                        'version' => defined( 'PCP_AI_ADDON_VERSION' ) ? PCP_AI_ADDON_VERSION : '0.0.0',
                    ),
                    'capabilities'    => array(
                        'tools' => new \stdClass(),
                    ),
                ) );

            case 'tools/list':
                return $this->mcp_result( $id, array(
                    'tools' => array( $this->tool_descriptor_review() ),
                ) );

            case 'tools/call':
                return $this->mcp_handle_tools_call( $id, $params );

            case 'notifications/initialized':
            case 'ping':
                return new \WP_REST_Response( null, 204 );

            default:
                return $this->mcp_error( $id, -32601, 'Method not found: ' . $method );
        }
    }

    /**
     * Tool descriptor for tools/list.
     *
     * @return array
     */
    protected function tool_descriptor_review() {
        return array(
            'name'        => 'pcp_ai.review',
            'description' => 'Run an AI-assisted review on a WordPress plugin installed on this site. Returns severity, summary, top issues, and recommendations.',
            'inputSchema' => array(
                'type'       => 'object',
                'properties' => array(
                    'plugin'   => array(
                        'type'        => 'string',
                        'description' => 'Plugin basename ("folder/file.php") or bare folder slug.',
                    ),
                    'no_cache' => array(
                        'type'        => 'boolean',
                        'description' => 'If true, bypass the 15-minute cache and force a fresh AI call.',
                        'default'     => false,
                    ),
                ),
                'required'   => array( 'plugin' ),
            ),
        );
    }

    /**
     * Execute tools/call for pcp_ai.review.
     */
    protected function mcp_handle_tools_call( $id, array $params ) {
        $name = isset( $params['name'] ) ? (string) $params['name'] : '';
        $args = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();

        if ( 'pcp_ai.review' !== $name ) {
            return $this->mcp_error( $id, -32602, 'Unknown tool: ' . $name );
        }

        $plugin = isset( $args['plugin'] ) ? (string) $args['plugin'] : '';
        $opts   = array();
        if ( ! empty( $args['no_cache'] ) ) {
            $opts['no_cache'] = true;
        }

        $result = Review_Runner::run( $plugin, $opts );
        if ( is_wp_error( $result ) ) {
            return $this->mcp_result( $id, array(
                'isError' => true,
                'content' => array(
                    array( 'type' => 'text', 'text' => $result->get_error_message() ),
                ),
            ) );
        }

        return $this->mcp_result( $id, array(
            'isError'           => false,
            'structuredContent' => $result,
            'content'           => array(
                array( 'type' => 'text', 'text' => Review_Runner::to_markdown( $result ) ),
            ),
        ) );
    }

    /**
     * Format a successful JSON-RPC 2.0 response.
     */
    protected function mcp_result( $id, array $result ) {
        return rest_ensure_response( array(
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        ) );
    }

    /**
     * Format a JSON-RPC 2.0 error response.
     */
    protected function mcp_error( $id, $code, $message ) {
        return rest_ensure_response( array(
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => array(
                'code'    => (int) $code,
                'message' => (string) $message,
            ),
        ) );
    }
}
