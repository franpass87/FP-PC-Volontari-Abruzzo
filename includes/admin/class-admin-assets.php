<?php
/**
 * Gestione asset admin (CSS/JS)
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Admin_Assets {

    const VERSION = '1.2.0';
    const TEXT_DOMAIN = 'pc-volontari-abruzzo';
    const MENU_SLUG = 'pcv-volontari';

    private $plugin_file;
    private $province_data;
    private $comuni_data;
    private $all_comuni;

    public function __construct( $plugin_file, $province_data, $comuni_data, $all_comuni ) {
        $this->plugin_file = $plugin_file;
        $this->province_data = $province_data;
        $this->comuni_data = $comuni_data;
        $this->all_comuni = $all_comuni;
    }

    public function enqueue( $hook ) {
        if ( strpos( $hook, self::MENU_SLUG ) === false ) {
            return;
        }

        $css = ".pcv-topbar{display:flex;gap:10px;align-items:center;margin:12px 0}.pcv-topbar form{display:flex;gap:8px;align-items:center}.wrap .tablenav{overflow:visible}.pcv-topbar, .pcv-topbar form{flex-wrap:wrap}.pcv-topbar select{min-width:180px}";
        wp_register_style( 'pcv-admin-inline', false );
        wp_enqueue_style( 'pcv-admin-inline' );
        wp_add_inline_style( 'pcv-admin-inline', $css );

        $selected_prov = isset( $_GET['f_prov'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['f_prov'] ) ) ) : '';
        if ( ! array_key_exists( $selected_prov, $this->province_data ) ) {
            $selected_prov = '';
        }
        $selected_comune = isset( $_GET['f_comune'] ) ? sanitize_text_field( wp_unslash( $_GET['f_comune'] ) ) : '';

        if ( $selected_comune !== '' && ! in_array( $selected_comune, $this->all_comuni, true ) ) {
            $selected_comune = '';
        }

        wp_enqueue_script( 'pcv-admin', plugins_url( 'assets/js/admin.js', $this->plugin_file ), [ 'jquery' ], self::VERSION, true );
        wp_localize_script( 'pcv-admin', 'PCV_ADMIN_DATA', [
            'province'           => $this->province_data,
            'comuni'             => $this->comuni_data,
            'allComuni'          => $this->all_comuni,
            'selectedProvincia'  => $selected_prov,
            'selectedComune'     => $selected_comune,
            'labels'             => [
                'placeholderComune' => __( 'Tutti i comuni', self::TEXT_DOMAIN ),
            ],
            'fallbacks'          => [
                'placeholderComune' => __( 'Tutti i comuni', self::TEXT_DOMAIN ),
            ],
        ] );
        
        wp_localize_script( 'pcv-admin', 'PCV_AJAX_DATA', [
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'pcv_ajax_nonce' ),
            'province'   => $this->province_data,
            'comuni'     => $this->comuni_data,
            'allComuni'  => $this->all_comuni,
            'categories' => PCV_Category_Manager::get_categories_for_select(),
        ] );
        
        // Enqueue CSS per i modal
        wp_enqueue_style( 'pcv-admin-modal', plugins_url( 'assets/css/frontend.css', $this->plugin_file ), [], self::VERSION );
    }
}