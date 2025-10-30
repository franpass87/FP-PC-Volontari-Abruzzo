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

		if ( ! empty( $filters['categoria'] ) ) {
			$f_cat = sanitize_text_field( $filters['categoria'] );
			$where .= " AND categoria LIKE %s";
			$params[] = '%' . $wpdb->esc_like( $f_cat ) . '%';
		}

		$allowed_orderby = [ 'created_at', 'nome', 'cognome', 'comune', 'provincia', 'categoria' ];
		$orderby = 'created_at';
		if ( ! empty( $filters['sort_by'] ) ) {
			$maybe = sanitize_key( $filters['sort_by'] );
			if ( in_array( $maybe, $allowed_orderby, true ) ) {
				$orderby = $maybe;
			}
		}
		$order = 'DESC';
		if ( ! empty( $filters['sort_dir'] ) ) {
			$dir = strtoupper( $filters['sort_dir'] );
			$order = ( $dir === 'ASC' ) ? 'ASC' : 'DESC';
		}

		$sql = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order}";
        $rows = empty( $params )
            ? $wpdb->get_results( $sql, ARRAY_A )
            : $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=volontari_abruzzo_' . date( 'Ymd_His' ) . '.csv' );

        $out = fopen( 'php://output', 'w' );
		$all_headers = [
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

		$default_order = array_keys( $all_headers );
		$requested_cols = [];
		if ( ! empty( $filters['columns'] ) && is_array( $filters['columns'] ) ) {
			foreach ( $filters['columns'] as $col ) {
				$k = sanitize_key( $col );
				if ( isset( $all_headers[ $k ] ) ) {
					$requested_cols[] = $k;
				}
			}
		}
		$final_cols = ! empty( $requested_cols ) ? $requested_cols : $default_order;

		$headers = [];
		foreach ( $final_cols as $k ) {
			$headers[] = $all_headers[ $k ];
		}
		fputcsv( $out, $headers, ';' );

		foreach ( $rows as $r ) {
			$line = [];
			foreach ( $final_cols as $k ) {
				switch ( $k ) {
					case 'id':
						$line[] = isset( $r['id'] ) ? $r['id'] : '';
						break;
					case 'created_at':
						$line[] = isset( $r['created_at'] ) ? $r['created_at'] : '';
						break;
					case 'nome':
					case 'cognome':
					case 'comune':
					case 'provincia':
					case 'email':
					case 'telefono':
					case 'categoria':
					case 'note':
					case 'accompagnatori':
					case 'ip':
					case 'user_agent':
						$line[] = $this->sanitizer->csv_text_guard( isset( $r[ $k ] ) ? $r[ $k ] : '' );
						break;
					case 'num_accompagnatori':
						$line[] = isset( $r['num_accompagnatori'] ) ? (int) $r['num_accompagnatori'] : 0;
						break;
					case 'chiamato':
					case 'privacy':
					case 'partecipa':
					case 'dorme':
					case 'mangia':
						$val = ! empty( $r[ $k ] ) ? '1' : '0';
						$line[] = $val;
						break;
					default:
						$line[] = isset( $r[ $k ] ) ? $r[ $k ] : '';
				}
			}
			fputcsv( $out, $line, ';' );
		}
        fclose( $out );
        exit;
    }
}