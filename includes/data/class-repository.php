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
            [ '%s','%s','%s','%s','%s','%s','%s','%s','%d','%d','%d','%d','%s','%s','%s' ]
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
            'limit'   => 100,
            'offset'  => 0,
            'search'  => '',
            'comune'  => '',
            'provincia' => '',
            'categoria' => '',
        ];

        $args = wp_parse_args( $args, $defaults );

        $where = 'WHERE 1=1';
        $params = [];

        if ( $args['comune'] !== '' ) {
            $where .= " AND comune = %s";
            $params[] = $args['comune'];
        }

        if ( $args['provincia'] !== '' ) {
            $where .= " AND provincia = %s";
            $params[] = $args['provincia'];
        }

        if ( $args['categoria'] !== '' ) {
            $where .= " AND categoria = %s";
            $params[] = $args['categoria'];
        }

        if ( $args['search'] !== '' ) {
            $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where .= " AND ( nome LIKE %s OR cognome LIKE %s OR email LIKE %s OR telefono LIKE %s OR categoria LIKE %s OR comune LIKE %s )";
            array_push( $params, $like, $like, $like, $like, $like, $like );
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
            'categoria' => '',
        ];

        $args = wp_parse_args( $args, $defaults );

        $where = 'WHERE 1=1';
        $params = [];

        if ( $args['comune'] !== '' ) {
            $where .= " AND comune = %s";
            $params[] = $args['comune'];
        }

        if ( $args['provincia'] !== '' ) {
            $where .= " AND provincia = %s";
            $params[] = $args['provincia'];
        }

        if ( $args['categoria'] !== '' ) {
            $where .= " AND categoria = %s";
            $params[] = $args['categoria'];
        }

        if ( $args['search'] !== '' ) {
            $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where .= " AND ( nome LIKE %s OR cognome LIKE %s OR email LIKE %s OR telefono LIKE %s OR categoria LIKE %s OR comune LIKE %s )";
            array_push( $params, $like, $like, $like, $like, $like, $like );
        }

        $sql = "SELECT COUNT(*) FROM {$table} {$where}";

        if ( empty( $params ) ) {
            return (int) $wpdb->get_var( $sql );
        }

        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
    }

    /**
     * Recupera un singolo volontario per ID
     *
     * @param int $id ID del volontario
     * @return object|null Oggetto volontario o null
     */
    public function get_by_id( $id ) {
        global $wpdb;
        $table = PCV_Database::get_table_name();
        
        $id = absint( $id );
        if ( ! $id ) {
            return null;
        }

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
    }

    /**
     * Aggiorna un volontario
     *
     * @param int $id ID del volontario
     * @param array $data Dati da aggiornare
     * @return int|false Numero di righe aggiornate o false
     */
    public function update( $id, array $data ) {
        global $wpdb;
        $table = PCV_Database::get_table_name();

        $id = absint( $id );
        if ( ! $id ) {
            return false;
        }

        // Definisci i formati per ogni campo
        $formats = [];
        $allowed_fields = [
            'nome' => '%s',
            'cognome' => '%s',
            'comune' => '%s',
            'provincia' => '%s',
            'email' => '%s',
            'telefono' => '%s',
            'categoria' => '%s',
            'privacy' => '%d',
            'partecipa' => '%d',
            'dorme' => '%d',
            'mangia' => '%d',
            'note' => '%s',
        ];

        // Filtra solo i campi permessi
        $update_data = [];
        foreach ( $data as $key => $value ) {
            if ( isset( $allowed_fields[ $key ] ) ) {
                $update_data[ $key ] = $value;
                $formats[] = $allowed_fields[ $key ];
            }
        }

        if ( empty( $update_data ) ) {
            return false;
        }

        return $wpdb->update( $table, $update_data, [ 'id' => $id ], $formats, [ '%d' ] );
    }

    /**
     * Aggiorna più volontari in blocco
     *
     * @param array $ids Array di ID
     * @param array $data Dati da aggiornare
     * @return int Numero di righe aggiornate
     */
    public function update_by_ids( array $ids, array $data ) {
        if ( empty( $ids ) || empty( $data ) ) {
            return 0;
        }

        $ids = array_map( 'absint', $ids );
        $ids = array_filter( $ids );

        if ( empty( $ids ) ) {
            return 0;
        }

        $count = 0;
        foreach ( $ids as $id ) {
            $result = $this->update( $id, $data );
            if ( $result !== false ) {
                $count++;
            }
        }

        return $count;
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