# PCP AI Reviewer Add-on

AI-assisted triage and developer guidance layer on top of the [Plugin Check](https://wordpress.org/plugins/plugin-check/) (PCP) plugin.

## Status
Developer preview — **0.1.0-dev**. Not yet submitted to WordPress.org.

## What it does
- Runs five AI review passes (general, security, performance, accessibility, WP.org repo guidelines) alongside PCP's static checks.
- Uses OpenRouter as the inference backend so you can choose between Sonnet, Grok, GPT, etc.
- Adds a Settings page (**Settings → PCP AI Add-on**) with feature flags and packaging guidance.

## Requirements
- WordPress 6.3+ (6.5+ recommended)
- PHP 7.4+
- Plugin Check plugin active
- An OpenRouter API key (`OPENROUTER_API_KEY` env var, or paste it into the settings page — it's encrypted at rest with WP salts)

## Installation
1. Install and activate **Plugin Check**.
2. Clone or download this repo into `wp-content/plugins/pcp-ai-addon/`.
3. Activate **PCP AI Reviewer Add-on** from the Plugins screen.
4. Provide your OpenRouter API key via `OPENROUTER_API_KEY` environment variable, or Settings → PCP AI Add-on.
5. Run Plugin Check as usual — AI review results appear alongside the static findings.

## Security
- API key never appears in plugin source. It is loaded from environment or the WP options table (encrypted with WordPress salts).
- All admin endpoints require `manage_options` capability.
- Direct PHP access is blocked in every file.

## License
GPL-2.0-or-later. See the plugin header for details.

## Contributing
Issues and PRs welcome. This is an early-stage tool — expect rough edges.
