<?php
/**
 * Gestione ruoli e capacità del plugin
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Role_Manager {

    /**
     * Nome del ruolo personalizzato
     */
    const ROLE_SLUG = 'pcv_manager';

    /**
     * Capacità del plugin
     */
    const CAP_VIEW_VOLUNTEERS = 'pcv_view_volunteers';
    const CAP_MANAGE_VOLUNTEERS = 'pcv_manage_volunteers';
    const CAP_EXPORT_VOLUNTEERS = 'pcv_export_volunteers';
    const CAP_IMPORT_VOLUNTEERS = 'pcv_import_volunteers';
    const CAP_MANAGE_SETTINGS = 'pcv_manage_settings';
    const CAP_DELETE_VOLUNTEERS = 'pcv_delete_volunteers';

    /**
     * Crea il ruolo personalizzato
     *
     * @return void
     */
    public static function create_role() {
        // Rimuovi il ruolo se esiste già (per aggiornamenti)
        remove_role( self::ROLE_SLUG );

        // Crea il nuovo ruolo con tutte le capacità del plugin
        add_role(
            self::ROLE_SLUG,
            __( 'Gestore Volontari', 'pc-volontari-abruzzo' ),
            [
                'read' => true, // Capacità base di WordPress
                self::CAP_VIEW_VOLUNTEERS => true,
                self::CAP_MANAGE_VOLUNTEERS => true,
                self::CAP_EXPORT_VOLUNTEERS => true,
                self::CAP_IMPORT_VOLUNTEERS => true,
                self::CAP_MANAGE_SETTINGS => true,
                self::CAP_DELETE_VOLUNTEERS => true,
            ]
        );

        // Aggiungi le capacità anche all'amministratore
        self::add_caps_to_admin();
    }

    /**
     * Rimuove il ruolo personalizzato
     *
     * @return void
     */
    public static function remove_role() {
        // Rimuovi capacità dall'amministratore
        self::remove_caps_from_admin();

        // Rimuovi il ruolo
        remove_role( self::ROLE_SLUG );
    }

    /**
     * Aggiunge le capacità del plugin all'amministratore
     *
     * @return void
     */
    private static function add_caps_to_admin() {
        $admin_role = get_role( 'administrator' );
        
        if ( ! $admin_role ) {
            return;
        }

        $admin_role->add_cap( self::CAP_VIEW_VOLUNTEERS );
        $admin_role->add_cap( self::CAP_MANAGE_VOLUNTEERS );
        $admin_role->add_cap( self::CAP_EXPORT_VOLUNTEERS );
        $admin_role->add_cap( self::CAP_IMPORT_VOLUNTEERS );
        $admin_role->add_cap( self::CAP_MANAGE_SETTINGS );
        $admin_role->add_cap( self::CAP_DELETE_VOLUNTEERS );
    }

    /**
     * Rimuove le capacità del plugin dall'amministratore
     *
     * @return void
     */
    private static function remove_caps_from_admin() {
        $admin_role = get_role( 'administrator' );
        
        if ( ! $admin_role ) {
            return;
        }

        $admin_role->remove_cap( self::CAP_VIEW_VOLUNTEERS );
        $admin_role->remove_cap( self::CAP_MANAGE_VOLUNTEERS );
        $admin_role->remove_cap( self::CAP_EXPORT_VOLUNTEERS );
        $admin_role->remove_cap( self::CAP_IMPORT_VOLUNTEERS );
        $admin_role->remove_cap( self::CAP_MANAGE_SETTINGS );
        $admin_role->remove_cap( self::CAP_DELETE_VOLUNTEERS );
    }

    /**
     * Verifica se l'utente corrente può visualizzare i volontari
     *
     * @return bool
     */
    public static function can_view_volunteers() {
        return current_user_can( self::CAP_VIEW_VOLUNTEERS );
    }

    /**
     * Verifica se l'utente corrente può gestire i volontari
     *
     * @return bool
     */
    public static function can_manage_volunteers() {
        return current_user_can( self::CAP_MANAGE_VOLUNTEERS );
    }

    /**
     * Verifica se l'utente corrente può esportare i volontari
     *
     * @return bool
     */
    public static function can_export_volunteers() {
        return current_user_can( self::CAP_EXPORT_VOLUNTEERS );
    }

    /**
     * Verifica se l'utente corrente può importare i volontari
     *
     * @return bool
     */
    public static function can_import_volunteers() {
        return current_user_can( self::CAP_IMPORT_VOLUNTEERS );
    }

    /**
     * Verifica se l'utente corrente può gestire le impostazioni
     *
     * @return bool
     */
    public static function can_manage_settings() {
        return current_user_can( self::CAP_MANAGE_SETTINGS );
    }

    /**
     * Verifica se l'utente corrente può eliminare i volontari
     *
     * @return bool
     */
    public static function can_delete_volunteers() {
        return current_user_can( self::CAP_DELETE_VOLUNTEERS );
    }

    /**
     * Ottiene tutte le capacità del plugin
     *
     * @return array
     */
    public static function get_all_capabilities() {
        return [
            self::CAP_VIEW_VOLUNTEERS,
            self::CAP_MANAGE_VOLUNTEERS,
            self::CAP_EXPORT_VOLUNTEERS,
            self::CAP_IMPORT_VOLUNTEERS,
            self::CAP_MANAGE_SETTINGS,
            self::CAP_DELETE_VOLUNTEERS,
        ];
    }
}
