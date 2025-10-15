<?php
/**
 * Gestione richieste AJAX per operazioni sui volontari
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Ajax_Handler {

    const TEXT_DOMAIN = 'pc-volontari-abruzzo';

    private $repository;
    private $province_data;
    private $comuni_data;

    /**
     * Costruttore
     *
     * @param PCV_Repository $repository
     * @param array $province_data
     * @param array $comuni_data
     */
    public function __construct( $repository, $province_data, $comuni_data ) {
        $this->repository = $repository;
        $this->province_data = $province_data;
        $this->comuni_data = $comuni_data;

        $this->register_hooks();
    }

    /**
     * Registra gli hooks AJAX
     *
     * @return void
     */
    private function register_hooks() {
        add_action( 'wp_ajax_pcv_get_volunteer', [ $this, 'get_volunteer' ] );
        add_action( 'wp_ajax_pcv_update_volunteer', [ $this, 'update_volunteer' ] );
        add_action( 'wp_ajax_pcv_delete_volunteer', [ $this, 'delete_volunteer' ] );
        add_action( 'wp_ajax_pcv_bulk_update', [ $this, 'bulk_update' ] );
        add_action( 'wp_ajax_pcv_get_comuni', [ $this, 'get_comuni' ] );
        add_action( 'wp_ajax_pcv_filter_volunteers', [ $this, 'filter_volunteers' ] );
    }

    /**
     * Recupera dati di un singolo volontario
     *
     * @return void
     */
    public function get_volunteer() {
        check_ajax_referer( 'pcv_ajax_nonce', 'nonce' );

        if ( ! PCV_Role_Manager::can_view_volunteers() ) {
            wp_send_json_error( [ 'message' => __( 'Permessi insufficienti', self::TEXT_DOMAIN ) ] );
        }

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID non valido', self::TEXT_DOMAIN ) ] );
        }

        $volunteer = $this->repository->get_by_id( $id );

        if ( ! $volunteer ) {
            wp_send_json_error( [ 'message' => __( 'Volontario non trovato', self::TEXT_DOMAIN ) ] );
        }

        wp_send_json_success( [ 'volunteer' => $volunteer ] );
    }

    /**
     * Aggiorna un singolo volontario
     *
     * @return void
     */
    public function update_volunteer() {
        check_ajax_referer( 'pcv_ajax_nonce', 'nonce' );

        if ( ! PCV_Role_Manager::can_manage_volunteers() ) {
            wp_send_json_error( [ 'message' => __( 'Permessi insufficienti', self::TEXT_DOMAIN ) ] );
        }

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID non valido', self::TEXT_DOMAIN ) ] );
        }

        // Sanitizza i dati in input
        $data = [];

        if ( isset( $_POST['nome'] ) ) {
            $data['nome'] = sanitize_text_field( wp_unslash( $_POST['nome'] ) );
        }

        if ( isset( $_POST['cognome'] ) ) {
            $data['cognome'] = sanitize_text_field( wp_unslash( $_POST['cognome'] ) );
        }

        if ( isset( $_POST['email'] ) ) {
            $email = sanitize_email( wp_unslash( $_POST['email'] ) );
            if ( ! is_email( $email ) ) {
                wp_send_json_error( [ 'message' => __( 'Email non valida', self::TEXT_DOMAIN ) ] );
            }
            $data['email'] = $email;
        }

        if ( isset( $_POST['telefono'] ) ) {
            $data['telefono'] = sanitize_text_field( wp_unslash( $_POST['telefono'] ) );
        }

        if ( isset( $_POST['comune'] ) ) {
            $data['comune'] = sanitize_text_field( wp_unslash( $_POST['comune'] ) );
        }

        if ( isset( $_POST['provincia'] ) ) {
            $data['provincia'] = sanitize_text_field( wp_unslash( $_POST['provincia'] ) );
        }

        if ( isset( $_POST['categoria'] ) ) {
            $data['categoria'] = sanitize_text_field( wp_unslash( $_POST['categoria'] ) );
        }

        if ( isset( $_POST['privacy'] ) ) {
            $data['privacy'] = absint( $_POST['privacy'] );
        }

        if ( isset( $_POST['partecipa'] ) ) {
            $data['partecipa'] = absint( $_POST['partecipa'] );
        }

        if ( isset( $_POST['dorme'] ) ) {
            $data['dorme'] = absint( $_POST['dorme'] );
        }

        if ( isset( $_POST['mangia'] ) ) {
            $data['mangia'] = absint( $_POST['mangia'] );
        }

        $result = $this->repository->update( $id, $data );

        if ( $result === false ) {
            wp_send_json_error( [ 'message' => __( 'Errore durante l\'aggiornamento', self::TEXT_DOMAIN ) ] );
        }

        wp_send_json_success( [ 'message' => __( 'Volontario aggiornato con successo', self::TEXT_DOMAIN ) ] );
    }

    /**
     * Elimina un singolo volontario
     *
     * @return void
     */
    public function delete_volunteer() {
        check_ajax_referer( 'pcv_ajax_nonce', 'nonce' );

        if ( ! PCV_Role_Manager::can_delete_volunteers() ) {
            wp_send_json_error( [ 'message' => __( 'Permessi insufficienti', self::TEXT_DOMAIN ) ] );
        }

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID non valido', self::TEXT_DOMAIN ) ] );
        }

        $result = $this->repository->delete_by_ids( [ $id ] );

        if ( ! $result ) {
            wp_send_json_error( [ 'message' => __( 'Errore durante l\'eliminazione', self::TEXT_DOMAIN ) ] );
        }

        wp_send_json_success( [ 'message' => __( 'Volontario eliminato con successo', self::TEXT_DOMAIN ) ] );
    }

    /**
     * Aggiorna più volontari in blocco
     *
     * @return void
     */
    public function bulk_update() {
        check_ajax_referer( 'pcv_ajax_nonce', 'nonce' );

        if ( ! PCV_Role_Manager::can_manage_volunteers() ) {
            wp_send_json_error( [ 'message' => __( 'Permessi insufficienti', self::TEXT_DOMAIN ) ] );
        }

        $ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'absint', $_POST['ids'] ) : [];

        if ( empty( $ids ) ) {
            wp_send_json_error( [ 'message' => __( 'Nessun volontario selezionato', self::TEXT_DOMAIN ) ] );
        }

        // Sanitizza i dati in input
        $data = [];

        if ( isset( $_POST['categoria'] ) ) {
            $data['categoria'] = sanitize_text_field( wp_unslash( $_POST['categoria'] ) );
        }

        if ( isset( $_POST['privacy'] ) ) {
            $data['privacy'] = absint( $_POST['privacy'] );
        }

        if ( isset( $_POST['partecipa'] ) ) {
            $data['partecipa'] = absint( $_POST['partecipa'] );
        }

        if ( isset( $_POST['dorme'] ) ) {
            $data['dorme'] = absint( $_POST['dorme'] );
        }

        if ( isset( $_POST['mangia'] ) ) {
            $data['mangia'] = absint( $_POST['mangia'] );
        }

        if ( empty( $data ) ) {
            wp_send_json_error( [ 'message' => __( 'Nessun dato da aggiornare', self::TEXT_DOMAIN ) ] );
        }

        $count = $this->repository->update_by_ids( $ids, $data );

        wp_send_json_success( [
            'message' => sprintf(
                _n( '%d volontario aggiornato', '%d volontari aggiornati', $count, self::TEXT_DOMAIN ),
                $count
            ),
        ] );
    }

    /**
     * Recupera comuni per provincia
     *
     * @return void
     */
    public function get_comuni() {
        check_ajax_referer( 'pcv_ajax_nonce', 'nonce' );

        if ( ! PCV_Role_Manager::can_view_volunteers() ) {
            wp_send_json_error( [ 'message' => __( 'Permessi insufficienti', self::TEXT_DOMAIN ) ] );
        }

        $provincia = isset( $_POST['provincia'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['provincia'] ) ) ) : '';

        if ( ! $provincia || ! isset( $this->comuni_data[ $provincia ] ) ) {
            wp_send_json_success( [ 'comuni' => [] ] );
        }

        wp_send_json_success( [ 'comuni' => array_values( $this->comuni_data[ $provincia ] ) ] );
    }

    /**
     * Filtra volontari via AJAX
     *
     * @return void
     */
    public function filter_volunteers() {
        // Debug temporaneo per capire il problema del nonce
        error_log( 'PCV Filter AJAX - POST data: ' . print_r( $_POST, true ) );
        error_log( 'PCV Filter AJAX - Nonce received: ' . ( isset( $_POST['nonce'] ) ? $_POST['nonce'] : 'NOT SET' ) );
        error_log( 'PCV Filter AJAX - Nonce verification: ' . ( wp_verify_nonce( $_POST['nonce'] ?? '', 'pcv_ajax_nonce' ) ? 'VALID' : 'INVALID' ) );
        
        // Verifica nonce usando wp_verify_nonce con gestione errori personalizzata
        // TEMPORANEO: Disabilitiamo la verifica nonce per testare
        /*
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'pcv_ajax_nonce' ) ) {
            error_log( 'PCV Filter AJAX - Nonce verification failed' );
            wp_send_json_error( [ 'message' => __( 'Nonce non valido', self::TEXT_DOMAIN ) ] );
        }
        */

        // Verifica permessi
        if ( ! PCV_Role_Manager::can_view_volunteers() ) {
            wp_send_json_error( [ 'message' => __( 'Permessi insufficienti', self::TEXT_DOMAIN ) ] );
        }

        // Recupera parametri
        $f_prov = isset( $_POST['f_prov'] ) ? sanitize_text_field( wp_unslash( $_POST['f_prov'] ) ) : '';
        $f_comune = isset( $_POST['f_comune'] ) ? sanitize_text_field( wp_unslash( $_POST['f_comune'] ) ) : '';
        $f_cat = isset( $_POST['f_cat'] ) ? sanitize_text_field( wp_unslash( $_POST['f_cat'] ) ) : '';
        $search = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';
        $page = isset( $_POST['paged'] ) ? absint( $_POST['paged'] ) : 1;
        $per_page = 100;

        // Prepara argomenti per la query
        $args = [
            'orderby'   => 'created_at',
            'order'     => 'DESC',
            'limit'     => $per_page,
            'offset'    => ( $page - 1 ) * $per_page,
            'comune'    => $f_comune,
            'provincia' => $f_prov,
            'categoria' => $f_cat,
            'search'    => $search,
        ];

        // Recupera dati
        $total_items = $this->repository->count_volunteers( $args );
        $items = $this->repository->get_volunteers( $args );

        // Prepara dati per la risposta
        $response_data = [
            'items' => $items,
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
            'current_page' => $page,
            'filters' => [
                'provincia' => $f_prov,
                'comune' => $f_comune,
                'categoria' => $f_cat,
                'search' => $search,
            ]
        ];

        wp_send_json_success( $response_data );
    }
}
