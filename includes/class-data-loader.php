<?php
/**
 * Caricamento dati province e comuni Abruzzo
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Data_Loader {

    private $province = [];
    private $comuni = [];

    /**
     * Costruttore - carica dati dal JSON
     *
     * @param string $plugin_dir Directory del plugin
     */
    public function __construct( $plugin_dir ) {
        $data = [ 'province' => [], 'comuni' => [] ];
        $file = $plugin_dir . '/data/comuni_abruzzo.json';

        if ( file_exists( $file ) && is_readable( $file ) ) {
            $json = file_get_contents( $file );
            if ( $json !== false ) {
                $decoded = json_decode( $json, true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                    $data = $decoded;
                } else {
                    error_log( 'PCV_Data_Loader: JSON decode error - ' . json_last_error_msg() );
                }
            } else {
                error_log( 'PCV_Data_Loader: unable to read comuni data file' );
            }
        } else {
            error_log( 'PCV_Data_Loader: comuni data file missing or unreadable' );
        }

        $this->province = $data['province'] ?? [];
        $this->comuni = $data['comuni'] ?? [];
    }

    /**
     * Ottiene dati province
     *
     * @return array
     */
    public function get_province_data() {
        return $this->province;
    }

    /**
     * Ottiene dati comuni
     *
     * @return array
     */
    public function get_comuni_data() {
        return $this->comuni;
    }

    /**
     * Ottiene tutti i comuni (flat array ordinato)
     *
     * @return array
     */
    public function get_all_comuni() {
        $all = [];
        foreach ( $this->comuni as $province_comuni ) {
            if ( ! is_array( $province_comuni ) ) {
                continue;
            }
            foreach ( $province_comuni as $comune_name ) {
                if ( is_string( $comune_name ) && $comune_name !== '' ) {
                    $all[ $comune_name ] = $comune_name;
                }
            }
        }

        $values = array_values( $all );
        sort( $values, SORT_NATURAL | SORT_FLAG_CASE );

        return $values;
    }
}