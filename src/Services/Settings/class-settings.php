<?php

namespace PCP_AI_Addon\Services\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use PCP_AI_Addon\Services\AI\API_Key_Manager;

/**
 * Settings registration and rendering for the add-on.
 */
class Settings {

    const OPTION_KEY = 'pcp_ai_addon_settings';

    /**
     * Curated model options for the selector (OpenRouter slugs).
     * "custom" allows any OpenRouter model via a free-text field.
     *
     * @return array<string,string> slug => human label
     */
    public static function get_model_options() {
        return array(
            'anthropic/claude-opus-4.7'   => __( 'Claude Opus 4.7 (default, highest quality)', 'pcp-ai-addon' ),
            'anthropic/claude-sonnet-4.6' => __( 'Claude Sonnet 4.6 (fast, cost-balanced)', 'pcp-ai-addon' ),
            'anthropic/claude-haiku-4.5'  => __( 'Claude Haiku 4.5 (cheapest, fastest)', 'pcp-ai-addon' ),
            'openai/gpt-5'                => __( 'OpenAI GPT-5', 'pcp-ai-addon' ),
            'x-ai/grok-code-fast-1'       => __( 'xAI Grok Code Fast', 'pcp-ai-addon' ),
            'custom'                      => __( 'Custom (enter OpenRouter slug below)', 'pcp-ai-addon' ),
        );
    }

    /**
     * Register settings, sections, and fields.
     */
    public static function register() {
        register_setting( self::OPTION_KEY, self::OPTION_KEY, array( __CLASS__, 'sanitize' ) );

        add_settings_section(
            'pcp_ai_general',
            __( 'AI Integration', 'pcp-ai-addon' ),
            array( __CLASS__, 'render_general_section_intro' ),
            self::OPTION_KEY
        );

        add_settings_field(
            'openrouter_api_key',
            __( 'OpenRouter API Key', 'pcp-ai-addon' ),
            array( __CLASS__, 'render_api_key_field' ),
            self::OPTION_KEY,
            'pcp_ai_general'
        );

        add_settings_field(
            'openrouter_model',
            __( 'AI Model', 'pcp-ai-addon' ),
            array( __CLASS__, 'render_model_field' ),
            self::OPTION_KEY,
            'pcp_ai_general'
        );

        add_settings_field(
            'submission_history_flag',
            __( 'Submission History Insights', 'pcp-ai-addon' ),
            array( __CLASS__, 'render_submission_history_field' ),
            self::OPTION_KEY,
            'pcp_ai_general'
        );
    }

    /**
     * Sanitize settings input, storing API keys securely in database.
     *
     * @param array $input Raw input.
     * @return array Sanitized settings.
     */
    public static function sanitize( $input ) {
        $settings = self::get_settings();

        if ( isset( $input['submission_history_flag'] ) ) {
            $settings['submission_history_flag'] = (bool) $input['submission_history_flag'];
        } else {
            $settings['submission_history_flag'] = false;
        }

        // Model selection.
        $model_choice = isset( $input['openrouter_model'] ) ? sanitize_text_field( (string) $input['openrouter_model'] ) : '';
        if ( 'custom' === $model_choice ) {
            $custom_slug = isset( $input['openrouter_model_custom'] ) ? sanitize_text_field( (string) $input['openrouter_model_custom'] ) : '';
            // Permissive: OpenRouter slugs are `vendor/model-id` with letters, digits, dots, dashes, underscores, slashes.
            if ( '' !== $custom_slug && preg_match( '#^[a-z0-9._/-]+$#i', $custom_slug ) ) {
                $settings['openrouter_model'] = $custom_slug;
            }
        } elseif ( array_key_exists( $model_choice, self::get_model_options() ) ) {
            $settings['openrouter_model'] = $model_choice;
        }

        // Explicit clear takes precedence.
        if ( ! empty( $input['openrouter_api_key_clear'] ) ) {
            unset( $settings['openrouter_api_key'] );
            add_settings_error(
                self::OPTION_KEY,
                'pcp_ai_api_key_cleared',
                __( 'OpenRouter API key cleared.', 'pcp-ai-addon' ),
                'updated'
            );
            return $settings;
        }

        // A non-empty submitted key replaces the stored one. An empty submission
        // is treated as "no change" so reloading the settings page doesn't wipe
        // the stored key (the input is masked and intentionally empty on render).
        if ( isset( $input['openrouter_api_key'] ) && '' !== trim( (string) $input['openrouter_api_key'] ) ) {
            $settings['openrouter_api_key'] = self::encrypt_api_key( $input['openrouter_api_key'] );
            add_settings_error(
                self::OPTION_KEY,
                'pcp_ai_api_key_saved',
                __( 'OpenRouter API key saved successfully.', 'pcp-ai-addon' ),
                'updated'
            );
        }

        return $settings;
    }

    /**
     * Render the settings page.
     */
    public static function render_page() {
        $settings = self::get_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'PCP AI Add-on Settings', 'pcp-ai-addon' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( self::OPTION_KEY );
                do_settings_sections( self::OPTION_KEY );
                submit_button();
                ?>
            </form>

            <hr />

            <h2><?php esc_html_e( 'Developer Workflow Preview', 'pcp-ai-addon' ); ?></h2>
            <p><?php esc_html_e( 'The developer-facing workflow will guide packaging, readme authoring, screenshot guidance, and zipped submissions.', 'pcp-ai-addon' ); ?></p>
            <ol>
                <li><?php esc_html_e( 'Run Plugin Check and AI analysis to gather compliance issues.', 'pcp-ai-addon' ); ?></li>
                <li><?php esc_html_e( 'Follow AI instructions to sanitize code, address guideline violations, and prepare documentation.', 'pcp-ai-addon' ); ?></li>
                <li><?php esc_html_e( 'Use packaging wizard (coming soon) to include required files, exclude disallowed assets, and generate a directory-ready ZIP.', 'pcp-ai-addon' ); ?></li>
                <li><?php esc_html_e( 'Review submission checklist, including screenshots and readme compliance, before uploading to WordPress.org.', 'pcp-ai-addon' ); ?></li>
            </ol>
        </div>
        <?php
    }

    /**
     * Render general section intro.
     */
    public static function render_general_section_intro() {
        echo '<p>' . esc_html__( 'Configure AI integration and upcoming submission history insights.', 'pcp-ai-addon' ) . '</p>';
    }

    /**
     * Render API key input field.
     *
     * When a key is already stored, the field is rendered empty with a masked
     * placeholder so the plaintext key never appears in the page HTML. An
     * empty submission preserves the existing key; a non-empty submission
     * replaces it. Users can check the "clear" box to delete it.
     */
    public static function render_api_key_field() {
        $settings  = self::get_settings();
        $has_key   = ! empty( $settings['openrouter_api_key'] );
        $name_attr = esc_attr( self::OPTION_KEY );
        ?>
        <input type="password"
               name="<?php echo $name_attr; ?>[openrouter_api_key]"
               value=""
               autocomplete="new-password"
               class="regular-text"
               placeholder="<?php echo $has_key ? esc_attr__( '••••••••  (key saved — leave blank to keep)', 'pcp-ai-addon' ) : esc_attr__( 'Enter your OpenRouter API key', 'pcp-ai-addon' ); ?>" />
        <?php if ( $has_key ) : ?>
            <p>
                <label>
                    <input type="checkbox" name="<?php echo $name_attr; ?>[openrouter_api_key_clear]" value="1" />
                    <?php esc_html_e( 'Clear saved OpenRouter API key', 'pcp-ai-addon' ); ?>
                </label>
            </p>
        <?php endif; ?>
        <p class="description">
            <?php esc_html_e( 'Your OpenRouter API key is encrypted with WordPress salts and stored in the database. Leave the field blank to keep the existing key.', 'pcp-ai-addon' ); ?>
        </p>
        <?php
    }

    /**
     * Render AI model selector.
     */
    public static function render_model_field() {
        $settings  = self::get_settings();
        $current   = isset( $settings['openrouter_model'] ) ? (string) $settings['openrouter_model'] : '';
        $options   = self::get_model_options();
        $is_custom = ( '' !== $current && ! array_key_exists( $current, $options ) );
        $selected  = $is_custom ? 'custom' : ( '' === $current ? 'anthropic/claude-opus-4.7' : $current );
        $name_attr = esc_attr( self::OPTION_KEY );
        ?>
        <select id="pcp_ai_openrouter_model"
                name="<?php echo $name_attr; ?>[openrouter_model]"
                class="regular-text">
            <?php foreach ( $options as $slug => $label ) : ?>
                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $selected, $slug ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p>
            <input type="text"
                   id="pcp_ai_openrouter_model_custom"
                   name="<?php echo $name_attr; ?>[openrouter_model_custom]"
                   value="<?php echo esc_attr( $is_custom ? $current : '' ); ?>"
                   class="regular-text code"
                   placeholder="vendor/model-slug"
                   <?php echo $is_custom ? '' : 'style="display:none;"'; ?> />
        </p>
        <p class="description">
            <?php
            printf(
                /* translators: %s: URL to OpenRouter models list */
                esc_html__( 'Default is Claude Opus 4.7. Choose "Custom" to use any OpenRouter slug — see %s for the full list.', 'pcp-ai-addon' ),
                '<a href="https://openrouter.ai/models" target="_blank" rel="noopener">openrouter.ai/models</a>'
            );
            ?>
        </p>
        <script>
        (function () {
            var sel = document.getElementById('pcp_ai_openrouter_model');
            var cust = document.getElementById('pcp_ai_openrouter_model_custom');
            if ( ! sel || ! cust ) { return; }
            sel.addEventListener('change', function () {
                cust.style.display = ( sel.value === 'custom' ) ? '' : 'none';
            });
        })();
        </script>
        <?php
    }

    /**
     * Resolve the effective model slug (stored setting or DEFAULT_MODEL fallback).
     *
     * @return string
     */
    public static function get_effective_model() {
        $settings = self::get_settings();
        return ! empty( $settings['openrouter_model'] ) ? (string) $settings['openrouter_model'] : \PCP_AI_Addon\AI\LLM_Client::DEFAULT_MODEL;
    }

    /**
     * Render submission history feature flag field.
     */
    public static function render_submission_history_field() {
        $settings = self::get_settings();
        $checked  = ! empty( $settings['submission_history_flag'] ) ? 'checked' : '';
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[submission_history_flag]" value="1" <?php echo esc_attr( $checked ); ?> disabled />
            <?php esc_html_e( 'Enable Submission History Insights (coming soon)', 'pcp-ai-addon' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'Placeholder: requires WordPress.org submission history API access. UI will display historical review outcomes when available.', 'pcp-ai-addon' ); ?>
        </p>
        <?php
    }

    /**
     * Retrieve current settings.
     *
     * @return array
     */
    public static function get_settings() {
        $defaults = array(
            'submission_history_flag' => false,
        );

        $settings = get_option( self::OPTION_KEY, array() );

        return wp_parse_args( $settings, $defaults );
    }

    /**
     * Encrypt API key using WordPress salts.
     *
     * @param string $api_key The API key to encrypt.
     * @return string Encrypted API key.
     */
    private static function encrypt_api_key( $api_key ) {
        if ( empty( $api_key ) ) {
            return '';
        }

        $key = wp_salt( 'auth' );
        $iv  = substr( hash( 'sha256', wp_salt( 'secure_auth' ) ), 0, 16 );

        $encrypted = openssl_encrypt( $api_key, 'AES-256-CBC', $key, 0, $iv );

        if ( false === $encrypted ) {
            add_settings_error(
                self::OPTION_KEY,
                'pcp_ai_api_key_encrypt_failed',
                __( 'OpenRouter API key could not be encrypted. Please try again.', 'pcp-ai-addon' ),
                'error'
            );
            return '';
        }

        return base64_encode( $encrypted );
    }

}

