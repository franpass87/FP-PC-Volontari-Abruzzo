<?php
/**
 * Tabella lista volontari in admin
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class PCV_List_Table extends WP_List_Table {

    const TEXT_DOMAIN = 'pc-volontari-abruzzo';

    private $repository;
    private $province_data;
    private $comuni_data;
    private $all_comuni;

    /**
     * Costruttore
     */
    public function __construct( $repository, $province_data, $comuni_data, $all_comuni ) {
        $this->repository = $repository;
        $this->province_data = $province_data;
        $this->comuni_data = $comuni_data;
        $this->all_comuni = $all_comuni;

        parent::__construct( [
            'singular' => __( 'volontario', self::TEXT_DOMAIN ),
            'plural'   => __( 'volontari', self::TEXT_DOMAIN ),
            'ajax'     => false,
        ] );
    }

    /**
     * Ottiene colonne
     *
     * @return array
     */
    public function get_columns() {
        return [
            'cb'         => '<input type="checkbox" />',
            'created_at' => esc_html__( 'Data', self::TEXT_DOMAIN ),
            'nome'       => esc_html__( 'Nome', self::TEXT_DOMAIN ),
            'cognome'    => esc_html__( 'Cognome', self::TEXT_DOMAIN ),
            'comune'     => esc_html__( 'Comune', self::TEXT_DOMAIN ),
            'provincia'  => esc_html__( 'Provincia', self::TEXT_DOMAIN ),
            'email'      => esc_html__( 'Email', self::TEXT_DOMAIN ),
            'telefono'   => esc_html__( 'Telefono', self::TEXT_DOMAIN ),
            'categoria'  => esc_html__( 'Categoria', self::TEXT_DOMAIN ),
            'privacy'    => esc_html__( 'Privacy', self::TEXT_DOMAIN ),
            'partecipa'  => esc_html__( 'Partecipa', self::TEXT_DOMAIN ),
            'dorme'      => esc_html__( 'Pernotta', self::TEXT_DOMAIN ),
            'mangia'     => esc_html__( 'Pasti', self::TEXT_DOMAIN ),
        ];
    }

    /**
     * Ottiene colonne ordinabili
     *
     * @return array
     */
    protected function get_sortable_columns() {
        return [
            'created_at' => [ 'created_at', true ],
            'nome'       => [ 'nome', false ],
            'cognome'    => [ 'cognome', false ],
            'comune'     => [ 'comune', false ],
            'provincia'  => [ 'provincia', false ],
            'categoria'  => [ 'categoria', false ],
        ];
    }

    /**
     * Checkbox colonna
     *
     * @param object $item
     * @return string
     */
    protected function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="id[]" value="%d" />', $item->id );
    }

    /**
     * Colonna nome con row actions
     *
     * @param object $item
     * @return string
     */
    protected function column_nome( $item ) {
        $actions = [
            'edit' => sprintf(
                '<a href="#" class="pcv-edit-volunteer" data-id="%d">%s</a>',
                $item->id,
                esc_html__( 'Modifica', self::TEXT_DOMAIN )
            ),
            'delete' => sprintf(
                '<a href="#" class="pcv-delete-volunteer" data-id="%d">%s</a>',
                $item->id,
                esc_html__( 'Elimina', self::TEXT_DOMAIN )
            ),
        ];

        return sprintf( '%s %s', esc_html( $item->nome ), $this->row_actions( $actions ) );
    }

    /**
     * Colonna default
     *
     * @param object $item
     * @param string $col
     * @return string
     */
    protected function column_default( $item, $col ) {
        switch ( $col ) {
            case 'created_at':
                return esc_html( mysql2date( 'd/m/Y H:i', $item->created_at ) );
            case 'cognome':
            case 'comune':
            case 'provincia':
            case 'email':
            case 'telefono':
            case 'categoria':
                return esc_html( $item->$col );
            case 'privacy':
            case 'partecipa':
            case 'dorme':
            case 'mangia':
                return $item->$col ? esc_html__( 'Sì', self::TEXT_DOMAIN ) : esc_html__( 'No', self::TEXT_DOMAIN );
            default:
                return '';
        }
    }

    /**
     * Azioni bulk
     *
     * @return array
     */
    public function get_bulk_actions() {
        return [
            'delete' => esc_html__( 'Elimina', self::TEXT_DOMAIN ),
            'bulk_edit' => esc_html__( 'Modifica campi selezionati', self::TEXT_DOMAIN ),
        ];
    }

    /**
     * Processa azione bulk
     *
     * @return void
     */
    public function process_bulk_action() {
        if ( 'delete' !== $this->current_action() ) {
            return;
        }

        check_admin_referer( 'pcv_bulk_action' );

        if ( ! PCV_Role_Manager::can_delete_volunteers() ) {
            return;
        }

        if ( empty( $_POST['id'] ) || ! is_array( $_POST['id'] ) ) {
            return;
        }

        $ids = array_map( 'absint', $_POST['id'] );
        $this->repository->delete_by_ids( $ids );
    }

    /**
     * Ottiene dati province
     *
     * @return array
     */
    public function get_province_data() {
        return $this->province_data;
    }

    /**
     * Ottiene dati comuni
     *
     * @return array
     */
    public function get_comuni_data() {
        return $this->comuni_data;
    }

    /**
     * Prepara items
     *
     * @return void
     */
    public function prepare_items() {
        $this->process_bulk_action();

        $per_page = 100;
        $current_page = $this->get_pagenum();

        $orderby_raw = isset( $_GET['orderby'] ) ? wp_unslash( $_GET['orderby'] ) : 'created_at';
        $orderby = sanitize_key( $orderby_raw );

        $order_raw = isset( $_GET['order'] ) ? wp_unslash( $_GET['order'] ) : 'DESC';
        $order = ( strtolower( $order_raw ) === 'asc' ) ? 'ASC' : 'DESC';

        $allowed = [ 'created_at', 'nome', 'cognome', 'comune', 'provincia', 'categoria' ];
        if ( ! in_array( $orderby, $allowed, true ) ) {
            $orderby = 'created_at';
        }

        $f_comune_raw = isset( $_GET['f_comune'] ) ? wp_unslash( $_GET['f_comune'] ) : '';
        $f_comune = trim( sanitize_text_field( $f_comune_raw ) );

        $f_prov_raw = isset( $_GET['f_prov'] ) ? wp_unslash( $_GET['f_prov'] ) : '';
        $f_prov = trim( sanitize_text_field( $f_prov_raw ) );

        $f_cat_raw = isset( $_GET['f_cat'] ) ? wp_unslash( $_GET['f_cat'] ) : '';
        $f_cat = trim( sanitize_text_field( $f_cat_raw ) );

        $s_raw = isset( $_GET['s'] ) ? wp_unslash( $_GET['s'] ) : '';
        $s = trim( sanitize_text_field( $s_raw ) );

        $args = [
            'orderby'   => $orderby,
            'order'     => $order,
            'limit'     => $per_page,
            'offset'    => ( $current_page - 1 ) * $per_page,
            'comune'    => $f_comune,
            'provincia' => $f_prov,
            'categoria' => $f_cat,
            'search'    => $s,
        ];

        $total_items = $this->repository->count_volunteers( $args );
        $items = $this->repository->get_volunteers( $args );

        $this->items = $items;
        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ] );

        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns(), 'nome' ];
    }

    /**
     * Toolbar extra con filtri
     *
     * @param string $which
     * @return void
     */
    public function extra_tablenav( $which ) {
        if ( $which !== 'top' ) {
            return;
        }

        $f_comune_raw = isset( $_GET['f_comune'] ) ? wp_unslash( $_GET['f_comune'] ) : '';
        $f_comune = sanitize_text_field( $f_comune_raw );

        $f_prov_raw = isset( $_GET['f_prov'] ) ? wp_unslash( $_GET['f_prov'] ) : '';
        $f_prov = strtoupper( sanitize_text_field( $f_prov_raw ) );

        $f_cat_raw = isset( $_GET['f_cat'] ) ? wp_unslash( $_GET['f_cat'] ) : '';
        $f_cat = sanitize_text_field( $f_cat_raw );

        $s_raw = isset( $_GET['s'] ) ? wp_unslash( $_GET['s'] ) : '';
        $s = sanitize_text_field( $s_raw );

        if ( ! array_key_exists( $f_prov, $this->province_data ) ) {
            $f_prov = '';
        }

        if ( $f_comune !== '' && ! in_array( $f_comune, $this->all_comuni, true ) ) {
            $f_comune = '';
        }

        if ( $f_prov && isset( $this->comuni_data[ $f_prov ] ) ) {
            $comuni_options = array_values( $this->comuni_data[ $f_prov ] );
        } else {
            $comuni_options = $this->all_comuni;
        }

        $comuni_options = array_filter( $comuni_options, 'is_string' );
        $comuni_options = array_values( array_unique( $comuni_options ) );
        sort( $comuni_options, SORT_NATURAL | SORT_FLAG_CASE );

        // Ottieni categorie per filtro
        $categories = PCV_Category_Manager::get_categories_for_select();

        $url_no_vars = remove_query_arg( [ 'f_comune', 'f_prov', 'f_cat', 's', 'paged' ] );

        echo '<div class="pcv-topbar"><form method="get" id="pcv-filter-form">';
        echo '<input type="hidden" name="page" value="pcv-volontari">';

        echo '<label class="screen-reader-text" for="pcv-admin-provincia">' . esc_html__( 'Filtra per Provincia', self::TEXT_DOMAIN ) . '</label>';
        echo '<select name="f_prov" id="pcv-admin-provincia">';
        echo '<option value="">' . esc_html__( 'Tutte le province', self::TEXT_DOMAIN ) . '</option>';
        foreach ( $this->province_data as $code => $label ) {
            $selected_attr = selected( $f_prov, $code, false );
            $option_label = sprintf( '%s (%s)', $label, $code );
            echo '<option value="' . esc_attr( $code ) . '"' . $selected_attr . '>' . esc_html( $option_label ) . '</option>';
        }
        echo '</select>';

        echo '<label class="screen-reader-text" for="pcv-admin-comune">' . esc_html__( 'Filtra per Comune', self::TEXT_DOMAIN ) . '</label>';
        echo '<select name="f_comune" id="pcv-admin-comune" data-selected="' . esc_attr( $f_comune ) . '">';
        echo '<option value="">' . esc_html__( 'Tutti i comuni', self::TEXT_DOMAIN ) . '</option>';
        foreach ( $comuni_options as $comune_name ) {
            $selected_attr = selected( $f_comune, $comune_name, false );
            echo '<option value="' . esc_attr( $comune_name ) . '"' . $selected_attr . '>' . esc_html( $comune_name ) . '</option>';
        }
        echo '</select>';

        echo '<label class="screen-reader-text" for="pcv-admin-categoria">' . esc_html__( 'Filtra per Categoria', self::TEXT_DOMAIN ) . '</label>';
        echo '<select name="f_cat" id="pcv-admin-categoria">';
        echo '<option value="">' . esc_html__( 'Tutte le categorie', self::TEXT_DOMAIN ) . '</option>';
        foreach ( $categories as $cat_name ) {
            $selected_attr = selected( $f_cat, $cat_name, false );
            echo '<option value="' . esc_attr( $cat_name ) . '"' . $selected_attr . '>' . esc_html( $cat_name ) . '</option>';
        }
        echo '</select>';

        echo '<input type="search" name="s" value="' . esc_attr( $s ) . '" placeholder="' . esc_attr__( 'Cerca…', self::TEXT_DOMAIN ) . '">';
        submit_button( __( 'Filtra', self::TEXT_DOMAIN ), 'secondary', '', false );
        echo ' <a href="' . esc_url( $url_no_vars ) . '" class="button">' . esc_html__( 'Pulisci', self::TEXT_DOMAIN ) . '</a> ';
        $export_url = wp_nonce_url( add_query_arg( [ 'pcv_export' => 'csv' ], admin_url( 'admin.php?page=pcv-volontari' ) ), 'pcv_export' );
        echo ' <a class="button button-primary" href="' . esc_url( $export_url ) . '">' . esc_html__( 'Export CSV', self::TEXT_DOMAIN ) . '</a>';
        echo '</form></div>';
    }
}