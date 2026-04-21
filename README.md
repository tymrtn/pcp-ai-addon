# PCP AI Reviewer Add-on

AI-assisted triage and developer guidance layer on top of the [Plugin Check](https://wordpress.org/plugins/plugin-check/) (PCP) plugin.

## Status
Current release: **v0.3.0**. Not yet submitted to WordPress.org — distributed via this GitHub repo.

## What it does
- Runs five AI review passes (general, security, performance, accessibility, WP.org repo guidelines) alongside PCP's static checks.
- **Evaluates every plugin against each of the [18 WordPress.org Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)** — each category prompt cites the guidelines in scope and requires the AI to emit a per-rule `PASS` / `FAIL` / `UNCLEAR` verdict with evidence. FAILs surface as Plugin Check errors with a deep link to the guideline text.
- Uses OpenRouter as the inference backend. **Default model: Claude Opus 4.7** (`anthropic/claude-opus-4.7`). Switchable in Settings to Sonnet 4.6, Haiku 4.5, GPT-5, Grok, or any custom OpenRouter slug.
- Settings page at **Settings → PCP AI Add-on** for API key + model selection.
- **Agent-addressable** via a JSON REST endpoint and an MCP server (see [Agent access](#agent-access) below).

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

## Agent access

Every review the add-on produces is addressable from outside WordPress so coding agents can pull reviews into their own workflows.

### REST (Markdown or JSON)
```
GET /wp-json/pcp-ai/v1/review?plugin=<slug-or-basename>[&format=md]
```
- Auth: WordPress cookie (for the UI) or an [Application Password](https://wordpress.org/documentation/article/application-passwords/) (for agents).
- Capability: `manage_options`.
- Default response is JSON; pass `format=md` for a ready-to-paste Markdown review.

### MCP server
```
POST /wp-json/pcp-ai/v1/mcp
```
A minimal JSON-RPC 2.0 [Model Context Protocol](https://modelcontextprotocol.io/) server. Implements `initialize`, `tools/list`, and `tools/call`. Exposes a single tool:

- **`pcp_ai.review`** — inputs `{ plugin: string, no_cache?: boolean }`, returns structured severity / summary / issues / recommendations plus a Markdown rendering.

Smoke-test with the MCP Inspector:
```
npx @modelcontextprotocol/inspector --cli https://your-site/wp-json/pcp-ai/v1/mcp \
  -H "Authorization: Basic $(echo -n user:app-password | base64)"
```

This is the piece that lets agents that speak MCP — Claude Code, Cursor, Codex, [@wporg/mcp](https://make.wordpress.org/meta/2026/03/20/plugin-directory-mcp-server/) — invoke an AI review as part of a larger submission or audit flow.

## Versioning
Semantic versioning. Tags on the `main` branch are the authoritative release markers — see [Releases](https://github.com/tymrtn/pcp-ai-addon/releases) for changelogs. `readme.txt`'s `Stable tag` mirrors the latest release tag.

## Security
- API key never appears in plugin source. It is loaded from environment or the WP options table (encrypted with WordPress salts).
- All admin and REST endpoints require `manage_options` capability.
- Plugin metadata is sanitized before LLM prompts (prompt-injection defense).
- Per-user rate limit (10 calls/minute) on LLM calls.
- Direct PHP access is blocked in every file.

## License
GPL-2.0-or-later. See the plugin header for details.

## Contributing
Issues and PRs welcome. This is an early-stage tool — expect rough edges.
