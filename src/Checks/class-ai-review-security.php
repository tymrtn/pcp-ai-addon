<?php

namespace PCP_AI_Addon\Checks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AI-powered review check for Security category.
 */
class AI_Review_Security extends AI_Review_General {

	/**
	 * Gets the categories for the check.
	 *
	 * @return array<string> The categories.
	 */
	public function get_categories() {
		return array( 'security' );
	}

	/**
	 * Runs the check on files.
	 *
	 * @param \WordPress\Plugin_Check\Checker\Check_Result $result The check result object.
	 * @param array $files List of files to check.
	 */
	protected function check_files( \WordPress\Plugin_Check\Checker\Check_Result $result, array $files ) {
		$this->run_ai_analysis( $result, $files, 'security', 'Security vulnerabilities, sanitization, and nonce verification' );
	}

	/**
	 * Gets the description for the check.
	 *
	 * @return string The check description.
	 */
	public function get_description(): string {
		return __( 'Provides AI-powered analysis of Security category issues.', 'pcp-ai-addon' );
	}
}
