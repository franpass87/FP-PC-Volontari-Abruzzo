<?php
/**
 * Plugin Name: PC Volontari Abruzzo
 * Description: Raccolta iscrizioni volontari (Protezione Civile Abruzzo) con form via shortcode, popup comune, lista completa Comuni/Province Abruzzo, reCAPTCHA v2 e gestionale backend.
 * Version: 1.0
 * Author: Francesco Passeri
 * License: GPLv2 or later
 */

if ( ! defined('ABSPATH') ) exit;

class PCV_Abruzzo_Plugin {

    const VERSION   = '1.0';
    const TABLE     = 'pcv_volontari';
    const NONCE     = 'pcv_form_nonce';
    const MENU_SLUG = 'pcv-volontari';
    const OPT_RECAPTCHA_SITE = 'pcv_recaptcha_site';
    const OPT_RECAPTCHA_SECRET = 'pcv_recaptcha_secret';

    /** Province e comuni caricati da file */
    private $province = [];
    private $comuni   = [];

    public function __construct() {
        $data = json_decode( file_get_contents( __DIR__ . '/data/comuni_abruzzo.json' ), true );
        $this->province = $data['province'] ?? [];
        $this->comuni   = $data['comuni'] ?? [];

        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        add_shortcode( 'pc_volontari_form', [ $this, 'render_form_shortcode' ] );

        // Assets
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_front_assets' ] );

        // Handle POST
        add_action( 'init', [ $this, 'maybe_handle_submission' ] );

        // Admin
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );

        // Export
        add_action( 'admin_init', [ $this, 'maybe_export_csv' ] );
    }

    /* ---------------- Activation: create table ---------------- */
    public function activate() {
        global $wpdb;
        $table = $this->table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
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
            ip VARCHAR(45) NULL,
            user_agent TEXT NULL,
            PRIMARY KEY (id),
            KEY idx_cognome (cognome),
            KEY idx_nome (nome),
            KEY idx_comune (comune),
            KEY idx_provincia (provincia),
            KEY idx_created (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public function table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    /* ---------------- Frontend: assets + shortcode ---------------- */
    public function enqueue_front_assets() {
        // CSS
        $css = "
        .pcv-modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:99999}
        .pcv-modal{background:#fff;border-radius:12px;max-width:520px;width:92%;padding:22px;box-shadow:0 10px 30px rgba(0,0,0,.2)}
        .pcv-modal h3{margin:0 0 10px;font-size:20px}
        .pcv-modal .pcv-actions{margin-top:14px;display:flex;gap:10px;justify-content:flex-end}
        .pcv-hidden{display:none}
        .pcv-form .pcv-row{display:flex;gap:14px;flex-wrap:wrap}
        .pcv-form .pcv-field{flex:1 1 240px;display:flex;flex-direction:column;margin-bottom:12px}
        .pcv-form label{font-weight:600;margin-bottom:6px}
        .pcv-form select,.pcv-form input[type=text],.pcv-form input[type=email],.pcv-form input[type=tel]{padding:10px;border:1px solid #dcdcdc;border-radius:6px}
        .pcv-form .pcv-checkbox{display:flex;gap:8px;align-items:flex-start;margin:8px 0}
        .pcv-form .pcv-submit{margin-top:8px}
        .pcv-alert{padding:10px;border-radius:6px;margin-bottom:12px}
        .pcv-alert.success{background:#e6ffed;border:1px solid #b7f0c3}
        .pcv-alert.error{background:#ffecec;border:1px solid #f5b0b0}
        ";
        wp_register_style( 'pcv-inline', false );
        wp_enqueue_style( 'pcv-inline' );
        wp_add_inline_style( 'pcv-inline', $css );

        // Front JS: gestione popup, province->comuni, reCAPTCHA
        $prov = $this->province;
        $comuni = $this->comuni;
        $data = [
            'province' => $prov,
            'comuni'   => $comuni,
            'recaptcha_site' => get_option(self::OPT_RECAPTCHA_SITE, ''),
        ];
        wp_register_script( 'pcv-inline', '', [], false, true );
        wp_enqueue_script( 'pcv-inline' );
        wp_add_inline_script( 'pcv-inline', 'window.PCV_DATA='.wp_json_encode($data).';' );

        $js = <<<JS
        (function(){
          function qs(s,ctx){return (ctx||document).querySelector(s);}
          function ce(t){return document.createElement(t);}
          function fillProvince(sel){
            sel.innerHTML = '<option value="">Seleziona provincia</option>';
            for (var code in PCV_DATA.province){
              var opt = ce('option');
              opt.value = code;
              opt.textContent = PCV_DATA.province[code] + ' ('+code+')';
              sel.appendChild(opt);
            }
          }
          function fillComuni(sel, prov, preselect){
            sel.innerHTML = '<option value="">Seleziona comune</option>';
            var list = PCV_DATA.comuni[prov] || [];
            list.forEach(function(c){
              var o = ce('option'); o.value = c; o.textContent = c;
              sel.appendChild(o);
            });
            if(preselect){
              sel.value = preselect;
            }
          }
          document.addEventListener('DOMContentLoaded', function(){
            var selProv = qs('#pcv_provincia');
            var selComune = qs('#pcv_comune');
            fillProvince(selProv);

            selProv.addEventListener('change', function(){
              fillComuni(selComune, selProv.value, null);
              selComune.dispatchEvent(new Event('change'));
            });

            // Modal per comune (memorizza localStorage)
            var modal = qs('#pcvComuneModal');
            var storedComune = localStorage.getItem('pcv_comune')||'';
            var storedProv   = localStorage.getItem('pcv_provincia')||'';

            // Se abbiamo già locale, pre-seleziona
            if(storedProv){
              selProv.value = storedProv;
              selProv.dispatchEvent(new Event('change'));
            }
            if(storedComune && storedProv){
              selComune.value = storedComune;
            }

            if(!storedComune){
              if(modal) modal.classList.remove('pcv-hidden');
            }

            var confirmBtn = qs('#pcvComuneConfirm');
            if(confirmBtn){
              confirmBtn.addEventListener('click', function(){
                var inpComune = qs('#pcvComuneInput');
                var inpProv = qs('#pcvProvinciaInput');
                var comune = inpComune && inpComune.value ? inpComune.value.trim() : '';
                var prov = inpProv && inpProv.value ? inpProv.value : '';
                if(prov && comune){
                  localStorage.setItem('pcv_provincia', prov);
                  localStorage.setItem('pcv_comune', comune);
                  selProv.value = prov;
                  selProv.dispatchEvent(new Event('change'));
                  selComune.value = comune;
                  if(modal) modal.classList.add('pcv-hidden');
                } else {
                  alert('Seleziona provincia e comune.');
                }
              });
            }
            var skipBtn = qs('#pcvComuneSkip');
            if(skipBtn){
              skipBtn.addEventListener('click', function(){ if(modal) modal.classList.add('pcv-hidden'); });
            }

            // Selezione guidata nel popup (provincia -> comuni)
            var popProv = qs('#pcvProvinciaInput');
            var popComune = qs('#pcvComuneInput');
            if(popProv && popComune){
              // riempi province
              for (var code in PCV_DATA.province){
                var o = ce('option'); o.value = code; o.textContent = PCV_DATA.province[code] + ' ('+code+')'; popProv.appendChild(o);
              }
              popProv.addEventListener('change', function(){
                var prov = popProv.value;
                popComune.innerHTML = '<option value="">Seleziona comune</option>';
                (PCV_DATA.comuni[prov]||[]).forEach(function(c){
                  var o = ce('option'); o.value = c; o.textContent = c; popComune.appendChild(o);
                });
              });
              if(storedProv){
                popProv.value = storedProv; popProv.dispatchEvent(new Event('change'));
                if(storedComune){ popComune.value = storedComune; }
              }
            }

            // reCAPTCHA v2
            if(PCV_DATA.recaptcha_site){
              var s = document.createElement('script');
              s.src = 'https://www.google.com/recaptcha/api.js';
              s.async = true; s.defer = true;
              document.head.appendChild(s);
            }
          });
        })();
        JS;
        wp_add_inline_script( 'pcv-inline', $js );
    }

    public function render_form_shortcode( $atts ) {
        $atts = shortcode_atts( [], $atts, 'pc_volontari_form' );
        $out = '';

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

        ob_start(); ?>
        <!-- Modal Provincia/Comune -->
        <div id="pcvComuneModal" class="pcv-modal-backdrop pcv-hidden" role="dialog" aria-modal="true">
          <div class="pcv-modal">
            <h3>Seleziona <strong>Provincia</strong> e <strong>Comune</strong></h3>
            <p>Li useremo per precompilare il form. Puoi modificarli dopo.</p>

            <label for="pcvProvinciaInput" style="font-weight:600;margin-top:8px;">Provincia</label>
            <select id="pcvProvinciaInput" style="width:100%;padding:10px;border:1px solid #dcdcdc;border-radius:6px;margin-top:6px;">
              <option value="">Seleziona provincia</option>
            </select>

            <label for="pcvComuneInput" style="font-weight:600;margin-top:8px;">Comune</label>
            <select id="pcvComuneInput" style="width:100%;padding:10px;border:1px solid #dcdcdc;border-radius:6px;margin-top:6px;">
              <option value="">Seleziona comune</option>
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
                    <label for="pcv_nome">Nome *</label>
                    <input type="text" id="pcv_nome" name="pcv_nome" required>
                </div>
                <div class="pcv-field">
                    <label for="pcv_cognome">Cognome *</label>
                    <input type="text" id="pcv_cognome" name="pcv_cognome" required>
                </div>
            </div>

            <div class="pcv-row">
                <div class="pcv-field">
                    <label for="pcv_provincia">Provincia *</label>
                    <select id="pcv_provincia" name="pcv_provincia" required>
                        <option value="">Seleziona provincia</option>
                    </select>
                </div>
                <div class="pcv-field">
                    <label for="pcv_comune">Comune di provenienza *</label>
                    <select id="pcv_comune" name="pcv_comune" required>
                        <option value="">Seleziona comune</option>
                    </select>
                </div>
            </div>

            <div class="pcv-row">
                <div class="pcv-field">
                    <label for="pcv_email">Email *</label>
                    <input type="email" id="pcv_email" name="pcv_email" required>
                </div>
                <div class="pcv-field">
                    <label for="pcv_telefono">Telefono *</label>
                    <input type="tel" id="pcv_telefono" name="pcv_telefono" required>
                </div>
            </div>

            <div class="pcv-checkbox">
                <input type="checkbox" id="pcv_privacy" name="pcv_privacy" value="1" required>
                <label for="pcv_privacy">Ho letto e accetto l’Informativa Privacy *</label>
            </div>

            <div class="pcv-checkbox">
                <input type="checkbox" id="pcv_partecipa" name="pcv_partecipa" value="1">
                <label for="pcv_partecipa">Sì, voglio partecipare all’evento</label>
            </div>

            <?php if ( $site_key ) : ?>
                <div class="g-recaptcha" data-sitekey="<?php echo $site_key; ?>"></div>
            <?php endif; ?>

            <div class="pcv-submit">
                <button type="submit" class="button button-primary">Invia iscrizione</button>
            </div>

            <p style="font-size:12px;color:#666;margin-top:10px;">
                I dati saranno trattati ai sensi del Reg. UE 2016/679 (GDPR) per la gestione dell’evento e finalità organizzative. Titolare del trattamento: [inserire]. 
            </p>
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
                    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
                ]
            ]);
            if ( is_wp_error($resp) ) $this->redirect_with_status('err');

            $body = json_decode( wp_remote_retrieve_body($resp), true );
            if ( empty($body['success']) ) $this->redirect_with_status('err');
        }

        $nome      = $this->sanitize_name( wp_unslash( $_POST['pcv_nome'] ?? '' ) );
        $cognome   = $this->sanitize_name( wp_unslash( $_POST['pcv_cognome'] ?? '' ) );
        $provincia = strtoupper( trim( wp_unslash( $_POST['pcv_provincia'] ?? '' ) ) );
        $comune    = $this->sanitize_text( wp_unslash( $_POST['pcv_comune'] ?? '' ) );
        $email     = sanitize_email( wp_unslash( $_POST['pcv_email'] ?? '' ) );
        $telefono  = $this->sanitize_phone( wp_unslash( $_POST['pcv_telefono'] ?? '' ) );
        $privacy   = isset($_POST['pcv_privacy']) ? 1 : 0;
        $partecipa = isset($_POST['pcv_partecipa']) ? 1 : 0;

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
                'ip'         => $this->get_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ],
            [ '%s','%s','%s','%s','%s','%s','%d','%d','%s','%s' ]
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
        $css = ".pcv-topbar{display:flex;gap:10px;align-items:center;margin:12px 0}.pcv-topbar form{display:flex;gap:8px;align-items:center}";
        wp_register_style('pcv-admin-inline', false);
        wp_enqueue_style('pcv-admin-inline');
        wp_add_inline_style('pcv-admin-inline', $css);
    }

    public function render_admin_page() {
        if ( ! current_user_can('manage_options') ) return;

        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

        echo '<div class="wrap"><h1 class="wp-heading-inline">Volontari Abruzzo</h1></div>';
        $table = new PCV_List_Table( $this );
        $table->prepare_items();
        echo '<form method="post">'; $table->display(); echo '</form>';
    }

    public function render_settings_page() {
        if ( ! current_user_can('manage_options') ) return;

        if ( isset($_POST['pcv_save_keys']) && check_admin_referer('pcv_save_keys_nonce') ) {
            update_option(self::OPT_RECAPTCHA_SITE, sanitize_text_field($_POST['pcv_site_key'] ?? ''));
            update_option(self::OPT_RECAPTCHA_SECRET, sanitize_text_field($_POST['pcv_secret_key'] ?? ''));
            echo '<div class="updated notice"><p>Impostazioni salvate.</p></div>';
        }

        $site = esc_attr( get_option(self::OPT_RECAPTCHA_SITE, '') );
        $secret = esc_attr( get_option(self::OPT_RECAPTCHA_SECRET, '') );

        echo '<div class="wrap"><h1>Impostazioni reCAPTCHA</h1>';
        echo '<form method="post">';
        wp_nonce_field('pcv_save_keys_nonce');
        echo '<table class="form-table">';
        echo '<tr><th scope="row"><label for="pcv_site_key">Site Key</label></th><td><input type="text" id="pcv_site_key" name="pcv_site_key" value="'.$site.'" class="regular-text"></td></tr>';
        echo '<tr><th scope="row"><label for="pcv_secret_key">Secret Key</label></th><td><input type="text" id="pcv_secret_key" name="pcv_secret_key" value="'.$secret.'" class="regular-text"></td></tr>';
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

        $rows = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$table} {$where} ORDER BY created_at DESC", $params), ARRAY_A );

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=volontari_abruzzo_'.date('Ymd_His').'.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Data','Nome','Cognome','Comune','Provincia','Email','Telefono','Privacy','Partecipa','IP','User Agent'], ';');

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'],$r['created_at'],$r['nome'],$r['cognome'],$r['comune'],$r['provincia'],
                $r['email'],$r['telefono'],$r['privacy'] ? '1' : '0',$r['partecipa'] ? '1' : '0',$r['ip'],$r['user_agent'],
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
            return ['cb'=>'<input type="checkbox" />','created_at'=>'Data','nome'=>'Nome','cognome'=>'Cognome','comune'=>'Comune','provincia'=>'Provincia','email'=>'Email','telefono'=>'Telefono','privacy'=>'Privacy','partecipa'=>'Partecipa'];
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
                case 'privacy':case 'partecipa': return $item->$col ? 'Sì':'No';
                default: return '';
            }
        }
        
        public function get_bulk_actions(){ 
            return []; 
        }
        
        public function prepare_items(){
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
            $total_items = (int)$wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM {$table} {$where}", $params) );
            $offset = ($current_page-1)*$per_page;
            $query = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
            $items = $wpdb->get_results( $wpdb->prepare($query, array_merge($params,[ $per_page,$offset ])) );
            $this->items=$items;
            $this->set_pagination_args(['total_items'=>$total_items,'per_page'=>$per_page,'total_pages'=>ceil($total_items/$per_page)]);
            $this->_column_headers=[$this->get_columns(),[], $this->get_sortable_columns(),'nome'];
        }
        
        public function extra_tablenav($which){
            if($which!=='top') return;
            $f_comune = isset($_GET['f_comune'])?esc_attr($_GET['f_comune']):'';
            $f_prov   = isset($_GET['f_prov'])?esc_attr($_GET['f_prov']):'';
            $s        = isset($_GET['s'])?esc_attr($_GET['s']):'';
            $url_no_vars = remove_query_arg(['f_comune','f_prov','s','paged']);
            echo '<div class="pcv-topbar"><form method="get">';
            echo '<input type="hidden" name="page" value="'.esc_attr(PCV_Abruzzo_Plugin::MENU_SLUG).'">';
            echo '<input type="text" name="f_comune" value="'.$f_comune.'" placeholder="Filtra per Comune">';
            echo '<input type="text" name="f_prov" value="'.$f_prov.'" placeholder="Filtra per Provincia">';
            echo '<input type="search" name="s" value="'.$s.'" placeholder="Cerca...">';
            submit_button('Filtra','secondary','',false);
            echo ' <a href="'.esc_url($url_no_vars).'" class="button">Pulisci</a> ';
            $export_url = wp_nonce_url( add_query_arg(['pcv_export'=>'csv'], admin_url('admin.php?page='.PCV_Abruzzo_Plugin::MENU_SLUG) ), 'pcv_export' );
            echo ' <a class="button button-primary" href="'.esc_url($export_url).'">Export CSV</a>';
            echo '</form></div>';
        }
    }
}

new PCV_Abruzzo_Plugin();
