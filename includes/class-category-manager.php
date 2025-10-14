<?php
/**
 * Gestione categorie volontari
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Category_Manager {

    const OPTION_KEY = 'pcv_categories';
    const TEXT_DOMAIN = 'pc-volontari-abruzzo';

    /**
     * Ottiene tutte le categorie
     *
     * @return array
     */
    public static function get_categories() {
        $categories = get_option( self::OPTION_KEY, [] );
        
        if ( ! is_array( $categories ) || empty( $categories ) ) {
            return self::get_default_categories();
        }
        
        return $categories;
    }

    /**
     * Ottiene categorie predefinite
     *
     * @return array
     */
    public static function get_default_categories() {
        return [
            'Volontari',
            'Staff',
            'Organizzatori',
            'Protezione Civile',
        ];
    }

    /**
     * Salva le categorie
     *
     * @param array $categories
     * @return bool
     */
    public static function save_categories( $categories ) {
        if ( ! is_array( $categories ) ) {
            return false;
        }

        // Rimuovi duplicati e valori vuoti
        $categories = array_filter( $categories, function( $cat ) {
            return is_string( $cat ) && trim( $cat ) !== '';
        });
        
        $categories = array_unique( array_map( 'trim', $categories ) );
        $categories = array_values( $categories ); // Re-index

        return update_option( self::OPTION_KEY, $categories );
    }

    /**
     * Aggiunge una categoria
     *
     * @param string $category
     * @return bool
     */
    public static function add_category( $category ) {
        $category = trim( sanitize_text_field( $category ) );
        
        if ( empty( $category ) ) {
            return false;
        }

        $categories = self::get_categories();
        
        if ( in_array( $category, $categories, true ) ) {
            return false; // Già esistente
        }

        $categories[] = $category;
        return self::save_categories( $categories );
    }

    /**
     * Elimina una categoria
     *
     * @param string $category
     * @return bool
     */
    public static function delete_category( $category ) {
        $categories = self::get_categories();
        $key = array_search( $category, $categories, true );
        
        if ( $key === false ) {
            return false;
        }

        unset( $categories[ $key ] );
        return self::save_categories( $categories );
    }

    /**
     * Rinomina una categoria (aggiorna anche i volontari)
     *
     * @param string $old_name
     * @param string $new_name
     * @return bool
     */
    public static function rename_category( $old_name, $new_name ) {
        $old_name = trim( sanitize_text_field( $old_name ) );
        $new_name = trim( sanitize_text_field( $new_name ) );

        if ( empty( $old_name ) || empty( $new_name ) ) {
            return false;
        }

        $categories = self::get_categories();
        $key = array_search( $old_name, $categories, true );
        
        if ( $key === false ) {
            return false;
        }

        // Aggiorna nei volontari
        global $wpdb;
        $table = PCV_Database::get_table_name();
        
        $wpdb->update(
            $table,
            [ 'categoria' => $new_name ],
            [ 'categoria' => $old_name ],
            [ '%s' ],
            [ '%s' ]
        );

        // Aggiorna nell'elenco categorie
        $categories[ $key ] = $new_name;
        return self::save_categories( $categories );
    }

    /**
     * Conta volontari per categoria
     *
     * @return array
     */
    public static function count_volunteers_by_category() {
        global $wpdb;
        $table = PCV_Database::get_table_name();

        $results = $wpdb->get_results(
            "SELECT categoria, COUNT(*) as count 
            FROM {$table} 
            WHERE categoria != '' 
            GROUP BY categoria 
            ORDER BY count DESC",
            ARRAY_A
        );

        $counts = [];
        foreach ( $results as $row ) {
            $counts[ $row['categoria'] ] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Ottiene tutte le categorie usate (anche quelle non in lista)
     *
     * @return array
     */
    public static function get_used_categories() {
        global $wpdb;
        $table = PCV_Database::get_table_name();

        $results = $wpdb->get_col(
            "SELECT DISTINCT categoria 
            FROM {$table} 
            WHERE categoria != '' 
            ORDER BY categoria ASC"
        );

        return array_filter( $results );
    }

    /**
     * Ottiene categorie per select (predefinite + usate)
     *
     * @return array
     */
    public static function get_categories_for_select() {
        $defined = self::get_categories();
        $used = self::get_used_categories();
        
        // Unisci e rimuovi duplicati
        $all = array_unique( array_merge( $defined, $used ) );
        sort( $all, SORT_NATURAL | SORT_FLAG_CASE );
        
        return $all;
    }
}

