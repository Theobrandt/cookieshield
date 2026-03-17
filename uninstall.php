<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package CookieShield
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'cookieshield_settings' );
