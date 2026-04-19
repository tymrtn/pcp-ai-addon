=== PCP AI Reviewer Add-on ===
Contributors: copyrightsh
Requires at least: 6.3
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.2.0
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

= 0.2.0 =
* AI model selector in Settings (Opus 4.7, Sonnet 4.6, Haiku 4.5, GPT-5, Grok, or any custom OpenRouter slug).
* REST endpoint `GET /wp-json/pcp-ai/v1/review?plugin=slug[&format=md]` returns a structured AI review as JSON or Markdown.
* MCP server at `POST /wp-json/pcp-ai/v1/mcp` exposes a `pcp_ai.review` tool to any Model Context Protocol client (Claude Code, Cursor, Codex, @wporg/mcp).
* Both endpoints gated by `manage_options`; authenticate via Application Passwords for agent clients.

= 0.1.0-dev =
* Initial developer preview with settings page, feature flag UI, and packaging wizard placeholder.
