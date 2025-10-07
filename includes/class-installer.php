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
        PCV_Database::create_or_upgrade_schema();
    }

    /**
     * Disinstallazione plugin
     *
     * @return void
     */
    public static function uninstall() {
        PCV_Database::drop_table();

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