<?php

namespace PCP_AI_Addon\Guidelines;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registry of the 18 WordPress.org Detailed Plugin Guidelines.
 *
 * Mirrors https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
 * verbatim-short enough to fit in a prompt while still being the canonical
 * rule text the AI evaluates against.
 *
 * Each entry has:
 *   - id:           stable identifier, e.g. "G7"
 *   - title:        short human title
 *   - summary:      one-to-two-sentence rule text (extracted from guideline page)
 *   - category:     one of general | security | performance | accessibility |
 *                   plugin_repo | meta (meta = procedural/not code-checkable)
 *   - ai_checkable: whether the AI can make a reasonable verdict from code+metadata
 *   - url:          deep link anchor on the guidelines page
 */
class WPOrg_Guidelines {

    const DOC_URL = 'https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/';

    /**
     * Full guideline list.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function all() {
        return array(
            array(
                'id' => 'G1', 'title' => 'GPL-compatible license',
                'summary' => 'All code, data, and images must comply with the GPL or a GPL-compatible license. GPLv2 or later is recommended.',
                'category' => 'plugin_repo', 'ai_checkable' => true,
                'url' => self::DOC_URL . '#1-plugins-must-be-compatible-with-the-gnu-general-public-license',
            ),
            array(
                'id' => 'G2', 'title' => 'Developer responsibility',
                'summary' => 'Developers are solely responsible for guideline compliance. Intentionally writing code to circumvent guidelines is prohibited.',
                'category' => 'meta', 'ai_checkable' => false,
                'url' => self::DOC_URL . '#2-developers-are-responsible-for-the-contents-and-actions-of-their-plugins',
            ),
            array(
                'id' => 'G3', 'title' => 'Stable version available',
                'summary' => 'A stable version must be available from the plugin directory page. The readme.txt Stable tag must reference the released version.',
                'category' => 'plugin_repo', 'ai_checkable' => true,
                'url' => self::DOC_URL . '#3-a-stable-version-of-a-plugin-must-be-available-from-its-wordpress-plugin-directory-page',
            ),
            array(
                'id' => 'G4', 'title' => 'Human-readable code',
                'summary' => 'No obfuscation. Minified/packed code must have unminified source available. Techniques like p,a,c,k,e,r obfuscation are not permitted.',
                'category' => 'security', 'ai_checkable' => true,
                'url' => self::DOC_URL . '#4-code-must-be-mostly-human-readable',
            ),
            array(
                'id' => 'G5', 'title' => 'No trialware',
                'summary' => 'Plugins may not contain functionality restricted, locked, or disabled after a trial period. No paywalled core features.',
                'category' => 'plugin_repo', 'ai_checkable' => true,
                'url' => self::DOC_URL . '#5-trialware-is-not-permitted',
            ),
            array(
                'id' => 'G6', 'title' => 'Legitimate external services',
                'summary' => 'Acting as an interface to an external third-party service is allowed, even for paid services. License-validation-only, arbitrary code relocation, and storefronts without substantive functionality are prohibited.',
                'category' => 'plugin_repo', 'ai_checkable' => true,
                'url' => self::DOC_URL . '#6-software-as-a-service-is-permitted',
            ),
            array(
                'id' => 'G7', 'title' => 'No unauthorized user tracking / external contact',
                'summary' => 'Plugins may not contact external servers without explicit opt-in consent. Any external service usage must be disclosed in the readme with a link to the service\'s terms and privacy policy.',
                'category' => 'security', 'ai_checkable' => true,
                'url' => self::DOC_URL . '#7-plugins-may-not-track-users-without-their-consent',
            ),
            array(
                'id' => 'G8', 'title' => 'No third-party executable code',
                'summary' => 'Executing outside code when not acting as a service is prohibited. No remote update endpoints, no installing premium code from non-wp.org sources, no iframes for admin pages, no CDNs for non-font assets.',
                'category' => 'security', 'ai_checkable' => true,
                'url' => self::DOC_URL . '#8-plugins-may-not-send-executable-code-via-third-party-systems',
            ),
            array(
                'id' => 'G9', 'title' => 'No illegal, dishonest, or offensive conduct',
                'summary' => 'Prohibited: search manipulation, review compensation, plagiarism, crypto-mining, identity falsification, harassment, code-of-conduct violations.',
                'category' => 'meta', 'ai_checkable' => false,
                'url' => self::DOC_URL . '#9-developers-and-their-plugins-must-not-do-anything-illegal-dishonest-or-morally-offensive',
            ),
            array(
                'id' => 'G10', 'title' => 'No unconsented external links / credits',
                'summary' => 'Front-end "Powered By" links or credits must be optional and default-off. User must explicitly opt in to display them.',
                'category' => 'general', 'ai_checkable' => true,
                'url' => self::DOC_URL . '#10-plugins-may-not-embed-external-links-or-credits-on-the-public-site-without-explicitly-asking-the-users-permission',
            ),
            array(
                'id' => 'G11', 'title' => 'No dashboard hijacking',
                'summary' => 'Admin notices, upgrade prompts, and nags must be limited and dismissible. Site-wide notices must self-dismiss when resolved or offer a dismiss control.',
                'category' => 'general', 'ai_checkable' => true,
                'url' => self::DOC_URL . '#11-plugins-should-not-hijack-the-admin-dashboard',
            ),
            array(
                'id' => 'G12', 'title' => 'No readme spam',
                'summary' => 'readme.txt must not contain affiliate links, more than 5 tags total, competitor plugin tags, keyword stuffing, or blackhat SEO. Written for people, not bots.',
                'category' => 'plugin_repo', 'ai_checkable' => true,
                'url' => self::DOC_URL . '#12-public-facing-pages-on-wordpress-org-readmes-must-not-spam',
            ),
            array(
                'id' => 'G13', 'title' => 'Use WordPress default libraries',
                'summary' => 'Do not bundle jQuery, Underscore, Backbone, or other libraries WordPress already ships. Use wp_enqueue_script with core-registered handles.',
                'category' => 'performance', 'ai_checkable' => true,
                'url' => self::DOC_URL . '#13-plugins-must-use-wordpress-default-libraries',
            ),
            array(
                'id' => 'G14', 'title' => 'Avoid frequent SVN commits',
                'summary' => 'SVN is a release repository. Commit only deployment-ready code; every commit triggers a zip regeneration.',
                'category' => 'meta', 'ai_checkable' => false,
                'url' => self::DOC_URL . '#14-frequent-commits-to-a-plugin-should-be-avoided',
            ),
            array(
                'id' => 'G15', 'title' => 'Increment version numbers',
                'summary' => 'Users are only alerted to updates when the plugin version increases. The trunk readme.txt must always reflect the current version of the plugin.',
                'category' => 'plugin_repo', 'ai_checkable' => true,
                'url' => self::DOC_URL . '#15-plugin-version-numbers-must-be-incremented-for-each-new-release',
            ),
            array(
                'id' => 'G16', 'title' => 'Plugin must be complete at submission',
                'summary' => 'A complete, working plugin must be present at submission. No "coming soon" placeholder pages, empty admin screens, or disabled future-feature UIs.',
                'category' => 'general', 'ai_checkable' => true,
                'url' => self::DOC_URL . '#16-a-complete-plugin-must-be-available-at-the-time-of-submission',
            ),
            array(
                'id' => 'G17', 'title' => 'Respect trademarks and copyrights',
                'summary' => 'Do not use trademarks or other project names as the sole or initial term of a plugin slug without proof of legal ownership or representation.',
                'category' => 'plugin_repo', 'ai_checkable' => true,
                'url' => self::DOC_URL . '#17-plugins-must-respect-trademarks-copyrights-and-project-names',
            ),
            array(
                'id' => 'G18', 'title' => 'Directory maintenance rights',
                'summary' => 'WordPress.org reserves rights to update guidelines and moderate the directory. Procedural — not code-checkable.',
                'category' => 'meta', 'ai_checkable' => false,
                'url' => self::DOC_URL . '#18-we-reserve-the-right-to-maintain-the-plugin-directory-to-the-best-of-our-ability',
            ),
        );
    }

    /**
     * Get guidelines that apply to a specific category.
     *
     * Includes only AI-checkable guidelines. Meta/procedural rules are
     * excluded because the AI cannot evaluate them from code.
     *
     * @param string $category
     * @return array<int,array<string,mixed>>
     */
    public static function for_category( $category ) {
        $out = array();
        foreach ( self::all() as $g ) {
            if ( $g['category'] === $category && ! empty( $g['ai_checkable'] ) ) {
                $out[] = $g;
            }
        }
        return $out;
    }

    /**
     * Look up a single guideline by id.
     *
     * @param string $id
     * @return array|null
     */
    public static function get( $id ) {
        foreach ( self::all() as $g ) {
            if ( $g['id'] === $id ) {
                return $g;
            }
        }
        return null;
    }

    /**
     * Render a compact prompt block listing the guidelines for a category.
     *
     * @param string $category
     * @return string Empty string if no checkable guidelines for that category.
     */
    public static function render_prompt_block( $category ) {
        $list = self::for_category( $category );
        if ( empty( $list ) ) {
            return '';
        }
        $lines = array( 'WORDPRESS.ORG PLUGIN GUIDELINES TO EVALUATE (you MUST return a verdict for each):' );
        foreach ( $list as $g ) {
            $lines[] = sprintf( '- [%s] %s — %s', $g['id'], $g['title'], $g['summary'] );
        }
        return implode( "\n", $lines );
    }
}
