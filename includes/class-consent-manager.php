<?php
/**
 * Consent Manager — reads and validates the consent cookie server-side.
 *
 * @package CookieShield
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CookieShield_Consent_Manager
 *
 * Static utility class. No cookie writing happens here — that is handled by the
 * REST endpoint to avoid PHP header-timing issues.
 */
class CookieShield_Consent_Manager {

    /** @var string The name of the first-party consent cookie. */
    const COOKIE_NAME = 'cookieshield_consent';

    /** @var string[] Valid non-necessary consent category keys. */
    const CATEGORIES = [ 'analytics', 'marketing', 'preferences' ];

    /**
     * Reads and decodes the consent cookie.
     *
     * @return array|null Parsed consent array or null if absent/invalid.
     */
    public static function get_consent() {
        if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            return null;
        }

        $raw  = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
        $data = json_decode( $raw, true );

        if ( ! is_array( $data ) ) {
            return null;
        }

        if ( empty( $data['version'] ) || empty( $data['timestamp'] ) || ! isset( $data['categories'] ) ) {
            return null;
        }

        if ( ! is_array( $data['categories'] ) ) {
            return null;
        }

        return $data;
    }

    /**
     * Checks whether the user has given consent for a specific category.
     *
     * @param string $category Category key (e.g. 'analytics').
     * @return bool
     */
    public static function has_consent( $category ) {
        if ( 'necessary' === $category ) {
            return true;
        }

        $consent = self::get_consent();

        if ( null === $consent ) {
            return false;
        }

        return ! empty( $consent['categories'][ $category ] );
    }

    /**
     * Returns true if a valid consent cookie exists.
     *
     * @return bool
     */
    public static function consent_exists() {
        return null !== self::get_consent();
    }

    /**
     * Returns language-specific UI strings.
     *
     * @param string $lang Language code: 'en', 'sv', or 'auto'.
     * @return array
     */
    public static function get_language_strings( $lang = 'en' ) {
        if ( 'auto' === $lang ) {
            $locale = get_locale();
            $lang   = ( 0 === strpos( $locale, 'sv' ) ) ? 'sv' : 'en';
        }

        $all = [
            'en' => [
                'banner_title'           => 'This website uses cookies',
                'banner_description'     => 'We use cookies to enhance your browsing experience, serve personalised content, and analyse our traffic. Please select your preferences below.',
                'accept_label'           => 'Allow all',
                'reject_label'           => 'Reject',
                'selection_label'        => 'Allow selection',
                'settings_label'         => 'Cookie Settings',
                'save_label'             => 'Save Preferences',
                'tab_consent'            => 'Consent',
                'tab_info'               => 'Information',
                'tab_about'              => 'About',
                'necessary_label'        => 'Necessary',
                'analytics_label'        => 'Statistics',
                'analytics_description'  => 'Help us understand how visitors interact with our website by collecting and reporting information anonymously.',
                'marketing_label'        => 'Marketing',
                'marketing_description'  => 'Used to track visitors across websites to display relevant and engaging advertisements.',
                'preferences_label'      => 'Preferences',
                'preferences_description'=> 'Allows the website to remember information that changes the way the website behaves or looks, like your preferred language or region.',
            ],
            'sv' => [
                'banner_title'           => 'Denna webbplats använder cookies',
                'banner_description'     => 'Vi använder cookies för att förbättra din upplevelse, visa relevant innehåll och analysera vår trafik. Välj dina inställningar nedan.',
                'accept_label'           => 'Tillåt alla',
                'reject_label'           => 'Avvisa',
                'selection_label'        => 'Tillåt urval',
                'settings_label'         => 'Kakinställningar',
                'save_label'             => 'Spara inställningar',
                'tab_consent'            => 'Samtycke',
                'tab_info'               => 'Information',
                'tab_about'              => 'Om',
                'necessary_label'        => 'Nödvändig',
                'analytics_label'        => 'Statistik',
                'analytics_description'  => 'Hjälper oss förstå hur besökare interagerar med vår webbplats genom att samla in och rapportera information anonymt.',
                'marketing_label'        => 'Marknadsföring',
                'marketing_description'  => 'Används för att spåra besökare på webbplatser i syfte att visa relevanta och engagerande annonser.',
                'preferences_label'      => 'Inställningar',
                'preferences_description'=> 'Gör det möjligt för webbplatsen att komma ihåg information som påverkar hur webbplatsen beter sig eller ser ut, till exempel ditt föredragna språk.',
            ],
        ];

        return isset( $all[ $lang ] ) ? $all[ $lang ] : $all['en'];
    }

    /**
     * Returns the default plugin settings.
     *
     * @return array
     */
    public static function get_default_settings() {
        $strings = self::get_language_strings( 'auto' );

        return array_merge( $strings, [
            'banner_language'           => 'auto',
            'privacy_policy_url'        => '',
            'show_reject_button'        => true,
            'banner_position'           => 'center-modal',
            'powered_by_text'           => 'JT Media AB',
            'show_site_logo'            => true,
            'color_bg'                  => '#0c0c14',
            'color_text'                => '#dde0f0',
            'color_primary'             => '#5b8df6',
            'color_primary_text'        => '#ffffff',
            'color_reopen'              => '#5b8df6',
            'dark_mode'                 => 'dark',
            'cookie_lifetime'           => 365,
            'consent_version'           => '1.0',
        ] );
    }

    /**
     * Assembles a consent payload array with timestamp.
     *
     * @param array  $categories Associative array of category => bool.
     * @param string $version    Consent notice version string.
     * @return array
     */
    public static function build_consent_payload( array $categories, $version ) {
        $categories['necessary'] = true;

        return [
            'version'    => sanitize_text_field( $version ),
            'timestamp'  => gmdate( 'c' ),
            'categories' => $categories,
        ];
    }
}
