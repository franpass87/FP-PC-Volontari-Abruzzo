<?php
/**
 * Plugin Name: PC Volontari Abruzzo
 * Description: Raccolta iscrizioni volontari (Protezione Civile Abruzzo) con form via shortcode, popup comune, lista completa Comuni/Province Abruzzo, reCAPTCHA v2 e gestionale backend.
 * Version: 1.0.1
 * Author: Francesco Passeri
 * Author URI: https://francescopasseri.com
 * License: GPLv2 or later
 */

if ( ! defined('ABSPATH') ) exit;

class PCV_Abruzzo_Plugin {

    const VERSION   = '1.0.1';
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
        foreach ( [ 'dorme', 'mangia' ] as $column ) {
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
            return $default;
        }

        return $value;
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
        ];

        wp_localize_script( 'pcv-frontend', 'PCV_DATA', $data );

        // Messaggi post-submit via query var
        if ( isset($_GET['pcv_status']) ) {
            if ( $_GET['pcv_status'] === 'ok' ) {
                $out .= '<div class="pcv-alert success">Grazie! La tua registrazione è stata inviata correttamente.</div>';
            } elseif ( $_GET['pcv_status'] === 'err' ) {
                $out .= '<div class="pcv-alert error">Si è verificato un errore. Verifica i campi e riprova.</div>';
            }
        }

        $nonce = wp_create_nonce( self::NONCE );
        $site_key = esc_attr( get_option(self::OPT_RECAPTCHA_SITE, '') );
        $privacy_notice = get_option( self::OPT_PRIVACY_NOTICE, '' );
        if ( ! $privacy_notice ) {
            $privacy_notice = self::DEFAULT_PRIVACY_NOTICE;
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
            <h3>Seleziona <strong>Provincia</strong> e <strong>Comune</strong></h3>
            <p>Li useremo per precompilare il form. Puoi modificarli dopo.</p>

            <label for="pcvProvinciaInput" style="font-weight:600;margin-top:8px;">Provincia</label>
            <select id="pcvProvinciaInput" style="width:100%;padding:10px;border:1px solid #dcdcdc;border-radius:6px;margin-top:6px;">
              <option value=""><?php echo esc_html( $province_placeholder ); ?></option>
            </select>

            <label for="pcvComuneInput" style="font-weight:600;margin-top:8px;">Comune</label>
            <select id="pcvComuneInput" style="width:100%;padding:10px;border:1px solid #dcdcdc;border-radius:6px;margin-top:6px;">
              <option value=""><?php echo esc_html( $comune_placeholder ); ?></option>
            </select>

            <div class="pcv-actions">
              <button type="button" id="pcvComuneSkip" class="button">Salta</button>
              <button type="button" id="pcvComuneConfirm" class="button button-primary">Conferma</button>
            </div>
          </div>
        </div>

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

            <div class="pcv-privacy-notice" style="font-size:12px;color:#666;margin-top:10px;">
                <?php echo wpautop( wp_kses_post( $privacy_notice ) ); ?>
            </div>
        </form>
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
                'created_at' => current_time('mysql'),
                'nome'       => $nome,
                'cognome'    => $cognome,
                'comune'     => $comune,
                'provincia'  => $provincia,
                'email'      => $email,
                'telefono'   => $telefono,
                'privacy'    => $privacy,
                'partecipa'  => $partecipa,
                'dorme'      => $dorme,
                'mangia'     => $mangia,
                'ip'         => $this->get_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ],
            [ '%s','%s','%s','%s','%s','%s','%s','%d','%d','%d','%d','%s','%s' ]
        );

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
            'Volontari Abruzzo',
            'Volontari Abruzzo',
            'manage_options',
            self::MENU_SLUG,
            [ $this, 'render_admin_page' ],
            'dashicons-groups',
            26
        );
        add_submenu_page(
            self::MENU_SLUG,
            'Impostazioni reCAPTCHA',
            'Impostazioni',
            'manage_options',
            self::MENU_SLUG.'-settings',
            [ $this, 'render_settings_page' ]
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
                'placeholderComune' => 'Tutti i comuni',
            ],
        ] );
        wp_enqueue_script( 'pcv-admin' );
    }

    public function render_admin_page() {
        if ( ! current_user_can('manage_options') ) return;

        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

        echo '<div class="wrap"><h1 class="wp-heading-inline">Volontari Abruzzo</h1></div>';
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
                'label'       => 'Etichetta campo Nome',
                'description' => 'Testo mostrato accanto al campo Nome nel form pubblico.',
                'default'     => self::DEFAULT_NAME_LABEL,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_SURNAME_LABEL => [
                'label'       => 'Etichetta campo Cognome',
                'description' => 'Testo mostrato accanto al campo Cognome nel form pubblico.',
                'default'     => self::DEFAULT_SURNAME_LABEL,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_PROVINCE_LABEL => [
                'label'       => 'Etichetta campo Provincia',
                'description' => 'Testo mostrato accanto alla select della Provincia.',
                'default'     => self::DEFAULT_PROVINCE_LABEL,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_PROVINCE_PLACEHOLDER => [
                'label'       => 'Testo predefinito select Provincia',
                'description' => 'Prima opzione vuota mostrata nelle select della Provincia (form e popup).',
                'default'     => self::DEFAULT_PROVINCE_PLACEHOLDER,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_COMUNE_LABEL => [
                'label'       => 'Etichetta campo Comune',
                'description' => 'Testo mostrato accanto alla select del Comune.',
                'default'     => self::DEFAULT_COMUNE_LABEL,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_COMUNE_PLACEHOLDER => [
                'label'       => 'Testo predefinito select Comune',
                'description' => 'Prima opzione vuota mostrata nelle select del Comune (form e popup).',
                'default'     => self::DEFAULT_COMUNE_PLACEHOLDER,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_EMAIL_LABEL => [
                'label'       => 'Etichetta campo Email',
                'description' => 'Testo mostrato accanto al campo Email.',
                'default'     => self::DEFAULT_EMAIL_LABEL,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_PHONE_LABEL => [
                'label'       => 'Etichetta campo Telefono',
                'description' => 'Testo mostrato accanto al campo Telefono.',
                'default'     => self::DEFAULT_PHONE_LABEL,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_PARTICIPATION_LABEL => [
                'label'       => 'Etichetta partecipazione',
                'description' => 'Testo mostrato accanto all’opzione di partecipazione.',
                'default'     => self::DEFAULT_PARTICIPATION_LABEL,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_OVERNIGHT_LABEL => [
                'label'       => 'Etichetta pernottamento',
                'description' => 'Testo mostrato accanto all’opzione di pernottamento.',
                'default'     => self::DEFAULT_OVERNIGHT_LABEL,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_MEALS_LABEL => [
                'label'       => 'Etichetta pasti',
                'description' => 'Testo mostrato accanto all’opzione relativa ai pasti.',
                'default'     => self::DEFAULT_MEALS_LABEL,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_PRIVACY_FIELD_LABEL => [
                'label'       => 'Etichetta consenso privacy',
                'description' => 'Testo mostrato accanto alla casella di consenso privacy. Puoi includere un link usando HTML.',
                'default'     => self::DEFAULT_PRIVACY_FIELD_LABEL,
                'sanitize'    => 'wp_kses_post',
            ],
            self::OPT_OPTIONAL_GROUP_ARIA => [
                'label'       => 'Descrizione gruppo opzioni facoltative',
                'description' => 'Testo utilizzato per l’attributo aria-label del gruppo di checkbox facoltative.',
                'default'     => self::DEFAULT_OPTIONAL_GROUP_ARIA,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_SUBMIT_LABEL => [
                'label'       => 'Testo pulsante invio',
                'description' => 'Testo del pulsante di invio del form.',
                'default'     => self::DEFAULT_SUBMIT_LABEL,
                'sanitize'    => 'sanitize_text_field',
            ],
            self::OPT_MODAL_ALERT => [
                'label'       => 'Messaggio popup Provincia/Comune',
                'description' => 'Avviso mostrato nel popup se non vengono selezionati Provincia e Comune.',
                'default'     => self::DEFAULT_MODAL_ALERT,
                'sanitize'    => 'sanitize_text_field',
            ],
        ];

        if ( isset($_POST['pcv_save_keys']) && check_admin_referer('pcv_save_keys_nonce') ) {
            $site_value = isset($_POST['pcv_site_key']) ? sanitize_text_field( wp_unslash( $_POST['pcv_site_key'] ) ) : '';
            $secret_value = isset($_POST['pcv_secret_key']) ? sanitize_text_field( wp_unslash( $_POST['pcv_secret_key'] ) ) : '';
            $privacy_notice_value = isset($_POST['pcv_privacy_notice']) ? wp_kses_post( wp_unslash( $_POST['pcv_privacy_notice'] ) ) : '';

            update_option(self::OPT_RECAPTCHA_SITE, $site_value);
            update_option(self::OPT_RECAPTCHA_SECRET, $secret_value);
            update_option(self::OPT_PRIVACY_NOTICE, $privacy_notice_value);

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
            echo '<div class="updated notice"><p>Impostazioni salvate.</p></div>';
        }

        $site = esc_attr( get_option(self::OPT_RECAPTCHA_SITE, '') );
        $secret = esc_attr( get_option(self::OPT_RECAPTCHA_SECRET, '') );
        $privacy_notice = get_option(self::OPT_PRIVACY_NOTICE, '');
        if ( ! $privacy_notice ) {
            $privacy_notice = self::DEFAULT_PRIVACY_NOTICE;
        }

        $label_values = [];
        foreach ( $label_fields as $option_key => $field ) {
            $value = get_option( $option_key, '' );
            if ( ! is_string( $value ) || $value === '' ) {
                $value = $field['default'];
            }
            $label_values[ $option_key ] = $value;
        }

        echo '<div class="wrap"><h1>Impostazioni modulo Volontari</h1>';
        echo '<form method="post">';
        wp_nonce_field('pcv_save_keys_nonce');
        echo '<table class="form-table">';
        echo '<tr><th scope="row"><label for="pcv_site_key">Site Key</label></th><td><input type="text" id="pcv_site_key" name="pcv_site_key" value="'.$site.'" class="regular-text"></td></tr>';
        echo '<tr><th scope="row"><label for="pcv_secret_key">Secret Key</label></th><td><input type="text" id="pcv_secret_key" name="pcv_secret_key" value="'.$secret.'" class="regular-text"></td></tr>';
        foreach ( $label_fields as $option_key => $field ) {
            $value = $label_values[ $option_key ];
            $field_id = esc_attr( $option_key );
            $label = esc_html( $field['label'] );
            $description = isset( $field['description'] ) && $field['description'] !== '' ? '<p class="description">'.esc_html( $field['description'] ).'</p>' : '';
            echo '<tr><th scope="row"><label for="'.$field_id.'">'.$label.'</label></th><td><input type="text" id="'.$field_id.'" name="'.$field_id.'" value="'.esc_attr( $value ).'" class="regular-text">'.$description.'</td></tr>';
        }
        echo '<tr><th scope="row"><label for="pcv_privacy_notice">Informativa Privacy</label></th><td><textarea id="pcv_privacy_notice" name="pcv_privacy_notice" rows="6" class="large-text code">'.esc_textarea($privacy_notice).'</textarea><p class="description">Inserisci l’informativa privacy completa, includendo il Titolare del trattamento e le eventuali note legali.</p></td></tr>';
        echo '</table>';
        submit_button('Salva impostazioni', 'primary', 'pcv_save_keys');
        echo '</form></div>';
    }

    /* ---------------- Export CSV ---------------- */
    public function maybe_export_csv() {
        if ( ! is_admin() ) return;
        if ( ! isset($_GET['page']) || $_GET['page'] !== self::MENU_SLUG ) return;
        if ( ! isset($_GET['pcv_export']) || $_GET['pcv_export'] !== 'csv' ) return;
        if ( ! current_user_can('manage_options') ) return;
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'pcv_export' ) ) wp_die('Nonce non valido');

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
        fputcsv($out, ['ID','Data','Nome','Cognome','Comune','Provincia','Email','Telefono','Privacy','Partecipa','Dormire','Mangiare','IP','User Agent'], ';');

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
            parent::__construct(['singular'=>'volontario','plural'=>'volontari','ajax'=>false]);
        }
        
        public function get_columns() {
            return [
                'cb'         => '<input type="checkbox" />',
                'created_at' => 'Data',
                'nome'       => 'Nome',
                'cognome'    => 'Cognome',
                'comune'     => 'Comune',
                'provincia'  => 'Provincia',
                'email'      => 'Email',
                'telefono'   => 'Telefono',
                'privacy'    => 'Privacy',
                'partecipa'  => 'Partecipa',
                'dorme'      => 'Dormire',
                'mangia'     => 'Mangiare',
            ];
        }
        
        protected function get_sortable_columns() {
            return ['created_at'=>['created_at',true],'nome'=>['nome',false],'cognome'=>['cognome',false],'comune'=>['comune',false],'provincia'=>['provincia',false]];
        }
        
        protected function column_cb($item){ 
            return sprintf('<input type="checkbox" name="id[]" value="%d" />',$item->id); 
        }
        
        protected function column_default($item,$col){
            switch($col){
                case 'created_at': return esc_html(mysql2date('d/m/Y H:i',$item->created_at));
                case 'nome':case 'cognome':case 'comune':case 'provincia':case 'email':case 'telefono': return esc_html($item->$col);
                case 'privacy':case 'partecipa':case 'dorme':case 'mangia': return $item->$col ? 'Sì':'No';
                default: return '';
            }
        }
        
        public function get_bulk_actions(){
            return ['delete' => 'Elimina'];
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
            $allowed = ['created_at','nome','cognome','comune','provincia']; if(!in_array($orderby,$allowed,true)) $orderby='created_at';
            $where='WHERE 1=1'; $params=[];
            $f_comune = isset($_GET['f_comune'])?trim(sanitize_text_field($_GET['f_comune'])):'';
            $f_prov   = isset($_GET['f_prov'])?trim(sanitize_text_field($_GET['f_prov'])):'';
            $s        = isset($_GET['s'])?trim(sanitize_text_field($_GET['s'])):'';
            if($f_comune!==''){ $where.=" AND comune LIKE %s"; $params[]='%'.$wpdb->esc_like($f_comune).'%'; }
            if($f_prov!==''){ $where.=" AND provincia LIKE %s"; $params[]='%'.$wpdb->esc_like($f_prov).'%'; }
            if($s!==''){ $like='%'.$wpdb->esc_like($s).'%'; $where.=" AND ( nome LIKE %s OR cognome LIKE %s OR email LIKE %s OR telefono LIKE %s )"; array_push($params,$like,$like,$like,$like); }
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

            echo '<label class="screen-reader-text" for="pcv-admin-provincia">Filtra per Provincia</label>';
            echo '<select name="f_prov" id="pcv-admin-provincia">';
            echo '<option value="">Tutte le province</option>';
            foreach ($province as $code => $label) {
                $selected_attr = selected( $f_prov, $code, false );
                $option_label = sprintf( '%s (%s)', $label, $code );
                echo '<option value="'.esc_attr($code).'"'.$selected_attr.'>'.esc_html($option_label).'</option>';
            }
            echo '</select>';

            echo '<label class="screen-reader-text" for="pcv-admin-comune">Filtra per Comune</label>';
            echo '<select name="f_comune" id="pcv-admin-comune" data-selected="'.esc_attr($f_comune).'">';
            echo '<option value="">Tutti i comuni</option>';
            foreach ($comuni_options as $comune_name) {
                $selected_attr = selected( $f_comune, $comune_name, false );
                echo '<option value="'.esc_attr($comune_name).'"'.$selected_attr.'>'.esc_html($comune_name).'</option>';
            }
            echo '</select>';

            echo '<input type="search" name="s" value="'.esc_attr($s).'" placeholder="Cerca...">';
            submit_button('Filtra','secondary','',false);
            echo ' <a href="'.esc_url($url_no_vars).'" class="button">Pulisci</a> ';
            $export_url = wp_nonce_url( add_query_arg(['pcv_export'=>'csv'], admin_url('admin.php?page='.PCV_Abruzzo_Plugin::MENU_SLUG) ), 'pcv_export' );
            echo ' <a class="button button-primary" href="'.esc_url($export_url).'">Export CSV</a>';
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
    ];

    foreach ( $options as $option_name ) {
        delete_option( $option_name );
    }
}

register_uninstall_hook( __FILE__, 'pcv_uninstall' );

register_activation_hook( __FILE__, ['PCV_Abruzzo_Plugin', 'activate'] );

new PCV_Abruzzo_Plugin();
