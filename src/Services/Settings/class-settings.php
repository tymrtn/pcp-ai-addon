<?php

namespace PCP_AI_Addon\Services\Settings;

/**
 * Settings registration and rendering for the add-on.
 */
class Settings {

    const OPTION_KEY = 'pcp_ai_addon_settings';

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

        // Store API key securely in database with encryption.
        if ( isset( $input['openrouter_api_key'] ) ) {
            if ( ! empty( $input['openrouter_api_key'] ) ) {
                // Encrypt the API key using WordPress salts.
                $settings['openrouter_api_key'] = self::encrypt_api_key( $input['openrouter_api_key'] );
                add_settings_error(
                    self::OPTION_KEY,
                    'pcp_ai_api_key_saved',
                    __( 'OpenRouter API key saved successfully.', 'pcp-ai-addon' ),
                    'updated'
                );
            } else {
                // Clear the API key if empty.
                unset( $settings['openrouter_api_key'] );
            }
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
     */
    public static function render_api_key_field() {
        $settings = self::get_settings();
        $api_key = ! empty( $settings['openrouter_api_key'] ) ? self::decrypt_api_key( $settings['openrouter_api_key'] ) : '';
        ?>
        <input type="password" 
               name="<?php echo esc_attr( self::OPTION_KEY ); ?>[openrouter_api_key]" 
               value="<?php echo esc_attr( $api_key ); ?>" 
               class="regular-text" 
               placeholder="<?php esc_attr_e( 'Enter your OpenRouter API key', 'pcp-ai-addon' ); ?>" />
        <p class="description">
            <?php esc_html_e( 'Your OpenRouter API key is encrypted and stored securely in the database.', 'pcp-ai-addon' ); ?>
        </p>
        <?php
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

        $key = wp_salt( 'AUTH_KEY' );
        $iv = wp_salt( 'SECURE_AUTH_KEY' );
        $iv = substr( hash( 'sha256', $iv ), 0, 16 );
        
        $encrypted = openssl_encrypt( $api_key, 'AES-256-CBC', $key, 0, $iv );
        
        return base64_encode( $encrypted );
    }

    /**
     * Decrypt API key using WordPress salts.
     *
     * @param string $encrypted_api_key The encrypted API key.
     * @return string Decrypted API key.
     */
    private static function decrypt_api_key( $encrypted_api_key ) {
        if ( empty( $encrypted_api_key ) ) {
            return '';
        }

        $key = wp_salt( 'AUTH_KEY' );
        $iv = wp_salt( 'SECURE_AUTH_KEY' );
        $iv = substr( hash( 'sha256', $iv ), 0, 16 );
        
        $encrypted = base64_decode( $encrypted_api_key );
        $decrypted = openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );
        
        return $decrypted ?: '';
    }
}

