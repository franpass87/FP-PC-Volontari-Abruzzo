<?php
/**
 * Servizio per sanitizzazione dati
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Sanitizer {

    /**
     * Sanitizza nome/cognome
     *
     * @param string $value
     * @return string
     */
    public function sanitize_name( $value ) {
        $value = trim( wp_strip_all_tags( $value ) );
        return $value ? mb_substr( $value, 0, 100 ) : '';
    }

    /**
     * Sanitizza testo generico
     *
     * @param string $value
     * @return string
     */
    public function sanitize_text( $value ) {
        $value = trim( wp_strip_all_tags( $value ) );
        return $value ? mb_substr( $value, 0, 150 ) : '';
    }

    /**
     * Sanitizza numero di telefono
     *
     * @param string $value
     * @return string
     */
    public function sanitize_phone( $value ) {
        $value = preg_replace( '/[^0-9+ ]+/', '', $value );
        return $value ? mb_substr( $value, 0, 50 ) : '';
    }

    /**
     * Ottiene l'indirizzo IP del client
     *
     * @return string
     */
    public function get_client_ip() {
        $remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
        $remote_ip   = $this->validate_ip_address( $remote_addr );

        $trusted_proxies = apply_filters( 'pcv_trusted_proxies', [] );
        if ( $remote_ip && in_array( $remote_ip, (array) $trusted_proxies, true ) ) {
            foreach ( [ 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP' ] as $header ) {
                if ( empty( $_SERVER[ $header ] ) ) {
                    continue;
                }

                $raw_header = wp_unslash( $_SERVER[ $header ] );
                $candidates = array_map( 'trim', explode( ',', $raw_header ) );
                foreach ( $candidates as $candidate ) {
                    $candidate_ip = $this->validate_ip_address( $candidate );
                    if ( $candidate_ip ) {
                        return $candidate_ip;
                    }
                }
            }
        }

        // Se non è stato possibile determinare l'IP, ritorna una stringa vuota
        // Il database accetta NULL per questo campo
        return $remote_ip !== '' ? $remote_ip : '';
    }

    /**
     * Valida un indirizzo IP
     *
     * @param string $ip
     * @return string
     */
    private function validate_ip_address( $ip ) {
        $ip = trim( (string) $ip );
        if ( $ip === '' ) {
            return '';
        }

        $valid = filter_var( $ip, FILTER_VALIDATE_IP );

        return $valid ? $valid : '';
    }

    /**
     * Protegge testo CSV da formula injection
     *
     * @param string $value
     * @return string
     */
    public function csv_text_guard( $value ) {
        if ( ! is_string( $value ) || $value === '' ) {
            return $value;
        }

        $first_char = $value[0];
        if ( in_array( $first_char, ['=', '+', '-', '@'], true ) ) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Normalizza lista email destinatari
     *
     * @param string $raw_value
     * @return string
     */
    public function normalize_recipient_list( $raw_value ) {
        if ( ! is_string( $raw_value ) ) {
            $raw_value = '';
        }

        $parts = preg_split( '/[\r\n,;]+/', $raw_value );
        $emails = [];

        if ( is_array( $parts ) ) {
            foreach ( $parts as $part ) {
                $email = sanitize_email( trim( $part ) );
                if ( $email && is_email( $email ) ) {
                    $emails[] = $email;
                }
            }
        }

        $emails = array_values( array_unique( $emails ) );

        return implode( "\n", $emails );
    }

    /**
     * Normalizza input booleano
     *
     * @param mixed $value
     * @return int
     */
    public function normalize_boolean_input( $value ) {
        if ( is_string( $value ) ) {
            $value = strtolower( trim( $value ) );
        }

        if ( is_numeric( $value ) ) {
            return (int) ( (int) $value ? 1 : 0 );
        }

        $truthy = [ '1', 'true', 'si', 'sì', 'yes', 'y', 'on', 'x' ];
        $falsy  = [ '0', 'false', 'no', 'n', '' ];

        if ( is_string( $value ) ) {
            if ( in_array( $value, $truthy, true ) ) {
                return 1;
            }

            if ( in_array( $value, $falsy, true ) ) {
                return 0;
            }
        }

        return empty( $value ) ? 0 : 1;
    }

    /**
     * Normalizza input provincia
     *
     * @param string $value
     * @param array $province_map
     * @return string
     */
    public function normalize_province_input( $value, array $province_map ) {
        $value = strtoupper( trim( (string) $value ) );

        if ( $value === '' ) {
            return '';
        }

        if ( isset( $province_map[ $value ] ) ) {
            return $value;
        }

        foreach ( $province_map as $code => $label ) {
            if ( strcasecmp( $label, $value ) === 0 ) {
                return $code;
            }
        }

        return '';
    }

    /**
     * Normalizza input datetime
     *
     * @param string $value
     * @return string
     */
    public function normalize_datetime_input( $value ) {
        $value = trim( (string) $value );

        $timezone = wp_timezone();

        if ( $value === '' ) {
            $datetime = new \DateTime( 'now', $timezone );
            return $datetime->format( 'Y-m-d H:i:s' );
        }

        try {
            $datetime = new \DateTime( $value, $timezone );
        } catch ( \Exception $e ) {
            $datetime = new \DateTime( 'now', $timezone );
        }

        return $datetime->format( 'Y-m-d H:i:s' );
    }
}