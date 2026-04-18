<?php

namespace PCP_AI_Addon\Checks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AI-powered review check for Plugin Repo category.
 */
class AI_Review_Plugin_Repo extends AI_Review_General {

	/**
	 * Gets the categories for the check.
	 *
	 * @return array<string> The categories.
	 */
	public function get_categories() {
		return array( 'plugin_repo' );
	}

	/**
	 * Runs the check on files.
	 *
	 * @param \WordPress\Plugin_Check\Checker\Check_Result $result The check result object.
	 * @param array $files List of files to check.
	 */
	protected function check_files( \WordPress\Plugin_Check\Checker\Check_Result $result, array $files ) {
		$this->run_ai_analysis( $result, $files, 'plugin_repo', 'WordPress.org plugin repository requirements and submission guidelines' );
	}

	/**
	 * Gets the description for the check.
	 *
	 * @return string The check description.
	 */
	public function get_description(): string {
		return __( 'Provides AI-powered analysis of Plugin Repository category issues.', 'pcp-ai-addon' );
	}
}
