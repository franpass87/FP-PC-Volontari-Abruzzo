<?php
/**
 * Gestione upgrade plugin e migrazione dati
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Upgrade_Manager {

    const VERSION_OPTION = 'pcv_plugin_version';
    const BACKUP_OPTION = 'pcv_settings_backup';

    /**
     * Verifica e esegue upgrade se necessario
     *
     * @return void
     */
    public static function maybe_upgrade() {
        $current_version = get_option( self::VERSION_OPTION, '0.0.0' );
        $plugin_version = PCV_Plugin::VERSION;

        // Se è già aggiornato, non fare nulla
        if ( version_compare( $current_version, $plugin_version, '>=' ) ) {
            return;
        }

        // Backup impostazioni prima di qualsiasi upgrade
        self::backup_settings();

        // Esegui migrazioni specifiche per versione se necessario
        // (al momento non ce ne sono, ma è pronto per il futuro)

        // Aggiorna la versione salvata
        update_option( self::VERSION_OPTION, $plugin_version );
    }

    /**
     * Crea backup delle impostazioni correnti
     *
     * @return void
     */
    private static function backup_settings() {
        $settings_to_backup = [
            'pcv_recaptcha_site',
            'pcv_recaptcha_secret',
            'pcv_privacy_notice',
            'pcv_notify_enabled',
            'pcv_notify_recipients',
            'pcv_notify_subject',
            'pcv_default_category',
            'pcv_categories',
            'pcv_label_nome',
            'pcv_label_cognome',
            'pcv_label_provincia',
            'pcv_placeholder_provincia',
            'pcv_label_comune',
            'pcv_placeholder_comune',
            'pcv_label_email',
            'pcv_label_telefono',
            'pcv_label_partecipa',
            'pcv_label_dorme',
            'pcv_label_mangia',
            'pcv_label_privacy',
            'pcv_label_submit',
            'pcv_label_optional_group',
            'pcv_label_modal_alert',
        ];

        $backup = [];
        foreach ( $settings_to_backup as $option_name ) {
            $value = get_option( $option_name, null );
            if ( $value !== null ) {
                $backup[ $option_name ] = $value;
            }
        }

        // Salva backup con timestamp
        $backup['timestamp'] = current_time( 'mysql' );
        update_option( self::BACKUP_OPTION, $backup );
    }

    /**
     * Ripristina impostazioni dal backup
     *
     * @return bool True se ripristinato, false altrimenti
     */
    public static function restore_from_backup() {
        $backup = get_option( self::BACKUP_OPTION, [] );

        if ( empty( $backup ) || ! is_array( $backup ) ) {
            return false;
        }

        $restored = 0;
        foreach ( $backup as $option_name => $value ) {
            if ( $option_name === 'timestamp' ) {
                continue;
            }

            // Ripristina solo se l'opzione corrente è vuota o non esiste
            $current_value = get_option( $option_name, '' );
            if ( empty( $current_value ) && ! empty( $value ) ) {
                update_option( $option_name, $value );
                $restored++;
            }
        }

        return $restored > 0;
    }

    /**
     * Ottiene informazioni sul backup
     *
     * @return array|null
     */
    public static function get_backup_info() {
        $backup = get_option( self::BACKUP_OPTION, [] );

        if ( empty( $backup ) || ! is_array( $backup ) ) {
            return null;
        }

        return [
            'timestamp' => isset( $backup['timestamp'] ) ? $backup['timestamp'] : '',
            'settings_count' => count( $backup ) - 1, // -1 per escludere timestamp
        ];
    }
}

