<?php
/**
 * Pagina importazione volontari
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Import_Page {

    const TEXT_DOMAIN = 'pc-volontari-abruzzo';
    const MENU_SLUG = 'pcv-volontari';

    private $importer;
    private $sanitizer;

    public function __construct( $importer, $sanitizer ) {
        $this->importer = $importer;
        $this->sanitizer = $sanitizer;
    }

    public function render() {
        if ( ! PCV_Role_Manager::can_import_volunteers() ) {
            return;
        }

        $messages = [];
        $stage = 'upload';
        $mapping_args = [];

        if ( isset( $_POST['pcv_import_submit'] ) || isset( $_POST['pcv_import_confirm'] ) ) {
            $result = $this->handle_submission();
            if ( isset( $result['messages'] ) && is_array( $result['messages'] ) ) {
                $messages = array_merge( $messages, $result['messages'] );
            }
            if ( isset( $result['stage'] ) && $result['stage'] === 'map' ) {
                $stage = 'map';
                $mapping_args = $result;
            }
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Importa volontari', self::TEXT_DOMAIN ) . '</h1>';

        foreach ( $messages as $message ) {
            $type = isset( $message['type'] ) && $message['type'] === 'error' ? 'error' : 'updated';
            $text = isset( $message['text'] ) ? $message['text'] : '';
            if ( $text === '' ) {
                continue;
            }
            echo '<div class="notice ' . esc_attr( $type ) . '"><p>' . wp_kses_post( $text ) . '</p></div>';
        }

        if ( $stage === 'map' && ! empty( $mapping_args ) ) {
            $this->render_mapping_form( $mapping_args );
        } else {
            echo '<p>' . esc_html__( 'Carica un file CSV o Excel (.xlsx) con i dati dei volontari da importare.', self::TEXT_DOMAIN ) . '</p>';
            echo '<p>' . esc_html__( 'Assicurati che la prima riga contenga le intestazioni (Nome, Cognome, Comune, Provincia, Email, Telefono). I campi Privacy, Partecipa, Pernotta e Pasti sono opzionali.', self::TEXT_DOMAIN ) . '</p>';

            echo '<form method="post" enctype="multipart/form-data">';
            wp_nonce_field( 'pcv_import_nonce' );
            echo '<input type="hidden" name="pcv_import_stage" value="upload">';
            echo '<table class="form-table">';
            echo '<tr>';
            echo '<th scope="row"><label for="pcv_import_file">' . esc_html__( 'File da importare', self::TEXT_DOMAIN ) . '</label></th>';
            echo '<td><input type="file" id="pcv_import_file" name="pcv_import_file" accept=".csv, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></td>';
            echo '</tr>';
            echo '</table>';
            submit_button( __( 'Carica e scegli colonne', self::TEXT_DOMAIN ), 'primary', 'pcv_import_submit' );
            echo '</form>';
        }

        echo '</div>';
    }

    private function handle_submission() {
        $messages = [];

        if ( ! check_admin_referer( 'pcv_import_nonce' ) ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html__( 'Nonce non valido. Riprova.', self::TEXT_DOMAIN ),
            ];
            return [ 'messages' => $messages ];
        }

        $stage = isset( $_POST['pcv_import_stage'] ) ? sanitize_text_field( wp_unslash( $_POST['pcv_import_stage'] ) ) : 'upload';

        if ( $stage === 'map' ) {
            return $this->process_mapping_stage( $messages );
        }

        return $this->process_upload_stage( $messages );
    }

    private function process_upload_stage( $messages ) {
        if ( empty( $_FILES['pcv_import_file'] ) || ! is_array( $_FILES['pcv_import_file'] ) ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html__( 'Nessun file selezionato.', self::TEXT_DOMAIN ),
            ];
            return [ 'messages' => $messages ];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $file = $_FILES['pcv_import_file'];
        $overrides = [
            'test_form' => false,
            'mimes'     => [
                'csv'  => 'text/csv',
                'txt'  => 'text/plain',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'xls'  => 'application/vnd.ms-excel',
            ],
        ];

        $uploaded = wp_handle_upload( $file, $overrides );

        if ( isset( $uploaded['error'] ) ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html( $uploaded['error'] ),
            ];
            return [ 'messages' => $messages ];
        }

        $path = $uploaded['file'];
        $dataset = $this->importer->parse_file( $path );

        if ( file_exists( $path ) ) {
            unlink( $path );
        }

        if ( is_wp_error( $dataset ) ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html( $dataset->get_error_message() ),
            ];
            return [ 'messages' => $messages ];
        }

        if ( empty( $dataset ) || empty( $dataset['rows'] ) ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html__( 'Il file non contiene dati da importare.', self::TEXT_DOMAIN ),
            ];
            return [ 'messages' => $messages ];
        }

        $token = $this->importer->store_dataset( $dataset );
        if ( is_wp_error( $token ) ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html( $token->get_error_message() ),
            ];
            return [ 'messages' => $messages ];
        }

        $preview_rows = array_slice( $dataset['rows'], 0, 5 );
        $default_map = $this->importer->build_default_map( $dataset );

        $messages[] = [
            'type' => 'updated',
            'text' => esc_html__( 'File caricato correttamente. Associa le colonne e conferma per avviare l\'importazione.', self::TEXT_DOMAIN ),
        ];

        $category_option = get_option( 'pcv_default_category', 'Volontari' );
        if ( ! is_string( $category_option ) || $category_option === '' ) {
            $category_option = 'Volontari';
        }

        return [
            'messages'           => $messages,
            'stage'              => 'map',
            'headers'            => $dataset['headers'],
            'preview_rows'       => $preview_rows,
            'selected_map'       => $default_map,
            'selected_category'  => sanitize_text_field( $category_option ),
            'token'              => $token,
        ];
    }

    private function process_mapping_stage( $messages ) {
        $token = isset( $_POST['pcv_import_token'] ) ? sanitize_text_field( wp_unslash( $_POST['pcv_import_token'] ) ) : '';

        if ( $token === '' ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html__( 'Sessione di importazione non valida o scaduta. Carica nuovamente il file.', self::TEXT_DOMAIN ),
            ];
            return [ 'messages' => $messages ];
        }

        $dataset = $this->importer->get_dataset( $token );

        if ( empty( $dataset ) || ! is_array( $dataset ) ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html__( 'Sessione di importazione non trovata. Carica nuovamente il file.', self::TEXT_DOMAIN ),
            ];
            return [ 'messages' => $messages ];
        }

        $headers = isset( $dataset['headers'] ) && is_array( $dataset['headers'] ) ? $dataset['headers'] : [];
        $preview_rows = array_slice( isset( $dataset['rows'] ) && is_array( $dataset['rows'] ) ? $dataset['rows'] : [], 0, 5 );

        $raw_map = isset( $_POST['pcv_import_map'] ) && is_array( $_POST['pcv_import_map'] ) ? wp_unslash( $_POST['pcv_import_map'] ) : [];
        $sanitized_map = $this->importer->sanitize_map( $raw_map, count( $headers ) );
        $raw_category = isset( $_POST['pcv_import_category'] ) ? wp_unslash( $_POST['pcv_import_category'] ) : '';
        $category = $this->sanitizer->sanitize_text( $raw_category );

        $missing_required = [];
        foreach ( PCV_Importer::IMPORT_EXPECTED_COLUMNS as $required_field ) {
            if ( ! isset( $sanitized_map[ $required_field ] ) || $sanitized_map[ $required_field ] === null ) {
                $missing_required[] = $required_field;
            }
        }

        if ( ! empty( $missing_required ) ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html__( 'Completa la mappatura di tutti i campi obbligatori prima di procedere.', self::TEXT_DOMAIN ),
            ];

            return [
                'messages'           => $messages,
                'stage'              => 'map',
                'headers'            => $headers,
                'preview_rows'       => $preview_rows,
                'selected_map'       => $sanitized_map,
                'selected_category'  => $category,
                'token'              => $token,
            ];
        }

        if ( $category === '' ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html__( 'Indica una categoria per l\'elenco che stai importando.', self::TEXT_DOMAIN ),
            ];

            return [
                'messages'           => $messages,
                'stage'              => 'map',
                'headers'            => $headers,
                'preview_rows'       => $preview_rows,
                'selected_map'       => $sanitized_map,
                'selected_category'  => $category,
                'token'              => $token,
            ];
        }

        $rows = $this->importer->apply_mapping( $dataset, $sanitized_map );
        $result = $this->importer->import_rows( $rows, $category );

        $this->importer->delete_dataset( $token );

        if ( $result['imported'] > 0 ) {
            $messages[] = [
                'type' => 'success',
                'text' => esc_html( sprintf( __( 'Importazione completata: %1$d righe inserite, %2$d righe saltate.', self::TEXT_DOMAIN ), $result['imported'], $result['skipped'] ) ),
            ];
        }

        if ( ! empty( $result['errors'] ) ) {
            $error_list = '<ul>';
            foreach ( $result['errors'] as $error ) {
                $error_list .= '<li>' . esc_html( $error ) . '</li>';
            }
            $error_list .= '</ul>';
            $messages[] = [
                'type' => 'error',
                'text' => __( 'Alcune righe non sono state importate:', self::TEXT_DOMAIN ) . $error_list,
            ];
        }

        if ( empty( $messages ) ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html__( 'Si è verificato un errore durante l\'importazione.', self::TEXT_DOMAIN ),
            ];
        }

        return [ 'messages' => $messages ];
    }

    private function render_mapping_form( $args ) {
        $headers = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : [];
        $preview_rows = isset( $args['preview_rows'] ) && is_array( $args['preview_rows'] ) ? $args['preview_rows'] : [];
        $token = isset( $args['token'] ) ? sanitize_text_field( $args['token'] ) : '';
        $selected_map = isset( $args['selected_map'] ) && is_array( $args['selected_map'] ) ? $args['selected_map'] : [];
        $selected_category = isset( $args['selected_category'] ) ? sanitize_text_field( $args['selected_category'] ) : '';

        $field_definitions = $this->importer->get_field_definitions();

        echo '<p>' . esc_html__( 'Associa le colonne del file ai campi del gestionale. I campi contrassegnati come obbligatori devono essere sempre mappati.', self::TEXT_DOMAIN ) . '</p>';

        echo '<form method="post">';
        wp_nonce_field( 'pcv_import_nonce' );
        echo '<input type="hidden" name="pcv_import_stage" value="map">';
        echo '<input type="hidden" name="pcv_import_token" value="' . esc_attr( $token ) . '">';
        echo '<table class="form-table">';

        echo '<tr>';
        echo '<th scope="row"><label for="pcv_import_category">' . esc_html__( 'Categoria elenco', self::TEXT_DOMAIN ) . ' <span class="description" style="font-weight: normal;">' . esc_html__( '(obbligatorio)', self::TEXT_DOMAIN ) . '</span></label></th>';
        echo '<td>';
        echo '<input type="text" id="pcv_import_category" name="pcv_import_category" class="regular-text" value="' . esc_attr( $selected_category ) . '" required>';
        echo '<p class="description">' . esc_html__( 'Specifica a quale gruppo o contesto appartiene l\'elenco importato (es. Sindaci, Volontari 2024).', self::TEXT_DOMAIN ) . '</p>';
        echo '</td>';
        echo '</tr>';

        foreach ( $field_definitions as $field_key => $definition ) {
            $label = isset( $definition['label'] ) ? $definition['label'] : $field_key;
            $required = ! empty( $definition['required'] );
            $description = isset( $definition['description'] ) ? $definition['description'] : '';
            $select_id = 'pcv_import_map_' . $field_key;
            $current_value = '';
            if ( isset( $selected_map[ $field_key ] ) && $selected_map[ $field_key ] !== null && $selected_map[ $field_key ] !== '' ) {
                $current_value = (string) $selected_map[ $field_key ];
            }

            echo '<tr>';
            echo '<th scope="row"><label for="' . esc_attr( $select_id ) . '">' . esc_html( $label );
            if ( $required ) {
                echo ' <span class="description" style="font-weight: normal;">' . esc_html__( '(obbligatorio)', self::TEXT_DOMAIN ) . '</span>';
            }
            echo '</label></th>';
            echo '<td>';
            echo '<select id="' . esc_attr( $select_id ) . '" name="pcv_import_map[' . esc_attr( $field_key ) . ']">';
            echo '<option value="">' . esc_html__( 'Non importare', self::TEXT_DOMAIN ) . '</option>';
            foreach ( $headers as $index => $header_label ) {
                $option_value = (string) $index;
                $selected_attr = selected( $current_value, $option_value, false );
                echo '<option value="' . esc_attr( $option_value ) . '" ' . $selected_attr . '>' . esc_html( $header_label ) . '</option>';
            }
            echo '</select>';
            if ( $description !== '' ) {
                echo '<p class="description">' . esc_html( $description ) . '</p>';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</table>';
        submit_button( __( 'Avvia importazione', self::TEXT_DOMAIN ), 'primary', 'pcv_import_confirm' );

        $cancel_url = menu_page_url( self::MENU_SLUG . '-import', false );
        if ( $cancel_url ) {
            echo '<a href="' . esc_url( $cancel_url ) . '" class="button button-secondary" style="margin-left:10px;">' . esc_html__( 'Annulla', self::TEXT_DOMAIN ) . '</a>';
        }

        echo '</form>';

        if ( ! empty( $headers ) && ! empty( $preview_rows ) ) {
            echo '<h2>' . esc_html__( 'Anteprima dati', self::TEXT_DOMAIN ) . '</h2>';
            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            foreach ( $headers as $header_label ) {
                echo '<th>' . esc_html( $header_label ) . '</th>';
            }
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ( $preview_rows as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }
                echo '<tr>';
                foreach ( array_keys( $headers ) as $index ) {
                    $value = isset( $row[ $index ] ) ? $row[ $index ] : '';
                    echo '<td>' . esc_html( $value ) . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        }
    }
}