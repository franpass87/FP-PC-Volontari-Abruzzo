<?php
/**
 * Gestione installazione e disinstallazione plugin
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Installer {

    /**
     * Attivazione plugin
     *
     * @return void
     */
    public static function activate() {
        try {
            // Verifica che le classi necessarie esistano
            if ( ! class_exists( 'PCV_Database' ) ) {
                throw new Exception( 'Classe PCV_Database non trovata' );
            }
            
            if ( ! class_exists( 'PCV_Role_Manager' ) ) {
                throw new Exception( 'Classe PCV_Role_Manager non trovata' );
            }
            
            // Crea schema database
            PCV_Database::create_or_upgrade_schema();
            
            // Crea ruolo personalizzato
            PCV_Role_Manager::create_role();
            
        } catch ( Exception $e ) {
            // Log dell'errore
            if ( function_exists( 'wp_die' ) ) {
                wp_die(
                    sprintf(
                        /* translators: %s: messaggio di errore */
                        esc_html__( 'Errore durante l\'attivazione del plugin PC Volontari Abruzzo: %s', 'pc-volontari-abruzzo' ),
                        esc_html( $e->getMessage() )
                    ),
                    esc_html__( 'Errore Attivazione Plugin', 'pc-volontari-abruzzo' ),
                    [ 'back_link' => true ]
                );
            } else {
                // Fallback se wp_die non è disponibile
                error_log( 'PC Volontari Abruzzo - Errore attivazione: ' . $e->getMessage() );
                die( 'Errore durante l\'attivazione del plugin: ' . esc_html( $e->getMessage() ) );
            }
        }
    }

    /**
     * Disinstallazione plugin
     *
     * @return void
     */
    public static function uninstall() {
        PCV_Database::drop_table();
        PCV_Role_Manager::remove_role();

        $options = [
            'pcv_recaptcha_site',
            'pcv_recaptcha_secret',
            'pcv_privacy_notice',
            'pcv_label_partecipa',
            'pcv_label_dorme',
            'pcv_label_mangia',
            'pcv_label_nome',
            'pcv_label_cognome',
            'pcv_label_provincia',
            'pcv_placeholder_provincia',
            'pcv_label_comune',
            'pcv_placeholder_comune',
            'pcv_label_email',
            'pcv_label_telefono',
            'pcv_label_privacy',
            'pcv_label_submit',
            'pcv_label_optional_group',
            'pcv_label_modal_alert',
            'pcv_notify_enabled',
            'pcv_notify_recipients',
            'pcv_notify_subject',
            'pcv_default_category',
        ];

        foreach ( $options as $option_name ) {
            delete_option( $option_name );
        }
    }
}