<?php

namespace PCP_AI_Addon\Checks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AI-powered review check for Performance category.
 */
class AI_Review_Performance extends AI_Review_General {

	/**
	 * Gets the categories for the check.
	 *
	 * @return array<string> The categories.
	 */
	public function get_categories() {
		return array( 'performance' );
	}

	/**
	 * Runs the check on files.
	 *
	 * @param \WordPress\Plugin_Check\Checker\Check_Result $result The check result object.
	 * @param array $files List of files to check.
	 */
	protected function check_files( \WordPress\Plugin_Check\Checker\Check_Result $result, array $files ) {
		$this->run_ai_analysis( $result, $files, 'performance', 'Performance optimization, resource usage, and query efficiency' );
	}

	/**
	 * Gets the description for the check.
	 *
	 * @return string The check description.
	 */
	public function get_description(): string {
		return __( 'Provides AI-powered analysis of Performance category issues.', 'pcp-ai-addon' );
	}
}
