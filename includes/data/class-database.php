<?php
/**
 * Gestione schema database
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Database {

    const TABLE = 'pcv_volontari';

    /**
     * Crea o aggiorna lo schema del database
     *
     * @return void
     */
    public static function create_or_upgrade_schema() {
        global $wpdb;
        
        // Verifica che $wpdb sia disponibile
        if ( ! isset( $wpdb ) ) {
            throw new Exception( 'Database WordPress ($wpdb) non disponibile' );
        }
        
        $table = self::get_table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = self::get_schema_sql( $table, $charset );

        // Carica dbDelta se non disponibile
        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        
        dbDelta( $sql );
    }

    /**
     * Verifica se lo schema necessita di aggiornamento
     *
     * @return void
     */
    public static function maybe_upgrade_schema() {
        global $wpdb;

        $table = self::get_table_name();
        $table_like = $wpdb->esc_like( $table );
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_like ) );

        if ( $exists !== $table ) {
            self::create_or_upgrade_schema();
            return;
        }

        $needs_upgrade = false;
        foreach ( [ 'dorme', 'mangia', 'categoria', 'note', 'chiamato' ] as $column ) {
            $column_exists = $wpdb->get_var(
                $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $column )
            );

            if ( ! $column_exists ) {
                $needs_upgrade = true;
                break;
            }
        }

        if ( $needs_upgrade ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset = $wpdb->get_charset_collate();
            dbDelta( self::get_schema_sql( $table, $charset ) );
        }
    }

    /**
     * Ritorna il nome completo della tabella
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    /**
     * Genera l'SQL per la creazione dello schema
     *
     * @param string $table Nome tabella
     * @param string $charset Charset
     * @return string
     */
    private static function get_schema_sql( $table, $charset ) {
        return "CREATE TABLE `{$table}` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            nome VARCHAR(100) NOT NULL,
            cognome VARCHAR(100) NOT NULL,
            comune VARCHAR(150) NOT NULL,
            provincia VARCHAR(10) NOT NULL,
            email VARCHAR(190) NOT NULL,
            telefono VARCHAR(50) NOT NULL,
            categoria VARCHAR(150) NOT NULL DEFAULT '',
            privacy TINYINT(1) NOT NULL DEFAULT 0,
            partecipa TINYINT(1) NOT NULL DEFAULT 0,
            dorme TINYINT(1) NOT NULL DEFAULT 0,
            mangia TINYINT(1) NOT NULL DEFAULT 0,
            chiamato TINYINT(1) NOT NULL DEFAULT 0,
            note TEXT NULL,
            ip VARCHAR(45) NULL,
            user_agent TEXT NULL,
            PRIMARY KEY (id),
            KEY idx_cognome (cognome),
            KEY idx_nome (nome),
            KEY idx_comune (comune),
            KEY idx_provincia (provincia),
            KEY idx_created (created_at)
        ) {$charset};";
    }

    /**
     * Elimina la tabella dal database
     *
     * @return void
     */
    public static function drop_table() {
        global $wpdb;
        $table = self::get_table_name();
        // $table è già sicuro perché viene da get_table_name() che usa $wpdb->prefix
        $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
    }
}