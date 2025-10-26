<?php

namespace PCP_AI_Addon\Checks;

use PCP_AI_Addon\AI\Plugin_Analyzer;
use PCP_AI_Addon\Services\AI\API_Key_Manager;
use WordPress\Plugin_Check\Checker\Check_Result;
use WordPress\Plugin_Check\Checker\Checks\Abstract_File_Check;
use WordPress\Plugin_Check\Traits\Amend_Check_Result;
use WordPress\Plugin_Check\Traits\Stable_Check;

/**
 * AI-powered review check that enhances Plugin Check results.
 */
class AI_Review_Check extends Abstract_File_Check {
    use Amend_Check_Result;
    use Stable_Check;

    /**
     * Gets the categories for the check.
     *
     * @return array<string> The categories.
     */
    public function get_categories() {
        return array( 'ai_insights' );
    }

    /**
     * Returns an associative array of arguments to pass to the check.
     *
     * @return array The check arguments.
     */
    public function get_args() {
        return array();
    }

    /**
     * Runs the check on files.
     *
     * @param Check_Result $result The check result object.
     * @param array $files List of files to check.
     */
    protected function check_files( Check_Result $result, array $files ) {
        // Get the plugin being checked.
        $plugin = $result->plugin();
        $plugin_path = $plugin->basename();
        $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_path, false, false );

        $file_count = count( $files );
        $php_files = self::filter_files_by_extension( $files, 'php' );
        $php_count = count( $php_files );

        // Get existing errors and warnings - check both current result and accumulated results.
        $errors = $result->get_errors();
        $warnings = $result->get_warnings();

        // Check for accumulated results from previous check runs (for UI multi-check scenario).
        $accumulated = $this->get_accumulated_results( $plugin_path );
        if ( ! empty( $accumulated ) ) {
            $errors = array_merge_recursive( $errors, $accumulated['errors'] );
            $warnings = array_merge_recursive( $warnings, $accumulated['warnings'] );

            // Clean up the accumulated results after using them.
            $this->clear_accumulated_results( $plugin_path );
        }

        $error_count = $this->count_messages( $errors );
        $warning_count = $this->count_messages( $warnings );

        // Build context for AI analysis.
        $prompt = $this->build_ai_prompt( $plugin_data, $errors, $warnings, $file_count, $php_count );

        // Call AI for analysis.
        $ai_response = \PCP_AI_Addon\AI\LLM_Client::call( $prompt );

        if ( is_wp_error( $ai_response ) ) {
            $result->add_message(
                false,
                sprintf(
                    /* translators: %s: Error message from the AI API */
                    __( 'AI Review: Analysis failed. %s', 'pcp-ai-addon' ),
                    $ai_response->get_error_message()
                ),
                array(
                    'code' => 'ai_analysis_failed',
                )
            );
            return;
        }

        // Parse AI response into structured findings.
        $ai_content = $ai_response['content'];
        $severity_level = $this->extract_severity_level( $ai_content );
        $summary = $this->extract_summary( $ai_content );
        $issues = $this->extract_issues( $ai_content );
        $recommendations = $this->extract_recommendations( $ai_content );

        // Add AI summary as first message.
        $summary_message = sprintf(
            "AI Review Summary [%s severity]: %s",
            $severity_level,
            $summary ?: 'Analysis complete.'
        );

        $result->add_message(
            false,
            $summary_message,
            array(
                'code' => 'ai_summary',
            )
        );

        // Add each issue as a separate warning or error.
        foreach ( $issues as $i => $issue ) {
            $is_error = ( $severity_level === 'CRITICAL' );
            
            $result->add_message(
                $is_error,
                sprintf( 'AI Finding: %s', trim( $issue ) ),
                array(
                    'code' => 'ai_finding_' . ( $i + 1 ),
                )
            );
        }

        // Add each recommendation as a separate info message.
        foreach ( $recommendations as $i => $rec ) {
            $result->add_message(
                false,
                sprintf( 'AI Recommendation: %s', trim( $rec ) ),
                array(
                    'code' => 'ai_recommendation_' . ( $i + 1 ),
                )
            );
        }
    }

    /**
     * Build AI prompt for plugin analysis.
     *
     * @param array $plugin_data Plugin metadata.
     * @param array $errors Error messages.
     * @param array $warnings Warning messages.
     * @param int $file_count Total file count.
     * @param int $php_count PHP file count.
     * @return string Prompt.
     */
    private function build_ai_prompt( $plugin_data, $errors, $warnings, $file_count, $php_count ) {
        $error_count = count( $errors, COUNT_RECURSIVE ) - count( $errors );
        $warning_count = count( $warnings, COUNT_RECURSIVE ) - count( $warnings );

        // Build a summary of key issues.
        $issue_summary = $this->summarize_issues( $errors, $warnings );

        $prompt = sprintf(
            "You are an expert WordPress plugin reviewer. Analyze the Plugin Check results and provide actionable guidance.\n\n" .
            "PLUGIN INFO:\n" .
            "- Name: %s\n" .
            "- Version: %s\n" .
            "- Files: %d total (%d PHP files)\n\n" .
            "PLUGIN CHECK RESULTS:\n" .
            "- Errors: %d\n" .
            "- Warnings: %d\n\n" .
            "%s\n\n" .
            "IMPORTANT:\n" .
            "- Only analyze issues that were actually detected by Plugin Check\n" .
            "- Do NOT speculate about theoretical problems or best practices not flagged\n" .
            "- Focus on explaining detected issues and how to fix them\n" .
            "- If no issues were found, note that the plugin appears compliant\n\n" .
            "Provide your analysis in this EXACT format:\n\n" .
            "**TRIAGE LEVEL**: [TRIVIAL|MINOR|MODERATE|CRITICAL]\n\n" .
            "**SUMMARY**: 2-3 sentences explaining the detected issues and overall plugin quality.\n\n" .
            "**TOP ISSUES** (only if errors/warnings exist - explain the most critical ones):\n" .
            "1. Issue explanation\n" .
            "2. Issue explanation\n\n" .
            "**RECOMMENDATIONS** (how to fix the detected issues):\n" .
            "1. Specific fix\n" .
            "2. Specific fix\n\n" .
            "If no issues exist, skip TOP ISSUES section and provide brief positive feedback.",
            $plugin_data['Name'] ?? 'Unknown',
            $plugin_data['Version'] ?? 'Unknown',
            $file_count,
            $php_count,
            $error_count,
            $warning_count,
            $issue_summary
        );

        return $prompt;
    }

    /**
     * Summarize issues for AI prompt.
     *
     * @param array $errors Errors array.
     * @param array $warnings Warnings array.
     * @return string Summary.
     */
    private function summarize_issues( $errors, $warnings ) {
        $all_issues = array();

        // Extract error messages.
        foreach ( $errors as $file => $lines ) {
            foreach ( $lines as $line => $columns ) {
                foreach ( $columns as $column => $messages ) {
                    foreach ( $messages as $msg ) {
                        $all_issues[] = sprintf(
                            "[ERROR] %s:%d - %s (code: %s)",
                            $file,
                            $line,
                            substr( $msg['message'], 0, 150 ),
                            $msg['code'] ?? 'unknown'
                        );
                    }
                }
            }
        }

        // Extract warning messages.
        foreach ( $warnings as $file => $lines ) {
            foreach ( $lines as $line => $columns ) {
                foreach ( $columns as $column => $messages ) {
                    foreach ( $messages as $msg ) {
                        $all_issues[] = sprintf(
                            "[WARNING] %s:%d - %s (code: %s)",
                            $file,
                            $line,
                            substr( $msg['message'], 0, 150 ),
                            $msg['code'] ?? 'unknown'
                        );
                    }
                }
            }
        }

        if ( empty( $all_issues ) ) {
            return "KEY ISSUES:\nNo significant issues found! Plugin appears to be well-coded.";
        }

        // Limit to first 15 issues to keep prompt manageable.
        $issue_list = array_slice( $all_issues, 0, 15 );
        
        return "KEY ISSUES:\n" . implode( "\n", $issue_list );
    }

    /**
     * Extract severity level from AI response.
     *
     * @param string $content AI response.
     * @return string Severity level.
     */
    private function extract_severity_level( $content ) {
        if ( preg_match( '/\*\*TRIAGE\s+LEVEL\*\*[:\s]*(\w+)/i', $content, $matches ) ) {
            return strtoupper( $matches[1] );
        }
        return 'MODERATE';
    }

    /**
     * Extract summary from AI response.
     *
     * @param string $content AI response.
     * @return string Summary.
     */
    private function extract_summary( $content ) {
        if ( preg_match( '/\*\*SUMMARY\*\*[:\s]*(.+?)(?=\n\n\*\*|$)/is', $content, $matches ) ) {
            return trim( $matches[1] );
        }
        return '';
    }

    /**
     * Extract issues from AI response.
     *
     * @param string $content AI response.
     * @return array Issues.
     */
    private function extract_issues( $content ) {
        if ( preg_match( '/\*\*TOP\s+ISSUES\*\*.*?:\s*\n(.+?)(?=\n\n\*\*|$)/is', $content, $matches ) ) {
            $issues_text = trim( $matches[1] );
            preg_match_all( '/\d+\.\s*(.+?)(?=\n\d+\.|$)/s', $issues_text, $items );
            return array_map( 'trim', $items[1] );
        }
        return array();
    }

    /**
     * Extract recommendations from AI response.
     *
     * @param string $content AI response.
     * @return array Recommendations.
     */
    private function extract_recommendations( $content ) {
        if ( preg_match( '/\*\*RECOMMENDATIONS\*\*[:\s]*\n(.+?)(?=\n\n\*\*|$)/is', $content, $matches ) ) {
            $recs_text = trim( $matches[1] );
            preg_match_all( '/\d+\.\s*(.+?)(?=\n\d+\.|$)/s', $recs_text, $items );
            return array_map( 'trim', $items[1] );
        }
        return array();
    }

    /**
     * Add AI summary to results.
     *
     * @param Check_Result $result Check result object.
     * @param array $analysis AI analysis data.
     */
    private function add_ai_summary( $result, $analysis ) {
        $triage_level = $analysis['triage_level'] ?? 'MODERATE';
        $summary = $analysis['summary'] ?? '';

        $message = sprintf(
            "🤖 **AI Review Summary** (Triage: %s)\n\n%s",
            $triage_level,
            $summary
        );

        // Use error level for CRITICAL, warning for MODERATE/MINOR.
        $is_error = ( $triage_level === 'CRITICAL' );

        $result->add_message(
            $is_error,
            $message,
            array(
                'code' => 'ai_triage_' . strtolower( $triage_level ),
                'type' => $is_error ? 'error' : 'warning',
            )
        );
    }

    /**
     * Add AI recommendations to results.
     *
     * @param Check_Result $result Check result object.
     * @param array $analysis AI analysis data.
     */
    private function add_ai_recommendations( $result, $analysis ) {
        $recommendations = $analysis['recommendations'] ?? array();

        if ( empty( $recommendations ) ) {
            return;
        }

        $rec_text = "🎯 **AI Recommendations:**\n\n";
        foreach ( $recommendations as $i => $rec ) {
            $rec_text .= sprintf( "%d. %s\n", $i + 1, $rec );
        }

        $result->add_message(
            false,
            $rec_text,
            array(
                'code' => 'ai_recommendations',
                'type' => 'warning',
            )
        );
    }

    /**
     * Gets the description for the check.
     *
     * @return string The check description.
     */
    public function get_description(): string {
        return __( 'Provides AI-powered analysis and recommendations based on Plugin Check results.', 'pcp-ai-addon' );
    }

    /**
     * Gets the documentation URL for the check.
     *
     * @return string The documentation URL.
     */
    public function get_documentation_url(): string {
        return '';
    }

    /**
     * Get accumulated results from previous check runs.
     *
     * @param string $plugin_path Plugin path.
     * @return array Accumulated results or empty array.
     */
    private function get_accumulated_results( $plugin_path ) {
        $transient_key = 'pcp_ai_accumulated_' . md5( $plugin_path );
        $accumulated = get_transient( $transient_key );

        return is_array( $accumulated ) ? $accumulated : array();
    }

    /**
     * Clear accumulated results after use.
     *
     * @param string $plugin_path Plugin path.
     */
    private function clear_accumulated_results( $plugin_path ) {
        $transient_key = 'pcp_ai_accumulated_' . md5( $plugin_path );
        delete_transient( $transient_key );
    }

    /**
     * Count total messages in nested array structure.
     *
     * @param array $messages Nested messages array.
     * @return int Total count.
     */
    private function count_messages( $messages ) {
        $count = 0;

        foreach ( $messages as $lines ) {
            foreach ( $lines as $columns ) {
                $count += count( $columns );
            }
        }

        return $count;
    }
}

