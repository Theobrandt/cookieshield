<?php
/**
 * Admin Settings Page — WP Settings API implementation.
 *
 * @package CookieShield
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CookieShield_Admin_Settings
 */
class CookieShield_Admin_Settings {

    /** @var string Option name in wp_options. */
    const OPTION_NAME = 'cookieshield_settings';

    /** @var string Settings page slug. */
    const PAGE_SLUG = 'cookieshield-settings';

    /**
     * Registers WordPress hooks.
     */
    public function init() {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_init', [ $this, 'handle_reset' ] );
    }

    /**
     * Adds the settings page to Settings > Cookie Consent.
     */
    public function add_settings_page() {
        add_options_page(
            __( 'Cookie Consent', 'cookieshield' ),
            __( 'Cookie Consent', 'cookieshield' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Registers the setting, sections, and fields.
     */
    public function register_settings() {
        register_setting(
            'cookieshield_options',
            self::OPTION_NAME,
            [ $this, 'sanitize_settings' ]
        );

        // Section: Content.
        add_settings_section( 'cs_content', __( 'Banner Content', 'cookieshield' ), '__return_false', self::PAGE_SLUG );
        $this->add_select_field( 'banner_language', __( 'Default Language', 'cookieshield' ), 'cs_content', [
            'auto' => __( 'Auto (detect from WordPress locale)', 'cookieshield' ),
            'en'   => __( 'English', 'cookieshield' ),
            'sv'   => __( 'Svenska (Swedish)', 'cookieshield' ),
        ] );
        $this->add_text_field( 'banner_title', __( 'Banner Title', 'cookieshield' ), 'cs_content' );
        $this->add_textarea_field( 'banner_description', __( 'Banner Description', 'cookieshield' ), 'cs_content' );
        $this->add_text_field( 'privacy_policy_url', __( 'Privacy Policy URL', 'cookieshield' ), 'cs_content', 'url' );
        $this->add_text_field( 'accept_label', __( 'Accept Button Label', 'cookieshield' ), 'cs_content' );
        $this->add_text_field( 'reject_label', __( 'Reject Button Label', 'cookieshield' ), 'cs_content' );
        $this->add_text_field( 'settings_label', __( 'Settings Button Label', 'cookieshield' ), 'cs_content' );
        $this->add_text_field( 'save_label', __( 'Save Preferences Label', 'cookieshield' ), 'cs_content' );
        $this->add_text_field( 'selection_label', __( 'Allow Selection Button Label', 'cookieshield' ), 'cs_content' );
        $this->add_checkbox_field( 'show_reject_button', __( 'Show Reject Button', 'cookieshield' ), 'cs_content' );
        $this->add_text_field( 'powered_by_text', __( '"Powered by" Text', 'cookieshield' ), 'cs_content' );
        $this->add_checkbox_field( 'show_site_logo', __( 'Show Site Logo', 'cookieshield' ), 'cs_content' );

        // Section: Appearance.
        add_settings_section( 'cs_appearance', __( 'Appearance', 'cookieshield' ), '__return_false', self::PAGE_SLUG );
        $this->add_select_field( 'banner_position', __( 'Banner Position', 'cookieshield' ), 'cs_appearance', [
            'bottom-bar'   => __( 'Bottom Bar', 'cookieshield' ),
            'bottom-left'  => __( 'Bottom Left', 'cookieshield' ),
            'bottom-right' => __( 'Bottom Right', 'cookieshield' ),
            'center-modal' => __( 'Center Modal', 'cookieshield' ),
        ] );
        $this->add_color_field( 'color_bg', __( 'Background Color', 'cookieshield' ), 'cs_appearance' );
        $this->add_color_field( 'color_text', __( 'Text Color', 'cookieshield' ), 'cs_appearance' );
        $this->add_color_field( 'color_primary', __( 'Primary Color', 'cookieshield' ), 'cs_appearance' );
        $this->add_color_field( 'color_primary_text', __( 'Primary Text Color', 'cookieshield' ), 'cs_appearance' );
        $this->add_color_field( 'color_reopen', __( 'Reopen Button Color', 'cookieshield' ), 'cs_appearance' );
        $this->add_select_field( 'dark_mode', __( 'Dark Mode', 'cookieshield' ), 'cs_appearance', [
            'auto'  => __( 'Auto (follow OS)', 'cookieshield' ),
            'light' => __( 'Always Light', 'cookieshield' ),
            'dark'  => __( 'Always Dark', 'cookieshield' ),
        ] );

        // Section: Categories.
        add_settings_section( 'cs_categories', __( 'Cookie Categories', 'cookieshield' ), '__return_false', self::PAGE_SLUG );
        foreach ( [ 'analytics', 'marketing', 'preferences' ] as $cat ) {
            $this->add_text_field( "{$cat}_label", ucfirst( $cat ) . ' ' . __( 'Label', 'cookieshield' ), 'cs_categories' );
            $this->add_textarea_field( "{$cat}_description", ucfirst( $cat ) . ' ' . __( 'Description', 'cookieshield' ), 'cs_categories' );
        }

        // Section: Advanced.
        add_settings_section( 'cs_advanced', __( 'Advanced', 'cookieshield' ), '__return_false', self::PAGE_SLUG );
        $this->add_number_field( 'cookie_lifetime', __( 'Cookie Lifetime (days)', 'cookieshield' ), 'cs_advanced' );
        $this->add_text_field( 'consent_version', __( 'Consent Version', 'cookieshield' ), 'cs_advanced' );
    }

    /**
     * Handles the reset-to-defaults action.
     */
    public function handle_reset() {
        if (
            isset( $_POST['cookieshield_reset'] ) &&
            check_admin_referer( 'cookieshield_reset_nonce', 'cookieshield_reset_nonce_field' )
        ) {
            update_option( self::OPTION_NAME, CookieShield_Consent_Manager::get_default_settings() );
            wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&reset=1' ) );
            exit;
        }
    }

    /**
     * Sanitizes all settings before saving.
     *
     * @param array $input Raw input array.
     * @return array Sanitized array.
     */
    public function sanitize_settings( $input ) {
        $defaults = CookieShield_Consent_Manager::get_default_settings();
        $clean    = [];

        $text_fields = [
            'banner_title', 'accept_label', 'reject_label', 'selection_label', 'settings_label', 'save_label',
            'consent_version', 'analytics_label', 'marketing_label', 'preferences_label',
            'necessary_label', 'powered_by_text',
        ];

        foreach ( $text_fields as $field ) {
            $clean[ $field ] = isset( $input[ $field ] )
                ? sanitize_text_field( $input[ $field ] )
                : $defaults[ $field ];
        }

        $textarea_fields = [ 'banner_description', 'analytics_description', 'marketing_description', 'preferences_description' ];
        foreach ( $textarea_fields as $field ) {
            $clean[ $field ] = isset( $input[ $field ] )
                ? sanitize_textarea_field( $input[ $field ] )
                : $defaults[ $field ];
        }

        $clean['privacy_policy_url'] = isset( $input['privacy_policy_url'] )
            ? esc_url_raw( $input['privacy_policy_url'] )
            : '';

        $clean['show_reject_button'] = ! empty( $input['show_reject_button'] );
        $clean['show_site_logo']     = ! empty( $input['show_site_logo'] );

        $allowed_positions = [ 'bottom-bar', 'bottom-left', 'bottom-right', 'center-modal' ];
        $clean['banner_position'] = in_array( $input['banner_position'] ?? '', $allowed_positions, true )
            ? $input['banner_position']
            : 'bottom-bar';

        $color_fields = [ 'color_bg', 'color_text', 'color_primary', 'color_primary_text', 'color_reopen' ];
        foreach ( $color_fields as $field ) {
            $clean[ $field ] = isset( $input[ $field ] ) && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $input[ $field ] )
                ? $input[ $field ]
                : $defaults[ $field ];
        }

        $allowed_dark = [ 'auto', 'light', 'dark' ];
        $clean['dark_mode'] = in_array( $input['dark_mode'] ?? '', $allowed_dark, true )
            ? $input['dark_mode']
            : 'dark';

        $allowed_languages = [ 'auto', 'en', 'sv' ];
        $clean['banner_language'] = in_array( $input['banner_language'] ?? '', $allowed_languages, true )
            ? $input['banner_language']
            : 'auto';

        $clean['cookie_lifetime'] = isset( $input['cookie_lifetime'] )
            ? max( 1, min( 730, absint( $input['cookie_lifetime'] ) ) )
            : 365;

        return $clean;
    }

    /**
     * Renders the settings page HTML.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings     = get_option( self::OPTION_NAME, CookieShield_Consent_Manager::get_default_settings() );
        $preview_url  = add_query_arg( 'cs_preview', '1', home_url( '/' ) );
        ?>
        <div class="wrap cookieshield-admin">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <?php if ( isset( $_GET['reset'] ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Settings reset to defaults.', 'cookieshield' ); ?></p>
                </div>
            <?php endif; ?>

            <div class="cookieshield-admin__layout">
                <div class="cookieshield-admin__form">
                    <div class="cs-tabs">
                        <nav class="cs-tabs__nav">
                            <button class="cs-tabs__btn is-active" data-tab="content"><?php esc_html_e( 'Content', 'cookieshield' ); ?></button>
                            <button class="cs-tabs__btn" data-tab="appearance"><?php esc_html_e( 'Appearance', 'cookieshield' ); ?></button>
                            <button class="cs-tabs__btn" data-tab="categories"><?php esc_html_e( 'Categories', 'cookieshield' ); ?></button>
                            <button class="cs-tabs__btn" data-tab="advanced"><?php esc_html_e( 'Advanced', 'cookieshield' ); ?></button>
                        </nav>

                        <form method="post" action="options.php">
                            <?php settings_fields( 'cookieshield_options' ); ?>

                            <div class="cs-tabs__panel is-active" data-panel="content">
                                <?php do_settings_sections( self::PAGE_SLUG . '#content' ); ?>
                                <?php $this->render_section_fields( 'cs_content' ); ?>
                            </div>
                            <div class="cs-tabs__panel" data-panel="appearance">
                                <?php $this->render_section_fields( 'cs_appearance' ); ?>
                            </div>
                            <div class="cs-tabs__panel" data-panel="categories">
                                <?php $this->render_section_fields( 'cs_categories' ); ?>
                            </div>
                            <div class="cs-tabs__panel" data-panel="advanced">
                                <?php $this->render_section_fields( 'cs_advanced' ); ?>
                            </div>

                            <?php submit_button(); ?>
                        </form>

                        <form method="post">
                            <?php wp_nonce_field( 'cookieshield_reset_nonce', 'cookieshield_reset_nonce_field' ); ?>
                            <input type="submit" name="cookieshield_reset" class="button button-secondary"
                                value="<?php esc_attr_e( 'Reset to Defaults', 'cookieshield' ); ?>">
                        </form>
                    </div>
                </div>

                <div class="cookieshield-admin__preview">
                    <h2><?php esc_html_e( 'Preview', 'cookieshield' ); ?></h2>
                    <iframe
                        id="cs-preview-iframe"
                        src="<?php echo esc_url( $preview_url ); ?>"
                        title="<?php esc_attr_e( 'Banner Preview', 'cookieshield' ); ?>"
                        data-position="<?php echo esc_attr( $settings['banner_position'] ); ?>"
                    ></iframe>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renders all fields registered to a given section.
     *
     * @param string $section_id Settings section ID.
     */
    private function render_section_fields( $section_id ) {
        global $wp_settings_fields;

        if ( ! isset( $wp_settings_fields[ self::PAGE_SLUG ][ $section_id ] ) ) {
            return;
        }

        echo '<table class="form-table" role="presentation">';
        do_settings_fields( self::PAGE_SLUG, $section_id );
        echo '</table>';
    }

    // -------------------------------------------------------------------------
    // Field helpers
    // -------------------------------------------------------------------------

    /**
     * Registers and renders a text input field.
     *
     * @param string $id      Field ID.
     * @param string $label   Field label.
     * @param string $section Section ID.
     * @param string $type    Input type attribute.
     */
    private function add_text_field( $id, $label, $section, $type = 'text' ) {
        add_settings_field( $id, $label, [ $this, 'render_text_field' ], self::PAGE_SLUG, $section, [
            'id'   => $id,
            'type' => $type,
        ] );
    }

    /**
     * Renders a text input.
     *
     * @param array $args Field arguments.
     */
    public function render_text_field( $args ) {
        $options = get_option( self::OPTION_NAME, CookieShield_Consent_Manager::get_default_settings() );
        $id      = $args['id'];
        $type    = $args['type'] ?? 'text';
        $value   = $options[ $id ] ?? '';
        printf(
            '<input type="%s" id="%s" name="%s[%s]" value="%s" class="regular-text" data-cs-field="%s">',
            esc_attr( $type ),
            esc_attr( $id ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $id ),
            esc_attr( $value ),
            esc_attr( $id )
        );
    }

    /**
     * Registers and renders a textarea field.
     *
     * @param string $id      Field ID.
     * @param string $label   Field label.
     * @param string $section Section ID.
     */
    private function add_textarea_field( $id, $label, $section ) {
        add_settings_field( $id, $label, [ $this, 'render_textarea_field' ], self::PAGE_SLUG, $section, [ 'id' => $id ] );
    }

    /**
     * Renders a textarea.
     *
     * @param array $args Field arguments.
     */
    public function render_textarea_field( $args ) {
        $options = get_option( self::OPTION_NAME, CookieShield_Consent_Manager::get_default_settings() );
        $id      = $args['id'];
        $value   = $options[ $id ] ?? '';
        printf(
            '<textarea id="%s" name="%s[%s]" rows="4" class="large-text" data-cs-field="%s">%s</textarea>',
            esc_attr( $id ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $id ),
            esc_attr( $id ),
            esc_textarea( $value )
        );
    }

    /**
     * Registers and renders a checkbox field.
     *
     * @param string $id      Field ID.
     * @param string $label   Field label.
     * @param string $section Section ID.
     */
    private function add_checkbox_field( $id, $label, $section ) {
        add_settings_field( $id, $label, [ $this, 'render_checkbox_field' ], self::PAGE_SLUG, $section, [ 'id' => $id ] );
    }

    /**
     * Renders a checkbox.
     *
     * @param array $args Field arguments.
     */
    public function render_checkbox_field( $args ) {
        $options = get_option( self::OPTION_NAME, CookieShield_Consent_Manager::get_default_settings() );
        $id      = $args['id'];
        $checked = ! empty( $options[ $id ] );
        printf(
            '<input type="checkbox" id="%s" name="%s[%s]" value="1" %s>',
            esc_attr( $id ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $id ),
            checked( $checked, true, false )
        );
    }

    /**
     * Registers and renders a select field.
     *
     * @param string $id      Field ID.
     * @param string $label   Field label.
     * @param string $section Section ID.
     * @param array  $options Options array value => label.
     */
    private function add_select_field( $id, $label, $section, $options ) {
        add_settings_field( $id, $label, [ $this, 'render_select_field' ], self::PAGE_SLUG, $section, [
            'id'      => $id,
            'options' => $options,
        ] );
    }

    /**
     * Renders a select element.
     *
     * @param array $args Field arguments including 'options'.
     */
    public function render_select_field( $args ) {
        $saved   = get_option( self::OPTION_NAME, CookieShield_Consent_Manager::get_default_settings() );
        $id      = $args['id'];
        $current = $saved[ $id ] ?? '';
        echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( self::OPTION_NAME ) . '[' . esc_attr( $id ) . ']" data-cs-field="' . esc_attr( $id ) . '">';
        foreach ( $args['options'] as $val => $text ) {
            echo '<option value="' . esc_attr( $val ) . '"' . selected( $current, $val, false ) . '>' . esc_html( $text ) . '</option>';
        }
        echo '</select>';
    }

    /**
     * Registers and renders a color picker field.
     *
     * @param string $id      Field ID.
     * @param string $label   Field label.
     * @param string $section Section ID.
     */
    private function add_color_field( $id, $label, $section ) {
        add_settings_field( $id, $label, [ $this, 'render_color_field' ], self::PAGE_SLUG, $section, [ 'id' => $id ] );
    }

    /**
     * Renders a native color input.
     *
     * @param array $args Field arguments.
     */
    public function render_color_field( $args ) {
        $options = get_option( self::OPTION_NAME, CookieShield_Consent_Manager::get_default_settings() );
        $id      = $args['id'];
        $value   = $options[ $id ] ?? '#ffffff';
        printf(
            '<input type="color" id="%s" name="%s[%s]" value="%s" class="cs-color-picker" data-cs-field="%s">
             <span class="cs-color-swatch" style="background:%s"></span>',
            esc_attr( $id ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $id ),
            esc_attr( $value ),
            esc_attr( $id ),
            esc_attr( $value )
        );
    }

    /**
     * Registers and renders a number input field.
     *
     * @param string $id      Field ID.
     * @param string $label   Field label.
     * @param string $section Section ID.
     */
    private function add_number_field( $id, $label, $section ) {
        add_settings_field( $id, $label, [ $this, 'render_number_field' ], self::PAGE_SLUG, $section, [ 'id' => $id ] );
    }

    /**
     * Renders a number input.
     *
     * @param array $args Field arguments.
     */
    public function render_number_field( $args ) {
        $options = get_option( self::OPTION_NAME, CookieShield_Consent_Manager::get_default_settings() );
        $id      = $args['id'];
        $value   = $options[ $id ] ?? 365;
        printf(
            '<input type="number" id="%s" name="%s[%s]" value="%s" min="1" max="730" class="small-text">',
            esc_attr( $id ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $id ),
            esc_attr( $value )
        );
    }
}
