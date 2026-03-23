<?php
/**
 * Plugin Name:       CookieShield
 * Plugin URI:        https://jtmedia.se
 * Description:       GDPR/ePrivacy-compliant cookie consent management for WordPress.
 * Version:           1.2.3
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            JT Media AB
 * Author URI:        https://jtmedia.se
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cookieshield
 * Domain Path:       /languages
 *
 * @package CookieShield
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'COOKIESHIELD_VERSION', '1.2.3' );
define( 'COOKIESHIELD_DIR', plugin_dir_path( __FILE__ ) );
define( 'COOKIESHIELD_URL', plugin_dir_url( __FILE__ ) );

// Automatic updates via GitHub.
if ( file_exists( COOKIESHIELD_DIR . 'vendor/plugin-update-checker/load-v5p6.php' ) ) {
    require_once COOKIESHIELD_DIR . 'vendor/plugin-update-checker/load-v5p6.php';
    $cookieshield_updater = YahnisElsts\PluginUpdateChecker\v5p6\PucFactory::buildUpdateChecker(
        'https://github.com/Theobrandt/cookieshield/',
        __FILE__,
        'cookieshield'
    );
    $cookieshield_updater->setBranch( 'main' );
}

require_once COOKIESHIELD_DIR . 'includes/class-consent-manager.php';
require_once COOKIESHIELD_DIR . 'includes/class-rest-api.php';
require_once COOKIESHIELD_DIR . 'includes/class-script-blocker.php';
require_once COOKIESHIELD_DIR . 'includes/class-admin-settings.php';
require_once COOKIESHIELD_DIR . 'includes/class-cookieshield.php';

/**
 * Runs on plugin activation.
 */
function cookieshield_activate() {
    if ( false === get_option( 'cookieshield_settings' ) ) {
        add_option( 'cookieshield_settings', CookieShield_Consent_Manager::get_default_settings() );
    }
}
register_activation_hook( __FILE__, 'cookieshield_activate' );

/**
 * Bootstraps the plugin on plugins_loaded.
 */
function cookieshield_init() {
    load_plugin_textdomain( 'cookieshield', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    $plugin = new CookieShield_Plugin();
    $plugin->init();

    $rest = new CookieShield_REST_API();
    $rest->init();

    $blocker = new CookieShield_Script_Blocker();
    $blocker->init();

    if ( is_admin() ) {
        $settings = new CookieShield_Admin_Settings();
        $settings->init();
    }
}
add_action( 'plugins_loaded', 'cookieshield_init' );
