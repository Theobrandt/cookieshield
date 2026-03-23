<?php
/**
 * Script Blocker — prevents non-consented scripts from executing.
 *
 * @package CookieShield
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CookieShield_Script_Blocker
 *
 * Two mechanisms:
 * 1. Output buffer on template_redirect: rewrites inline <script data-cookieshield="category">
 *    tags to type="text/plain" when the user hasn't consented to that category.
 * 2. script_loader_tag filter: blocks WordPress-enqueued script handles registered via the
 *    cookieshield_block_script_handle filter.
 */
class CookieShield_Script_Blocker {

    /** @var array<string,string> handle => category map populated by filter. */
    private $blocked_handles = [];

    /**
     * Registers WordPress hooks.
     */
    public function init() {
        add_action( 'template_redirect', [ $this, 'start_output_buffer' ] );
        add_filter( 'script_loader_tag', [ $this, 'maybe_block_enqueued_script' ], 10, 2 );

        /**
         * Allow third-party code to register script handles for blocking.
         *
         * Usage:
         *   add_filter( 'cookieshield_block_script_handle', function( $map ) {
         *       $map['google-analytics'] = 'analytics';
         *       return $map;
         *   } );
         */
        $this->blocked_handles = (array) apply_filters( 'cookieshield_block_script_handle', [] );
    }

    /**
     * Starts an output buffer that rewrites unconsented inline scripts.
     */
    public function start_output_buffer() {
        if ( is_admin() ) {
            return;
        }

        // Skip buffering if the user has already consented to all categories.
        if ( CookieShield_Consent_Manager::consent_exists() ) {
            return;
        }

        ob_start( [ $this, 'rewrite_inline_scripts' ] );
    }

    /**
     * Output buffer callback: rewrites <script data-cookieshield="category"> to type="text/plain"
     * for categories where consent has not been granted.
     *
     * @param string $html Full page HTML.
     * @return string Modified HTML.
     */
    public function rewrite_inline_scripts( $html ) {
        if ( empty( $html ) ) {
            return $html;
        }

        $html = preg_replace_callback(
            '/<script([^>]*\sdata-cookieshield=["\']([^"\']+)["\'][^>]*)>/i',
            [ $this, 'rewrite_script_tag' ],
            $html
        );

        return $html;
    }

    /**
     * Rewrites a single matched script tag.
     *
     * @param array $matches Regex matches: [0] full tag, [1] attributes, [2] category.
     * @return string
     */
    private function rewrite_script_tag( $matches ) {
        $attrs    = $matches[1];
        $category = strtolower( trim( $matches[2] ) );

        if ( CookieShield_Consent_Manager::has_consent( $category ) ) {
            return $matches[0];
        }

        // Remove any existing type attribute then set type="text/plain".
        $attrs = preg_replace( '/\s*type=["\'][^"\']*["\']/', '', $attrs );
        return '<script type="text/plain"' . $attrs . '>';
    }

    /**
     * Blocks WordPress-enqueued scripts whose handles are in the blocked list
     * and for which consent hasn't been granted.
     *
     * @param string $tag    HTML script tag.
     * @param string $handle Script handle.
     * @return string
     */
    public function maybe_block_enqueued_script( $tag, $handle ) {
        if ( ! isset( $this->blocked_handles[ $handle ] ) ) {
            return $tag;
        }

        $category = $this->blocked_handles[ $handle ];

        if ( CookieShield_Consent_Manager::has_consent( $category ) ) {
            return $tag;
        }

        // Rewrite to text/plain with a data attribute so JS can re-enable it later.
        $tag = str_replace( " src=", " data-cookieshield=\"{$category}\" data-src=", $tag );
        $tag = preg_replace( "/type=['\"]text\/javascript['\"]/", '', $tag );
        $tag = str_replace( '<script ', '<script type="text/plain" ', $tag );

        return $tag;
    }
}
