<?php
/**
 * Classe principale del plugin
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Plugin {

    const VERSION = '1.2.0';
    const TEXT_DOMAIN = 'pc-volontari-abruzzo';

    private $plugin_file;
    private $data_loader;

    // Services
    private $sanitizer;
    private $validator;
    private $notifier;
    private $recaptcha;

    // Data
    private $repository;

    // Import/Export
    private $importer;
    private $exporter;

    // Frontend
    private $assets_manager;
    private $form_handler;
    private $shortcode;

    // Admin
    private $list_table;
    private $admin_menu;
    private $admin_assets;
    private $ajax_handler;

    /**
     * Costruttore
     *
     * @param string $plugin_file Percorso file principale
     */
    public function __construct( $plugin_file ) {
        $this->plugin_file = $plugin_file;

        // Carica dati comuni/province
        $this->data_loader = new PCV_Data_Loader( dirname( $plugin_file ) );

        // Inizializza componenti base
        $this->init_services();
        $this->init_data();
        $this->init_import_export();
        $this->init_frontend();

        // Hook WordPress
        $this->register_hooks();
    }

    /**
     * Inizializza servizi
     *
     * @return void
     */
    private function init_services() {
        $this->sanitizer = new PCV_Sanitizer();
        $this->validator = new PCV_Validator(
            $this->data_loader->get_province_data(),
            $this->data_loader->get_comuni_data()
        );
        $this->notifier = new PCV_Notifier( $this->sanitizer );
        $this->recaptcha = new PCV_Recaptcha( $this->sanitizer );
    }

    /**
     * Inizializza componenti dati
     *
     * @return void
     */
    private function init_data() {
        $this->repository = new PCV_Repository();
    }

    /**
     * Inizializza import/export
     *
     * @return void
     */
    private function init_import_export() {
        $csv_parser = new PCV_CSV_Parser();
        $xlsx_parser = new PCV_XLSX_Parser();

        $this->importer = new PCV_Importer(
            $csv_parser,
            $xlsx_parser,
            $this->sanitizer,
            $this->data_loader->get_province_data()
        );

        $this->exporter = new PCV_Exporter( $this->repository, $this->sanitizer );
    }

    /**
     * Inizializza componenti frontend
     *
     * @return void
     */
    private function init_frontend() {
        $this->assets_manager = new PCV_Assets_Manager(
            $this->plugin_file,
            $this->data_loader->get_province_data(),
            $this->data_loader->get_comuni_data()
        );

        $this->form_handler = new PCV_Form_Handler(
            $this->validator,
            $this->sanitizer,
            $this->recaptcha,
            $this->repository,
            $this->notifier,
            $this->data_loader->get_province_data(),
            $this->data_loader->get_comuni_data()
        );

        $this->shortcode = new PCV_Shortcode( $this->assets_manager );
    }

    /**
     * Inizializza componenti admin
     *
     * @return void
     */
    private function init_admin() {
        $this->list_table = new PCV_List_Table(
            $this->repository,
            $this->data_loader->get_province_data(),
            $this->data_loader->get_comuni_data(),
            $this->data_loader->get_all_comuni()
        );

        $settings_page = new PCV_Settings_Page( $this->sanitizer );
        $import_page = new PCV_Import_Page( $this->importer, $this->sanitizer );
        $categories_page = new PCV_Categories_Page();
        $notes_page = new PCV_Notes_Page( $this->repository );

        $this->admin_menu = new PCV_Admin_Menu( $this->list_table, $settings_page, $import_page, $categories_page, $notes_page );

        $this->admin_assets = new PCV_Admin_Assets(
            $this->plugin_file,
            $this->data_loader->get_province_data(),
            $this->data_loader->get_comuni_data(),
            $this->data_loader->get_all_comuni()
        );

        $this->ajax_handler = new PCV_Ajax_Handler(
            $this->repository,
            $this->data_loader->get_province_data(),
            $this->data_loader->get_comuni_data()
        );
    }

    /**
     * Registra hook WordPress
     *
     * @return void
     */
    private function register_hooks() {
        // Textdomain
        add_action( 'init', [ $this, 'load_textdomain' ] );

        // Database upgrade
        add_action( 'init', [ 'PCV_Database', 'maybe_upgrade_schema' ] );
        
        // Plugin upgrade e backup impostazioni
        add_action( 'admin_init', [ 'PCV_Upgrade_Manager', 'maybe_upgrade' ] );

        // Frontend
        add_shortcode( 'pc_volontari_form', [ $this->shortcode, 'render' ] );
        add_action( 'wp_enqueue_scripts', [ $this->assets_manager, 'register_assets' ] );
        add_action( 'init', [ $this->form_handler, 'maybe_handle_submission' ] );

        // Admin - lazy loading
        add_action( 'admin_menu', [ $this, 'init_and_register_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'init_and_enqueue_admin_assets' ] );
        add_action( 'admin_init', [ $this, 'maybe_export_csv' ] );
        
        // Dashboard widget
        add_action( 'wp_dashboard_setup', [ 'PCV_Dashboard_Widget', 'register' ] );
        
        // Admin AJAX - deve essere sempre registrato nell'area admin
        if ( is_admin() ) {
            add_action( 'admin_init', [ $this, 'init_ajax_handler' ] );
        }
    }

    /**
     * Inizializza admin e registra menu (lazy loading)
     *
     * @return void
     */
    public function init_and_register_admin_menu() {
        if ( ! $this->admin_menu ) {
            $this->init_admin();
        }
        
        if ( $this->admin_menu ) {
            $this->admin_menu->register_menus();
        }
    }

    /**
     * Inizializza admin e registra assets (lazy loading)
     *
     * @return void
     */
    public function init_and_enqueue_admin_assets( $hook ) {
        if ( ! $this->admin_assets ) {
            $this->init_admin();
        }
        
        if ( $this->admin_assets ) {
            $this->admin_assets->enqueue( $hook );
        }
    }

    /**
     * Inizializza AJAX handler (sempre registrato nell'admin)
     *
     * @return void
     */
    public function init_ajax_handler() {
        if ( ! $this->ajax_handler ) {
            $this->init_admin();
        }
    }

    /**
     * Carica textdomain
     *
     * @return void
     */
    public function load_textdomain() {
        load_plugin_textdomain( self::TEXT_DOMAIN, false, dirname( plugin_basename( $this->plugin_file ) ) . '/languages' );
    }

    /**
     * Gestisce export CSV
     *
     * @return void
     */
    public function maybe_export_csv() {
        if ( ! is_admin() ) {
            return;
        }

        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'pcv-volontari' ) {
            return;
        }

        if ( ! isset( $_GET['pcv_export'] ) || $_GET['pcv_export'] !== 'csv' ) {
            return;
        }

        if ( ! PCV_Role_Manager::can_export_volunteers() ) {
            return;
        }

        $export_nonce = isset( $_GET['_wpnonce'] ) ? wp_unslash( $_GET['_wpnonce'] ) : '';
        if ( ! wp_verify_nonce( $export_nonce, 'pcv_export' ) ) {
            wp_die( esc_html__( 'Nonce non valido', self::TEXT_DOMAIN ) );
        }

        // Assicurati che exporter sia inizializzato
        if ( ! $this->exporter ) {
            return;
        }

        $filters = [
            'comune'    => isset( $_GET['f_comune'] ) ? sanitize_text_field( wp_unslash( $_GET['f_comune'] ) ) : '',
            'provincia' => isset( $_GET['f_prov'] ) ? sanitize_text_field( wp_unslash( $_GET['f_prov'] ) ) : '',
            'categoria' => isset( $_GET['f_cat'] ) ? sanitize_text_field( wp_unslash( $_GET['f_cat'] ) ) : '',
            'search'    => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
        ];

        $this->exporter->export_to_csv( $filters );
    }
}