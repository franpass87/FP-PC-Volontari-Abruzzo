<?php
/**
 * Gestione menu admin
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Admin_Menu {

    const MENU_SLUG = 'pcv-volontari';
    const TEXT_DOMAIN = 'pc-volontari-abruzzo';

    private $list_table;
    private $settings_page;
    private $import_page;
    private $categories_page;
    private $comuni_normalizer;

    /**
     * Costruttore
     */
    public function __construct( $list_table, $settings_page, $import_page, $categories_page ) {
        $this->list_table = $list_table;
        $this->settings_page = $settings_page;
        $this->import_page = $import_page;
        $this->categories_page = $categories_page;
        $this->comuni_normalizer = new PCV_Comuni_Normalizer();
    }

    /**
     * Registra menu admin
     *
     * @return void
     */
    public function register_menus() {
        add_menu_page(
            __( 'Volontari Abruzzo', self::TEXT_DOMAIN ),
            __( 'Volontari Abruzzo', self::TEXT_DOMAIN ),
            PCV_Role_Manager::CAP_VIEW_VOLUNTEERS,
            self::MENU_SLUG,
            [ $this, 'render_main_page' ],
            'dashicons-groups',
            26
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Impostazioni reCAPTCHA', self::TEXT_DOMAIN ),
            __( 'Impostazioni', self::TEXT_DOMAIN ),
            PCV_Role_Manager::CAP_MANAGE_SETTINGS,
            self::MENU_SLUG . '-settings',
            [ $this->settings_page, 'render' ]
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Importazione volontari', self::TEXT_DOMAIN ),
            __( 'Importa', self::TEXT_DOMAIN ),
            PCV_Role_Manager::CAP_IMPORT_VOLUNTEERS,
            self::MENU_SLUG . '-import',
            [ $this->import_page, 'render' ]
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Gestione Categorie', self::TEXT_DOMAIN ),
            __( 'Categorie', self::TEXT_DOMAIN ),
            PCV_Role_Manager::CAP_MANAGE_SETTINGS,
            self::MENU_SLUG . '-categories',
            [ $this->categories_page, 'render' ]
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Normalizzazione Comuni e Province', self::TEXT_DOMAIN ),
            __( 'Normalizza Dati', self::TEXT_DOMAIN ),
            PCV_Role_Manager::CAP_MANAGE_SETTINGS,
            self::MENU_SLUG . '-normalize',
            [ $this->comuni_normalizer, 'render_admin_page' ]
        );

    }

    /**
     * Renderizza pagina principale
     *
     * @return void
     */
    public function render_main_page() {
        if ( ! PCV_Role_Manager::can_view_volunteers() ) {
            return;
        }

        echo '<div class="wrap">';
        printf(
            '<h1 class="wp-heading-inline">%s</h1>',
            esc_html__( 'Volontari Abruzzo', self::TEXT_DOMAIN )
        );

        $this->list_table->prepare_items();
        
        echo '<form method="post">';
        wp_nonce_field( 'pcv_bulk_action' );
        $this->list_table->display();
        echo '</form>';
        echo '</div>';
    }
}