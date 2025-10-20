<?php
/**
 * Gestione esportazione volontari in CSV
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Exporter {

    const TEXT_DOMAIN = 'pc-volontari-abruzzo';

    private $repository;
    private $sanitizer;

    /**
     * Costruttore
     *
     * @param PCV_Repository $repository
     * @param PCV_Sanitizer $sanitizer
     */
    public function __construct( $repository, $sanitizer ) {
        $this->repository = $repository;
        $this->sanitizer = $sanitizer;
    }

    /**
     * Esporta volontari in CSV
     *
     * @param array $filters Filtri da applicare
     * @return void
     */
    public function export_to_csv( array $filters = [] ) {
        global $wpdb;
        $table = PCV_Database::get_table_name();

        $where = 'WHERE 1=1';
        $params = [];

        if ( ! empty( $filters['comune'] ) ) {
            $f_comune = sanitize_text_field( $filters['comune'] );
            $where .= " AND comune LIKE %s";
            $params[] = '%' . $wpdb->esc_like( $f_comune ) . '%';
        }

        if ( ! empty( $filters['provincia'] ) ) {
            $f_prov = sanitize_text_field( $filters['provincia'] );
            $where .= " AND provincia LIKE %s";
            $params[] = '%' . $wpdb->esc_like( $f_prov ) . '%';
        }

        if ( ! empty( $filters['search'] ) ) {
            $search = sanitize_text_field( $filters['search'] );
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= " AND ( nome LIKE %s OR cognome LIKE %s OR email LIKE %s OR telefono LIKE %s OR categoria LIKE %s OR accompagnatori LIKE %s )";
            array_push( $params, $like, $like, $like, $like, $like, $like );
        }

        $sql = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC";
        $rows = empty( $params )
            ? $wpdb->get_results( $sql, ARRAY_A )
            : $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=volontari_abruzzo_' . date( 'Ymd_His' ) . '.csv' );

        $out = fopen( 'php://output', 'w' );
        $headers = [
            __( 'ID', self::TEXT_DOMAIN ),
            __( 'Data', self::TEXT_DOMAIN ),
            __( 'Nome', self::TEXT_DOMAIN ),
            __( 'Cognome', self::TEXT_DOMAIN ),
            __( 'Comune', self::TEXT_DOMAIN ),
            __( 'Provincia', self::TEXT_DOMAIN ),
            __( 'Email', self::TEXT_DOMAIN ),
            __( 'Telefono', self::TEXT_DOMAIN ),
            __( 'Categoria', self::TEXT_DOMAIN ),
            __( 'Chiamato', self::TEXT_DOMAIN ),
            __( 'Note', self::TEXT_DOMAIN ),
            __( 'Accompagnatori', self::TEXT_DOMAIN ),
            __( 'Privacy', self::TEXT_DOMAIN ),
            __( 'Partecipa', self::TEXT_DOMAIN ),
            __( 'Pernotta', self::TEXT_DOMAIN ),
            __( 'Pasti', self::TEXT_DOMAIN ),
            __( 'IP', self::TEXT_DOMAIN ),
            __( 'User Agent', self::TEXT_DOMAIN ),
        ];
        fputcsv( $out, $headers, ';' );

        foreach ( $rows as $r ) {
            fputcsv( $out, [
                $r['id'],
                $r['created_at'],
                $this->sanitizer->csv_text_guard( $r['nome'] ),
                $this->sanitizer->csv_text_guard( $r['cognome'] ),
                $this->sanitizer->csv_text_guard( $r['comune'] ),
                $this->sanitizer->csv_text_guard( $r['provincia'] ),
                $this->sanitizer->csv_text_guard( $r['email'] ),
                $this->sanitizer->csv_text_guard( $r['telefono'] ),
                $this->sanitizer->csv_text_guard( isset( $r['categoria'] ) ? $r['categoria'] : '' ),
                ! empty( $r['chiamato'] ) ? '1' : '0',
                $this->sanitizer->csv_text_guard( isset( $r['note'] ) ? $r['note'] : '' ),
                $this->sanitizer->csv_text_guard( isset( $r['accompagnatori'] ) ? $r['accompagnatori'] : '' ),
                $r['privacy'] ? '1' : '0',
                $r['partecipa'] ? '1' : '0',
                ! empty( $r['dorme'] ) ? '1' : '0',
                ! empty( $r['mangia'] ) ? '1' : '0',
                $this->sanitizer->csv_text_guard( $r['ip'] ),
                $this->sanitizer->csv_text_guard( $r['user_agent'] ),
            ], ';' );
        }
        fclose( $out );
        exit;
    }
}