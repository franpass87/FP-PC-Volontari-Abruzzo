<?php
/**
 * Gestione importazione volontari
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Importer {

    const TEXT_DOMAIN = 'pc-volontari-abruzzo';
    const IMPORT_EXPECTED_COLUMNS = [
        'nome',
        'cognome',
        'comune',
        'provincia',
        'email',
        'telefono',
    ];

    private $csv_parser;
    private $xlsx_parser;
    private $sanitizer;
    private $province_data;

    /**
     * Costruttore
     *
     * @param PCV_CSV_Parser $csv_parser
     * @param PCV_XLSX_Parser $xlsx_parser
     * @param PCV_Sanitizer $sanitizer
     * @param array $province_data
     */
    public function __construct( $csv_parser, $xlsx_parser, $sanitizer, $province_data ) {
        $this->csv_parser = $csv_parser;
        $this->xlsx_parser = $xlsx_parser;
        $this->sanitizer = $sanitizer;
        $this->province_data = $province_data;
    }

    /**
     * Parse file di import
     *
     * @param string $path
     * @return array|WP_Error
     */
    public function parse_file( $path ) {
        $extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

        if ( $extension === 'csv' || $extension === 'txt' ) {
            return $this->csv_parser->parse( $path );
        }

        if ( $extension === 'xlsx' ) {
            return $this->xlsx_parser->parse( $path );
        }

        if ( $extension === 'xls' ) {
            return new WP_Error( 'pcv_import_invalid_extension', __( 'I file Excel in formato .xls non sono supportati. Converti il file in formato .xlsx e riprova.', self::TEXT_DOMAIN ) );
        }

        return new WP_Error( 'pcv_import_invalid_extension', __( 'Formato file non supportato. Carica un file CSV o Excel (.xlsx).', self::TEXT_DOMAIN ) );
    }

    /**
     * Costruisce mapping predefinito
     *
     * @param array $dataset
     * @return array
     */
    public function build_default_map( array $dataset ) {
        $field_definitions = $this->get_field_definitions();
        $fields = array_keys( $field_definitions );
        $map = array_fill_keys( $fields, null );

        $normalized_headers = isset( $dataset['normalized_headers'] ) && is_array( $dataset['normalized_headers'] ) ? $dataset['normalized_headers'] : [];

        foreach ( $normalized_headers as $index => $normalized ) {
            if ( $normalized === '' ) {
                continue;
            }

            if ( array_key_exists( $normalized, $map ) && $map[ $normalized ] === null ) {
                $map[ $normalized ] = $index;
            }
        }

        return $map;
    }

    /**
     * Sanitizza mapping
     *
     * @param array $raw_map
     * @param int $headers_count
     * @return array
     */
    public function sanitize_map( array $raw_map, $headers_count ) {
        $field_definitions = $this->get_field_definitions();
        $clean_map = array_fill_keys( array_keys( $field_definitions ), null );

        foreach ( $raw_map as $field => $value ) {
            if ( ! array_key_exists( $field, $clean_map ) ) {
                continue;
            }

            if ( is_string( $value ) ) {
                $value = trim( $value );
            }

            if ( $value === '' || $value === null ) {
                $clean_map[ $field ] = null;
                continue;
            }

            if ( is_numeric( $value ) ) {
                $index = (int) $value;
                if ( $index >= 0 && $index < $headers_count ) {
                    $clean_map[ $field ] = $index;
                }
            }
        }

        return $clean_map;
    }

    /**
     * Applica mapping al dataset
     *
     * @param array $dataset
     * @param array $map
     * @return array
     */
    public function apply_mapping( array $dataset, array $map ) {
        $rows = [];
        $data_rows = isset( $dataset['rows'] ) && is_array( $dataset['rows'] ) ? $dataset['rows'] : [];
        $field_definitions = $this->get_field_definitions();

        foreach ( $data_rows as $row_values ) {
            if ( ! is_array( $row_values ) ) {
                continue;
            }

            $mapped = [];
            foreach ( $map as $field => $index ) {
                if ( $index === null || ! isset( $field_definitions[ $field ] ) ) {
                    continue;
                }

                $value = isset( $row_values[ $index ] ) ? $row_values[ $index ] : '';
                $mapped[ $field ] = $value;
            }

            if ( ! empty( $mapped ) ) {
                $rows[] = $mapped;
            }
        }

        return $rows;
    }

    /**
     * Importa righe nel database
     *
     * @param array $rows
     * @param string $category
     * @return array
     */
    public function import_rows( array $rows, $category = '' ) {
        global $wpdb;

        $table = PCV_Database::get_table_name();
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $row_index = 1;
        $category = $this->sanitizer->sanitize_text( $category );

        foreach ( $rows as $row ) {
            $row_index++;

            $nome      = $this->sanitizer->sanitize_name( $row['nome'] ?? '' );
            $cognome   = $this->sanitizer->sanitize_name( $row['cognome'] ?? '' );
            $comune    = $this->sanitizer->normalize_comune_input( $row['comune'] ?? '' );
            $provincia = $this->sanitizer->normalize_province_input( $row['provincia'] ?? '', $this->province_data );
            $email_raw = isset( $row['email'] ) ? sanitize_email( $row['email'] ) : '';
            $telefono  = $this->sanitizer->sanitize_phone( $row['telefono'] ?? '' );

            if ( $nome === '' || $cognome === '' || $comune === '' || $provincia === '' || $email_raw === '' || $telefono === '' ) {
                $errors[] = sprintf( __( 'Riga %d: dati obbligatori mancanti o non validi.', self::TEXT_DOMAIN ), $row_index );
                $skipped++;
                continue;
            }

            if ( ! is_email( $email_raw ) ) {
                $errors[] = sprintf( __( 'Riga %d: indirizzo email non valido.', self::TEXT_DOMAIN ), $row_index );
                $skipped++;
                continue;
            }

            $privacy   = $this->sanitizer->normalize_boolean_input( $row['privacy'] ?? '1' );
            $partecipa = $this->sanitizer->normalize_boolean_input( $row['partecipa'] ?? '0' );
            $dorme     = $this->sanitizer->normalize_boolean_input( $row['dorme'] ?? '0' );
            $mangia    = $this->sanitizer->normalize_boolean_input( $row['mangia'] ?? ( $row['pasti'] ?? '0' ) );

            $created_at = $this->sanitizer->normalize_datetime_input( $row['created_at'] ?? ( $row['data'] ?? '' ) );
            $ip         = isset( $row['ip'] ) ? sanitize_text_field( $row['ip'] ) : '';
            $user_agent = isset( $row['user_agent'] ) ? wp_strip_all_tags( $row['user_agent'] ) : '';

            $inserted = $wpdb->insert(
                $table,
                [
                    'created_at' => $created_at,
                    'nome'       => $nome,
                    'cognome'    => $cognome,
                    'comune'     => $comune,
                    'provincia'  => $provincia,
                    'email'      => $email_raw,
                    'telefono'   => $telefono,
                    'categoria'  => $category,
                    'privacy'    => $privacy,
                    'partecipa'  => $partecipa,
                    'dorme'      => $dorme,
                    'mangia'     => $mangia,
                    'ip'         => $ip,
                    'user_agent' => $user_agent,
                ],
                [ '%s','%s','%s','%s','%s','%s','%s','%s','%d','%d','%d','%d','%s','%s' ]
            );

            if ( $inserted ) {
                $imported++;
            } else {
                $errors[] = sprintf( __( 'Riga %d: errore durante il salvataggio nel database.', self::TEXT_DOMAIN ), $row_index );
                $skipped++;
            }
        }

        return [
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ];
    }

    /**
     * Ottiene definizioni campi
     *
     * @return array
     */
    public function get_field_definitions() {
        return [
            'nome' => [
                'label'     => __( 'Nome', self::TEXT_DOMAIN ),
                'required'  => true,
            ],
            'cognome' => [
                'label'     => __( 'Cognome', self::TEXT_DOMAIN ),
                'required'  => true,
            ],
            'comune' => [
                'label'     => __( 'Comune', self::TEXT_DOMAIN ),
                'required'  => true,
            ],
            'provincia' => [
                'label'     => __( 'Provincia', self::TEXT_DOMAIN ),
                'required'  => true,
            ],
            'email' => [
                'label'     => __( 'Email', self::TEXT_DOMAIN ),
                'required'  => true,
            ],
            'telefono' => [
                'label'     => __( 'Telefono', self::TEXT_DOMAIN ),
                'required'  => true,
            ],
            'privacy' => [
                'label'       => __( 'Consenso privacy', self::TEXT_DOMAIN ),
                'required'    => false,
                'description' => __( 'Valori ammessi: 1/0, si/no, true/false.', self::TEXT_DOMAIN ),
            ],
            'partecipa' => [
                'label'       => __( 'Partecipa', self::TEXT_DOMAIN ),
                'required'    => false,
                'description' => __( 'Indica la partecipazione all\'evento (1/0, si/no, true/false).', self::TEXT_DOMAIN ),
            ],
            'dorme' => [
                'label'       => __( 'Pernotta', self::TEXT_DOMAIN ),
                'required'    => false,
                'description' => __( 'Specifica se il volontario pernotta (1/0, si/no, true/false).', self::TEXT_DOMAIN ),
            ],
            'mangia' => [
                'label'       => __( 'Pasti', self::TEXT_DOMAIN ),
                'required'    => false,
                'description' => __( 'Indica se il volontario consumerà i pasti (1/0, si/no, true/false).', self::TEXT_DOMAIN ),
            ],
            'created_at' => [
                'label'       => __( 'Data iscrizione', self::TEXT_DOMAIN ),
                'required'    => false,
                'description' => __( 'Formato consigliato: YYYY-MM-DD HH:MM:SS.', self::TEXT_DOMAIN ),
            ],
            'ip' => [
                'label'       => __( 'Indirizzo IP', self::TEXT_DOMAIN ),
                'required'    => false,
            ],
            'user_agent' => [
                'label'       => __( 'User Agent', self::TEXT_DOMAIN ),
                'required'    => false,
            ],
        ];
    }

    /**
     * Salva dataset in transient
     *
     * @param array $dataset
     * @return string|WP_Error Token o errore
     */
    public function store_dataset( array $dataset ) {
        $token = wp_generate_password( 20, false, false );
        $key = 'pcv_import_' . $token;

        $stored = set_transient( $key, $dataset, 30 * MINUTE_IN_SECONDS );

        if ( ! $stored ) {
            return new WP_Error( 'pcv_import_store_failed', __( 'Impossibile inizializzare la sessione di importazione. Riprova.', self::TEXT_DOMAIN ) );
        }

        return $token;
    }

    /**
     * Recupera dataset da transient
     *
     * @param string $token
     * @return array|null
     */
    public function get_dataset( $token ) {
        if ( $token === '' ) {
            return null;
        }

        $dataset = get_transient( 'pcv_import_' . $token );

        if ( ! is_array( $dataset ) || empty( $dataset['headers'] ) || ! isset( $dataset['rows'] ) ) {
            return null;
        }

        return $dataset;
    }

    /**
     * Elimina dataset da transient
     *
     * @param string $token
     * @return void
     */
    public function delete_dataset( $token ) {
        if ( $token === '' ) {
            return;
        }

        delete_transient( 'pcv_import_' . $token );
    }
}