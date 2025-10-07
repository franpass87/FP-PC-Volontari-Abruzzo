<?php
/**
 * Pagina impostazioni admin
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Settings_Page {

    const TEXT_DOMAIN = 'pc-volontari-abruzzo';

    private $sanitizer;

    public function __construct( $sanitizer ) {
        $this->sanitizer = $sanitizer;
    }

    /**
     * Renderizza pagina impostazioni
     *
     * @return void
     */
    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $label_fields = $this->get_label_fields();

        // Salva impostazioni
        if ( isset( $_POST['pcv_save_keys'] ) && check_admin_referer( 'pcv_save_keys_nonce' ) ) {
            $this->save_settings( $label_fields );
            echo '<div class="updated notice"><p>' . esc_html__( 'Impostazioni salvate.', self::TEXT_DOMAIN ) . '</p></div>';
        }

        $site = esc_attr( get_option( 'pcv_recaptcha_site', '' ) );
        $secret = esc_attr( get_option( 'pcv_recaptcha_secret', '' ) );
        $privacy_notice = get_option( 'pcv_privacy_notice', '' );
        if ( ! $privacy_notice ) {
            $privacy_notice = __( "I dati saranno trattati ai sensi del Reg. UE 2016/679 (GDPR) per la gestione dell'evento e finalità organizzative. Titolare del trattamento: [inserire].", self::TEXT_DOMAIN );
        }

        $notify_enabled = $this->is_notify_enabled();
        $notify_recipients = $this->get_notify_recipients();
        $notify_subject = $this->get_notify_subject();

        $label_values = $this->get_label_values( $label_fields );

        echo '<div class="wrap"><h1>' . esc_html__( 'Impostazioni modulo Volontari', self::TEXT_DOMAIN ) . '</h1>';
        echo '<form method="post">';
        wp_nonce_field( 'pcv_save_keys_nonce' );
        echo '<table class="form-table">';
        echo '<tr><th scope="row"><label for="pcv_site_key">' . esc_html__( 'Site Key', self::TEXT_DOMAIN ) . '</label></th><td><input type="text" id="pcv_site_key" name="pcv_site_key" value="' . $site . '" class="regular-text"></td></tr>';
        echo '<tr><th scope="row"><label for="pcv_secret_key">' . esc_html__( 'Secret Key', self::TEXT_DOMAIN ) . '</label></th><td><input type="text" id="pcv_secret_key" name="pcv_secret_key" value="' . $secret . '" class="regular-text"></td></tr>';
        echo '<tr><th scope="row">' . esc_html__( 'Notifiche email', self::TEXT_DOMAIN ) . '</th><td><label><input type="checkbox" name="pcv_notify_enabled" value="1" ' . checked( $notify_enabled, true, false ) . '> ' . esc_html__( 'Invia una email di notifica ad ogni nuova iscrizione.', self::TEXT_DOMAIN ) . '</label><p class="description">' . esc_html__( 'Per impostazione predefinita la notifica viene inviata all\'email amministratore di WordPress.', self::TEXT_DOMAIN ) . '</p></td></tr>';
        echo '<tr><th scope="row"><label for="pcv_notify_recipients">' . esc_html__( 'Destinatari notifiche', self::TEXT_DOMAIN ) . '</label></th><td><textarea id="pcv_notify_recipients" name="pcv_notify_recipients" rows="4" class="large-text code">' . esc_textarea( $notify_recipients ) . '</textarea><p class="description">' . esc_html__( 'Inserisci uno o più indirizzi email (uno per riga, virgola o punto e virgola). Se lasci vuoto verrà utilizzata l\'email amministratore.', self::TEXT_DOMAIN ) . '</p></td></tr>';
        echo '<tr><th scope="row"><label for="pcv_notify_subject">' . esc_html__( 'Oggetto email notifica', self::TEXT_DOMAIN ) . '</label></th><td><input type="text" id="pcv_notify_subject" name="pcv_notify_subject" value="' . esc_attr( $notify_subject ) . '" class="regular-text"><p class="description">' . esc_html__( 'Personalizza l\'oggetto delle email inviate ai referenti.', self::TEXT_DOMAIN ) . '</p></td></tr>';

        foreach ( $label_fields as $option_key => $field ) {
            $value = $label_values[ $option_key ];
            $field_id = esc_attr( $option_key );
            $label = esc_html( $field['label'] );
            $description = isset( $field['description'] ) && $field['description'] !== '' ? '<p class="description">' . esc_html( $field['description'] ) . '</p>' : '';
            echo '<tr><th scope="row"><label for="' . $field_id . '">' . $label . '</label></th><td><input type="text" id="' . $field_id . '" name="' . $field_id . '" value="' . esc_attr( $value ) . '" class="regular-text">' . $description . '</td></tr>';
        }

        echo '<tr><th scope="row"><label for="pcv_privacy_notice">' . esc_html__( 'Informativa Privacy', self::TEXT_DOMAIN ) . '</label></th><td><textarea id="pcv_privacy_notice" name="pcv_privacy_notice" rows="6" class="large-text code">' . esc_textarea( $privacy_notice ) . '</textarea><p class="description">' . esc_html__( 'Inserisci l\'informativa privacy completa, includendo il Titolare del trattamento e le eventuali note legali.', self::TEXT_DOMAIN ) . '</p></td></tr>';
        echo '</table>';
        submit_button( __( 'Salva impostazioni', self::TEXT_DOMAIN ), 'primary', 'pcv_save_keys' );
        echo '</form></div>';
    }

    private function get_label_fields() {
        return [
            'pcv_label_nome' => [
                'label'       => __( 'Etichetta campo Nome', self::TEXT_DOMAIN ),
                'description' => __( 'Testo mostrato accanto al campo Nome nel form pubblico.', self::TEXT_DOMAIN ),
                'default'     => 'Nome *',
                'sanitize'    => 'sanitize_text_field',
            ],
            'pcv_label_cognome' => [
                'label'       => __( 'Etichetta campo Cognome', self::TEXT_DOMAIN ),
                'description' => __( 'Testo mostrato accanto al campo Cognome nel form pubblico.', self::TEXT_DOMAIN ),
                'default'     => 'Cognome *',
                'sanitize'    => 'sanitize_text_field',
            ],
            'pcv_label_provincia' => [
                'label'       => __( 'Etichetta campo Provincia', self::TEXT_DOMAIN ),
                'description' => __( 'Testo mostrato accanto alla select della Provincia.', self::TEXT_DOMAIN ),
                'default'     => 'Provincia *',
                'sanitize'    => 'sanitize_text_field',
            ],
            'pcv_placeholder_provincia' => [
                'label'       => __( 'Testo predefinito select Provincia', self::TEXT_DOMAIN ),
                'description' => __( 'Prima opzione vuota mostrata nelle select della Provincia (form e popup).', self::TEXT_DOMAIN ),
                'default'     => 'Seleziona provincia',
                'sanitize'    => 'sanitize_text_field',
            ],
            'pcv_label_comune' => [
                'label'       => __( 'Etichetta campo Comune', self::TEXT_DOMAIN ),
                'description' => __( 'Testo mostrato accanto alla select del Comune.', self::TEXT_DOMAIN ),
                'default'     => 'Comune di provenienza *',
                'sanitize'    => 'sanitize_text_field',
            ],
            'pcv_placeholder_comune' => [
                'label'       => __( 'Testo predefinito select Comune', self::TEXT_DOMAIN ),
                'description' => __( 'Prima opzione vuota mostrata nelle select del Comune (form e popup).', self::TEXT_DOMAIN ),
                'default'     => 'Seleziona comune',
                'sanitize'    => 'sanitize_text_field',
            ],
            'pcv_label_email' => [
                'label'       => __( 'Etichetta campo Email', self::TEXT_DOMAIN ),
                'description' => __( 'Testo mostrato accanto al campo Email.', self::TEXT_DOMAIN ),
                'default'     => 'Email *',
                'sanitize'    => 'sanitize_text_field',
            ],
            'pcv_label_telefono' => [
                'label'       => __( 'Etichetta campo Telefono', self::TEXT_DOMAIN ),
                'description' => __( 'Testo mostrato accanto al campo Telefono.', self::TEXT_DOMAIN ),
                'default'     => 'Telefono *',
                'sanitize'    => 'sanitize_text_field',
            ],
            'pcv_label_partecipa' => [
                'label'       => __( 'Etichetta partecipazione', self::TEXT_DOMAIN ),
                'description' => __( 'Testo mostrato accanto all\'opzione di partecipazione.', self::TEXT_DOMAIN ),
                'default'     => 'Sì, voglio partecipare all\'evento',
                'sanitize'    => 'sanitize_text_field',
            ],
            'pcv_label_dorme' => [
                'label'       => __( 'Etichetta pernottamento', self::TEXT_DOMAIN ),
                'description' => __( 'Testo mostrato accanto all\'opzione di pernottamento.', self::TEXT_DOMAIN ),
                'default'     => 'Mi fermo a dormire',
                'sanitize'    => 'sanitize_text_field',
            ],
            'pcv_label_mangia' => [
                'label'       => __( 'Etichetta pasti', self::TEXT_DOMAIN ),
                'description' => __( 'Testo mostrato accanto all\'opzione relativa ai pasti.', self::TEXT_DOMAIN ),
                'default'     => 'Parteciperò ai pasti',
                'sanitize'    => 'sanitize_text_field',
            ],
            'pcv_label_privacy' => [
                'label'       => __( 'Etichetta consenso privacy', self::TEXT_DOMAIN ),
                'description' => __( 'Testo mostrato accanto alla casella di consenso privacy. Puoi includere un link usando HTML.', self::TEXT_DOMAIN ),
                'default'     => 'Ho letto e accetto l\'Informativa Privacy *',
                'sanitize'    => 'wp_kses_post',
            ],
            'pcv_label_optional_group' => [
                'label'       => __( 'Descrizione gruppo opzioni facoltative', self::TEXT_DOMAIN ),
                'description' => __( 'Testo utilizzato per l\'attributo aria-label del gruppo di checkbox facoltative.', self::TEXT_DOMAIN ),
                'default'     => 'Opzioni facoltative',
                'sanitize'    => 'sanitize_text_field',
            ],
            'pcv_label_submit' => [
                'label'       => __( 'Testo pulsante invio', self::TEXT_DOMAIN ),
                'description' => __( 'Testo del pulsante di invio del form.', self::TEXT_DOMAIN ),
                'default'     => 'Invia iscrizione',
                'sanitize'    => 'sanitize_text_field',
            ],
            'pcv_label_modal_alert' => [
                'label'       => __( 'Messaggio popup Provincia/Comune', self::TEXT_DOMAIN ),
                'description' => __( 'Avviso mostrato nel popup se non vengono selezionati Provincia e Comune.', self::TEXT_DOMAIN ),
                'default'     => 'Seleziona provincia e comune.',
                'sanitize'    => 'sanitize_text_field',
            ],
            'pcv_default_category' => [
                'label'       => __( 'Categoria predefinita iscrizioni', self::TEXT_DOMAIN ),
                'description' => __( 'Valore assegnato automaticamente alle registrazioni inviate dal form pubblico.', self::TEXT_DOMAIN ),
                'default'     => 'Volontari',
                'sanitize'    => 'sanitize_text_field',
            ],
        ];
    }

    private function save_settings( $label_fields ) {
        $site_value = isset( $_POST['pcv_site_key'] ) ? sanitize_text_field( wp_unslash( $_POST['pcv_site_key'] ) ) : '';
        $secret_value = isset( $_POST['pcv_secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['pcv_secret_key'] ) ) : '';
        $privacy_notice_value = isset( $_POST['pcv_privacy_notice'] ) ? wp_kses_post( wp_unslash( $_POST['pcv_privacy_notice'] ) ) : '';
        $notify_enabled_value = isset( $_POST['pcv_notify_enabled'] ) ? '1' : '0';
        $notify_recipients_raw = isset( $_POST['pcv_notify_recipients'] ) ? wp_unslash( $_POST['pcv_notify_recipients'] ) : '';
        $notify_recipients_value = $this->sanitizer->normalize_recipient_list( $notify_recipients_raw );
        $notify_subject_raw = isset( $_POST['pcv_notify_subject'] ) ? wp_unslash( $_POST['pcv_notify_subject'] ) : '';
        $notify_subject_value = sanitize_text_field( $notify_subject_raw );

        update_option( 'pcv_recaptcha_site', $site_value );
        update_option( 'pcv_recaptcha_secret', $secret_value );
        update_option( 'pcv_privacy_notice', $privacy_notice_value );
        update_option( 'pcv_notify_enabled', $notify_enabled_value );
        update_option( 'pcv_notify_recipients', $notify_recipients_value );
        update_option( 'pcv_notify_subject', $notify_subject_value );

        foreach ( $label_fields as $option_key => $field ) {
            $raw_value = isset( $_POST[ $option_key ] ) ? wp_unslash( $_POST[ $option_key ] ) : '';
            $sanitize_cb = $field['sanitize'];
            if ( is_callable( $sanitize_cb ) ) {
                $clean_value = call_user_func( $sanitize_cb, $raw_value );
            } else {
                $clean_value = sanitize_text_field( $raw_value );
            }
            update_option( $option_key, $clean_value );
        }
    }

    private function is_notify_enabled() {
        $option = get_option( 'pcv_notify_enabled', '' );
        if ( $option === '' ) {
            return true;
        }
        return (string) $option === '1';
    }

    private function get_notify_recipients() {
        $stored = get_option( 'pcv_notify_recipients', '' );
        if ( ! is_string( $stored ) ) {
            $stored = '';
        }
        return $this->sanitizer->normalize_recipient_list( $stored );
    }

    private function get_notify_subject() {
        $notify_subject = get_option( 'pcv_notify_subject', '' );
        if ( ! is_string( $notify_subject ) || $notify_subject === '' ) {
            $notify_subject = __( 'Nuova iscrizione volontario', self::TEXT_DOMAIN );
        }
        return $notify_subject;
    }

    private function get_label_values( $label_fields ) {
        $values = [];
        foreach ( $label_fields as $option_key => $field ) {
            $value = get_option( $option_key, '' );
            if ( ! is_string( $value ) || $value === '' ) {
                $value = $field['default'];
            }
            $values[ $option_key ] = $value;
        }
        return $values;
    }
}