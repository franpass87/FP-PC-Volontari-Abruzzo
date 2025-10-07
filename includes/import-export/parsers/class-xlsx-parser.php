<?php
/**
 * Parser per file Excel (.xlsx)
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_XLSX_Parser {

    const TEXT_DOMAIN = 'pc-volontari-abruzzo';

    /**
     * Parse un file XLSX
     *
     * @param string $path Percorso al file
     * @return array|WP_Error
     */
    public function parse( $path ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 'pcv_import_zip_missing', __( 'Il server non supporta l\'apertura dei file Excel (.xlsx).', self::TEXT_DOMAIN ) );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $path ) !== true ) {
            return new WP_Error( 'pcv_import_xlsx_open', __( 'Impossibile aprire il file Excel.', self::TEXT_DOMAIN ) );
        }

        $sheet_path = 'xl/worksheets/sheet1.xml';
        $workbook = $zip->getFromName( 'xl/workbook.xml' );
        $rels = $zip->getFromName( 'xl/_rels/workbook.xml.rels' );

        if ( $workbook && $rels ) {
            $sheet_path = $this->get_first_sheet_path( $workbook, $rels );
        }

        $sheet_xml = $zip->getFromName( $sheet_path );
        if ( ! $sheet_xml ) {
            $zip->close();

            return new WP_Error( 'pcv_import_xlsx_sheet', __( 'Impossibile trovare il foglio di lavoro nel file Excel.', self::TEXT_DOMAIN ) );
        }

        $shared_strings = $this->extract_shared_strings( $zip );

        $zip->close();

        $sheet = simplexml_load_string( $sheet_xml );
        if ( ! $sheet || ! isset( $sheet->sheetData ) ) {
            return new WP_Error( 'pcv_import_xlsx_parse', __( 'Formato Excel non valido.', self::TEXT_DOMAIN ) );
        }

        $rows = $this->extract_rows_from_sheet( $sheet, $shared_strings );

        if ( empty( $rows ) ) {
            return [
                'headers'             => [],
                'normalized_headers' => [],
                'rows'                => [],
            ];
        }

        $headers = array_shift( $rows );
        return $this->prepare_dataset( $headers, $rows );
    }

    /**
     * Estrae stringhe condivise dal file XLSX
     *
     * @param ZipArchive $zip
     * @return array
     */
    private function extract_shared_strings( $zip ) {
        $shared_strings = [];
        $shared_xml = $zip->getFromName( 'xl/sharedStrings.xml' );
        if ( $shared_xml ) {
            $shared = simplexml_load_string( $shared_xml );
            if ( $shared ) {
                foreach ( $shared->si as $si ) {
                    if ( isset( $si->t ) ) {
                        $shared_strings[] = (string) $si->t;
                    } elseif ( isset( $si->r ) ) {
                        $text = '';
                        foreach ( $si->r as $run ) {
                            $text .= (string) $run->t;
                        }
                        $shared_strings[] = $text;
                    } else {
                        $shared_strings[] = '';
                    }
                }
            }
        }

        return $shared_strings;
    }

    /**
     * Estrae righe dal foglio Excel
     *
     * @param SimpleXMLElement $sheet
     * @param array $shared_strings
     * @return array
     */
    private function extract_rows_from_sheet( $sheet, array $shared_strings ) {
        $rows = [];
        foreach ( $sheet->sheetData->row as $row ) {
            $row_values = [];
            foreach ( $row->c as $cell ) {
                $ref = isset( $cell['r'] ) ? (string) $cell['r'] : '';
                $column_index = $ref !== '' ? $this->column_reference_to_index( $ref ) : count( $row_values );
                if ( $column_index === null ) {
                    $column_index = count( $row_values );
                }

                $type = isset( $cell['t'] ) ? (string) $cell['t'] : '';
                $value = '';
                if ( $type === 's' ) {
                    $idx = isset( $cell->v ) ? (int) $cell->v : -1;
                    $value = $idx >= 0 && isset( $shared_strings[ $idx ] ) ? $shared_strings[ $idx ] : '';
                } elseif ( $type === 'inlineStr' ) {
                    $value = isset( $cell->is->t ) ? (string) $cell->is->t : '';
                } else {
                    $value = isset( $cell->v ) ? (string) $cell->v : '';
                }

                $row_values[ $column_index ] = trim( $value );
            }

            if ( ! empty( $row_values ) ) {
                ksort( $row_values );
                $rows[] = array_values( $row_values );
            }
        }

        return $rows;
    }

    /**
     * Ottiene il percorso del primo foglio dal workbook
     *
     * @param string $workbook_xml
     * @param string $rels_xml
     * @return string
     */
    private function get_first_sheet_path( $workbook_xml, $rels_xml ) {
        $sheet_path = 'xl/worksheets/sheet1.xml';

        $workbook = simplexml_load_string( $workbook_xml );
        $rels = simplexml_load_string( $rels_xml );

        if ( ! $workbook || ! isset( $workbook->sheets->sheet ) || ! $rels ) {
            return $sheet_path;
        }

        $relationships = [];
        foreach ( $rels->Relationship as $rel ) {
            $id = isset( $rel['Id'] ) ? (string) $rel['Id'] : '';
            $target = isset( $rel['Target'] ) ? (string) $rel['Target'] : '';
            if ( $id && $target ) {
                $relationships[ $id ] = $target;
            }
        }

        foreach ( $workbook->sheets->sheet as $sheet ) {
            $r_id = isset( $sheet['r:id'] ) ? (string) $sheet['r:id'] : '';
            if ( $r_id && isset( $relationships[ $r_id ] ) ) {
                $target = $relationships[ $r_id ];
                if ( strpos( $target, '/' ) === 0 ) {
                    $target = ltrim( $target, '/' );
                }

                if ( strpos( $target, 'xl/' ) === 0 ) {
                    return $target;
                }

                return 'xl/' . $target;
            }

            break;
        }

        return $sheet_path;
    }

    /**
     * Converte riferimento colonna (es. A1) in indice numerico
     *
     * @param string $reference
     * @return int|null
     */
    private function column_reference_to_index( $reference ) {
        if ( ! preg_match( '/^([A-Z]+)[0-9]+$/i', $reference, $matches ) ) {
            return null;
        }

        $letters = strtoupper( $matches[1] );
        $length = strlen( $letters );
        $index = 0;
        for ( $i = 0; $i < $length; $i++ ) {
            $index = $index * 26 + ( ord( $letters[ $i ] ) - 64 );
        }

        return $index - 1;
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
            'nome'       => 'nome',
            'cognome'    => 'cognome',
            'comune'     => 'comune',
            'provincia'  => 'provincia',
            'email'      => 'email',
            'telefono'   => 'telefono',
            'privacy'    => 'privacy',
            'partecipa'  => 'partecipa',
            'pasti'      => 'mangia',
            'mangia'     => 'mangia',
            'pernotta'   => 'dorme',
            'dorme'      => 'dorme',
            'created_at' => 'created_at',
            'data'       => 'created_at',
            'ip'         => 'ip',
            'user_agent' => 'user_agent',
        ];

        if ( isset( $map[ $header ] ) ) {
            return $map[ $header ];
        }

        return '';
    }
}