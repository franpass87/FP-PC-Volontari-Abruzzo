<?php
/**
 * Servizio per notifiche email
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Notifier {

    const OPT_NOTIFY_ENABLED    = 'pcv_notify_enabled';
    const OPT_NOTIFY_RECIPIENTS = 'pcv_notify_recipients';
    const OPT_NOTIFY_SUBJECT    = 'pcv_notify_subject';
    const DEFAULT_NOTIFY_SUBJECT = 'Nuova iscrizione volontario';
    const TEXT_DOMAIN = 'pc-volontari-abruzzo';

    private $sanitizer;

    /**
     * Costruttore
     *
     * @param PCV_Sanitizer $sanitizer
     */
    public function __construct( PCV_Sanitizer $sanitizer ) {
        $this->sanitizer = $sanitizer;
    }

    /**
     * Verifica se le notifiche sono abilitate
     *
     * @return bool
     */
    public function are_notifications_enabled() {
        $option = get_option( self::OPT_NOTIFY_ENABLED, '' );
        if ( $option === '' ) {
            return true;
        }

        return (string) $option === '1';
    }

    /**
     * Ottiene i destinatari delle notifiche
     *
     * @return array
     */
    public function get_notification_recipients() {
        $stored = get_option( self::OPT_NOTIFY_RECIPIENTS, '' );
        if ( ! is_string( $stored ) ) {
            $stored = '';
        }

        $normalized = $this->sanitizer->normalize_recipient_list( $stored );
        $parts = $normalized !== '' ? preg_split( '/[\r\n]+/', $normalized ) : [];
        $emails = is_array( $parts ) ? array_filter( $parts ) : [];

        if ( empty( $emails ) ) {
            $admin_email = get_option( 'admin_email' );
            if ( $admin_email && is_email( $admin_email ) ) {
                $emails[] = $admin_email;
            }
        }

        $emails = array_values( array_unique( $emails ) );

        /**
         * Consente di modificare i destinatari delle email di notifica.
         *
         * @param string[] $emails  Elenco di indirizzi email validi.
         */
        $emails = apply_filters( 'pcv_notification_email_recipients', $emails );

        return array_values( array_filter( $emails, function( $email ) {
            $clean = sanitize_email( $email );
            return $clean && is_email( $clean );
        } ) );
    }

    /**
     * Ottiene l'oggetto della notifica
     *
     * @param array $record
     * @return string
     */
    public function get_notification_subject( array $record ) {
        $subject = get_option( self::OPT_NOTIFY_SUBJECT, '' );
        if ( ! is_string( $subject ) || $subject === '' ) {
            $subject = __( self::DEFAULT_NOTIFY_SUBJECT, self::TEXT_DOMAIN );
        }

        /**
         * Consente di filtrare l'oggetto della notifica email.
         *
         * @param string $subject Oggetto dell'email.
         * @param array  $record  Dati del volontario registrato.
         */
        return apply_filters( 'pcv_notification_email_subject', $subject, $record );
    }

    /**
     * Costruisce il messaggio della notifica
     *
     * @param array $record
     * @return string
     */
    public function build_notification_message( array $record ) {
        $lines = [
            sprintf(
                /* translators: 1: nome volontario, 2: cognome volontario */
                __( 'Nuova registrazione volontario: %1$s %2$s', self::TEXT_DOMAIN ),
                $record['nome'],
                $record['cognome']
            ),
            '',
            __( 'Dettagli iscrizione:', self::TEXT_DOMAIN ),
            sprintf( '%s: %s', __( 'Nome', self::TEXT_DOMAIN ), $record['nome'] ),
            sprintf( '%s: %s', __( 'Cognome', self::TEXT_DOMAIN ), $record['cognome'] ),
            sprintf( '%s: %s', __( 'Email', self::TEXT_DOMAIN ), $record['email'] ),
            sprintf( '%s: %s', __( 'Telefono', self::TEXT_DOMAIN ), $record['telefono'] ),
            sprintf( '%s: %s', __( 'Comune', self::TEXT_DOMAIN ), $record['comune'] ),
            sprintf( '%s: %s', __( 'Provincia', self::TEXT_DOMAIN ), $record['provincia'] ),
            ! empty( $record['categoria'] ) ? sprintf( '%s: %s', __( 'Categoria', self::TEXT_DOMAIN ), $record['categoria'] ) : '',
            sprintf( '%s: %s', __( 'Partecipa', self::TEXT_DOMAIN ), $record['partecipa'] ? __( 'Sì', self::TEXT_DOMAIN ) : __( 'No', self::TEXT_DOMAIN ) ),
            sprintf( '%s: %s', __( 'Pernotta', self::TEXT_DOMAIN ), $record['dorme'] ? __( 'Sì', self::TEXT_DOMAIN ) : __( 'No', self::TEXT_DOMAIN ) ),
            sprintf( '%s: %s', __( 'Pasti', self::TEXT_DOMAIN ), $record['mangia'] ? __( 'Sì', self::TEXT_DOMAIN ) : __( 'No', self::TEXT_DOMAIN ) ),
            '',
            sprintf( '%s: %s', __( 'IP', self::TEXT_DOMAIN ), $record['ip'] ),
            sprintf( '%s: %s', __( 'User Agent', self::TEXT_DOMAIN ), $record['user_agent'] ),
        ];

        /**
         * Permette di modificare il testo della notifica email.
         *
         * @param string $message Testo dell'email.
         * @param array  $record  Dati del volontario registrato.
         */
        $lines = array_values( array_filter( $lines, static function( $line ) {
            return $line !== '';
        } ) );

        return apply_filters( 'pcv_notification_email_message', implode( "\n", $lines ), $record );
    }

    /**
     * Invia notifica email se abilitata
     *
     * @param array $record
     * @return void
     */
    public function maybe_send_notification_email( array $record ) {
        if ( ! $this->are_notifications_enabled() ) {
            return;
        }

        $recipients = $this->get_notification_recipients();
        if ( empty( $recipients ) ) {
            return;
        }

        $subject = $this->get_notification_subject( $record );
        $message = $this->build_notification_message( $record );
        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

        wp_mail( $recipients, $subject, $message, $headers );
    }
}