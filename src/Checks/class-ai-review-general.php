<?php

namespace PCP_AI_Addon\Checks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use PCP_AI_Addon\AI\LLM_Client;
use WordPress\Plugin_Check\Checker\Check_Result;
use WordPress\Plugin_Check\Checker\Checks\Abstract_File_Check;
use WordPress\Plugin_Check\Traits\Amend_Check_Result;
use WordPress\Plugin_Check\Traits\Stable_Check;

/**
 * AI-powered review check for General category.
 */
class AI_Review_General extends Abstract_File_Check {
	use Amend_Check_Result;
	use Stable_Check;

	/**
	 * Gets the categories for the check.
	 *
	 * @return array<string> The categories.
	 */
	public function get_categories() {
		return array( 'general' );
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
		$this->run_ai_analysis( $result, $files, 'general', 'General best practices and coding standards' );
	}

	/**
	 * Run AI analysis for this category.
	 *
	 * @param Check_Result $result The check result object.
	 * @param array $files List of files to check.
	 * @param string $category Category name.
	 * @param string $focus Focus description.
	 */
	protected function run_ai_analysis( Check_Result $result, array $files, $category, $focus ) {
		// Get the plugin being checked.
		$plugin = $result->plugin();
		$plugin_path = $plugin->basename();
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_path, false, false );

		// Harden metadata before it reaches the LLM prompt (plugin headers are attacker-controlled).
		foreach ( array( 'Name', 'Version', 'Description' ) as $field ) {
			if ( isset( $plugin_data[ $field ] ) ) {
				$plugin_data[ $field ] = LLM_Client::sanitize_for_prompt( $plugin_data[ $field ] );
			}
		}

		$file_count = count( $files );
		$php_files = self::filter_files_by_extension( $files, 'php' );
		$php_count = count( $php_files );

		// Get existing errors and warnings from the result object.
		$errors = $result->get_errors();
		$warnings = $result->get_warnings();

		// Check for accumulated results from previous check runs in this category (for UI multi-check scenario).
		$accumulated = $this->get_accumulated_results( $plugin_path, $category );
		if ( ! empty( $accumulated ) ) {
			$errors = array_merge_recursive( $errors, $accumulated['errors'] );
			$warnings = array_merge_recursive( $warnings, $accumulated['warnings'] );

			// Clean up the accumulated results after using them.
			$this->clear_accumulated_results( $plugin_path, $category );
		}

		// Skip AI analysis if there are errors in this category.
		// AI only provides insights for categories that passed error checks (warnings are acceptable).
		if ( ! empty( $errors ) ) {
			return;
		}

		$error_count = $this->count_messages( $errors );
		$warning_count = $this->count_messages( $warnings );

		// Build context for AI analysis.
		$prompt = $this->build_ai_prompt( $plugin_data, $errors, $warnings, $file_count, $php_count, $category, $focus );

		// Call AI for analysis.
		$ai_response = \PCP_AI_Addon\AI\LLM_Client::call( $prompt );

		if ( is_wp_error( $ai_response ) ) {
			$result->add_message(
				false,
				sprintf(
					/* translators: %s: Error message from the AI API */
					__( 'AI Review (%s): Analysis failed. %s', 'pcp-ai-addon' ),
					ucfirst( $category ),
					$ai_response->get_error_message()
				),
				array(
					'code' => 'ai_analysis_failed_' . $category,
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

		// Add AI summary with severity badge.
		$severity_badges = array(
			'CRITICAL' => '🔴',
			'MODERATE' => '🟡',
			'MINOR'    => '🟢',
			'TRIVIAL'  => '✅',
		);
		$severity_emoji = $severity_badges[ $severity_level ] ?? '✅';

		$result->add_message(
			false,
			sprintf(
				'%s AI Review (%s) [%s]: %s',
				$severity_emoji,
				ucfirst( str_replace( '_', ' ', $category ) ),
				$severity_level,
				$summary
			),
			array(
				'code' => 'ai_summary_' . $category,
			)
		);

		// Add findings/observations if any.
		if ( ! empty( $issues ) ) {
			$label = ( $warning_count > 0 ) ? "Key Observations" : "Best Practice Suggestions";
			$findings_text = $label . ":\n";
			foreach ( $issues as $idx => $issue ) {
				$findings_text .= sprintf( "%d. %s\n", $idx + 1, $issue );
			}

			$result->add_message(
				false,
				$findings_text,
				array(
					'code' => 'ai_findings_' . $category,
				)
			);
		}

		// Add recommendations if any.
		if ( ! empty( $recommendations ) ) {
			$recs_label = ( $warning_count > 0 ) ? "Recommended Improvements" : "Enhancement Suggestions";
			$recs_text = $recs_label . ":\n";
			foreach ( $recommendations as $idx => $rec ) {
				$recs_text .= sprintf( "%d. %s\n", $idx + 1, $rec );
			}

			$result->add_message(
				false,
				$recs_text,
				array(
					'code' => 'ai_recommendations_' . $category,
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
	 * @param string $category Category name.
	 * @param string $focus Focus description.
	 * @return string Prompt.
	 */
	protected function build_ai_prompt( $plugin_data, $errors, $warnings, $file_count, $php_count, $category, $focus ) {
		$error_count = $this->count_messages( $errors );
		$warning_count = $this->count_messages( $warnings );

		// Build a summary of warnings (no errors since we skip AI if errors exist).
		$issue_summary = $this->summarize_issues( $errors, $warnings );

		$has_warnings = ! empty( $warnings );

		if ( $has_warnings ) {
			$prompt = sprintf(
				"You are an expert WordPress plugin reviewer providing best practices guidance for the %s category (%s).\n\n" .
				"PLUGIN INFO:\n" .
				"- Name: %s\n" .
				"- Version: %s\n" .
				"- Files: %d total (%d PHP files)\n\n" .
				"CATEGORY: %s\n" .
				"FOCUS: %s\n\n" .
				"PLUGIN CHECK RESULTS (%s CATEGORY):\n" .
				"- Errors: 0 (passed!)\n" .
				"- Warnings: %d\n\n" .
				"%s\n\n" .
				"CONTEXT:\n" .
				"This plugin passed all error-level checks in the %s category. Your role is to review the warnings and provide actionable guidance for improvement.\n\n" .
				"IMPORTANT:\n" .
				"- Focus ONLY on %s category warnings\n" .
				"- Explain the implications of each warning\n" .
				"- Provide specific improvement recommendations with code examples\n" .
				"- Frame as improvements, not critical fixes\n\n" .
				"Provide your analysis in this EXACT format:\n\n" .
				"**TRIAGE LEVEL**: [TRIVIAL|MINOR|MODERATE] (use TRIVIAL or MINOR since no errors exist)\n\n" .
				"**SUMMARY**: 2-3 sentences about potential improvements for the %s warnings.\n\n" .
				"**KEY OBSERVATIONS** (if any exist):\n" .
				"1. Observation about warning\n" .
				"2. Observation about warning\n\n" .
				"**RECOMMENDATIONS** (specific improvements):\n" .
				"1. Specific improvement with code example\n" .
				"2. Specific improvement with code example",
				strtoupper( $category ),
				$focus,
				$plugin_data['Name'] ?? 'Unknown',
				$plugin_data['Version'] ?? 'Unknown',
				$file_count,
				$php_count,
				strtoupper( $category ),
				$focus,
				strtoupper( $category ),
				$warning_count,
				$issue_summary,
				$category,
				$category,
				$category
			);
		} else {
			$prompt = sprintf(
				"You are an expert WordPress plugin reviewer providing best practices guidance for the %s category (%s).\n\n" .
				"PLUGIN INFO:\n" .
				"- Name: %s\n" .
				"- Version: %s\n" .
				"- Files: %d total (%d PHP files)\n\n" .
				"CATEGORY: %s\n" .
				"FOCUS: %s\n\n" .
				"PLUGIN CHECK RESULTS (%s CATEGORY):\n" .
				"- Errors: 0 (passed!)\n" .
				"- Warnings: 0 (passed!)\n\n" .
				"CONTEXT:\n" .
				"This plugin passed ALL checks in the %s category with no errors or warnings. Your role is to provide expert-level best practices and potential enhancements.\n\n" .
				"IMPORTANT:\n" .
				"- Focus ONLY on %s category best practices\n" .
				"- Suggest advanced optimizations, edge cases to consider, or modern WordPress patterns\n" .
				"- Keep recommendations practical and high-value\n" .
				"- Limit to 2-3 key recommendations\n\n" .
				"Provide your analysis in this EXACT format:\n\n" .
				"**TRIAGE LEVEL**: TRIVIAL (no issues found)\n\n" .
				"**SUMMARY**: 1-2 sentences acknowledging clean code and suggesting areas for enhancement.\n\n" .
				"**BEST PRACTICE SUGGESTIONS**:\n" .
				"1. Enhancement suggestion with rationale\n" .
				"2. Enhancement suggestion with rationale\n\n" .
				"**RECOMMENDATIONS** (optional improvements):\n" .
				"1. Specific enhancement with code example if applicable\n" .
				"2. Specific enhancement with code example if applicable",
				strtoupper( $category ),
				$focus,
				$plugin_data['Name'] ?? 'Unknown',
				$plugin_data['Version'] ?? 'Unknown',
				$file_count,
				$php_count,
				strtoupper( $category ),
				$focus,
				strtoupper( $category ),
				$category,
				$category
			);
		}

		return $prompt;
	}

	/**
	 * Summarize issues for AI prompt.
	 *
	 * @param array $errors Errors array.
	 * @param array $warnings Warnings array.
	 * @return string Summary.
	 */
	protected function summarize_issues( $errors, $warnings ) {
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
			return "KEY ISSUES:\nNo issues found in this category.";
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
	protected function extract_severity_level( $content ) {
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
	protected function extract_summary( $content ) {
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
	protected function extract_issues( $content ) {
		// Try "KEY OBSERVATIONS" first (for warnings scenario).
		if ( preg_match( '/\*\*KEY\s+OBSERVATIONS\*\*.*?:\s*\n(.+?)(?=\n\n\*\*|$)/is', $content, $matches ) ) {
			$issues_text = trim( $matches[1] );
			preg_match_all( '/\d+\.\s*(.+?)(?=\n\d+\.|$)/s', $issues_text, $items );
			return array_map( 'trim', $items[1] );
		}

		// Try "BEST PRACTICE SUGGESTIONS" (for clean code scenario).
		if ( preg_match( '/\*\*BEST\s+PRACTICE\s+SUGGESTIONS\*\*.*?:\s*\n(.+?)(?=\n\n\*\*|$)/is', $content, $matches ) ) {
			$issues_text = trim( $matches[1] );
			preg_match_all( '/\d+\.\s*(.+?)(?=\n\d+\.|$)/s', $issues_text, $items );
			return array_map( 'trim', $items[1] );
		}

		// Fallback to old format for backwards compatibility.
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
	protected function extract_recommendations( $content ) {
		if ( preg_match( '/\*\*RECOMMENDATIONS\*\*[:\s]*\n(.+?)(?=\n\n\*\*|$)/is', $content, $matches ) ) {
			$recs_text = trim( $matches[1] );
			preg_match_all( '/\d+\.\s*(.+?)(?=\n\d+\.|$)/s', $recs_text, $items );
			return array_map( 'trim', $items[1] );
		}
		return array();
	}

	/**
	 * Count total messages in nested array structure.
	 *
	 * @param array $messages Nested messages array.
	 * @return int Total count.
	 */
	protected function count_messages( $messages ) {
		$count = 0;

		foreach ( $messages as $lines ) {
			foreach ( $lines as $columns ) {
				$count += count( $columns );
			}
		}

		return $count;
	}

	/**
	 * Gets the description for the check.
	 *
	 * @return string The check description.
	 */
	public function get_description(): string {
		return __( 'Provides AI-powered analysis of General category issues.', 'pcp-ai-addon' );
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
	 * Get accumulated results from previous check runs in this category.
	 *
	 * @param string $plugin_path Plugin path.
	 * @param string $category Category name.
	 * @return array Accumulated results or empty array.
	 */
	protected function get_accumulated_results( $plugin_path, $category ) {
		$transient_key = 'pcp_ai_cat_' . $category . '_' . md5( $plugin_path );
		$accumulated = get_transient( $transient_key );

		return is_array( $accumulated ) ? $accumulated : array();
	}

	/**
	 * Clear accumulated results after use.
	 *
	 * @param string $plugin_path Plugin path.
	 * @param string $category Category name.
	 */
	protected function clear_accumulated_results( $plugin_path, $category ) {
		$transient_key = 'pcp_ai_cat_' . $category . '_' . md5( $plugin_path );
		delete_transient( $transient_key );
	}
}
