<?php
/**
 * Gestione submit form iscrizione volontari
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Form_Handler {

    const NONCE = 'pcv_form_nonce';
    const TEXT_DOMAIN = 'pc-volontari-abruzzo';

    private $validator;
    private $sanitizer;
    private $recaptcha;
    private $repository;
    private $notifier;
    private $province_data;
    private $comuni_data;

    /**
     * Costruttore
     */
    public function __construct( $validator, $sanitizer, $recaptcha, $repository, $notifier, $province_data, $comuni_data ) {
        $this->validator = $validator;
        $this->sanitizer = $sanitizer;
        $this->recaptcha = $recaptcha;
        $this->repository = $repository;
        $this->notifier = $notifier;
        $this->province_data = $province_data;
        $this->comuni_data = $comuni_data;
    }

    /**
     * Gestisce eventuale submit del form
     *
     * @return void
     */
    public function maybe_handle_submission() {
        if ( ! isset( $_POST['pcv_submit'] ) ) {
            return;
        }

        if ( ! isset( $_POST['pcv_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['pcv_nonce'] ), self::NONCE ) ) {
            $this->redirect_with_status( 'err' );
        }

        // Verifica reCAPTCHA se configurato
        if ( $this->recaptcha->is_configured() ) {
            $token = isset( $_POST['g-recaptcha-response'] ) ? wp_unslash( $_POST['g-recaptcha-response'] ) : '';
            if ( ! $this->recaptcha->verify_token( $token ) ) {
                $this->redirect_with_status( 'err' );
            }
        }

        // Sanitizza dati
        $nome       = $this->sanitizer->sanitize_name( wp_unslash( $_POST['pcv_nome'] ?? '' ) );
        $cognome    = $this->sanitizer->sanitize_name( wp_unslash( $_POST['pcv_cognome'] ?? '' ) );
        $provincia  = strtoupper( trim( wp_unslash( $_POST['pcv_provincia'] ?? '' ) ) );
        $comune     = $this->sanitizer->sanitize_text( wp_unslash( $_POST['pcv_comune'] ?? '' ) );
        $email      = sanitize_email( wp_unslash( $_POST['pcv_email'] ?? '' ) );
        $telefono   = $this->sanitizer->sanitize_phone( wp_unslash( $_POST['pcv_telefono'] ?? '' ) );
        $privacy    = isset( $_POST['pcv_privacy'] ) ? 1 : 0;

        $partecipa_raw = isset( $_POST['pcv_partecipa'] ) ? wp_unslash( $_POST['pcv_partecipa'] ) : null;
        $dorme_raw  = isset( $_POST['pcv_dorme'] ) ? wp_unslash( $_POST['pcv_dorme'] ) : null;
        $mangia_raw = isset( $_POST['pcv_mangia'] ) ? wp_unslash( $_POST['pcv_mangia'] ) : null;

        // Validazione checkbox (devono essere '1' se presenti)
        if ( ! $this->validator->validate_checkbox_value( $partecipa_raw ) ) {
            $this->redirect_with_status( 'err' );
        }

        if ( ! $this->validator->validate_checkbox_value( $dorme_raw ) ) {
            $this->redirect_with_status( 'err' );
        }

        if ( ! $this->validator->validate_checkbox_value( $mangia_raw ) ) {
            $this->redirect_with_status( 'err' );
        }

        $partecipa = $partecipa_raw === '1' ? 1 : 0;
        $dorme  = $dorme_raw === '1' ? 1 : 0;
        $mangia = $mangia_raw === '1' ? 1 : 0;

        $data = [
            'nome'       => $nome,
            'cognome'    => $cognome,
            'provincia'  => $provincia,
            'comune'     => $comune,
            'email'      => $email,
            'telefono'   => $telefono,
            'privacy'    => $privacy,
            'partecipa'  => $partecipa,
            'dorme'      => $dorme,
            'mangia'     => $mangia,
        ];

        // Valida dati
        $validation = $this->validator->validate_registration( $data );
        if ( $validation !== true ) {
            $this->redirect_with_status( 'err' );
        }

        // Prepara dati per inserimento
        $now = current_time( 'mysql' );
        $ip_address = $this->sanitizer->get_client_ip();
        $user_agent_raw = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
        $user_agent = $user_agent_raw !== '' ? mb_substr( sanitize_text_field( $user_agent_raw ), 0, 255 ) : '';
        $category = $this->get_default_category_value();

        $insert_data = [
            'created_at' => $now,
            'nome'       => $nome,
            'cognome'    => $cognome,
            'comune'     => $comune,
            'provincia'  => $provincia,
            'email'      => $email,
            'telefono'   => $telefono,
            'categoria'  => $category,
            'privacy'    => $privacy,
            'partecipa'  => $partecipa,
            'dorme'      => $dorme,
            'mangia'     => $mangia,
            'ip'         => $ip_address,
            'user_agent' => $user_agent,
        ];

        $insert_id = $this->repository->insert( $insert_data );

        if ( $insert_id ) {
            $record = array_merge( $insert_data, [ 'id' => $insert_id ] );

            $this->notifier->maybe_send_notification_email( $record );

            /**
             * Viene eseguito dopo la registrazione di un volontario.
             *
             * @param array $record Dati del volontario salvato.
             */
            do_action( 'pcv_volunteer_registered', $record );
        }

        $this->redirect_with_status( $insert_id ? 'ok' : 'err' );
    }

    /**
     * Ottiene categoria predefinita
     *
     * @return string
     */
    private function get_default_category_value() {
        $category_option = get_option( 'pcv_default_category', 'Volontari' );
        if ( ! is_string( $category_option ) || $category_option === '' ) {
            $category_option = 'Volontari';
        }

        return sanitize_text_field( $category_option );
    }

    /**
     * Redirect con stato
     *
     * @param string $status
     * @return void
     */
    private function redirect_with_status( $status ) {
        $url = add_query_arg( 'pcv_status', $status, wp_get_referer() ?: home_url() );
        wp_safe_redirect( $url );
        exit;
    }
}