<?php
/**
 * Gestione note del plugin
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Notes_Manager {

    const TEXT_DOMAIN = 'pc-volontari-abruzzo';

    /**
     * Aggiunge una nuova nota
     *
     * @param string $title Titolo della nota
     * @param string $content Contenuto della nota
     * @return int|false ID della nota inserita o false in caso di errore
     */
    public static function add_note( $title, $content ) {
        global $wpdb;
        
        $table = self::get_table_name();
        
        $result = $wpdb->insert(
            $table,
            [
                'title' => $title,
                'content' => $content,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' )
            ],
            [ '%s', '%s', '%s', '%s' ]
        );
        
        return $result ? (int) $wpdb->insert_id : false;
    }

    /**
     * Aggiorna una nota esistente
     *
     * @param int $note_id ID della nota
     * @param string $title Nuovo titolo
     * @param string $content Nuovo contenuto
     * @return bool True se aggiornata con successo
     */
    public static function update_note( $note_id, $title, $content ) {
        global $wpdb;
        
        $table = self::get_table_name();
        $note_id = absint( $note_id );
        
        if ( ! $note_id ) {
            return false;
        }
        
        $result = $wpdb->update(
            $table,
            [
                'title' => $title,
                'content' => $content,
                'updated_at' => current_time( 'mysql' )
            ],
            [ 'id' => $note_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );
        
        return $result !== false;
    }

    /**
     * Elimina una nota
     *
     * @param int $note_id ID della nota
     * @return bool True se eliminata con successo
     */
    public static function delete_note( $note_id ) {
        global $wpdb;
        
        $table = self::get_table_name();
        $note_id = absint( $note_id );
        
        if ( ! $note_id ) {
            return false;
        }
        
        $result = $wpdb->delete(
            $table,
            [ 'id' => $note_id ],
            [ '%d' ]
        );
        
        return $result !== false;
    }

    /**
     * Recupera tutte le note
     *
     * @return array Array di oggetti note
     */
    public static function get_all_notes() {
        global $wpdb;
        
        $table = self::get_table_name();
        
        return $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY created_at DESC"
        );
    }

    /**
     * Recupera una nota per ID
     *
     * @param int $note_id ID della nota
     * @return object|null Oggetto nota o null
     */
    public static function get_note_by_id( $note_id ) {
        global $wpdb;
        
        $table = self::get_table_name();
        $note_id = absint( $note_id );
        
        if ( ! $note_id ) {
            return null;
        }
        
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $note_id )
        );
    }

    /**
     * Recupera il nome della tabella delle note
     *
     * @return string Nome completo della tabella
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'pcv_notes';
    }

    /**
     * Crea la tabella delle note se non esiste
     *
     * @return void
     */
    public static function create_table() {
        global $wpdb;
        
        $table = self::get_table_name();
        $charset = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE `{$table}` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_created (created_at),
            KEY idx_updated (updated_at)
        ) {$charset};";
        
        // Carica dbDelta se non disponibile
        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        
        dbDelta( $sql );
    }

    /**
     * Elimina la tabella delle note
     *
     * @return void
     */
    public static function drop_table() {
        global $wpdb;
        $table = self::get_table_name();
        $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
    }
}
