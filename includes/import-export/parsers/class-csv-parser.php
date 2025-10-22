<?php
/**
 * Parser per file CSV
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_CSV_Parser {

    const TEXT_DOMAIN = 'pc-volontari-abruzzo';

    /**
     * Parse un file CSV
     *
     * @param string $path Percorso al file
     * @return array|WP_Error
     */
    public function parse( $path ) {
        $handle = fopen( $path, 'r' );

        if ( ! $handle ) {
            return new WP_Error( 'pcv_import_csv_open', __( 'Impossibile leggere il file CSV.', self::TEXT_DOMAIN ) );
        }

        $first_line = fgets( $handle );
        if ( $first_line === false ) {
            fclose( $handle );

            return [
                'headers'            => [],
                'normalized_headers' => [],
                'rows'               => [],
            ];
        }

        $delimiters = [ ';', ',', "\t" ];
        $delimiter = ';';
        $max_count = 0;
        foreach ( $delimiters as $candidate ) {
            $count = substr_count( $first_line, $candidate );
            if ( $count > $max_count ) {
                $max_count = $count;
                $delimiter = $candidate;
            }
        }

        rewind( $handle );

        $headers = fgetcsv( $handle, 0, $delimiter );
        if ( ! is_array( $headers ) ) {
            fclose( $handle );

            return new WP_Error( 'pcv_import_csv_header', __( 'Intestazioni CSV non valide.', self::TEXT_DOMAIN ) );
        }

        $rows = [];
        while ( ( $data = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
            if ( $data === null ) {
                continue;
            }

            $rows[] = $data;
        }

        fclose( $handle );

        return $this->prepare_dataset( $headers, $rows );
    }

    /**
     * Prepara il dataset normalizzando headers e rows
     *
     * @param array $headers
     * @param array $raw_rows
     * @return array
     */
    private function prepare_dataset( array $headers, array $raw_rows ) {
        $clean_headers = [];
        $normalized_headers = [];

        foreach ( $headers as $index => $header ) {
            $original_header = (string) $header;
            if ( $index === 0 ) {
                $original_header = preg_replace( '/^\xEF\xBB\xBF/', '', $original_header );
            }

            $label = trim( $original_header );
            if ( $label === '' ) {
                $label = sprintf( __( 'Colonna %d', self::TEXT_DOMAIN ), $index + 1 );
            }

            $clean_headers[ $index ] = $label;
            $normalized_headers[ $index ] = $this->normalize_header( $original_header );
        }

        $rows = [];
        foreach ( $raw_rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $values = [];
            $has_value = false;
            foreach ( $clean_headers as $index => $label ) {
                $value = isset( $row[ $index ] ) ? trim( (string) $row[ $index ] ) : '';
                if ( $value !== '' ) {
                    $has_value = true;
                }
                $values[ $index ] = $value;
            }

            if ( $has_value ) {
                $rows[] = $values;
            }
        }

        return [
            'headers'             => array_values( $clean_headers ),
            'normalized_headers' => array_values( $normalized_headers ),
            'rows'                => $rows,
        ];
    }

    /**
     * Normalizza header per mapping automatico
     *
     * @param string $header
     * @return string
     */
    private function normalize_header( $header ) {
        $header = strtolower( trim( (string) $header ) );
        $header = str_replace( [ 'à', 'è', 'é', 'ì', 'ò', 'ù' ], [ 'a', 'e', 'e', 'i', 'o', 'u' ], $header );
        $header = preg_replace( '/[^a-z0-9]+/', '_', $header );
        $header = trim( $header, '_' );

        $map = [
            'nome'           => 'nome',
            'cognome'        => 'cognome',
            'comune'         => 'comune',
            'provincia'      => 'provincia',
            'email'          => 'email',
            'telefono'       => 'telefono',
            'privacy'        => 'privacy',
            'partecipa'      => 'partecipa',
            'pasti'          => 'mangia',
            'mangia'         => 'mangia',
            'pernotta'       => 'dorme',
            'dorme'          => 'dorme',
            'accompagnatori' => 'accompagnatori',
            'num_accompagnatori' => 'num_accompagnatori',
            'created_at'     => 'created_at',
            'data'           => 'created_at',
            'ip'             => 'ip',
            'user_agent'     => 'user_agent',
        ];

        if ( isset( $map[ $header ] ) ) {
            return $map[ $header ];
        }

        return '';
    }
}