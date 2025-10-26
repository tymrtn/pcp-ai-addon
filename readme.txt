=== PCP AI Reviewer Add-on ===
Contributors: copyrightsh
Requires at least: 6.3
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0-dev
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Feature-rich add-on for Plugin Check that introduces AI-assisted triage, developer packaging guidance, and workflow enhancements.

== Description ==

This add-on integrates with the official Plugin Check plugin to:

* Orchestrate AI-powered reviews using OpenRouter (Sonnet 4.5) via environment-stored API keys.
* Provide developer-focused packaging guidance before submitting to WordPress.org.
* Prepare for submission history insights with feature-flagged UI placeholders.

== Installation ==

1. Install and activate Plugin Check (PCP).
2. Upload this add-on to `wp-content/plugins/pcp-ai-addon/` and activate it.
3. Define `OPENROUTER_API_KEY` in your environment (`.env`, wp-config, or hosting secrets) for AI access.
4. Visit **Settings > PCP AI Add-on** to review configuration and upcoming features.

== Changelog ==

= 0.1.0-dev =
* Initial developer preview with settings page, feature flag UI, and packaging wizard placeholder.
