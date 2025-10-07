<?php
/**
 * Gestione asset frontend (CSS/JS)
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Assets_Manager {

    const VERSION = '1.1.0';
    const TEXT_DOMAIN = 'pc-volontari-abruzzo';

    private $plugin_file;
    private $province_data;
    private $comuni_data;

    /**
     * Costruttore
     *
     * @param string $plugin_file Percorso al file principale del plugin
     * @param array $province_data
     * @param array $comuni_data
     */
    public function __construct( $plugin_file, $province_data, $comuni_data ) {
        $this->plugin_file = $plugin_file;
        $this->province_data = $province_data;
        $this->comuni_data = $comuni_data;
    }

    /**
     * Registra asset frontend
     *
     * @return void
     */
    public function register_assets() {
        wp_register_style( 'pcv-frontend', plugins_url( 'assets/css/frontend.css', $this->plugin_file ), [], self::VERSION );
        wp_register_script( 'pcv-frontend', plugins_url( 'assets/js/frontend.js', $this->plugin_file ), [], self::VERSION, true );
    }

    /**
     * Enqueue asset frontend e localizza dati
     *
     * @param array $labels Label personalizzate
     * @return void
     */
    public function enqueue_assets( array $labels = [] ) {
        wp_enqueue_style( 'pcv-frontend' );
        wp_enqueue_script( 'pcv-frontend' );

        $recaptcha_site = get_option( 'pcv_recaptcha_site', '' );

        $data = [
            'province'       => $this->province_data,
            'comuni'         => $this->comuni_data,
            'recaptcha_site' => $recaptcha_site,
            'labels'         => $labels,
            'fallbacks'      => [
                'selectProvince' => __( 'Seleziona provincia', self::TEXT_DOMAIN ),
                'selectComune'   => __( 'Seleziona comune', self::TEXT_DOMAIN ),
                'modalAlert'     => __( 'Seleziona provincia e comune.', self::TEXT_DOMAIN ),
            ],
        ];

        wp_localize_script( 'pcv-frontend', 'PCV_DATA', $data );
    }
}