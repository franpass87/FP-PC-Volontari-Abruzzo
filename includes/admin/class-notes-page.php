<?php
/**
 * Pagina gestione note per singoli contatti
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Notes_Page {

    const TEXT_DOMAIN = 'pc-volontari-abruzzo';

    private $repository;

    public function __construct( $repository ) {
        $this->repository = $repository;
    }

    /**
     * Renderizza la pagina note
     *
     * @return void
     */
    public function render() {
        if ( ! PCV_Role_Manager::can_manage_settings() ) {
            return;
        }

        // Gestione azioni
        $message = '';
        $error = '';

        if ( isset( $_POST['pcv_update_note'] ) && check_admin_referer( 'pcv_update_note_nonce' ) ) {
            $volunteer_id = isset( $_POST['volunteer_id'] ) ? absint( $_POST['volunteer_id'] ) : 0;
            $note = isset( $_POST['note'] ) ? wp_kses_post( wp_unslash( $_POST['note'] ) ) : '';
            
            if ( $volunteer_id > 0 ) {
                $result = $this->repository->update( $volunteer_id, [ 'note' => $note ] );
                if ( $result !== false ) {
                    $message = __( 'Nota aggiornata con successo.', self::TEXT_DOMAIN );
                } else {
                    $error = __( 'Errore durante l\'aggiornamento della nota.', self::TEXT_DOMAIN );
                }
            } else {
                $error = __( 'ID volontario non valido.', self::TEXT_DOMAIN );
            }
        }

        // Recupera ID volontario se specificato
        $volunteer_id = isset( $_GET['volunteer_id'] ) ? absint( $_GET['volunteer_id'] ) : 0;
        $volunteer = null;
        
        if ( $volunteer_id > 0 ) {
            $volunteer = $this->repository->get_by_id( $volunteer_id );
        }

        echo '<div class="wrap">';
        
        if ( $volunteer ) {
            printf( 
                '<h1 class="wp-heading-inline">%s - %s %s</h1>', 
                esc_html__( 'Note per', self::TEXT_DOMAIN ),
                esc_html( $volunteer->nome ),
                esc_html( $volunteer->cognome )
            );
        } else {
            printf( '<h1 class="wp-heading-inline">%s</h1>', esc_html__( 'Gestione Note', self::TEXT_DOMAIN ) );
        }
        
        // Mostra messaggi
        if ( ! empty( $message ) ) {
            echo '<div class="updated notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
        }
        
        if ( ! empty( $error ) ) {
            echo '<div class="error notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
        }

        if ( $volunteer ) {
            // Form per modificare la nota del volontario specifico
            echo '<div class="pcv-note-form-container">';
            echo '<form method="post" class="pcv-note-form">';
            wp_nonce_field( 'pcv_update_note_nonce' );
            echo '<input type="hidden" name="volunteer_id" value="' . esc_attr( $volunteer_id ) . '" />';
            
            echo '<table class="form-table">';
            echo '<tr>';
            echo '<th scope="row"><label for="note">' . esc_html__( 'Note per', self::TEXT_DOMAIN ) . ' ' . esc_html( $volunteer->nome ) . ' ' . esc_html( $volunteer->cognome ) . '</label></th>';
            echo '<td>';
            $current_note = isset( $volunteer->note ) ? $volunteer->note : '';
            echo '<textarea id="note" name="note" rows="8" class="large-text" placeholder="' . esc_attr__( 'Inserisci le note per questo contatto...', self::TEXT_DOMAIN ) . '">' . esc_textarea( $current_note ) . '</textarea>';
            echo '<p class="description">' . esc_html__( 'Queste note sono visibili solo agli amministratori e possono contenere informazioni aggiuntive sul contatto.', self::TEXT_DOMAIN ) . '</p>';
            echo '</td>';
            echo '</tr>';
            echo '</table>';
            
            submit_button( __( 'Salva Note', self::TEXT_DOMAIN ), 'primary', 'pcv_update_note' );
            echo '</form>';
            echo '</div>';

            // Informazioni aggiuntive sul contatto
            echo '<div class="pcv-volunteer-info">';
            echo '<h3>' . esc_html__( 'Informazioni Contatto', self::TEXT_DOMAIN ) . '</h3>';
            echo '<table class="widefat fixed striped">';
            echo '<tr><td><strong>' . esc_html__( 'Email:', self::TEXT_DOMAIN ) . '</strong></td><td>' . esc_html( $volunteer->email ) . '</td></tr>';
            echo '<tr><td><strong>' . esc_html__( 'Telefono:', self::TEXT_DOMAIN ) . '</strong></td><td>' . esc_html( $volunteer->telefono ) . '</td></tr>';
            echo '<tr><td><strong>' . esc_html__( 'Comune:', self::TEXT_DOMAIN ) . '</strong></td><td>' . esc_html( $volunteer->comune ) . '</td></tr>';
            echo '<tr><td><strong>' . esc_html__( 'Provincia:', self::TEXT_DOMAIN ) . '</strong></td><td>' . esc_html( $volunteer->provincia ) . '</td></tr>';
            echo '<tr><td><strong>' . esc_html__( 'Categoria:', self::TEXT_DOMAIN ) . '</strong></td><td>' . esc_html( $volunteer->categoria ) . '</td></tr>';
            echo '<tr><td><strong>' . esc_html__( 'Data registrazione:', self::TEXT_DOMAIN ) . '</strong></td><td>' . esc_html( mysql2date( 'd/m/Y H:i', $volunteer->created_at ) ) . '</td></tr>';
            echo '</table>';
            echo '</div>';

        } else {
            // Lista di tutti i volontari con note
            echo '<div class="pcv-notes-overview">';
            echo '<p>' . esc_html__( 'Seleziona un contatto dalla lista principale per gestire le sue note, oppure utilizza i link "Note" nella colonna Nome.', self::TEXT_DOMAIN ) . '</p>';
            echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=pcv-volontari' ) ) . '" class="button button-primary">' . esc_html__( 'Torna alla Lista Volontari', self::TEXT_DOMAIN ) . '</a></p>';
            echo '</div>';
        }

        echo '</div>'; // .wrap

        // CSS per la pagina
        echo '<style>
        .pcv-note-form-container { 
            background: #fff; 
            padding: 20px; 
            border: 1px solid #ccd0d4; 
            margin: 20px 0; 
            border-radius: 4px;
        }
        .pcv-volunteer-info { 
            background: #fff; 
            padding: 20px; 
            border: 1px solid #ccd0d4; 
            margin: 20px 0; 
            border-radius: 4px;
        }
        .pcv-volunteer-info h3 { 
            margin-top: 0; 
            color: #23282d; 
        }
        .pcv-notes-overview { 
            background: #fff; 
            padding: 20px; 
            border: 1px solid #ccd0d4; 
            margin: 20px 0; 
            border-radius: 4px;
            text-align: center;
        }
        .pcv-note-preview { 
            cursor: help; 
            border-bottom: 1px dotted #666; 
        }
        .pcv-no-note { 
            color: #999; 
            font-style: italic; 
        }
        </style>';
    }
}
