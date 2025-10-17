<?php
/**
 * Pagina gestione note
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Notes_Page {

    const TEXT_DOMAIN = 'pc-volontari-abruzzo';

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

        if ( isset( $_POST['pcv_add_note'] ) && check_admin_referer( 'pcv_add_note_nonce' ) ) {
            $title = isset( $_POST['note_title'] ) ? sanitize_text_field( wp_unslash( $_POST['note_title'] ) ) : '';
            $content = isset( $_POST['note_content'] ) ? wp_kses_post( wp_unslash( $_POST['note_content'] ) ) : '';
            
            if ( ! empty( $title ) && ! empty( $content ) ) {
                if ( PCV_Notes_Manager::add_note( $title, $content ) ) {
                    $message = __( 'Nota aggiunta con successo.', self::TEXT_DOMAIN );
                } else {
                    $error = __( 'Errore durante il salvataggio della nota.', self::TEXT_DOMAIN );
                }
            } else {
                $error = __( 'Titolo e contenuto sono obbligatori.', self::TEXT_DOMAIN );
            }
        }

        if ( isset( $_POST['pcv_edit_note'] ) && check_admin_referer( 'pcv_edit_note_nonce' ) ) {
            $note_id = isset( $_POST['note_id'] ) ? absint( $_POST['note_id'] ) : 0;
            $title = isset( $_POST['note_title'] ) ? sanitize_text_field( wp_unslash( $_POST['note_title'] ) ) : '';
            $content = isset( $_POST['note_content'] ) ? wp_kses_post( wp_unslash( $_POST['note_content'] ) ) : '';
            
            if ( $note_id > 0 && ! empty( $title ) && ! empty( $content ) ) {
                if ( PCV_Notes_Manager::update_note( $note_id, $title, $content ) ) {
                    $message = __( 'Nota aggiornata con successo.', self::TEXT_DOMAIN );
                } else {
                    $error = __( 'Errore durante l\'aggiornamento della nota.', self::TEXT_DOMAIN );
                }
            } else {
                $error = __( 'Dati non validi per l\'aggiornamento.', self::TEXT_DOMAIN );
            }
        }

        if ( isset( $_POST['pcv_delete_note'] ) && check_admin_referer( 'pcv_delete_note_nonce' ) ) {
            $note_id = isset( $_POST['note_id'] ) ? absint( $_POST['note_id'] ) : 0;
            
            if ( $note_id > 0 ) {
                if ( PCV_Notes_Manager::delete_note( $note_id ) ) {
                    $message = __( 'Nota eliminata con successo.', self::TEXT_DOMAIN );
                } else {
                    $error = __( 'Errore durante l\'eliminazione della nota.', self::TEXT_DOMAIN );
                }
            }
        }

        // Recupera tutte le note
        $notes = PCV_Notes_Manager::get_all_notes();

        echo '<div class="wrap">';
        printf( '<h1 class="wp-heading-inline">%s</h1>', esc_html__( 'Gestione Note', self::TEXT_DOMAIN ) );
        
        // Mostra messaggi
        if ( ! empty( $message ) ) {
            echo '<div class="updated notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
        }
        
        if ( ! empty( $error ) ) {
            echo '<div class="error notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
        }

        echo '<div class="pcv-notes-container">';
        
        // Form per aggiungere nuova nota
        echo '<div class="pcv-add-note-section">';
        echo '<h2>' . esc_html__( 'Aggiungi Nuova Nota', self::TEXT_DOMAIN ) . '</h2>';
        echo '<form method="post" class="pcv-note-form">';
        wp_nonce_field( 'pcv_add_note_nonce' );
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row"><label for="note_title">' . esc_html__( 'Titolo', self::TEXT_DOMAIN ) . '</label></th>';
        echo '<td><input type="text" id="note_title" name="note_title" class="regular-text" required /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="note_content">' . esc_html__( 'Contenuto', self::TEXT_DOMAIN ) . '</label></th>';
        echo '<td><textarea id="note_content" name="note_content" rows="6" class="large-text" required></textarea></td>';
        echo '</tr>';
        echo '</table>';
        submit_button( __( 'Aggiungi Nota', self::TEXT_DOMAIN ), 'primary', 'pcv_add_note' );
        echo '</form>';
        echo '</div>';

        // Lista delle note esistenti
        if ( ! empty( $notes ) ) {
            echo '<div class="pcv-notes-list">';
            echo '<h2>' . esc_html__( 'Note Esistenti', self::TEXT_DOMAIN ) . '</h2>';
            
            foreach ( $notes as $note ) {
                echo '<div class="pcv-note-item" data-note-id="' . esc_attr( $note->id ) . '">';
                echo '<div class="pcv-note-header">';
                echo '<h3 class="pcv-note-title">' . esc_html( $note->title ) . '</h3>';
                echo '<div class="pcv-note-meta">';
                printf( 
                    '<span class="pcv-note-date">%s</span>',
                    esc_html( mysql2date( 'd/m/Y H:i', $note->created_at ) )
                );
                echo '</div>';
                echo '</div>';
                
                echo '<div class="pcv-note-content">';
                echo wp_kses_post( $note->content );
                echo '</div>';
                
                echo '<div class="pcv-note-actions">';
                echo '<button type="button" class="button button-secondary pcv-edit-note" data-note-id="' . esc_attr( $note->id ) . '">' . esc_html__( 'Modifica', self::TEXT_DOMAIN ) . '</button>';
                echo '<form method="post" style="display: inline-block; margin-left: 5px;">';
                wp_nonce_field( 'pcv_delete_note_nonce' );
                echo '<input type="hidden" name="note_id" value="' . esc_attr( $note->id ) . '" />';
                echo '<input type="submit" name="pcv_delete_note" class="button button-link-delete" value="' . esc_attr__( 'Elimina', self::TEXT_DOMAIN ) . '" onclick="return confirm(\'' . esc_js( __( 'Sei sicuro di voler eliminare questa nota?', self::TEXT_DOMAIN ) ) . '\');" />';
                echo '</form>';
                echo '</div>';
                echo '</div>';
            }
            
            echo '</div>';
        } else {
            echo '<div class="pcv-no-notes">';
            echo '<p>' . esc_html__( 'Nessuna nota presente.', self::TEXT_DOMAIN ) . '</p>';
            echo '</div>';
        }

        echo '</div>'; // .pcv-notes-container
        echo '</div>'; // .wrap

        // Form nascosto per modifica
        echo '<div id="pcv-edit-note-modal" style="display: none;">';
        echo '<form method="post" class="pcv-note-form">';
        wp_nonce_field( 'pcv_edit_note_nonce' );
        echo '<input type="hidden" name="note_id" id="edit_note_id" />';
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row"><label for="edit_note_title">' . esc_html__( 'Titolo', self::TEXT_DOMAIN ) . '</label></th>';
        echo '<td><input type="text" id="edit_note_title" name="note_title" class="regular-text" required /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="edit_note_content">' . esc_html__( 'Contenuto', self::TEXT_DOMAIN ) . '</label></th>';
        echo '<td><textarea id="edit_note_content" name="note_content" rows="6" class="large-text" required></textarea></td>';
        echo '</tr>';
        echo '</table>';
        submit_button( __( 'Aggiorna Nota', self::TEXT_DOMAIN ), 'primary', 'pcv_edit_note' );
        echo '</form>';
        echo '</div>';

        // CSS e JavaScript inline per la funzionalità
        echo '<style>
        .pcv-notes-container { margin-top: 20px; }
        .pcv-add-note-section { background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-bottom: 20px; }
        .pcv-notes-list { background: #fff; padding: 20px; border: 1px solid #ccd0d4; }
        .pcv-note-item { border: 1px solid #ddd; margin-bottom: 15px; padding: 15px; background: #f9f9f9; }
        .pcv-note-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .pcv-note-title { margin: 0; color: #23282d; }
        .pcv-note-meta { font-size: 12px; color: #666; }
        .pcv-note-content { margin-bottom: 15px; line-height: 1.6; }
        .pcv-note-actions { border-top: 1px solid #ddd; padding-top: 10px; }
        .pcv-no-notes { text-align: center; padding: 40px; color: #666; }
        </style>';

        echo '<script>
        jQuery(document).ready(function($) {
            $(".pcv-edit-note").on("click", function() {
                var noteId = $(this).data("note-id");
                var noteItem = $(".pcv-note-item[data-note-id=\'" + noteId + "\']");
                var title = noteItem.find(".pcv-note-title").text();
                var content = noteItem.find(".pcv-note-content").html();
                
                $("#edit_note_id").val(noteId);
                $("#edit_note_title").val(title);
                $("#edit_note_content").val(content.replace(/<br\s*\/?>/gi, "\n"));
                
                $("#pcv-edit-note-modal").show();
                $("html, body").animate({ scrollTop: 0 }, 500);
            });
        });
        </script>';
    }
}
