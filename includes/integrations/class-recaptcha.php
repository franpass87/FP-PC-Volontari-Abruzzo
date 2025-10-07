<?php
/**
 * Integrazione Google reCAPTCHA v2
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Recaptcha {

    const OPT_RECAPTCHA_SITE   = 'pcv_recaptcha_site';
    const OPT_RECAPTCHA_SECRET = 'pcv_recaptcha_secret';
    const TEXT_DOMAIN = 'pc-volontari-abruzzo';

    private $sanitizer;

    /**
     * Costruttore
     *
     * @param PCV_Sanitizer $sanitizer
     */
    public function __construct( PCV_Sanitizer $sanitizer ) {
        $this->sanitizer = $sanitizer;
    }

    /**
     * Verifica se reCAPTCHA è configurato
     *
     * @return bool
     */
    public function is_configured() {
        $secret = get_option( self::OPT_RECAPTCHA_SECRET, '' );
        return ! empty( $secret );
    }

    /**
     * Ottiene la site key
     *
     * @return string
     */
    public function get_site_key() {
        return get_option( self::OPT_RECAPTCHA_SITE, '' );
    }

    /**
     * Verifica il token reCAPTCHA
     *
     * @param string $token
     * @return bool
     */
    public function verify_token( $token ) {
        $secret = get_option( self::OPT_RECAPTCHA_SECRET, '' );

        if ( empty( $token ) || empty( $secret ) ) {
            return false;
        }

        $resp = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
            'timeout' => 10,
            'body'    => [
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => $this->sanitizer->get_client_ip(),
            ]
        ] );

        if ( is_wp_error( $resp ) ) {
            return false;
        }

        $body_raw = wp_remote_retrieve_body( $resp );
        $body = json_decode( $body_raw, true );
        $json_error = json_last_error();
        $raw_snippet = is_string( $body_raw ) ? substr( $body_raw, 0, 200 ) : '[non-string response]';

        if ( JSON_ERROR_NONE !== $json_error ) {
            error_log( 'PCV_Recaptcha: JSON decode error - ' . json_last_error_msg() . '. Raw response: ' . $raw_snippet );
            return false;
        }

        if ( ! is_array( $body ) || ! array_key_exists( 'success', $body ) ) {
            error_log( 'PCV_Recaptcha: Unexpected reCAPTCHA response structure. Raw response: ' . $raw_snippet );
            return false;
        }

        return ! empty( $body['success'] );
    }
}