<?php

namespace PCP_AI_Addon\AI;

/**
 * Analyzes plugins using AI to provide enhanced insights.
 */
class Plugin_Analyzer {

    /**
     * Analyze plugin check results with AI.
     *
     * @param array $check_results Plugin Check results array.
     * @param string $plugin_path Path to the plugin being checked.
     * @return array Enhanced analysis with AI insights.
     */
    public static function analyze( $check_results, $plugin_path ) {
        $summary = self::summarize_results( $check_results );
        $plugin_context = self::get_plugin_context( $plugin_path );

        $prompt = self::build_prompt( $summary, $plugin_context );
        $ai_response = LLM_Client::call( $prompt );

        if ( is_wp_error( $ai_response ) ) {
            return array(
                'success' => false,
                'error' => $ai_response->get_error_message(),
            );
        }

        return array(
            'success' => true,
            'ai_insights' => $ai_response['content'],
            'triage_level' => self::extract_triage_level( $ai_response['content'] ),
            'summary' => self::extract_summary( $ai_response['content'] ),
            'recommendations' => self::extract_recommendations( $ai_response['content'] ),
            'model' => $ai_response['model'],
            'usage' => $ai_response['usage'],
        );
    }

    /**
     * Summarize Plugin Check results for AI analysis.
     *
     * @param array $check_results Plugin Check results.
     * @return string Formatted summary.
     */
    private static function summarize_results( $check_results ) {
        $error_count = 0;
        $warning_count = 0;
        $issues = array();

        foreach ( $check_results as $check_slug => $result ) {
            if ( empty( $result['messages'] ) ) {
                continue;
            }

            foreach ( $result['messages'] as $message ) {
                $type = strtoupper( $message['type'] ?? 'UNKNOWN' );
                
                if ( $type === 'ERROR' ) {
                    $error_count++;
                } elseif ( $type === 'WARNING' ) {
                    $warning_count++;
                }

                $issues[] = sprintf(
                    '[%s] %s: %s (Code: %s)',
                    $type,
                    $check_slug,
                    $message['message'] ?? 'No message',
                    $message['code'] ?? 'unknown'
                );
            }
        }

        return sprintf(
            "Plugin Check Results:\n- Errors: %d\n- Warnings: %d\n\nIssues Found:\n%s",
            $error_count,
            $warning_count,
            implode( "\n", array_slice( $issues, 0, 20 ) ) // Limit to 20 issues for prompt
        );
    }

    /**
     * Get plugin context information.
     *
     * @param string $plugin_path Path to plugin.
     * @return string Plugin context.
     */
    private static function get_plugin_context( $plugin_path ) {
        $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_path, false, false );
        
        return sprintf(
            "Plugin: %s\nVersion: %s\nDescription: %s",
            $plugin_data['Name'] ?? 'Unknown',
            $plugin_data['Version'] ?? 'Unknown',
            $plugin_data['Description'] ?? 'No description'
        );
    }

    /**
     * Build AI prompt for analysis.
     *
     * @param string $summary Results summary.
     * @param string $context Plugin context.
     * @return string Prompt.
     */
    private static function build_prompt( $summary, $context ) {
        $prompt = 'You are an expert WordPress plugin reviewer analyzing Plugin Check results. Your role is to provide actionable insights to help developers fix their plugins before submitting to WordPress.org.' . "\n\n";
        $prompt .= $context . "\n\n";
        $prompt .= $summary . "\n\n";
        $prompt .= "Please provide:\n";
        $prompt .= "1. **TRIAGE LEVEL**: Rate as 'Trivial', 'Minor', 'Moderate', or 'Critical' based on the severity of issues\n";
        $prompt .= "2. **SUMMARY**: A brief 2-3 sentence overview of the plugin's compliance status\n";
        $prompt .= "3. **ISSUES**: List the top 3-5 most important issues that must be fixed, focusing epecially on policy or submissions requirements\n";
        $prompt .= "4. **RECOMMENDATIONS**: Specific, actionable steps to resolve the issues\n";
        $prompt .= 'Format your response clearly with these sections marked.';

        return $prompt;
    }

    /**
     * Extract triage level from AI response.
     *
     * @param string $content AI response content.
     * @return string Triage level.
     */
    private static function extract_triage_level( $content ) {
        if ( preg_match( '/TRIAGE\s+LEVEL[:\s]+(\w+)/i', $content, $matches ) ) {
            return strtoupper( $matches[1] );
        }
        return 'MODERATE';
    }

    /**
     * Extract summary from AI response.
     *
     * @param string $content AI response content.
     * @return string Summary.
     */
    private static function extract_summary( $content ) {
        if ( preg_match( '/EXECUTIVE\s+SUMMARY[:\s]+(.+?)(?=\n\n|\*\*|$)/is', $content, $matches ) ) {
            return trim( $matches[1] );
        }
        return '';
    }

    /**
     * Extract recommendations from AI response.
     *
     * @param string $content AI response content.
     * @return array Recommendations.
     */
    private static function extract_recommendations( $content ) {
        if ( preg_match( '/RECOMMENDATIONS[:\s]+(.+?)(?=\n\n\*\*|$)/is', $content, $matches ) ) {
            $recs_text = trim( $matches[1] );
            // Split by numbered list items
            preg_match_all( '/\d+\.\s*(.+?)(?=\d+\.|$)/s', $recs_text, $items );
            return array_map( 'trim', $items[1] );
        }
        return array();
    }
}



