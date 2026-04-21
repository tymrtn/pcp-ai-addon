=== PCP AI Reviewer Add-on ===
Contributors: copyrightsh
Requires at least: 6.3
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Feature-rich add-on for Plugin Check that introduces AI-assisted triage, developer packaging guidance, and workflow enhancements.

== Description ==

This add-on integrates with the official Plugin Check plugin to:

* Orchestrate AI-powered reviews using OpenRouter (default: Claude Opus 4.7; model is configurable).
* Provide developer-focused packaging guidance before submitting to WordPress.org.
* Prepare for submission history insights with feature-flagged UI placeholders.

== Installation ==

1. Install and activate Plugin Check (PCP).
2. Upload this add-on to `wp-content/plugins/pcp-ai-addon/` and activate it.
3. Provide your OpenRouter API key either as the `OPENROUTER_API_KEY` environment variable (`.env`, wp-config, or hosting secrets) or by pasting it into **Settings > PCP AI Add-on** (stored encrypted with WordPress salts).
4. Run Plugin Check — AI review results appear alongside the static findings.

== Changelog ==

= 0.3.0 =
* Guideline-grounded review prompts. Each of the 5 category checks now cites the specific WordPress.org Detailed Plugin Guidelines that apply to that category and requires the AI to emit a per-guideline PASS / FAIL / UNCLEAR verdict with evidence.
* FAILs surface as Plugin Check errors; UNCLEAR as warnings; each carries a deep link to the guideline text on developer.wordpress.org.
* New compliance rollup line on every category summary: "Compliance: N PASS / N FAIL / N UNCLEAR (of N checkable)".
* New registry class `PCP_AI_Addon\Guidelines\WPOrg_Guidelines` maps each of the 18 numbered guidelines to a review category.

= 0.2.0 =
* AI model selector in Settings (Opus 4.7, Sonnet 4.6, Haiku 4.5, GPT-5, Grok, or any custom OpenRouter slug).
* REST endpoint `GET /wp-json/pcp-ai/v1/review?plugin=slug[&format=md]` returns a structured AI review as JSON or Markdown.
* MCP server at `POST /wp-json/pcp-ai/v1/mcp` exposes a `pcp_ai.review` tool to any Model Context Protocol client (Claude Code, Cursor, Codex, @wporg/mcp).
* Both endpoints gated by `manage_options`; authenticate via Application Passwords for agent clients.

= 0.1.0-dev =
* Initial developer preview with settings page, feature flag UI, and packaging wizard placeholder.
