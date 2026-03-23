<?php
/**
 * Core plugin class — hooks, asset enqueue, banner output.
 *
 * @package CookieShield
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CookieShield_Plugin
 */
class CookieShield_Plugin {

    /**
     * Registers all WordPress hooks.
     */
    public function init() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public_assets' ], 20 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_head', [ $this, 'output_inline_styles' ], 1 );
        add_action( 'wp_footer', [ $this, 'output_banner_html' ] );
        add_action( 'template_redirect', [ $this, 'maybe_force_preview' ] );
    }

    /**
     * Enqueues front-end CSS and JS.
     */
    public function enqueue_public_assets() {
        wp_enqueue_style(
            'cookieshield-banner',
            COOKIESHIELD_URL . 'public/css/banner.css',
            [],
            COOKIESHIELD_VERSION
        );

        wp_enqueue_script(
            'cookieshield-banner',
            COOKIESHIELD_URL . 'public/js/banner.js',
            [],
            COOKIESHIELD_VERSION,
            true
        );

        $settings     = get_option( 'cookieshield_settings', CookieShield_Consent_Manager::get_default_settings() );
        $consent      = CookieShield_Consent_Manager::get_consent();
        $lang         = $settings['banner_language'] ?? 'auto';
        $lang_strings = CookieShield_Consent_Manager::get_language_strings( $lang );

        // Helper: use saved value when present, else fall back to language-specific default.
        $s = function( $key ) use ( $settings, $lang_strings ) {
            $val = isset( $settings[ $key ] ) && '' !== $settings[ $key ] ? $settings[ $key ] : ( $lang_strings[ $key ] ?? '' );
            return wp_strip_all_tags( $val );
        };

        wp_localize_script(
            'cookieshield-banner',
            'cookieshieldData',
            [
                'restUrl'  => esc_url_raw( rest_url( 'cookieshield/v1/consent' ) ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'consent'  => $consent,
                'settings' => [
                    'position'         => sanitize_text_field( $settings['banner_position'] ),
                    'showReject'       => ! empty( $settings['show_reject_button'] ),
                    'version'          => sanitize_text_field( $settings['consent_version'] ),
                    'lifetime'         => absint( $settings['cookie_lifetime'] ),
                    'darkMode'         => sanitize_text_field( $settings['dark_mode'] ),
                    'bannerTitle'      => $s( 'banner_title' ),
                    'bannerDesc'       => $s( 'banner_description' ),
                    'privacyUrl'       => esc_url_raw( $settings['privacy_policy_url'] ),
                    'acceptLabel'      => $s( 'accept_label' ),
                    'rejectLabel'      => $s( 'reject_label' ),
                    'selectionLabel'   => $s( 'selection_label' ),
                    'tabConsent'       => $s( 'tab_consent' ),
                    'tabInfo'          => $s( 'tab_info' ),
                    'tabAbout'         => $s( 'tab_about' ),
                    'necessaryLabel'   => $s( 'necessary_label' ),
                    'analyticsLabel'   => $s( 'analytics_label' ),
                    'analyticsDesc'    => $s( 'analytics_description' ),
                    'marketingLabel'   => $s( 'marketing_label' ),
                    'marketingDesc'    => $s( 'marketing_description' ),
                    'preferencesLabel' => $s( 'preferences_label' ),
                    'preferencesDesc'  => $s( 'preferences_description' ),
                ],
            ]
        );
    }

    /**
     * Enqueues admin CSS and JS (only on the plugin settings page).
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'settings_page_cookieshield-settings' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'cookieshield-admin',
            COOKIESHIELD_URL . 'admin/css/admin.css',
            [],
            COOKIESHIELD_VERSION
        );

        wp_enqueue_script(
            'cookieshield-admin',
            COOKIESHIELD_URL . 'admin/js/admin.js',
            [],
            COOKIESHIELD_VERSION,
            true
        );

        wp_localize_script(
            'cookieshield-admin',
            'cookieshieldAdmin',
            [
                'languages' => [
                    'en' => CookieShield_Consent_Manager::get_language_strings( 'en' ),
                    'sv' => CookieShield_Consent_Manager::get_language_strings( 'sv' ),
                ],
            ]
        );
    }

    /**
     * Outputs the inline <style> block that sets CSS custom properties from settings.
     * NOTE: Custom properties are now applied via inline style attribute on the banner
     * element itself (in output_banner_html) so they always win over the enqueued CSS.
     * This method is kept as a hook target but outputs nothing.
     */
    public function output_inline_styles() {
        // Intentionally empty — vars are set via inline style on the banner element.
    }

    /**
     * Outputs the banner HTML in the footer.
     *
     * The banner is always output so the JS API and reopen button work on every page.
     * JavaScript hides the banner immediately if consent already exists.
     */
    public function output_banner_html() {
        $settings     = get_option( 'cookieshield_settings', CookieShield_Consent_Manager::get_default_settings() );
        $position     = sanitize_text_field( $settings['banner_position'] );
        $dark         = sanitize_text_field( $settings['dark_mode'] );
        $powered_by   = sanitize_text_field( $settings['powered_by_text'] ?? 'JT Media AB' );
        $show_logo    = ! empty( $settings['show_site_logo'] );

        // Dark is the default. Set data-cs-dark="false" only for forced light mode.
        $dark_attr = 'light' === $dark ? ' data-cs-dark="false"' : '';

        // Site logo HTML.
        $logo_html = '';
        if ( $show_logo ) {
            $custom_logo = get_custom_logo();
            if ( $custom_logo ) {
                $logo_html = $custom_logo;
            } else {
                $logo_html = '<span class="cookieshield-banner__site-name">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
            }
        }
        ?>

        <?php
        // Build inline CSS vars — inline style wins over any external stylesheet.
        // Derive --cs-muted from the actual text color so it's always readable.
        $text_hex = ltrim( $settings['color_text'], '#' );
        $r        = hexdec( substr( $text_hex, 0, 2 ) );
        $g        = hexdec( substr( $text_hex, 2, 2 ) );
        $b        = hexdec( substr( $text_hex, 4, 2 ) );
        $cs_muted = "rgba({$r},{$g},{$b},0.7)";

        $inline_vars = sprintf(
            '--cs-bg:%s;--cs-text:%s;--cs-primary:%s;--cs-primary-text:%s;--cs-muted:%s;',
            esc_attr( $settings['color_bg'] ),
            esc_attr( $settings['color_text'] ),
            esc_attr( $settings['color_primary'] ),
            esc_attr( $settings['color_primary_text'] ),
            $cs_muted
        );
        ?>
        <!-- CookieShield Banner -->
        <div id="cookieshield-banner"
             class="cookieshield-banner cookieshield-banner--<?php echo esc_attr( $position ); ?>"
             role="dialog"
             aria-modal="true"
             aria-label="<?php esc_attr_e( 'Cookie consent', 'cookieshield' ); ?>"
             style="<?php echo $inline_vars; // phpcs:ignore WordPress.Security.EscapeOutput -- values are esc_attr'd above ?>"
             <?php echo $dark_attr; // phpcs:ignore WordPress.Security.EscapeOutput ?>
             hidden>

            <div class="cookieshield-banner__overlay" aria-hidden="true"></div>

            <div class="cookieshield-banner__dialog">

                <!-- Header: logo + branding -->
                <div class="cookieshield-banner__header">
                    <div class="cookieshield-banner__logo">
                        <?php echo $logo_html; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitized above ?>
                    </div>
                    <?php if ( $powered_by ) : ?>
                    <div class="cookieshield-banner__branding">
                        Powered by
                        <strong><a href="https://jtmedia.se" target="_blank" rel="noopener noreferrer nofollow" class="cookieshield-banner__branding-link"><em><?php echo esc_html( $powered_by ); ?></em></a></strong>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Tab navigation -->
                <nav class="cookieshield-banner__tabs" role="tablist">
                    <button role="tab" aria-selected="true" class="cookieshield-tab is-active" data-cstab="consent" id="cs-tab-consent"></button>
                    <button role="tab" aria-selected="false" class="cookieshield-tab" data-cstab="info" id="cs-tab-info"></button>
                    <button role="tab" aria-selected="false" class="cookieshield-tab" data-cstab="about" id="cs-tab-about"></button>
                </nav>

                <!-- Consent panel -->
                <div class="cookieshield-banner__panel is-active" role="tabpanel" aria-labelledby="cs-tab-consent" data-cspanel="consent">
                    <h2 class="cookieshield-banner__title"></h2>
                    <p class="cookieshield-banner__description"></p>

                    <!-- Category grid: Necessary | Preferences | Statistics | Marketing -->
                    <div class="cookieshield-cats-grid">

                        <div class="cookieshield-cat-cell">
                            <span class="cookieshield-cat-cell__label" id="cs-cat-necessary-label"></span>
                            <div class="cookieshield-toggle cookieshield-toggle--always-on" aria-label="<?php esc_attr_e( 'Always active', 'cookieshield' ); ?>">
                                <span class="cookieshield-toggle__track">
                                    <span class="cookieshield-toggle__thumb"></span>
                                </span>
                            </div>
                        </div>

                        <div class="cookieshield-cat-cell">
                            <label class="cookieshield-cat-cell__label" for="cs-toggle-preferences" id="cs-cat-pref-label"></label>
                            <label class="cookieshield-toggle" for="cs-toggle-preferences">
                                <input type="checkbox" id="cs-toggle-preferences" name="preferences" class="cookieshield-toggle__input">
                                <span class="cookieshield-toggle__track"><span class="cookieshield-toggle__thumb"></span></span>
                                <span class="screen-reader-text"><?php esc_html_e( 'Preferences cookies', 'cookieshield' ); ?></span>
                            </label>
                        </div>

                        <div class="cookieshield-cat-cell">
                            <label class="cookieshield-cat-cell__label" for="cs-toggle-analytics" id="cs-cat-analytics-label"></label>
                            <label class="cookieshield-toggle" for="cs-toggle-analytics">
                                <input type="checkbox" id="cs-toggle-analytics" name="analytics" class="cookieshield-toggle__input">
                                <span class="cookieshield-toggle__track"><span class="cookieshield-toggle__thumb"></span></span>
                                <span class="screen-reader-text"><?php esc_html_e( 'Statistics cookies', 'cookieshield' ); ?></span>
                            </label>
                        </div>

                        <div class="cookieshield-cat-cell">
                            <label class="cookieshield-cat-cell__label" for="cs-toggle-marketing" id="cs-cat-marketing-label"></label>
                            <label class="cookieshield-toggle" for="cs-toggle-marketing">
                                <input type="checkbox" id="cs-toggle-marketing" name="marketing" class="cookieshield-toggle__input">
                                <span class="cookieshield-toggle__track"><span class="cookieshield-toggle__thumb"></span></span>
                                <span class="screen-reader-text"><?php esc_html_e( 'Marketing cookies', 'cookieshield' ); ?></span>
                            </label>
                        </div>

                    </div><!-- /.cookieshield-cats-grid -->
                </div><!-- /consent panel -->

                <!-- Information panel -->
                <div class="cookieshield-banner__panel" role="tabpanel" aria-labelledby="cs-tab-info" data-cspanel="info" hidden>
                    <div class="cookieshield-panel-content">
                        <p class="cookieshield-banner__description"></p>
                        <a class="cookieshield-privacy-link" href="#" target="_blank" rel="noopener noreferrer nofollow" hidden>
                            <?php esc_html_e( 'Read our Privacy Policy', 'cookieshield' ); ?>
                        </a>
                    </div>
                </div>

                <!-- About panel -->
                <div class="cookieshield-banner__panel" role="tabpanel" aria-labelledby="cs-tab-about" data-cspanel="about" hidden>
                    <div class="cookieshield-panel-content">
                        <p class="cookieshield-banner__description">
                            <?php
                            printf(
                                /* translators: %s: powered-by company name */
                                esc_html__( 'This cookie consent solution is provided by CookieShield, a product by %s. It helps websites manage cookie consent in compliance with GDPR and the ePrivacy Directive.', 'cookieshield' ),
                                '<strong>' . esc_html( $powered_by ) . '</strong>'
                            );
                            ?>
                        </p>
                    </div>
                </div>

                <!-- Footer: three action buttons -->
                <div class="cookieshield-banner__footer">
                    <button type="button" id="cs-reject-all" class="cookieshield-btn cookieshield-btn--ghost cs-reject-btn"></button>
                    <button type="button" id="cs-save-selection" class="cookieshield-btn cookieshield-btn--secondary"></button>
                    <button type="button" id="cs-accept-all" class="cookieshield-btn cookieshield-btn--primary"></button>
                </div>

            </div><!-- /.cookieshield-banner__dialog -->
        </div><!-- /#cookieshield-banner -->

        <?php
        $reopen_color = sanitize_text_field( $settings['color_reopen'] ?? '#5b8df6' );
        ?>
        <!-- Reopen Button -->
        <button type="button" id="cookieshield-reopen" class="cookieshield-reopen"
                aria-label="<?php esc_attr_e( 'Cookie settings', 'cookieshield' ); ?>"
                style="position:fixed!important;bottom:20px!important;left:20px!important;width:40px!important;height:40px!important;border-radius:50%!important;background:<?php echo esc_attr( $reopen_color ); ?>!important;border:none!important;cursor:pointer!important;display:flex!important;align-items:center!important;justify-content:center!important;z-index:99999!important;padding:0!important;box-shadow:0 4px 20px rgba(0,0,0,0.3)!important;"
                hidden>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" style="display:block;flex-shrink:0;" aria-hidden="true">
                <path d="M12 2.5L4.5 6v5c0 4.9 3.2 9.4 7.5 10.8C16.3 20.4 19.5 15.9 19.5 11V6L12 2.5Z" fill="none" stroke="white" stroke-width="1.25" stroke-linejoin="round" stroke-linecap="round"/>
                <path d="M9 12l2 2 4-4" fill="none" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
        <?php
    }

    /**
     * Forces the banner to show in preview mode by setting a body class.
     * Also prevents consent cookie from hiding it.
     */
    public function maybe_force_preview() {
        if ( isset( $_GET['cs_preview'] ) && current_user_can( 'manage_options' ) ) {
            add_filter( 'body_class', function( $classes ) {
                $classes[] = 'cookieshield-preview';
                return $classes;
            } );
        }
    }
}
