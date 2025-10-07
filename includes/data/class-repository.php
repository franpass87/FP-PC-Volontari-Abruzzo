<?php
/**
 * Repository per operazioni CRUD sui volontari
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Repository {

    /**
     * Inserisce un nuovo volontario nel database
     *
     * @param array $data Dati del volontario
     * @return int|false ID inserito o false in caso di errore
     */
    public function insert( array $data ) {
        global $wpdb;
        $table = PCV_Database::get_table_name();

        $inserted = $wpdb->insert(
            $table,
            $data,
            [ '%s','%s','%s','%s','%s','%s','%s','%s','%d','%d','%d','%d','%s','%s' ]
        );

        return $inserted ? (int) $wpdb->insert_id : false;
    }

    /**
     * Recupera volontari con filtri e paginazione
     *
     * @param array $args Argomenti per la query
     * @return array
     */
    public function get_volunteers( array $args = [] ) {
        global $wpdb;
        $table = PCV_Database::get_table_name();

        $defaults = [
            'orderby' => 'created_at',
            'order'   => 'DESC',
            'limit'   => 20,
            'offset'  => 0,
            'search'  => '',
            'comune'  => '',
            'provincia' => '',
        ];

        $args = wp_parse_args( $args, $defaults );

        $where = 'WHERE 1=1';
        $params = [];

        if ( $args['comune'] !== '' ) {
            $where .= " AND comune LIKE %s";
            $params[] = '%' . $wpdb->esc_like( $args['comune'] ) . '%';
        }

        if ( $args['provincia'] !== '' ) {
            $where .= " AND provincia LIKE %s";
            $params[] = '%' . $wpdb->esc_like( $args['provincia'] ) . '%';
        }

        if ( $args['search'] !== '' ) {
            $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where .= " AND ( nome LIKE %s OR cognome LIKE %s OR email LIKE %s OR telefono LIKE %s OR categoria LIKE %s )";
            array_push( $params, $like, $like, $like, $like, $like );
        }

        $orderby = sanitize_key( $args['orderby'] );
        $allowed_orderby = ['created_at','nome','cognome','comune','provincia','categoria'];
        if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
            $orderby = 'created_at';
        }

        $order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = (int) $args['limit'];
        $params[] = (int) $args['offset'];

        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
    }

    /**
     * Conta i volontari con filtri
     *
     * @param array $args Argomenti per la query
     * @return int
     */
    public function count_volunteers( array $args = [] ) {
        global $wpdb;
        $table = PCV_Database::get_table_name();

        $defaults = [
            'search'    => '',
            'comune'    => '',
            'provincia' => '',
        ];

        $args = wp_parse_args( $args, $defaults );

        $where = 'WHERE 1=1';
        $params = [];

        if ( $args['comune'] !== '' ) {
            $where .= " AND comune LIKE %s";
            $params[] = '%' . $wpdb->esc_like( $args['comune'] ) . '%';
        }

        if ( $args['provincia'] !== '' ) {
            $where .= " AND provincia LIKE %s";
            $params[] = '%' . $wpdb->esc_like( $args['provincia'] ) . '%';
        }

        if ( $args['search'] !== '' ) {
            $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where .= " AND ( nome LIKE %s OR cognome LIKE %s OR email LIKE %s OR telefono LIKE %s OR categoria LIKE %s )";
            array_push( $params, $like, $like, $like, $like, $like );
        }

        $sql = "SELECT COUNT(*) FROM {$table} {$where}";

        if ( empty( $params ) ) {
            return (int) $wpdb->get_var( $sql );
        }

        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
    }

    /**
     * Elimina volontari per ID
     *
     * @param array $ids Array di ID
     * @return int|false Numero di righe eliminate
     */
    public function delete_by_ids( array $ids ) {
        if ( empty( $ids ) ) {
            return 0;
        }

        global $wpdb;
        $table = PCV_Database::get_table_name();

        $ids = array_map( 'absint', $ids );
        $ids = array_filter( $ids );

        if ( empty( $ids ) ) {
            return 0;
        }

        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        return $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ($placeholders)", $ids ) );
    }
}