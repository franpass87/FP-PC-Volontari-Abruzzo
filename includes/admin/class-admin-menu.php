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

		// Interstiziale: opzioni export CSV
		if ( isset( $_GET['pcv_export'] ) && $_GET['pcv_export'] === 'csv_options' && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'pcv_export' ) && PCV_Role_Manager::can_export_volunteers() ) {
			echo '<div class="wrap">';
			printf('<h1 class="wp-heading-inline">%s</h1>', esc_html__( 'Opzioni Export CSV', self::TEXT_DOMAIN ));
			echo '<p>' . esc_html__( 'Scegli l\'ordinamento dei record e l\'ordine delle colonne nel file CSV.', self::TEXT_DOMAIN ) . '</p>';

			// Costruisci lista colonne disponibili (coerente con la tabella e l'export)
			$columns = [
				'id' => __( 'ID', self::TEXT_DOMAIN ),
				'created_at' => __( 'Data', self::TEXT_DOMAIN ),
				'nome' => __( 'Nome', self::TEXT_DOMAIN ),
				'cognome' => __( 'Cognome', self::TEXT_DOMAIN ),
				'comune' => __( 'Comune', self::TEXT_DOMAIN ),
				'provincia' => __( 'Provincia', self::TEXT_DOMAIN ),
				'email' => __( 'Email', self::TEXT_DOMAIN ),
				'telefono' => __( 'Telefono', self::TEXT_DOMAIN ),
				'categoria' => __( 'Categoria', self::TEXT_DOMAIN ),
				'chiamato' => __( 'Chiamato', self::TEXT_DOMAIN ),
				'note' => __( 'Note', self::TEXT_DOMAIN ),
				'num_accompagnatori' => __( 'N° Accompagnatori', self::TEXT_DOMAIN ),
				'accompagnatori' => __( 'Dettagli Accompagnatori', self::TEXT_DOMAIN ),
				'privacy' => __( 'Privacy', self::TEXT_DOMAIN ),
				'partecipa' => __( 'Partecipa', self::TEXT_DOMAIN ),
				'dorme' => __( 'Pernotta', self::TEXT_DOMAIN ),
				'mangia' => __( 'Pasti', self::TEXT_DOMAIN ),
				'ip' => __( 'IP', self::TEXT_DOMAIN ),
				'user_agent' => __( 'User Agent', self::TEXT_DOMAIN ),
			];

			// Recupera filtri correnti per propagarli
			$filters = [ 'f_comune', 'f_prov', 'f_cat', 'f_partecipa', 'f_dorme', 'f_mangia', 'f_chiamato', 's' ];

			echo '<form method="get" id="pcv-export-options" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
			echo '<input type="hidden" name="page" value="pcv-volontari">';
			echo '<input type="hidden" name="pcv_export" value="csv">';
			echo wp_nonce_field( 'pcv_export', '_wpnonce', true, false );
			foreach ( $filters as $f ) {
				if ( isset( $_GET[$f] ) && $_GET[$f] !== '' ) {
					echo '<input type="hidden" name="' . esc_attr( $f ) . '" value="' . esc_attr( wp_unslash( $_GET[$f] ) ) . '">';
				}
			}

			echo '<table class="form-table" role="presentation"><tbody>';
			echo '<tr><th scope="row">' . esc_html__( 'Ordina per', self::TEXT_DOMAIN ) . '</th><td>';
			echo '<select name="sort_by">';
			$sort_opts = [
				'created_at' => __( 'Data', self::TEXT_DOMAIN ),
				'nome' => __( 'Nome', self::TEXT_DOMAIN ),
				'cognome' => __( 'Cognome', self::TEXT_DOMAIN ),
				'comune' => __( 'Comune', self::TEXT_DOMAIN ),
				'provincia' => __( 'Provincia', self::TEXT_DOMAIN ),
				'categoria' => __( 'Categoria', self::TEXT_DOMAIN ),
			];
			foreach ( $sort_opts as $k => $label ) {
				echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</option>';
			}
			echo '</select> ';
			echo '<label><input type="radio" name="sort_dir" value="ASC"> ' . esc_html__( 'Crescente (A → Z)', self::TEXT_DOMAIN ) . '</label> ';
			echo '<label style="margin-left:12px"><input type="radio" name="sort_dir" value="DESC" checked> ' . esc_html__( 'Decrescente (Z → A)', self::TEXT_DOMAIN ) . '</label>';
			echo '</td></tr>';

			echo '<tr><th scope="row">' . esc_html__( 'Ordine colonne', self::TEXT_DOMAIN ) . '</th><td>';
			echo '<p class="description" style="margin-top:0">' . esc_html__( 'Trascina per riordinare. Tutte le colonne saranno incluse.', self::TEXT_DOMAIN ) . '</p>';
			echo '<ul id="pcv-cols-sortable" style="list-style: none; margin: 0; padding: 0; max-width: 560px; border:1px solid #ccd0d4; border-radius:4px;">';
			foreach ( $columns as $key => $label ) {
				echo '<li draggable="true" data-key="' . esc_attr( $key ) . '" style="padding:8px 10px; border-bottom:1px solid #eee; background:#fff; cursor:move">' . esc_html( $label ) . ' <span style="opacity:.6">(' . esc_html( $key ) . ')</span></li>';
			}
			echo '</ul>';
			echo '<input type="hidden" name="cols" id="pcv-cols-input" value="">';
			echo '</td></tr>';
			echo '</tbody></table>';

			submit_button( __( 'Esporta', self::TEXT_DOMAIN ), 'primary' );
			echo ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=pcv-volontari' ) ) . '">' . esc_html__( 'Annulla', self::TEXT_DOMAIN ) . '</a>';
			echo '</form>';

			// Inline JS per drag & drop e serializzazione ordine
			echo '<script>(function(){\n\tvar list=document.getElementById("pcv-cols-sortable");\n\tvar input=document.getElementById("pcv-cols-input");\n\tvar dragEl=null;\n\tlist.addEventListener("dragstart",function(e){dragEl=e.target;e.dataTransfer.effectAllowed="move";});\n\tlist.addEventListener("dragover",function(e){e.preventDefault();var t=e.target;while(t&&t.parentNode!==list){t=t.parentNode;}if(!t||t===dragEl)return;var rect=t.getBoundingClientRect();var next=(e.clientY-rect.top)/(rect.bottom-rect.top)>0.5;list.insertBefore(dragEl,next?t.nextSibling:t);});\n\tfunction serialize(){var keys=[];list.querySelectorAll("li").forEach(function(li){keys.push(li.getAttribute("data-key"));});input.value=keys.join(",");}\n\tserialize();\n\tdocument.getElementById("pcv-export-options").addEventListener("submit",function(){serialize();});\n})();</script>';

			echo '</div>';
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