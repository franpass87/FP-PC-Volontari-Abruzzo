<?php
/**
 * Plugin Name: PC Volontari Abruzzo
 * Description: Raccolta iscrizioni volontari (Protezione Civile Abruzzo) con form via shortcode, popup comune, lista completa Comuni/Province Abruzzo, reCAPTCHA v2 e gestionale backend.
 * Version: 1.1.0
 * Author: Francesco Passeri
 * Author URI: https://francescopasseri.com
 * License: GPLv2 or later
 * Text Domain: pc-volontari-abruzzo
 */

if ( ! defined('ABSPATH') ) exit;

class PCV_Abruzzo_Plugin {

    const VERSION   = '1.1.0';
    const TEXT_DOMAIN = 'pc-volontari-abruzzo';
    const TABLE     = 'pcv_volontari';
    const NONCE     = 'pcv_form_nonce';
    const MENU_SLUG = 'pcv-volontari';
    const OPT_RECAPTCHA_SITE      = 'pcv_recaptcha_site';
    const OPT_RECAPTCHA_SECRET    = 'pcv_recaptcha_secret';
    const OPT_PRIVACY_NOTICE      = 'pcv_privacy_notice';
    const OPT_PARTICIPATION_LABEL = 'pcv_label_partecipa';
    const OPT_OVERNIGHT_LABEL     = 'pcv_label_dorme';
    const OPT_MEALS_LABEL         = 'pcv_label_mangia';
    const OPT_NAME_LABEL          = 'pcv_label_nome';
    const OPT_SURNAME_LABEL       = 'pcv_label_cognome';
    const OPT_PROVINCE_LABEL      = 'pcv_label_provincia';
    const OPT_PROVINCE_PLACEHOLDER = 'pcv_placeholder_provincia';
    const OPT_COMUNE_LABEL        = 'pcv_label_comune';
    const OPT_COMUNE_PLACEHOLDER  = 'pcv_placeholder_comune';
    const OPT_EMAIL_LABEL         = 'pcv_label_email';
    const OPT_PHONE_LABEL         = 'pcv_label_telefono';
    const OPT_PRIVACY_FIELD_LABEL = 'pcv_label_privacy';
    const OPT_SUBMIT_LABEL        = 'pcv_label_submit';
    const OPT_OPTIONAL_GROUP_ARIA = 'pcv_label_optional_group';
    const OPT_MODAL_ALERT         = 'pcv_label_modal_alert';
    const OPT_NOTIFY_ENABLED      = 'pcv_notify_enabled';
    const OPT_NOTIFY_RECIPIENTS   = 'pcv_notify_recipients';
    const OPT_NOTIFY_SUBJECT      = 'pcv_notify_subject';
    const IMPORT_EXPECTED_COLUMNS = [
        'nome',
        'cognome',
        'comune',
        'provincia',
        'email',
        'telefono',
    ];
    const DEFAULT_PRIVACY_NOTICE  = "I dati saranno trattati ai sensi del Reg. UE 2016/679 (GDPR) per la gestione dell’evento e finalità organizzative. Titolare del trattamento: [inserire].";
    const DEFAULT_PARTICIPATION_LABEL = 'Sì, voglio partecipare all’evento';
    const DEFAULT_OVERNIGHT_LABEL     = 'Mi fermo a dormire';
    const DEFAULT_MEALS_LABEL         = 'Parteciperò ai pasti';
    const DEFAULT_NAME_LABEL          = 'Nome *';
    const DEFAULT_SURNAME_LABEL       = 'Cognome *';
    const DEFAULT_PROVINCE_LABEL      = 'Provincia *';
    const DEFAULT_PROVINCE_PLACEHOLDER = 'Seleziona provincia';
    const DEFAULT_COMUNE_LABEL        = 'Comune di provenienza *';
    const DEFAULT_COMUNE_PLACEHOLDER  = 'Seleziona comune';
    const DEFAULT_EMAIL_LABEL         = 'Email *';
    const DEFAULT_PHONE_LABEL         = 'Telefono *';
    const DEFAULT_PRIVACY_FIELD_LABEL = 'Ho letto e accetto l’Informativa Privacy *';
    const DEFAULT_SUBMIT_LABEL        = 'Invia iscrizione';
    const DEFAULT_OPTIONAL_GROUP_ARIA = 'Opzioni facoltative';
    const DEFAULT_MODAL_ALERT         = 'Seleziona provincia e comune.';
    const DEFAULT_NOTIFY_SUBJECT      = 'Nuova iscrizione volontario';

    /** Province e comuni caricati da file */
    private $province = [];
    private $comuni   = [];

    public function __construct() {
        $data = ['province' => [], 'comuni' => []];
        $file = __DIR__ . '/data/comuni_abruzzo.json';
        if ( file_exists( $file ) && is_readable( $file ) ) {
            $json = file_get_contents( $file );
            if ( $json !== false ) {
                $decoded = json_decode( $json, true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                    $data = $decoded;
                } else {
                    error_log( 'PCV_Abruzzo_Plugin: JSON decode error for comuni data - ' . json_last_error_msg() );
                }
            } else {
                error_log( 'PCV_Abruzzo_Plugin: unable to read comuni data file' );
            }
        } else {
            error_log( 'PCV_Abruzzo_Plugin: comuni data file missing or unreadable' );
        }
        $this->province = $data['province'] ?? [];
        $this->comuni   = $data['comuni'] ?? [];

        add_shortcode( 'pc_volontari_form', [ $this, 'render_form_shortcode' ] );

        add_action( 'init', [ $this, 'load_textdomain' ] );

        // Assets
        add_action( 'wp_enqueue_scripts', [ $this, 'register_front_assets' ] );
        add_action( 'plugins_loaded', [ $this, 'maybe_upgrade_schema' ] );

        // Handle POST
        add_action( 'init', [ $this, 'maybe_handle_submission' ] );

        // Admin
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );

        // Export
        add_action( 'admin_init', [ $this, 'maybe_export_csv' ] );
    }

    public function get_province_data() {
        return $this->province;
    }

    public function get_comuni_data() {
        return $this->comuni;
    }

    public function get_all_comuni() {
        $all = [];
        foreach ( $this->comuni as $province_comuni ) {
            if ( ! is_array( $province_comuni ) ) {
                continue;
            }
            foreach ( $province_comuni as $comune_name ) {
                if ( is_string( $comune_name ) && $comune_name !== '' ) {
                    $all[ $comune_name ] = $comune_name;
                }
            }
        }

        $values = array_values( $all );
        sort( $values, SORT_NATURAL | SORT_FLAG_CASE );

        return $values;
    }

    /* ---------------- Activation: create table ---------------- */
    public static function activate() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();

        $sql = self::get_schema_sql( $table, $charset );

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

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

    public function maybe_upgrade_schema() {
        global $wpdb;

        $table = $this->table_name();
        $table_like = $wpdb->esc_like( $table );
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_like ) );

        if ( $exists !== $table ) {
            self::activate();
            return;
        }

        $needs_upgrade = false;
        $table_sql = esc_sql( $table );
        foreach ( [ 'dorme', 'mangia', 'categoria' ] as $column ) {
            $column_exists = $wpdb->get_var(
                $wpdb->prepare( "SHOW COLUMNS FROM `{$table_sql}` LIKE %s", $column )
            );

            if ( ! $column_exists ) {
                $needs_upgrade = true;
                break;
            }
        }

        if ( ! $needs_upgrade ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        dbDelta( self::get_schema_sql( $table, $charset ) );
    }

    public function table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    private function csv_text_guard( $value ) {
        if ( ! is_string( $value ) || $value === '' ) {
            return $value;
        }

        $first_char = $value[0];
        if ( in_array( $first_char, ['=', '+', '-', '@'], true ) ) {
            return "'" . $value;
        }

        return $value;
    }

    private function get_label_value( $option, $default ) {
        $value = get_option( $option, '' );
        if ( ! is_string( $value ) || $value === '' ) {
            return __( $default, self::TEXT_DOMAIN );
        }

        return $value;
    }

    private function notifications_enabled() {
        $option = get_option( self::OPT_NOTIFY_ENABLED, '' );
        if ( $option === '' ) {
            return true;
        }

        return (string) $option === '1';
    }

    private function normalize_recipient_list( $raw_value ) {
        if ( ! is_string( $raw_value ) ) {
            $raw_value = '';
        }

        $parts = preg_split( '/[\r\n,;]+/', $raw_value );
        $emails = [];

        if ( is_array( $parts ) ) {
            foreach ( $parts as $part ) {
                $email = sanitize_email( trim( $part ) );
                if ( $email && is_email( $email ) ) {
                    $emails[] = $email;
                }
            }
        }

        $emails = array_values( array_unique( $emails ) );

        return implode( "\n", $emails );
    }

    private function get_notification_recipients() {
        $stored = get_option( self::OPT_NOTIFY_RECIPIENTS, '' );
        if ( ! is_string( $stored ) ) {
            $stored = '';
        }

        $normalized = $this->normalize_recipient_list( $stored );
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

    private function get_notification_subject( array $record ) {
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

    private function build_notification_message( array $record ) {
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

    private function maybe_send_notification_email( array $record ) {
        if ( ! $this->notifications_enabled() ) {
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

    public function load_textdomain() {
        load_plugin_textdomain( self::TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /* ---------------- Frontend: assets + shortcode ---------------- */
    public function register_front_assets() {
        wp_register_style( 'pcv-frontend', plugins_url( 'assets/css/frontend.css', __FILE__ ), [], self::VERSION );
        wp_register_script( 'pcv-frontend', plugins_url( 'assets/js/frontend.js', __FILE__ ), [], self::VERSION, true );
    }

    public function render_form_shortcode( $atts ) {
        $atts = shortcode_atts( [], $atts, 'pc_volontari_form' );
        $out = '';

        wp_enqueue_style( 'pcv-frontend' );
        wp_enqueue_script( 'pcv-frontend' );

        $province_placeholder = $this->get_label_value( self::OPT_PROVINCE_PLACEHOLDER, self::DEFAULT_PROVINCE_PLACEHOLDER );
        $comune_placeholder   = $this->get_label_value( self::OPT_COMUNE_PLACEHOLDER, self::DEFAULT_COMUNE_PLACEHOLDER );
        $modal_alert_label    = $this->get_label_value( self::OPT_MODAL_ALERT, self::DEFAULT_MODAL_ALERT );

        $data = [
            'province'       => $this->province,
            'comuni'         => $this->comuni,
            'recaptcha_site' => get_option( self::OPT_RECAPTCHA_SITE, '' ),
            'labels'         => [
                'selectProvince' => $province_placeholder,
                'selectComune'   => $comune_placeholder,
                'modalAlert'     => $modal_alert_label,
            ],
            'fallbacks'      => [
                'selectProvince' => __( 'Seleziona provincia', self::TEXT_DOMAIN ),
                'selectComune'   => __( 'Seleziona comune', self::TEXT_DOMAIN ),
                'modalAlert'     => __( 'Seleziona provincia e comune.', self::TEXT_DOMAIN ),
            ],
        ];

        wp_localize_script( 'pcv-frontend', 'PCV_DATA', $data );

        // Messaggi post-submit via query var
        if ( isset($_GET['pcv_status']) ) {
            $status = sanitize_key( wp_unslash( $_GET['pcv_status'] ) );

            if ( $status === 'ok' ) {
                $message = esc_html__( 'Grazie! La tua registrazione è stata inviata correttamente.', self::TEXT_DOMAIN );
                $out    .= '<div class="pcv-alert success" role="status" aria-live="polite"><span class="pcv-alert-icon" aria-hidden="true">✓</span><div class="pcv-alert-message">' . $message . '</div></div>';
            } elseif ( $status === 'err' ) {
                $message = esc_html__( 'Si è verificato un errore. Verifica i campi e riprova.', self::TEXT_DOMAIN );
                $out    .= '<div class="pcv-alert error" role="alert" aria-live="assertive"><span class="pcv-alert-icon" aria-hidden="true">!</span><div class="pcv-alert-message">' . $message . '</div></div>';
            }
        }

        $nonce = wp_create_nonce( self::NONCE );
        $site_key = esc_attr( get_option(self::OPT_RECAPTCHA_SITE, '') );
        $privacy_notice = get_option( self::OPT_PRIVACY_NOTICE, '' );
        if ( ! $privacy_notice ) {
            $privacy_notice = __( self::DEFAULT_PRIVACY_NOTICE, self::TEXT_DOMAIN );
        }

        $participation_label = $this->get_label_value( self::OPT_PARTICIPATION_LABEL, self::DEFAULT_PARTICIPATION_LABEL );
        $overnight_label     = $this->get_label_value( self::OPT_OVERNIGHT_LABEL, self::DEFAULT_OVERNIGHT_LABEL );
        $meals_label         = $this->get_label_value( self::OPT_MEALS_LABEL, self::DEFAULT_MEALS_LABEL );
        $name_label          = $this->get_label_value( self::OPT_NAME_LABEL, self::DEFAULT_NAME_LABEL );
        $surname_label       = $this->get_label_value( self::OPT_SURNAME_LABEL, self::DEFAULT_SURNAME_LABEL );
        $province_label      = $this->get_label_value( self::OPT_PROVINCE_LABEL, self::DEFAULT_PROVINCE_LABEL );
        $comune_label        = $this->get_label_value( self::OPT_COMUNE_LABEL, self::DEFAULT_COMUNE_LABEL );
        $email_label         = $this->get_label_value( self::OPT_EMAIL_LABEL, self::DEFAULT_EMAIL_LABEL );
        $phone_label         = $this->get_label_value( self::OPT_PHONE_LABEL, self::DEFAULT_PHONE_LABEL );
        $privacy_field_label = $this->get_label_value( self::OPT_PRIVACY_FIELD_LABEL, self::DEFAULT_PRIVACY_FIELD_LABEL );
        $submit_label        = $this->get_label_value( self::OPT_SUBMIT_LABEL, self::DEFAULT_SUBMIT_LABEL );
        $optional_group_aria = $this->get_label_value( self::OPT_OPTIONAL_GROUP_ARIA, self::DEFAULT_OPTIONAL_GROUP_ARIA );

        ob_start(); ?>
        <!-- Modal Provincia/Comune -->
        <div id="pcvComuneModal" class="pcv-modal-backdrop pcv-hidden" role="dialog" aria-modal="true">
          <div class="pcv-modal">
            <div class="pcv-modal-header">
              <span class="pcv-modal-icon" aria-hidden="true">📍</span>
              <div>
                <h3><?php printf( esc_html__( 'Seleziona %1$sProvincia%2$s e %1$sComune%2$s', self::TEXT_DOMAIN ), '<strong>', '</strong>' ); ?></h3>
                <p><?php esc_html_e( 'Li useremo per precompilare il form. Puoi modificarli dopo.', self::TEXT_DOMAIN ); ?></p>
              </div>
            </div>

            <div class="pcv-modal-body">
              <label for="pcvProvinciaInput" class="pcv-modal-label"><?php esc_html_e( 'Provincia', self::TEXT_DOMAIN ); ?></label>
              <select id="pcvProvinciaInput" class="pcv-modal-select">
                <option value=""><?php echo esc_html( $province_placeholder ); ?></option>
              </select>

              <label for="pcvComuneInput" class="pcv-modal-label"><?php esc_html_e( 'Comune', self::TEXT_DOMAIN ); ?></label>
              <select id="pcvComuneInput" class="pcv-modal-select">
                <option value=""><?php echo esc_html( $comune_placeholder ); ?></option>
              </select>
            </div>

            <div class="pcv-actions">
              <button type="button" id="pcvComuneSkip" class="button button-secondary"><?php esc_html_e( 'Salta', self::TEXT_DOMAIN ); ?></button>
              <button type="button" id="pcvComuneConfirm" class="button button-primary"><?php esc_html_e( 'Conferma', self::TEXT_DOMAIN ); ?></button>
            </div>
          </div>
        </div>

        <div class="pcv-form-shell">
            <form class="pcv-form" method="post">
            <input type="hidden" name="pcv_submit" value="1">
            <input type="hidden" name="pcv_nonce" value="<?php echo esc_attr($nonce); ?>">

            <div class="pcv-row">
                <div class="pcv-field">
                    <label for="pcv_nome"><?php echo esc_html( $name_label ); ?></label>
                    <input type="text" id="pcv_nome" name="pcv_nome" required>
                </div>
                <div class="pcv-field">
                    <label for="pcv_cognome"><?php echo esc_html( $surname_label ); ?></label>
                    <input type="text" id="pcv_cognome" name="pcv_cognome" required>
                </div>
            </div>

            <div class="pcv-row">
                <div class="pcv-field">
                    <label for="pcv_provincia"><?php echo esc_html( $province_label ); ?></label>
                    <select id="pcv_provincia" name="pcv_provincia" required>
                        <option value=""><?php echo esc_html( $province_placeholder ); ?></option>
                    </select>
                </div>
                <div class="pcv-field">
                    <label for="pcv_comune"><?php echo esc_html( $comune_label ); ?></label>
                    <select id="pcv_comune" name="pcv_comune" required>
                        <option value=""><?php echo esc_html( $comune_placeholder ); ?></option>
                    </select>
                </div>
            </div>

            <div class="pcv-row">
                <div class="pcv-field">
                    <label for="pcv_email"><?php echo esc_html( $email_label ); ?></label>
                    <input type="email" id="pcv_email" name="pcv_email" required>
                </div>
                <div class="pcv-field">
                    <label for="pcv_telefono"><?php echo esc_html( $phone_label ); ?></label>
                    <input type="tel" id="pcv_telefono" name="pcv_telefono" required>
                </div>
            </div>

            <div class="pcv-checkbox-group" role="group" aria-label="<?php echo esc_attr( $optional_group_aria ); ?>">
                <div class="pcv-optional-heading">
                    <h4 class="pcv-optional-heading-title"><?php echo esc_html( $optional_group_aria ); ?></h4>
                    <p class="pcv-optional-heading-subtitle"><?php esc_html_e( 'Indica le tue preferenze per organizzare al meglio la partecipazione.', self::TEXT_DOMAIN ); ?></p>
                </div>

                <div class="pcv-checkbox">
                    <input type="checkbox" id="pcv_partecipa" name="pcv_partecipa" value="1">
                    <label for="pcv_partecipa"><?php echo esc_html( $participation_label ); ?></label>
                </div>

                <div class="pcv-checkbox">
                    <input type="checkbox" id="pcv_dorme" name="pcv_dorme" value="1">
                    <label for="pcv_dorme"><?php echo esc_html( $overnight_label ); ?></label>
                </div>

                <div class="pcv-checkbox">
                    <input type="checkbox" id="pcv_mangia" name="pcv_mangia" value="1">
                    <label for="pcv_mangia"><?php echo esc_html( $meals_label ); ?></label>
                </div>
            </div>

            <div class="pcv-checkbox-divider" aria-hidden="true"></div>

            <div class="pcv-checkbox">
                <input type="checkbox" id="pcv_privacy" name="pcv_privacy" value="1" required>
                <label for="pcv_privacy"><?php echo wp_kses_post( $privacy_field_label ); ?></label>
            </div>

            <?php if ( $site_key ) : ?>
                <div class="g-recaptcha" data-sitekey="<?php echo $site_key; ?>"></div>
            <?php endif; ?>

            <div class="pcv-submit">
                <button type="submit" class="button button-primary"><?php echo esc_html( $submit_label ); ?></button>
            </div>

            <div class="pcv-privacy-notice">
                <?php echo wpautop( wp_kses_post( $privacy_notice ) ); ?>
            </div>
            </form>
        </div>
        <?php
        return $out . ob_get_clean();
    }

    /* ---------------- Handle submission ---------------- */
    public function maybe_handle_submission() {
        if ( ! isset($_POST['pcv_submit']) ) return;

        if ( ! isset($_POST['pcv_nonce']) || ! wp_verify_nonce( $_POST['pcv_nonce'], self::NONCE ) ) {
            $this->redirect_with_status('err');
        }

        // reCAPTCHA verify (if configured)
        $secret = get_option(self::OPT_RECAPTCHA_SECRET, '');
        if ( $secret ) {
            $token = $_POST['g-recaptcha-response'] ?? '';
            if ( empty($token) ) $this->redirect_with_status('err');

            $resp = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
                'timeout' => 10,
                'body' => [
                    'secret'   => $secret,
                    'response' => $token,
                    'remoteip' => $this->get_ip(),
                ]
            ]);
            if ( is_wp_error($resp) ) $this->redirect_with_status('err');

            $body_raw = wp_remote_retrieve_body( $resp );
            $body = json_decode( $body_raw, true );
            $json_error = json_last_error();
            $raw_snippet = is_string( $body_raw ) ? substr( $body_raw, 0, 200 ) : '[non-string response]';

            if ( JSON_ERROR_NONE !== $json_error ) {
                error_log( 'PCV_Abruzzo_Plugin: reCAPTCHA JSON decode error - ' . json_last_error_msg() . '. Raw response: ' . $raw_snippet );
                $this->redirect_with_status('err');
            }

            if ( ! is_array( $body ) || ! array_key_exists( 'success', $body ) ) {
                error_log( 'PCV_Abruzzo_Plugin: Unexpected reCAPTCHA response structure. Raw response: ' . $raw_snippet );
                $this->redirect_with_status('err');
            }

            if ( empty( $body['success'] ) ) {
                $this->redirect_with_status('err');
            }
        }

        $nome       = $this->sanitize_name( wp_unslash( $_POST['pcv_nome'] ?? '' ) );
        $cognome    = $this->sanitize_name( wp_unslash( $_POST['pcv_cognome'] ?? '' ) );
        $provincia  = strtoupper( trim( wp_unslash( $_POST['pcv_provincia'] ?? '' ) ) );
        $comune     = $this->sanitize_text( wp_unslash( $_POST['pcv_comune'] ?? '' ) );
        $email      = sanitize_email( wp_unslash( $_POST['pcv_email'] ?? '' ) );
        $telefono   = $this->sanitize_phone( wp_unslash( $_POST['pcv_telefono'] ?? '' ) );
        $privacy    = isset($_POST['pcv_privacy']) ? 1 : 0;
        $partecipa_raw = isset($_POST['pcv_partecipa']) ? wp_unslash( $_POST['pcv_partecipa'] ) : null;
        $dorme_raw  = isset($_POST['pcv_dorme']) ? wp_unslash( $_POST['pcv_dorme'] ) : null;
        $mangia_raw = isset($_POST['pcv_mangia']) ? wp_unslash( $_POST['pcv_mangia'] ) : null;

        if ( $partecipa_raw !== null && (string) $partecipa_raw !== '1' ) {
            $this->redirect_with_status('err');
        }

        if ( $dorme_raw !== null && (string) $dorme_raw !== '1' ) {
            $this->redirect_with_status('err');
        }

        if ( $mangia_raw !== null && (string) $mangia_raw !== '1' ) {
            $this->redirect_with_status('err');
        }

        $partecipa = $partecipa_raw === '1' ? 1 : 0;
        $dorme  = $dorme_raw === '1' ? 1 : 0;
        $mangia = $mangia_raw === '1' ? 1 : 0;

        $now = current_time( 'mysql' );
        $ip_address = $this->get_ip();
        $user_agent_raw = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
        $user_agent = $user_agent_raw !== '' ? mb_substr( sanitize_text_field( $user_agent_raw ), 0, 255 ) : '';
        $category = 'Volontari';

        // Validazioni: provincia e comune devono appartenere a liste Abruzzo
        if ( !array_key_exists($provincia, $this->province) ) $this->redirect_with_status('err');
        $validComune = in_array( $comune, $this->comuni[$provincia] ?? [], true );
        if ( ! $validComune ) $this->redirect_with_status('err');

        if ( ! $nome || ! $cognome || ! is_email($email) || ! $telefono || ! $privacy ) {
            $this->redirect_with_status('err');
        }

        global $wpdb;
        $table = $this->table_name();
        $inserted = $wpdb->insert(
            $table,
            [
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
            ],
            [ '%s','%s','%s','%s','%s','%s','%s','%s','%d','%d','%d','%d','%s','%s' ]
        );

        if ( $inserted ) {
            $record = [
                'id'         => (int) $wpdb->insert_id,
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

            $this->maybe_send_notification_email( $record );

            /**
             * Viene eseguito dopo la registrazione di un volontario.
             *
             * @param array $record Dati del volontario salvato.
             */
            do_action( 'pcv_volunteer_registered', $record );
        }

        $this->redirect_with_status( $inserted ? 'ok' : 'err' );
    }

    private function redirect_with_status( $status ) {
        $url = add_query_arg( 'pcv_status', $status, wp_get_referer() ?: home_url() );
        wp_safe_redirect( $url ); exit;
    }

    private function sanitize_name( $v ) { $v = trim( wp_strip_all_tags( $v ) ); return $v ? mb_substr($v, 0, 100) : ''; }
    private function sanitize_text( $v ) { $v = trim( wp_strip_all_tags( $v ) ); return $v ? mb_substr($v, 0, 150) : ''; }
    private function sanitize_phone( $v ) { $v = preg_replace('/[^0-9+ ]+/', '', $v); return $v ? mb_substr($v, 0, 50) : ''; }
    private function get_ip() {
        foreach (['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) { $ip = explode(',', $_SERVER[$key])[0]; return sanitize_text_field($ip); }
        } return '';
    }

    /* ---------------- Admin: menu + table + settings reCAPTCHA ---------------- */
    public function register_admin_menu() {
        add_menu_page(
            __( 'Volontari Abruzzo', self::TEXT_DOMAIN ),
            __( 'Volontari Abruzzo', self::TEXT_DOMAIN ),
            'manage_options',
            self::MENU_SLUG,
            [ $this, 'render_admin_page' ],
            'dashicons-groups',
            26
        );
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Impostazioni reCAPTCHA', self::TEXT_DOMAIN ),
            __( 'Impostazioni', self::TEXT_DOMAIN ),
            'manage_options',
            self::MENU_SLUG.'-settings',
            [ $this, 'render_settings_page' ]
        );
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Importazione volontari', self::TEXT_DOMAIN ),
            __( 'Importa', self::TEXT_DOMAIN ),
            'manage_options',
            self::MENU_SLUG.'-import',
            [ $this, 'render_import_page' ]
        );
    }

    public function admin_assets($hook) {
        if ( strpos($hook, self::MENU_SLUG) === false ) return;
        $css = ".pcv-topbar{display:flex;gap:10px;align-items:center;margin:12px 0}.pcv-topbar form{display:flex;gap:8px;align-items:center}.wrap .tablenav{overflow:visible}.pcv-topbar, .pcv-topbar form{flex-wrap:wrap}.pcv-topbar select{min-width:180px}";
        wp_register_style('pcv-admin-inline', false);
        wp_enqueue_style('pcv-admin-inline');
        wp_add_inline_style('pcv-admin-inline', $css);

        $selected_prov = isset($_GET['f_prov']) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['f_prov'] ) ) ) : '';
        if ( ! array_key_exists( $selected_prov, $this->province ) ) {
            $selected_prov = '';
        }
        $selected_comune = isset($_GET['f_comune']) ? sanitize_text_field( wp_unslash( $_GET['f_comune'] ) ) : '';

        $all_comuni = $this->get_all_comuni();
        if ( $selected_comune !== '' && ! in_array( $selected_comune, $all_comuni, true ) ) {
            $selected_comune = '';
        }

        wp_register_script( 'pcv-admin', plugins_url( 'assets/js/admin.js', __FILE__ ), [], self::VERSION, true );
        wp_localize_script( 'pcv-admin', 'PCV_ADMIN_DATA', [
            'province'           => $this->province,
            'comuni'             => $this->comuni,
            'allComuni'          => $all_comuni,
            'selectedProvincia'  => $selected_prov,
            'selectedComune'     => $selected_comune,
            'labels'             => [
                'placeholderComune' => __( 'Tutti i comuni', self::TEXT_DOMAIN ),
            ],
            'fallbacks'          => [
                'placeholderComune' => __( 'Tutti i comuni', self::TEXT_DOMAIN ),
            ],
        ] );
        wp_enqueue_script( 'pcv-admin' );
    }

    public function render_admin_page() {
        if ( ! current_user_can('manage_options') ) return;

        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

        printf(
            '<div class="wrap"><h1 class="wp-heading-inline">%s</h1></div>',
            esc_html__( 'Volontari Abruzzo', self::TEXT_DOMAIN )
        );
        $table = new PCV_List_Table( $this );
        $table->prepare_items();
        echo '<form method="post">';
        wp_nonce_field( 'pcv_bulk_action' );
        $table->display();
        echo '</form>';
    }

    public function render_settings_page() {
        if ( ! current_user_can('manage_options') ) return;

        $label_fields = [
            self::OPT_NAME_LABEL => [
                'label'       => __( 'Etichetta campo Nome', self::TEXT_DOMAIN ),
                'description' => __( 'Testo mostrato accanto al campo Nome nel form pubblico.', self::TEXT_DOMAIN ),
                'default'     => self::DEFAULT_NAME_LABEL,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_SURNAME_LABEL => [
                'label'       => __( 'Etichetta campo Cognome', self::TEXT_DOMAIN ),
                'description' => __( 'Testo mostrato accanto al campo Cognome nel form pubblico.', self::TEXT_DOMAIN ),
                'default'     => self::DEFAULT_SURNAME_LABEL,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_PROVINCE_LABEL => [
                'label'       => __( 'Etichetta campo Provincia', self::TEXT_DOMAIN ),
                'description' => __( 'Testo mostrato accanto alla select della Provincia.', self::TEXT_DOMAIN ),
                'default'     => self::DEFAULT_PROVINCE_LABEL,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_PROVINCE_PLACEHOLDER => [
                'label'       => __( 'Testo predefinito select Provincia', self::TEXT_DOMAIN ),
                'description' => __( 'Prima opzione vuota mostrata nelle select della Provincia (form e popup).', self::TEXT_DOMAIN ),
                'default'     => self::DEFAULT_PROVINCE_PLACEHOLDER,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_COMUNE_LABEL => [
                'label'       => __( 'Etichetta campo Comune', self::TEXT_DOMAIN ),
                'description' => __( 'Testo mostrato accanto alla select del Comune.', self::TEXT_DOMAIN ),
                'default'     => self::DEFAULT_COMUNE_LABEL,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_COMUNE_PLACEHOLDER => [
                'label'       => __( 'Testo predefinito select Comune', self::TEXT_DOMAIN ),
                'description' => __( 'Prima opzione vuota mostrata nelle select del Comune (form e popup).', self::TEXT_DOMAIN ),
                'default'     => self::DEFAULT_COMUNE_PLACEHOLDER,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_EMAIL_LABEL => [
                'label'       => __( 'Etichetta campo Email', self::TEXT_DOMAIN ),
                'description' => __( 'Testo mostrato accanto al campo Email.', self::TEXT_DOMAIN ),
                'default'     => self::DEFAULT_EMAIL_LABEL,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_PHONE_LABEL => [
                'label'       => __( 'Etichetta campo Telefono', self::TEXT_DOMAIN ),
                'description' => __( 'Testo mostrato accanto al campo Telefono.', self::TEXT_DOMAIN ),
                'default'     => self::DEFAULT_PHONE_LABEL,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_PARTICIPATION_LABEL => [
                'label'       => __( 'Etichetta partecipazione', self::TEXT_DOMAIN ),
                'description' => __( 'Testo mostrato accanto all’opzione di partecipazione.', self::TEXT_DOMAIN ),
                'default'     => self::DEFAULT_PARTICIPATION_LABEL,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_OVERNIGHT_LABEL => [
                'label'       => __( 'Etichetta pernottamento', self::TEXT_DOMAIN ),
                'description' => __( 'Testo mostrato accanto all’opzione di pernottamento.', self::TEXT_DOMAIN ),
                'default'     => self::DEFAULT_OVERNIGHT_LABEL,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_MEALS_LABEL => [
                'label'       => __( 'Etichetta pasti', self::TEXT_DOMAIN ),
                'description' => __( 'Testo mostrato accanto all’opzione relativa ai pasti.', self::TEXT_DOMAIN ),
                'default'     => self::DEFAULT_MEALS_LABEL,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_PRIVACY_FIELD_LABEL => [
                'label'       => __( 'Etichetta consenso privacy', self::TEXT_DOMAIN ),
                'description' => __( 'Testo mostrato accanto alla casella di consenso privacy. Puoi includere un link usando HTML.', self::TEXT_DOMAIN ),
                'default'     => self::DEFAULT_PRIVACY_FIELD_LABEL,
                'sanitize'    => 'wp_kses_post',
            ],
            self::OPT_OPTIONAL_GROUP_ARIA => [
                'label'       => __( 'Descrizione gruppo opzioni facoltative', self::TEXT_DOMAIN ),
                'description' => __( 'Testo utilizzato per l’attributo aria-label del gruppo di checkbox facoltative.', self::TEXT_DOMAIN ),
                'default'     => self::DEFAULT_OPTIONAL_GROUP_ARIA,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_SUBMIT_LABEL => [
                'label'       => __( 'Testo pulsante invio', self::TEXT_DOMAIN ),
                'description' => __( 'Testo del pulsante di invio del form.', self::TEXT_DOMAIN ),
                'default'     => self::DEFAULT_SUBMIT_LABEL,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_MODAL_ALERT => [
                'label'       => __( 'Messaggio popup Provincia/Comune', self::TEXT_DOMAIN ),
                'description' => __( 'Avviso mostrato nel popup se non vengono selezionati Provincia e Comune.', self::TEXT_DOMAIN ),
                'default'     => self::DEFAULT_MODAL_ALERT,
                'sanitize'    => 'sanitize_text_field',
            ],
        ];

        if ( isset($_POST['pcv_save_keys']) && check_admin_referer('pcv_save_keys_nonce') ) {
            $site_value = isset($_POST['pcv_site_key']) ? sanitize_text_field( wp_unslash( $_POST['pcv_site_key'] ) ) : '';
            $secret_value = isset($_POST['pcv_secret_key']) ? sanitize_text_field( wp_unslash( $_POST['pcv_secret_key'] ) ) : '';
            $privacy_notice_value = isset($_POST['pcv_privacy_notice']) ? wp_kses_post( wp_unslash( $_POST['pcv_privacy_notice'] ) ) : '';
            $notify_enabled_value = isset( $_POST['pcv_notify_enabled'] ) ? '1' : '0';
            $notify_recipients_raw = isset( $_POST['pcv_notify_recipients'] ) ? wp_unslash( $_POST['pcv_notify_recipients'] ) : '';
            $notify_recipients_value = $this->normalize_recipient_list( $notify_recipients_raw );
            $notify_subject_raw = isset( $_POST['pcv_notify_subject'] ) ? wp_unslash( $_POST['pcv_notify_subject'] ) : '';
            $notify_subject_value = sanitize_text_field( $notify_subject_raw );

            update_option(self::OPT_RECAPTCHA_SITE, $site_value);
            update_option(self::OPT_RECAPTCHA_SECRET, $secret_value);
            update_option(self::OPT_PRIVACY_NOTICE, $privacy_notice_value);
            update_option(self::OPT_NOTIFY_ENABLED, $notify_enabled_value);
            update_option(self::OPT_NOTIFY_RECIPIENTS, $notify_recipients_value);
            update_option(self::OPT_NOTIFY_SUBJECT, $notify_subject_value);

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
            echo '<div class="updated notice"><p>' . esc_html__( 'Impostazioni salvate.', self::TEXT_DOMAIN ) . '</p></div>';
        }

        $site = esc_attr( get_option(self::OPT_RECAPTCHA_SITE, '') );
        $secret = esc_attr( get_option(self::OPT_RECAPTCHA_SECRET, '') );
        $privacy_notice = get_option(self::OPT_PRIVACY_NOTICE, '');
        if ( ! $privacy_notice ) {
            $privacy_notice = __( self::DEFAULT_PRIVACY_NOTICE, self::TEXT_DOMAIN );
        }
        $notify_enabled = $this->notifications_enabled();
        $notify_recipients = get_option( self::OPT_NOTIFY_RECIPIENTS, '' );
        if ( ! is_string( $notify_recipients ) ) {
            $notify_recipients = '';
        }
        $notify_recipients = $this->normalize_recipient_list( $notify_recipients );
        $notify_subject = get_option( self::OPT_NOTIFY_SUBJECT, '' );
        if ( ! is_string( $notify_subject ) || $notify_subject === '' ) {
            $notify_subject = __( self::DEFAULT_NOTIFY_SUBJECT, self::TEXT_DOMAIN );
        }

        $label_values = [];
        foreach ( $label_fields as $option_key => $field ) {
            $value = get_option( $option_key, '' );
            if ( ! is_string( $value ) || $value === '' ) {
                $value = $field['default'];
            }
            $label_values[ $option_key ] = $value;
        }

        echo '<div class="wrap"><h1>' . esc_html__( 'Impostazioni modulo Volontari', self::TEXT_DOMAIN ) . '</h1>';
        echo '<form method="post">';
        wp_nonce_field('pcv_save_keys_nonce');
        echo '<table class="form-table">';
        echo '<tr><th scope="row"><label for="pcv_site_key">' . esc_html__( 'Site Key', self::TEXT_DOMAIN ) . '</label></th><td><input type="text" id="pcv_site_key" name="pcv_site_key" value="'.$site.'" class="regular-text"></td></tr>';
        echo '<tr><th scope="row"><label for="pcv_secret_key">' . esc_html__( 'Secret Key', self::TEXT_DOMAIN ) . '</label></th><td><input type="text" id="pcv_secret_key" name="pcv_secret_key" value="'.$secret.'" class="regular-text"></td></tr>';
        echo '<tr><th scope="row">' . esc_html__( 'Notifiche email', self::TEXT_DOMAIN ) . '</th><td><label><input type="checkbox" name="pcv_notify_enabled" value="1" ' . checked( $notify_enabled, true, false ) . '> ' . esc_html__( 'Invia una email di notifica ad ogni nuova iscrizione.', self::TEXT_DOMAIN ) . '</label><p class="description">' . esc_html__( 'Per impostazione predefinita la notifica viene inviata all’email amministratore di WordPress.', self::TEXT_DOMAIN ) . '</p></td></tr>';
        echo '<tr><th scope="row"><label for="pcv_notify_recipients">' . esc_html__( 'Destinatari notifiche', self::TEXT_DOMAIN ) . '</label></th><td><textarea id="pcv_notify_recipients" name="pcv_notify_recipients" rows="4" class="large-text code">' . esc_textarea( $notify_recipients ) . '</textarea><p class="description">' . esc_html__( 'Inserisci uno o più indirizzi email (uno per riga, virgola o punto e virgola). Se lasci vuoto verrà utilizzata l’email amministratore.', self::TEXT_DOMAIN ) . '</p></td></tr>';
        echo '<tr><th scope="row"><label for="pcv_notify_subject">' . esc_html__( 'Oggetto email notifica', self::TEXT_DOMAIN ) . '</label></th><td><input type="text" id="pcv_notify_subject" name="pcv_notify_subject" value="' . esc_attr( $notify_subject ) . '" class="regular-text"><p class="description">' . esc_html__( 'Personalizza l’oggetto delle email inviate ai referenti.', self::TEXT_DOMAIN ) . '</p></td></tr>';
        foreach ( $label_fields as $option_key => $field ) {
            $value = $label_values[ $option_key ];
            $field_id = esc_attr( $option_key );
            $label = esc_html( $field['label'] );
            $description = isset( $field['description'] ) && $field['description'] !== '' ? '<p class="description">'.esc_html( $field['description'] ).'</p>' : '';
            echo '<tr><th scope="row"><label for="'.$field_id.'">'.$label.'</label></th><td><input type="text" id="'.$field_id.'" name="'.$field_id.'" value="'.esc_attr( $value ).'" class="regular-text">'.$description.'</td></tr>';
        }
        echo '<tr><th scope="row"><label for="pcv_privacy_notice">' . esc_html__( 'Informativa Privacy', self::TEXT_DOMAIN ) . '</label></th><td><textarea id="pcv_privacy_notice" name="pcv_privacy_notice" rows="6" class="large-text code">'.esc_textarea($privacy_notice).'</textarea><p class="description">' . esc_html__( 'Inserisci l’informativa privacy completa, includendo il Titolare del trattamento e le eventuali note legali.', self::TEXT_DOMAIN ) . '</p></td></tr>';
        echo '</table>';
        submit_button( __( 'Salva impostazioni', self::TEXT_DOMAIN ), 'primary', 'pcv_save_keys' );
        echo '</form></div>';
    }

    public function render_import_page() {
        if ( ! current_user_can('manage_options') ) return;

        $messages = [];
        $stage = 'upload';
        $mapping_args = [];

        if ( isset( $_POST['pcv_import_submit'] ) || isset( $_POST['pcv_import_confirm'] ) ) {
            $result = $this->handle_import_submission();
            if ( isset( $result['messages'] ) && is_array( $result['messages'] ) ) {
                $messages = array_merge( $messages, $result['messages'] );
            }
            if ( isset( $result['stage'] ) && $result['stage'] === 'map' ) {
                $stage = 'map';
                $mapping_args = $result;
            }
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Importa volontari', self::TEXT_DOMAIN ) . '</h1>';

        foreach ( $messages as $message ) {
            $type = isset( $message['type'] ) && $message['type'] === 'error' ? 'error' : 'updated';
            $text = isset( $message['text'] ) ? $message['text'] : '';
            if ( $text === '' ) {
                continue;
            }
            echo '<div class="notice ' . esc_attr( $type ) . '"><p>' . wp_kses_post( $text ) . '</p></div>';
        }

        if ( $stage === 'map' && ! empty( $mapping_args ) ) {
            $this->render_import_mapping_form( $mapping_args );
        } else {
            echo '<p>' . esc_html__( 'Carica un file CSV o Excel (.xlsx) con i dati dei volontari da importare.', self::TEXT_DOMAIN ) . '</p>';
            echo '<p>' . esc_html__( 'Assicurati che la prima riga contenga le intestazioni (Nome, Cognome, Comune, Provincia, Email, Telefono). I campi Privacy, Partecipa, Pernotta e Pasti sono opzionali.', self::TEXT_DOMAIN ) . '</p>';

            echo '<form method="post" enctype="multipart/form-data">';
            wp_nonce_field( 'pcv_import_nonce' );
            echo '<input type="hidden" name="pcv_import_stage" value="upload">';
            echo '<table class="form-table">';
            echo '<tr>'; // File field row
            echo '<th scope="row"><label for="pcv_import_file">' . esc_html__( 'File da importare', self::TEXT_DOMAIN ) . '</label></th>';
            echo '<td><input type="file" id="pcv_import_file" name="pcv_import_file" accept=".csv, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></td>';
            echo '</tr>';
            echo '</table>';
            submit_button( __( 'Carica e scegli colonne', self::TEXT_DOMAIN ), 'primary', 'pcv_import_submit' );
            echo '</form>';
        }

        echo '</div>';
    }

    private function render_import_mapping_form( array $args ) {
        $headers = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : [];
        $preview_rows = isset( $args['preview_rows'] ) && is_array( $args['preview_rows'] ) ? $args['preview_rows'] : [];
        $token = isset( $args['token'] ) ? sanitize_text_field( $args['token'] ) : '';
        $selected_map = isset( $args['selected_map'] ) && is_array( $args['selected_map'] ) ? $args['selected_map'] : [];

        $field_definitions = $this->get_import_field_definitions();
        $selected_category = isset( $args['selected_category'] ) ? sanitize_text_field( $args['selected_category'] ) : '';

        echo '<p>' . esc_html__( 'Associa le colonne del file ai campi del gestionale. I campi contrassegnati come obbligatori devono essere sempre mappati.', self::TEXT_DOMAIN ) . '</p>';

        echo '<form method="post">';
        wp_nonce_field( 'pcv_import_nonce' );
        echo '<input type="hidden" name="pcv_import_stage" value="map">';
        echo '<input type="hidden" name="pcv_import_token" value="' . esc_attr( $token ) . '">';
        echo '<table class="form-table">';

        echo '<tr>';
        echo '<th scope="row"><label for="pcv_import_category">' . esc_html__( 'Categoria elenco', self::TEXT_DOMAIN ) . ' <span class="description" style="font-weight: normal;">' . esc_html__( '(obbligatorio)', self::TEXT_DOMAIN ) . '</span></label></th>';
        echo '<td>';
        echo '<input type="text" id="pcv_import_category" name="pcv_import_category" class="regular-text" value="' . esc_attr( $selected_category ) . '" required>';
        echo '<p class="description">' . esc_html__( 'Specifica a quale gruppo o contesto appartiene l’elenco importato (es. Sindaci, Volontari 2024).', self::TEXT_DOMAIN ) . '</p>';
        echo '</td>';
        echo '</tr>';

        foreach ( $field_definitions as $field_key => $definition ) {
            $label = isset( $definition['label'] ) ? $definition['label'] : $field_key;
            $required = ! empty( $definition['required'] );
            $description = isset( $definition['description'] ) ? $definition['description'] : '';
            $select_id = 'pcv_import_map_' . $field_key;
            $current_value = '';
            if ( isset( $selected_map[ $field_key ] ) && $selected_map[ $field_key ] !== null && $selected_map[ $field_key ] !== '' ) {
                $current_value = (string) $selected_map[ $field_key ];
            }

            echo '<tr>';
            echo '<th scope="row"><label for="' . esc_attr( $select_id ) . '">' . esc_html( $label );
            if ( $required ) {
                echo ' <span class="description" style="font-weight: normal;">' . esc_html__( '(obbligatorio)', self::TEXT_DOMAIN ) . '</span>';
            }
            echo '</label></th>';
            echo '<td>';
            echo '<select id="' . esc_attr( $select_id ) . '" name="pcv_import_map[' . esc_attr( $field_key ) . ']">';
            echo '<option value="">' . esc_html__( 'Non importare', self::TEXT_DOMAIN ) . '</option>';
            foreach ( $headers as $index => $header_label ) {
                $option_value = (string) $index;
                $selected_attr = selected( $current_value, $option_value, false );
                echo '<option value="' . esc_attr( $option_value ) . '" ' . $selected_attr . '>' . esc_html( $header_label ) . '</option>';
            }
            echo '</select>';
            if ( $description !== '' ) {
                echo '<p class="description">' . esc_html( $description ) . '</p>';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</table>';
        submit_button( __( 'Avvia importazione', self::TEXT_DOMAIN ), 'primary', 'pcv_import_confirm' );

        $cancel_url = menu_page_url( self::MENU_SLUG . '-importa', false );
        if ( $cancel_url ) {
            echo '<a href="' . esc_url( $cancel_url ) . '" class="button button-secondary" style="margin-left:10px;">' . esc_html__( 'Annulla', self::TEXT_DOMAIN ) . '</a>';
        }

        echo '</form>';

        if ( ! empty( $headers ) && ! empty( $preview_rows ) ) {
            echo '<h2>' . esc_html__( 'Anteprima dati', self::TEXT_DOMAIN ) . '</h2>';
            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            foreach ( $headers as $header_label ) {
                echo '<th>' . esc_html( $header_label ) . '</th>';
            }
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ( $preview_rows as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }
                echo '<tr>';
                foreach ( array_keys( $headers ) as $index ) {
                    $value = isset( $row[ $index ] ) ? $row[ $index ] : '';
                    echo '<td>' . esc_html( $value ) . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        }
    }

    private function get_import_field_definitions() {
        return [
            'nome' => [
                'label'     => __( 'Nome', self::TEXT_DOMAIN ),
                'required'  => true,
            ],
            'cognome' => [
                'label'     => __( 'Cognome', self::TEXT_DOMAIN ),
                'required'  => true,
            ],
            'comune' => [
                'label'     => __( 'Comune', self::TEXT_DOMAIN ),
                'required'  => true,
            ],
            'provincia' => [
                'label'     => __( 'Provincia', self::TEXT_DOMAIN ),
                'required'  => true,
            ],
            'email' => [
                'label'     => __( 'Email', self::TEXT_DOMAIN ),
                'required'  => true,
            ],
            'telefono' => [
                'label'     => __( 'Telefono', self::TEXT_DOMAIN ),
                'required'  => true,
            ],
            'privacy' => [
                'label'       => __( 'Consenso privacy', self::TEXT_DOMAIN ),
                'required'    => false,
                'description' => __( 'Valori ammessi: 1/0, si/no, true/false.', self::TEXT_DOMAIN ),
            ],
            'partecipa' => [
                'label'       => __( 'Partecipa', self::TEXT_DOMAIN ),
                'required'    => false,
                'description' => __( 'Indica la partecipazione all’evento (1/0, si/no, true/false).', self::TEXT_DOMAIN ),
            ],
            'dorme' => [
                'label'       => __( 'Pernotta', self::TEXT_DOMAIN ),
                'required'    => false,
                'description' => __( 'Specifica se il volontario pernotta (1/0, si/no, true/false).', self::TEXT_DOMAIN ),
            ],
            'mangia' => [
                'label'       => __( 'Pasti', self::TEXT_DOMAIN ),
                'required'    => false,
                'description' => __( 'Indica se il volontario consumerà i pasti (1/0, si/no, true/false).', self::TEXT_DOMAIN ),
            ],
            'created_at' => [
                'label'       => __( 'Data iscrizione', self::TEXT_DOMAIN ),
                'required'    => false,
                'description' => __( 'Formato consigliato: YYYY-MM-DD HH:MM:SS.', self::TEXT_DOMAIN ),
            ],
            'ip' => [
                'label'       => __( 'Indirizzo IP', self::TEXT_DOMAIN ),
                'required'    => false,
            ],
            'user_agent' => [
                'label'       => __( 'User Agent', self::TEXT_DOMAIN ),
                'required'    => false,
            ],
        ];
    }

    private function handle_import_submission() {
        $messages = [];

        if ( ! check_admin_referer( 'pcv_import_nonce' ) ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html__( 'Nonce non valido. Riprova.', self::TEXT_DOMAIN ),
            ];

            return [ 'messages' => $messages ];
        }

        $stage = isset( $_POST['pcv_import_stage'] ) ? sanitize_text_field( wp_unslash( $_POST['pcv_import_stage'] ) ) : 'upload';

        if ( $stage === 'map' ) {
            return $this->process_import_mapping_stage( $messages );
        }

        return $this->process_import_upload_stage( $messages );
    }

    private function process_import_upload_stage( array $messages ) {
        if ( empty( $_FILES['pcv_import_file'] ) || ! is_array( $_FILES['pcv_import_file'] ) ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html__( 'Nessun file selezionato.', self::TEXT_DOMAIN ),
            ];

            return [ 'messages' => $messages ];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $file = $_FILES['pcv_import_file'];
        $overrides = [
            'test_form' => false,
            'mimes'     => [
                'csv'  => 'text/csv',
                'txt'  => 'text/plain',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'xls'  => 'application/vnd.ms-excel',
            ],
        ];

        $uploaded = wp_handle_upload( $file, $overrides );

        if ( isset( $uploaded['error'] ) ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html( $uploaded['error'] ),
            ];

            return [ 'messages' => $messages ];
        }

        $path = $uploaded['file'];

        $dataset = $this->parse_import_file( $path );

        if ( file_exists( $path ) ) {
            unlink( $path );
        }

        if ( is_wp_error( $dataset ) ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html( $dataset->get_error_message() ),
            ];

            return [ 'messages' => $messages ];
        }

        if ( empty( $dataset ) || empty( $dataset['rows'] ) ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html__( 'Il file non contiene dati da importare.', self::TEXT_DOMAIN ),
            ];

            return [ 'messages' => $messages ];
        }

        $token = $this->store_import_dataset( $dataset );
        if ( is_wp_error( $token ) ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html( $token->get_error_message() ),
            ];

            return [ 'messages' => $messages ];
        }

        $preview_rows = array_slice( $dataset['rows'], 0, 5 );
        $default_map = $this->build_default_import_map( $dataset );

        $messages[] = [
            'type' => 'updated',
            'text' => esc_html__( 'File caricato correttamente. Associa le colonne e conferma per avviare l’importazione.', self::TEXT_DOMAIN ),
        ];

        return [
            'messages'           => $messages,
            'stage'              => 'map',
            'headers'            => $dataset['headers'],
            'preview_rows'       => $preview_rows,
            'selected_map'       => $default_map,
            'selected_category'  => '',
            'token'              => $token,
        ];
    }

    private function process_import_mapping_stage( array $messages ) {
        $token = isset( $_POST['pcv_import_token'] ) ? sanitize_text_field( wp_unslash( $_POST['pcv_import_token'] ) ) : '';

        if ( $token === '' ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html__( 'Sessione di importazione non valida o scaduta. Carica nuovamente il file.', self::TEXT_DOMAIN ),
            ];

            return [ 'messages' => $messages ];
        }

        $dataset = $this->get_import_dataset( $token );

        if ( empty( $dataset ) || ! is_array( $dataset ) ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html__( 'Sessione di importazione non trovata. Carica nuovamente il file.', self::TEXT_DOMAIN ),
            ];

            return [ 'messages' => $messages ];
        }

        $headers = isset( $dataset['headers'] ) && is_array( $dataset['headers'] ) ? $dataset['headers'] : [];
        $preview_rows = array_slice( isset( $dataset['rows'] ) && is_array( $dataset['rows'] ) ? $dataset['rows'] : [], 0, 5 );

        $raw_map = isset( $_POST['pcv_import_map'] ) && is_array( $_POST['pcv_import_map'] ) ? wp_unslash( $_POST['pcv_import_map'] ) : [];
        $sanitized_map = $this->sanitize_import_map( $raw_map, count( $headers ) );
        $raw_category = isset( $_POST['pcv_import_category'] ) ? wp_unslash( $_POST['pcv_import_category'] ) : '';
        $category = $this->sanitize_text( $raw_category );

        $missing_required = [];
        foreach ( self::IMPORT_EXPECTED_COLUMNS as $required_field ) {
            if ( ! isset( $sanitized_map[ $required_field ] ) || $sanitized_map[ $required_field ] === null ) {
                $missing_required[] = $required_field;
            }
        }

        if ( ! empty( $missing_required ) ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html__( 'Completa la mappatura di tutti i campi obbligatori prima di procedere.', self::TEXT_DOMAIN ),
            ];

            return [
                'messages'           => $messages,
                'stage'              => 'map',
                'headers'            => $headers,
                'preview_rows'       => $preview_rows,
                'selected_map'       => $sanitized_map,
                'selected_category'  => $category,
                'token'              => $token,
            ];
        }

        if ( $category === '' ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html__( 'Indica una categoria per l’elenco che stai importando.', self::TEXT_DOMAIN ),
            ];

            return [
                'messages'           => $messages,
                'stage'              => 'map',
                'headers'            => $headers,
                'preview_rows'       => $preview_rows,
                'selected_map'       => $sanitized_map,
                'selected_category'  => $category,
                'token'              => $token,
            ];
        }

        $rows = $this->apply_import_mapping( $dataset, $sanitized_map );
        $result = $this->import_rows( $rows, $category );

        $this->delete_import_dataset( $token );

        if ( $result['imported'] > 0 ) {
            $messages[] = [
                'type' => 'success',
                'text' => esc_html( sprintf( __( 'Importazione completata: %1$d righe inserite, %2$d righe saltate.', self::TEXT_DOMAIN ), $result['imported'], $result['skipped'] ) ),
            ];
        }

        if ( ! empty( $result['errors'] ) ) {
            $error_list = '<ul>';
            foreach ( $result['errors'] as $error ) {
                $error_list .= '<li>' . esc_html( $error ) . '</li>';
            }
            $error_list .= '</ul>';
            $messages[] = [
                'type' => 'error',
                'text' => __( 'Alcune righe non sono state importate:', self::TEXT_DOMAIN ) . $error_list,
            ];
        }

        if ( empty( $messages ) ) {
            $messages[] = [
                'type' => 'error',
                'text' => esc_html__( 'Si è verificato un errore durante l\'importazione.', self::TEXT_DOMAIN ),
            ];
        }

        return [ 'messages' => $messages ];
    }

    private function store_import_dataset( array $dataset ) {
        $token = wp_generate_password( 20, false, false );
        $key = 'pcv_import_' . $token;

        $stored = set_transient( $key, $dataset, 30 * MINUTE_IN_SECONDS );

        if ( ! $stored ) {
            return new WP_Error( 'pcv_import_store_failed', __( 'Impossibile inizializzare la sessione di importazione. Riprova.', self::TEXT_DOMAIN ) );
        }

        return $token;
    }

    private function get_import_dataset( $token ) {
        if ( $token === '' ) {
            return null;
        }

        $dataset = get_transient( 'pcv_import_' . $token );

        if ( ! is_array( $dataset ) || empty( $dataset['headers'] ) || ! isset( $dataset['rows'] ) ) {
            return null;
        }

        return $dataset;
    }

    private function delete_import_dataset( $token ) {
        if ( $token === '' ) {
            return;
        }

        delete_transient( 'pcv_import_' . $token );
    }

    private function build_default_import_map( array $dataset ) {
        $field_definitions = $this->get_import_field_definitions();
        $fields = array_keys( $field_definitions );
        $map = array_fill_keys( $fields, null );

        $normalized_headers = isset( $dataset['normalized_headers'] ) && is_array( $dataset['normalized_headers'] ) ? $dataset['normalized_headers'] : [];

        foreach ( $normalized_headers as $index => $normalized ) {
            if ( $normalized === '' ) {
                continue;
            }

            if ( array_key_exists( $normalized, $map ) && $map[ $normalized ] === null ) {
                $map[ $normalized ] = $index;
            }
        }

        return $map;
    }

    private function sanitize_import_map( array $raw_map, $headers_count ) {
        $field_definitions = $this->get_import_field_definitions();
        $clean_map = array_fill_keys( array_keys( $field_definitions ), null );

        foreach ( $raw_map as $field => $value ) {
            if ( ! array_key_exists( $field, $clean_map ) ) {
                continue;
            }

            if ( is_string( $value ) ) {
                $value = trim( $value );
            }

            if ( $value === '' || $value === null ) {
                $clean_map[ $field ] = null;
                continue;
            }

            if ( is_numeric( $value ) ) {
                $index = (int) $value;
                if ( $index >= 0 && $index < $headers_count ) {
                    $clean_map[ $field ] = $index;
                }
            }
        }

        return $clean_map;
    }

    private function apply_import_mapping( array $dataset, array $map ) {
        $rows = [];
        $data_rows = isset( $dataset['rows'] ) && is_array( $dataset['rows'] ) ? $dataset['rows'] : [];
        $field_definitions = $this->get_import_field_definitions();

        foreach ( $data_rows as $row_values ) {
            if ( ! is_array( $row_values ) ) {
                continue;
            }

            $mapped = [];
            foreach ( $map as $field => $index ) {
                if ( $index === null || ! isset( $field_definitions[ $field ] ) ) {
                    continue;
                }

                $value = isset( $row_values[ $index ] ) ? $row_values[ $index ] : '';
                $mapped[ $field ] = $value;
            }

            if ( ! empty( $mapped ) ) {
                $rows[] = $mapped;
            }
        }

        return $rows;
    }

    private function parse_import_file( $path ) {
        $extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

        if ( $extension === 'csv' || $extension === 'txt' ) {
            return $this->parse_import_csv( $path );
        }

        if ( $extension === 'xlsx' ) {
            return $this->parse_import_xlsx( $path );
        }

        if ( $extension === 'xls' ) {
            return new WP_Error( 'pcv_import_invalid_extension', __( 'I file Excel in formato .xls non sono supportati. Converti il file in formato .xlsx e riprova.', self::TEXT_DOMAIN ) );
        }

        return new WP_Error( 'pcv_import_invalid_extension', __( 'Formato file non supportato. Carica un file CSV o Excel (.xlsx).', self::TEXT_DOMAIN ) );
    }

    private function parse_import_csv( $path ) {
        $handle = fopen( $path, 'r' );

        if ( ! $handle ) {
            return new WP_Error( 'pcv_import_csv_open', __( 'Impossibile leggere il file CSV.', self::TEXT_DOMAIN ) );
        }

        $first_line = fgets( $handle );
        if ( $first_line === false ) {
            fclose( $handle );

            return [
                'headers'             => [],
                'normalized_headers' => [],
                'rows'                => [],
            ];
        }

        $delimiters = [ ';', ',', "\t" ];
        $delimiter = ';';
        $max_count = 0;
        foreach ( $delimiters as $candidate ) {
            $count = substr_count( $first_line, $candidate );
            if ( $count > $max_count ) {
                $max_count = $count;
                $delimiter = $candidate;
            }
        }

        rewind( $handle );

        $headers = fgetcsv( $handle, 0, $delimiter );
        if ( ! is_array( $headers ) ) {
            fclose( $handle );

            return new WP_Error( 'pcv_import_csv_header', __( 'Intestazioni CSV non valide.', self::TEXT_DOMAIN ) );
        }

        $rows = [];
        while ( ( $data = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
            if ( $data === null ) {
                continue;
            }

            $rows[] = $data;
        }

        fclose( $handle );

        return $this->prepare_import_dataset( $headers, $rows );
    }

    private function parse_import_xlsx( $path ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 'pcv_import_zip_missing', __( 'Il server non supporta l\'apertura dei file Excel (.xlsx).', self::TEXT_DOMAIN ) );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $path ) !== true ) {
            return new WP_Error( 'pcv_import_xlsx_open', __( 'Impossibile aprire il file Excel.', self::TEXT_DOMAIN ) );
        }

        $sheet_path = 'xl/worksheets/sheet1.xml';
        $workbook = $zip->getFromName( 'xl/workbook.xml' );
        $rels = $zip->getFromName( 'xl/_rels/workbook.xml.rels' );

        if ( $workbook && $rels ) {
            $sheet_path = $this->get_first_sheet_path_from_workbook( $workbook, $rels );
        }

        $sheet_xml = $zip->getFromName( $sheet_path );
        if ( ! $sheet_xml ) {
            $zip->close();

            return new WP_Error( 'pcv_import_xlsx_sheet', __( 'Impossibile trovare il foglio di lavoro nel file Excel.', self::TEXT_DOMAIN ) );
        }

        $shared_strings = [];
        $shared_xml = $zip->getFromName( 'xl/sharedStrings.xml' );
        if ( $shared_xml ) {
            $shared = simplexml_load_string( $shared_xml );
            if ( $shared ) {
                foreach ( $shared->si as $si ) {
                    if ( isset( $si->t ) ) {
                        $shared_strings[] = (string) $si->t;
                    } elseif ( isset( $si->r ) ) {
                        $text = '';
                        foreach ( $si->r as $run ) {
                            $text .= (string) $run->t;
                        }
                        $shared_strings[] = $text;
                    } else {
                        $shared_strings[] = '';
                    }
                }
            }
        }

        $zip->close();

        $sheet = simplexml_load_string( $sheet_xml );
        if ( ! $sheet || ! isset( $sheet->sheetData ) ) {
            return new WP_Error( 'pcv_import_xlsx_parse', __( 'Formato Excel non valido.', self::TEXT_DOMAIN ) );
        }

        $rows = [];
        foreach ( $sheet->sheetData->row as $row ) {
            $row_values = [];
            foreach ( $row->c as $cell ) {
                $ref = isset( $cell['r'] ) ? (string) $cell['r'] : '';
                $column_index = $ref !== '' ? $this->column_reference_to_index( $ref ) : count( $row_values );
                if ( $column_index === null ) {
                    $column_index = count( $row_values );
                }

                $type = isset( $cell['t'] ) ? (string) $cell['t'] : '';
                $value = '';
                if ( $type === 's' ) {
                    $idx = isset( $cell->v ) ? (int) $cell->v : -1;
                    $value = $idx >= 0 && isset( $shared_strings[ $idx ] ) ? $shared_strings[ $idx ] : '';
                } elseif ( $type === 'inlineStr' ) {
                    $value = isset( $cell->is->t ) ? (string) $cell->is->t : '';
                } else {
                    $value = isset( $cell->v ) ? (string) $cell->v : '';
                }

                $row_values[ $column_index ] = trim( $value );
            }

            if ( ! empty( $row_values ) ) {
                ksort( $row_values );
                $rows[] = array_values( $row_values );
            }
        }

        if ( empty( $rows ) ) {
            return [
                'headers'             => [],
                'normalized_headers' => [],
                'rows'                => [],
            ];
        }

        $headers = array_shift( $rows );
        return $this->prepare_import_dataset( $headers, $rows );
    }

    private function prepare_import_dataset( array $headers, array $raw_rows ) {
        $clean_headers = [];
        $normalized_headers = [];

        foreach ( $headers as $index => $header ) {
            $original_header = (string) $header;
            if ( $index === 0 ) {
                $original_header = preg_replace( '/^\xEF\xBB\xBF/', '', $original_header );
            }

            $label = trim( $original_header );
            if ( $label === '' ) {
                $label = sprintf( __( 'Colonna %d', self::TEXT_DOMAIN ), $index + 1 );
            }

            $clean_headers[ $index ] = $label;
            $normalized_headers[ $index ] = $this->normalize_import_header( $original_header );
        }

        $rows = [];
        foreach ( $raw_rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $values = [];
            $has_value = false;
            foreach ( $clean_headers as $index => $label ) {
                $value = isset( $row[ $index ] ) ? trim( (string) $row[ $index ] ) : '';
                if ( $value !== '' ) {
                    $has_value = true;
                }
                $values[ $index ] = $value;
            }

            if ( $has_value ) {
                $rows[] = $values;
            }
        }

        return [
            'headers'             => array_values( $clean_headers ),
            'normalized_headers' => array_values( $normalized_headers ),
            'rows'                => $rows,
        ];
    }

    private function get_first_sheet_path_from_workbook( $workbook_xml, $rels_xml ) {
        $sheet_path = 'xl/worksheets/sheet1.xml';

        $workbook = simplexml_load_string( $workbook_xml );
        $rels = simplexml_load_string( $rels_xml );

        if ( ! $workbook || ! isset( $workbook->sheets->sheet ) || ! $rels ) {
            return $sheet_path;
        }

        $relationships = [];
        foreach ( $rels->Relationship as $rel ) {
            $id = isset( $rel['Id'] ) ? (string) $rel['Id'] : '';
            $target = isset( $rel['Target'] ) ? (string) $rel['Target'] : '';
            if ( $id && $target ) {
                $relationships[ $id ] = $target;
            }
        }

        foreach ( $workbook->sheets->sheet as $sheet ) {
            $r_id = isset( $sheet['r:id'] ) ? (string) $sheet['r:id'] : '';
            if ( $r_id && isset( $relationships[ $r_id ] ) ) {
                $target = $relationships[ $r_id ];
                if ( strpos( $target, '/' ) === 0 ) {
                    $target = ltrim( $target, '/' );
                }

                if ( strpos( $target, 'xl/' ) === 0 ) {
                    return $target;
                }

                return 'xl/' . $target;
            }

            break;
        }

        return $sheet_path;
    }

    private function column_reference_to_index( $reference ) {
        if ( ! preg_match( '/^([A-Z]+)[0-9]+$/i', $reference, $matches ) ) {
            return null;
        }

        $letters = strtoupper( $matches[1] );
        $length = strlen( $letters );
        $index = 0;
        for ( $i = 0; $i < $length; $i++ ) {
            $index = $index * 26 + ( ord( $letters[ $i ] ) - 64 );
        }

        return $index - 1;
    }

    private function normalize_import_header( $header ) {
        $header = strtolower( trim( (string) $header ) );
        $header = str_replace( [ 'à', 'è', 'é', 'ì', 'ò', 'ù' ], [ 'a', 'e', 'e', 'i', 'o', 'u' ], $header );
        $header = preg_replace( '/[^a-z0-9]+/', '_', $header );
        $header = trim( $header, '_' );

        $map = [
            'nome'       => 'nome',
            'cognome'    => 'cognome',
            'comune'     => 'comune',
            'provincia'  => 'provincia',
            'email'      => 'email',
            'telefono'   => 'telefono',
            'privacy'    => 'privacy',
            'partecipa'  => 'partecipa',
            'pasti'      => 'mangia',
            'mangia'     => 'mangia',
            'pernotta'   => 'dorme',
            'dorme'      => 'dorme',
            'created_at' => 'created_at',
            'data'       => 'created_at',
            'ip'         => 'ip',
            'user_agent' => 'user_agent',
        ];

        if ( isset( $map[ $header ] ) ) {
            return $map[ $header ];
        }

        return '';
    }

    private function import_rows( array $rows, $category = '' ) {
        global $wpdb;

        $table = $this->table_name();
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $row_index = 1;
        $category = $this->sanitize_text( $category );

        foreach ( $rows as $row ) {
            $row_index++;

            $nome      = $this->sanitize_name( $row['nome'] ?? '' );
            $cognome   = $this->sanitize_name( $row['cognome'] ?? '' );
            $comune    = $this->sanitize_text( $row['comune'] ?? '' );
            $provincia = $this->normalize_province_input( $row['provincia'] ?? '' );
            $email_raw = isset( $row['email'] ) ? sanitize_email( $row['email'] ) : '';
            $telefono  = $this->sanitize_phone( $row['telefono'] ?? '' );

            if ( $nome === '' || $cognome === '' || $comune === '' || $provincia === '' || $email_raw === '' || $telefono === '' ) {
                $errors[] = sprintf( __( 'Riga %d: dati obbligatori mancanti o non validi.', self::TEXT_DOMAIN ), $row_index );
                $skipped++;
                continue;
            }

            if ( ! is_email( $email_raw ) ) {
                $errors[] = sprintf( __( 'Riga %d: indirizzo email non valido.', self::TEXT_DOMAIN ), $row_index );
                $skipped++;
                continue;
            }

            $privacy   = $this->normalize_boolean_input( $row['privacy'] ?? '1' );
            $partecipa = $this->normalize_boolean_input( $row['partecipa'] ?? '0' );
            $dorme     = $this->normalize_boolean_input( $row['dorme'] ?? '0' );
            $mangia    = $this->normalize_boolean_input( $row['mangia'] ?? ( $row['pasti'] ?? '0' ) );

            $created_at = $this->normalize_datetime_input( $row['created_at'] ?? ( $row['data'] ?? '' ) );
            $ip         = isset( $row['ip'] ) ? sanitize_text_field( $row['ip'] ) : '';
            $user_agent = isset( $row['user_agent'] ) ? wp_strip_all_tags( $row['user_agent'] ) : '';

            $inserted = $wpdb->insert(
                $table,
                [
                    'created_at' => $created_at,
                    'nome'       => $nome,
                    'cognome'    => $cognome,
                    'comune'     => $comune,
                    'provincia'  => $provincia,
                    'email'      => $email_raw,
                    'telefono'   => $telefono,
                    'categoria'  => $category,
                    'privacy'    => $privacy,
                    'partecipa'  => $partecipa,
                    'dorme'      => $dorme,
                    'mangia'     => $mangia,
                    'ip'         => $ip,
                    'user_agent' => $user_agent,
                ],
                [ '%s','%s','%s','%s','%s','%s','%s','%s','%d','%d','%d','%d','%s','%s' ]
            );

            if ( $inserted ) {
                $imported++;
            } else {
                $errors[] = sprintf( __( 'Riga %d: errore durante il salvataggio nel database.', self::TEXT_DOMAIN ), $row_index );
                $skipped++;
            }
        }

        return [
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ];
    }

    private function normalize_boolean_input( $value ) {
        if ( is_string( $value ) ) {
            $value = strtolower( trim( $value ) );
        }

        if ( is_numeric( $value ) ) {
            return (int) ( (int) $value ? 1 : 0 );
        }

        $truthy = [ '1', 'true', 'si', 'sì', 'yes', 'y', 'on', 'x' ];
        $falsy  = [ '0', 'false', 'no', 'n', '' ];

        if ( is_string( $value ) ) {
            if ( in_array( $value, $truthy, true ) ) {
                return 1;
            }

            if ( in_array( $value, $falsy, true ) ) {
                return 0;
            }
        }

        return empty( $value ) ? 0 : 1;
    }

    private function normalize_province_input( $value ) {
        $value = strtoupper( trim( (string) $value ) );

        if ( $value === '' ) {
            return '';
        }

        if ( isset( $this->province[ $value ] ) ) {
            return $value;
        }

        foreach ( $this->province as $code => $label ) {
            if ( strcasecmp( $label, $value ) === 0 ) {
                return $code;
            }
        }

        return '';
    }

    private function normalize_datetime_input( $value ) {
        $value = trim( (string) $value );

        if ( $value === '' ) {
            return current_time( 'mysql' );
        }

        $timestamp = strtotime( $value );
        if ( $timestamp === false ) {
            return current_time( 'mysql' );
        }

        $offset = get_option( 'gmt_offset', 0 );
        $timestamp += $offset * HOUR_IN_SECONDS;

        return gmdate( 'Y-m-d H:i:s', $timestamp );
    }

    /* ---------------- Export CSV ---------------- */
    public function maybe_export_csv() {
        if ( ! is_admin() ) return;
        if ( ! isset($_GET['page']) || $_GET['page'] !== self::MENU_SLUG ) return;
        if ( ! isset($_GET['pcv_export']) || $_GET['pcv_export'] !== 'csv' ) return;
        if ( ! current_user_can('manage_options') ) return;
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'pcv_export' ) ) {
            wp_die( esc_html__( 'Nonce non valido', self::TEXT_DOMAIN ) );
        }

        global $wpdb;
        $table = $this->table_name();

        $where = 'WHERE 1=1'; $params = [];
        if ( isset($_GET['f_comune']) && $_GET['f_comune'] !== '' ) { $where .= " AND comune LIKE %s"; $params[] = '%'.$wpdb->esc_like(sanitize_text_field($_GET['f_comune'])).'%'; }
        if ( isset($_GET['f_prov']) && $_GET['f_prov'] !== '' ) { $where .= " AND provincia LIKE %s"; $params[] = '%'.$wpdb->esc_like(sanitize_text_field($_GET['f_prov'])).'%'; }
        if ( isset($_GET['s']) && $_GET['s'] !== '' ) {
            $like = '%'.$wpdb->esc_like(sanitize_text_field($_GET['s'])).'%';
            $where .= " AND ( nome LIKE %s OR cognome LIKE %s OR email LIKE %s OR telefono LIKE %s )";
            array_push($params, $like, $like, $like, $like);
        }

        $sql = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC";
        $rows = empty( $params )
            ? $wpdb->get_results( $sql, ARRAY_A )
            : $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=volontari_abruzzo_'.date('Ymd_His').'.csv');

        $out = fopen('php://output', 'w');
        $headers = [
            __( 'ID', self::TEXT_DOMAIN ),
            __( 'Data', self::TEXT_DOMAIN ),
            __( 'Nome', self::TEXT_DOMAIN ),
            __( 'Cognome', self::TEXT_DOMAIN ),
            __( 'Comune', self::TEXT_DOMAIN ),
            __( 'Provincia', self::TEXT_DOMAIN ),
            __( 'Email', self::TEXT_DOMAIN ),
            __( 'Telefono', self::TEXT_DOMAIN ),
            __( 'Categoria', self::TEXT_DOMAIN ),
            __( 'Privacy', self::TEXT_DOMAIN ),
            __( 'Partecipa', self::TEXT_DOMAIN ),
            __( 'Pernotta', self::TEXT_DOMAIN ),
            __( 'Pasti', self::TEXT_DOMAIN ),
            __( 'IP', self::TEXT_DOMAIN ),
            __( 'User Agent', self::TEXT_DOMAIN ),
        ];
        fputcsv( $out, $headers, ';' );

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'],
                $r['created_at'],
                $this->csv_text_guard( $r['nome'] ),
                $this->csv_text_guard( $r['cognome'] ),
                $this->csv_text_guard( $r['comune'] ),
                $this->csv_text_guard( $r['provincia'] ),
                $this->csv_text_guard( $r['email'] ),
                $this->csv_text_guard( $r['telefono'] ),
                $this->csv_text_guard( isset( $r['categoria'] ) ? $r['categoria'] : '' ),
                $r['privacy'] ? '1' : '0',
                $r['partecipa'] ? '1' : '0',
                ! empty($r['dorme']) ? '1' : '0',
                ! empty($r['mangia']) ? '1' : '0',
                $this->csv_text_guard( $r['ip'] ),
                $this->csv_text_guard( $r['user_agent'] ),
            ], ';');
        }
        fclose($out); exit;
    }
}

/**
 * Custom WP_List_Table implementation for managing volunteers data
 */
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
if ( ! class_exists( 'PCV_List_Table' ) ) {
    class PCV_List_Table extends WP_List_Table {
        private $plugin;
        
        public function __construct( $plugin_instance ) {
            $this->plugin = $plugin_instance;
            parent::__construct([
                'singular' => __( 'volontario', PCV_Abruzzo_Plugin::TEXT_DOMAIN ),
                'plural'   => __( 'volontari', PCV_Abruzzo_Plugin::TEXT_DOMAIN ),
                'ajax'     => false,
            ]);
        }

        public function get_columns() {
            return [
                'cb'         => '<input type="checkbox" />',
                'created_at' => esc_html__( 'Data', PCV_Abruzzo_Plugin::TEXT_DOMAIN ),
                'nome'       => esc_html__( 'Nome', PCV_Abruzzo_Plugin::TEXT_DOMAIN ),
                'cognome'    => esc_html__( 'Cognome', PCV_Abruzzo_Plugin::TEXT_DOMAIN ),
                'comune'     => esc_html__( 'Comune', PCV_Abruzzo_Plugin::TEXT_DOMAIN ),
                'provincia'  => esc_html__( 'Provincia', PCV_Abruzzo_Plugin::TEXT_DOMAIN ),
                'email'      => esc_html__( 'Email', PCV_Abruzzo_Plugin::TEXT_DOMAIN ),
                'telefono'   => esc_html__( 'Telefono', PCV_Abruzzo_Plugin::TEXT_DOMAIN ),
                'categoria'  => esc_html__( 'Categoria', PCV_Abruzzo_Plugin::TEXT_DOMAIN ),
                'privacy'    => esc_html__( 'Privacy', PCV_Abruzzo_Plugin::TEXT_DOMAIN ),
                'partecipa'  => esc_html__( 'Partecipa', PCV_Abruzzo_Plugin::TEXT_DOMAIN ),
                'dorme'      => esc_html__( 'Pernotta', PCV_Abruzzo_Plugin::TEXT_DOMAIN ),
                'mangia'     => esc_html__( 'Pasti', PCV_Abruzzo_Plugin::TEXT_DOMAIN ),
            ];
        }
        
        protected function get_sortable_columns() {
            return ['created_at'=>['created_at',true],'nome'=>['nome',false],'cognome'=>['cognome',false],'comune'=>['comune',false],'provincia'=>['provincia',false],'categoria'=>['categoria',false]];
        }
        
        protected function column_cb($item){ 
            return sprintf('<input type="checkbox" name="id[]" value="%d" />',$item->id); 
        }
        
        protected function column_default($item,$col){
            switch($col){
                case 'created_at': return esc_html(mysql2date('d/m/Y H:i',$item->created_at));
                case 'nome':case 'cognome':case 'comune':case 'provincia':case 'email':case 'telefono':case 'categoria': return esc_html($item->$col);
                case 'privacy':case 'partecipa':case 'dorme':case 'mangia':
                    return $item->$col ? esc_html__( 'Sì', PCV_Abruzzo_Plugin::TEXT_DOMAIN ) : esc_html__( 'No', PCV_Abruzzo_Plugin::TEXT_DOMAIN );
                default: return '';
            }
        }

        public function get_bulk_actions(){
            return ['delete' => esc_html__( 'Elimina', PCV_Abruzzo_Plugin::TEXT_DOMAIN )];
        }

        public function process_bulk_action(){
            if('delete' !== $this->current_action()) return;
            check_admin_referer('pcv_bulk_action');
            if(empty($_POST['id']) || !is_array($_POST['id'])) return;
            global $wpdb;
            $ids = array_map('absint', $_POST['id']);
            $ids = array_filter($ids);
            if(!$ids) return;
            $table = $this->plugin->table_name();
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $wpdb->query( $wpdb->prepare("DELETE FROM {$table} WHERE id IN ($placeholders)", $ids) );
        }

        public function prepare_items(){
            $this->process_bulk_action();
            global $wpdb;
            $table = $this->plugin->table_name();
            $per_page=20; $current_page=$this->get_pagenum();
            $orderby = $_GET['orderby'] ?? 'created_at';
            $order   = (isset($_GET['order']) && strtolower($_GET['order'])==='asc')?'ASC':'DESC';
            $allowed = ['created_at','nome','cognome','comune','provincia','categoria']; if(!in_array($orderby,$allowed,true)) $orderby='created_at';
            $where='WHERE 1=1'; $params=[];
            $f_comune = isset($_GET['f_comune'])?trim(sanitize_text_field($_GET['f_comune'])):'';
            $f_prov   = isset($_GET['f_prov'])?trim(sanitize_text_field($_GET['f_prov'])):'';
            $s        = isset($_GET['s'])?trim(sanitize_text_field($_GET['s'])):'';
            if($f_comune!==''){ $where.=" AND comune LIKE %s"; $params[]='%'.$wpdb->esc_like($f_comune).'%'; }
            if($f_prov!==''){ $where.=" AND provincia LIKE %s"; $params[]='%'.$wpdb->esc_like($f_prov).'%'; }
            if($s!==''){ $like='%'.$wpdb->esc_like($s).'%'; $where.=" AND ( nome LIKE %s OR cognome LIKE %s OR email LIKE %s OR telefono LIKE %s OR categoria LIKE %s )"; array_push($params,$like,$like,$like,$like,$like); }
            $count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
            $total_items = empty($params)
                ? (int) $wpdb->get_var( $count_sql )
                : (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
            $offset = ($current_page-1)*$per_page;
            $query = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
            $items = $wpdb->get_results( $wpdb->prepare($query, array_merge($params,[ $per_page,$offset ])) );
            $this->items=$items;
            $this->set_pagination_args(['total_items'=>$total_items,'per_page'=>$per_page,'total_pages'=>ceil($total_items/$per_page)]);
            $this->_column_headers=[$this->get_columns(),[], $this->get_sortable_columns(),'nome'];
        }
        
        public function extra_tablenav($which){
            if($which!=='top') return;

            $f_comune_raw = isset($_GET['f_comune']) ? wp_unslash($_GET['f_comune']) : '';
            $f_comune = sanitize_text_field( $f_comune_raw );
            $f_prov_raw = isset($_GET['f_prov']) ? wp_unslash($_GET['f_prov']) : '';
            $f_prov = strtoupper( sanitize_text_field( $f_prov_raw ) );
            $s_raw = isset($_GET['s']) ? wp_unslash($_GET['s']) : '';
            $s = sanitize_text_field( $s_raw );

            $province = $this->plugin->get_province_data();
            if ( ! array_key_exists( $f_prov, $province ) ) {
                $f_prov = '';
            }

            $comuni_map = $this->plugin->get_comuni_data();
            $all_comuni = $this->plugin->get_all_comuni();
            if ( $f_comune !== '' && ! in_array( $f_comune, $all_comuni, true ) ) {
                $f_comune = '';
            }

            if ( $f_prov && isset( $comuni_map[ $f_prov ] ) ) {
                $comuni_options = array_values( $comuni_map[ $f_prov ] );
            } else {
                $comuni_options = $all_comuni;
            }

            $comuni_options = array_filter( $comuni_options, 'is_string' );
            $comuni_options = array_values( array_unique( $comuni_options ) );
            sort( $comuni_options, SORT_NATURAL | SORT_FLAG_CASE );

            $url_no_vars = remove_query_arg(['f_comune','f_prov','s','paged']);
            echo '<div class="pcv-topbar"><form method="get">';
            echo '<input type="hidden" name="page" value="'.esc_attr(PCV_Abruzzo_Plugin::MENU_SLUG).'">';

            echo '<label class="screen-reader-text" for="pcv-admin-provincia">' . esc_html__( 'Filtra per Provincia', PCV_Abruzzo_Plugin::TEXT_DOMAIN ) . '</label>';
            echo '<select name="f_prov" id="pcv-admin-provincia">';
            echo '<option value="">' . esc_html__( 'Tutte le province', PCV_Abruzzo_Plugin::TEXT_DOMAIN ) . '</option>';
            foreach ($province as $code => $label) {
                $selected_attr = selected( $f_prov, $code, false );
                $option_label = sprintf( '%s (%s)', $label, $code );
                echo '<option value="'.esc_attr($code).'"'.$selected_attr.'>'.esc_html($option_label).'</option>';
            }
            echo '</select>';

            echo '<label class="screen-reader-text" for="pcv-admin-comune">' . esc_html__( 'Filtra per Comune', PCV_Abruzzo_Plugin::TEXT_DOMAIN ) . '</label>';
            echo '<select name="f_comune" id="pcv-admin-comune" data-selected="'.esc_attr($f_comune).'">';
            echo '<option value="">' . esc_html__( 'Tutti i comuni', PCV_Abruzzo_Plugin::TEXT_DOMAIN ) . '</option>';
            foreach ($comuni_options as $comune_name) {
                $selected_attr = selected( $f_comune, $comune_name, false );
                echo '<option value="'.esc_attr($comune_name).'"'.$selected_attr.'>'.esc_html($comune_name).'</option>';
            }
            echo '</select>';

            echo '<input type="search" name="s" value="'.esc_attr($s).'" placeholder="' . esc_attr__( 'Cerca…', PCV_Abruzzo_Plugin::TEXT_DOMAIN ) . '">';
            submit_button( __( 'Filtra', PCV_Abruzzo_Plugin::TEXT_DOMAIN ), 'secondary', '', false );
            echo ' <a href="'.esc_url($url_no_vars).'" class="button">' . esc_html__( 'Pulisci', PCV_Abruzzo_Plugin::TEXT_DOMAIN ) . '</a> ';
            $export_url = wp_nonce_url( add_query_arg(['pcv_export'=>'csv'], admin_url('admin.php?page='.PCV_Abruzzo_Plugin::MENU_SLUG) ), 'pcv_export' );
            echo ' <a class="button button-primary" href="'.esc_url($export_url).'">' . esc_html__( 'Export CSV', PCV_Abruzzo_Plugin::TEXT_DOMAIN ) . '</a>';
            echo '</form></div>';
        }
    }
}

/**
 * Cleanup plugin data on uninstall
 */
function pcv_uninstall() {
    global $wpdb;
    $table = $wpdb->prefix . PCV_Abruzzo_Plugin::TABLE;
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
    $options = [
        PCV_Abruzzo_Plugin::OPT_RECAPTCHA_SITE,
        PCV_Abruzzo_Plugin::OPT_RECAPTCHA_SECRET,
        PCV_Abruzzo_Plugin::OPT_PRIVACY_NOTICE,
        PCV_Abruzzo_Plugin::OPT_PARTICIPATION_LABEL,
        PCV_Abruzzo_Plugin::OPT_OVERNIGHT_LABEL,
        PCV_Abruzzo_Plugin::OPT_MEALS_LABEL,
        PCV_Abruzzo_Plugin::OPT_NAME_LABEL,
        PCV_Abruzzo_Plugin::OPT_SURNAME_LABEL,
        PCV_Abruzzo_Plugin::OPT_PROVINCE_LABEL,
        PCV_Abruzzo_Plugin::OPT_PROVINCE_PLACEHOLDER,
        PCV_Abruzzo_Plugin::OPT_COMUNE_LABEL,
        PCV_Abruzzo_Plugin::OPT_COMUNE_PLACEHOLDER,
        PCV_Abruzzo_Plugin::OPT_EMAIL_LABEL,
        PCV_Abruzzo_Plugin::OPT_PHONE_LABEL,
        PCV_Abruzzo_Plugin::OPT_PRIVACY_FIELD_LABEL,
        PCV_Abruzzo_Plugin::OPT_SUBMIT_LABEL,
        PCV_Abruzzo_Plugin::OPT_OPTIONAL_GROUP_ARIA,
        PCV_Abruzzo_Plugin::OPT_MODAL_ALERT,
        PCV_Abruzzo_Plugin::OPT_NOTIFY_ENABLED,
        PCV_Abruzzo_Plugin::OPT_NOTIFY_RECIPIENTS,
        PCV_Abruzzo_Plugin::OPT_NOTIFY_SUBJECT,
    ];

    foreach ( $options as $option_name ) {
        delete_option( $option_name );
    }
}

register_uninstall_hook( __FILE__, 'pcv_uninstall' );

register_activation_hook( __FILE__, ['PCV_Abruzzo_Plugin', 'activate'] );

new PCV_Abruzzo_Plugin();
