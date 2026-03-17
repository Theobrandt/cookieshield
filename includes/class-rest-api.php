<?php
/**
 * REST API endpoint for saving consent choices.
 *
 * @package CookieShield
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CookieShield_REST_API
 */
class CookieShield_REST_API {

    /**
     * Registers WordPress hooks.
     */
    public function init() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Registers the REST route.
     */
    public function register_routes() {
        register_rest_route(
            'cookieshield/v1',
            '/consent',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'save_consent' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'categories' => [
                        'required'          => true,
                        'type'              => 'object',
                        'sanitize_callback' => [ $this, 'sanitize_categories' ],
                    ],
                ],
            ]
        );
    }

    /**
     * Handles the POST /cookieshield/v1/consent request.
     *
     * @param WP_REST_Request $request Full request data.
     * @return WP_REST_Response|WP_Error
     */
    public function save_consent( WP_REST_Request $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );

        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error(
                'cookieshield_invalid_nonce',
                __( 'Invalid nonce.', 'cookieshield' ),
                [ 'status' => 403 ]
            );
        }

        $categories = $request->get_param( 'categories' );
        $settings   = get_option( 'cookieshield_settings', CookieShield_Consent_Manager::get_default_settings() );
        $version    = isset( $settings['consent_version'] ) ? $settings['consent_version'] : '1.0';
        $lifetime   = isset( $settings['cookie_lifetime'] ) ? absint( $settings['cookie_lifetime'] ) : 365;

        $payload = CookieShield_Consent_Manager::build_consent_payload( $categories, $version );

        setcookie(
            CookieShield_Consent_Manager::COOKIE_NAME,
            wp_json_encode( $payload ),
            [
                'expires'  => time() + ( $lifetime * DAY_IN_SECONDS ),
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly' => false,
                'samesite' => 'Lax',
            ]
        );

        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    /**
     * Sanitizes the incoming categories object.
     *
     * @param mixed $value Raw value from request.
     * @return array
     */
    public function sanitize_categories( $value ) {
        $allowed = CookieShield_Consent_Manager::CATEGORIES;
        $clean   = [];

        if ( ! is_array( $value ) ) {
            return $clean;
        }

        foreach ( $allowed as $key ) {
            $clean[ $key ] = ! empty( $value[ $key ] );
        }

        return $clean;
    }
}
