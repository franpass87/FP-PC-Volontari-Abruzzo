<?php
/**
 * Gestione shortcode form volontari
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Shortcode {

    const TEXT_DOMAIN = 'pc-volontari-abruzzo';
    const NONCE = 'pcv_form_nonce';

    private $assets_manager;

    /**
     * Costruttore
     *
     * @param PCV_Assets_Manager $assets_manager
     */
    public function __construct( $assets_manager ) {
        $this->assets_manager = $assets_manager;
    }

    /**
     * Renderizza shortcode form
     *
     * @param array $atts
     * @return string
     */
    public function render( $atts ) {
        $atts = shortcode_atts( [], $atts, 'pc_volontari_form' );
        $out = '';

        // Prepara label
        $province_placeholder = $this->get_label_value( 'pcv_placeholder_provincia', 'Seleziona provincia' );
        $comune_placeholder   = $this->get_label_value( 'pcv_placeholder_comune', 'Seleziona comune' );
        $modal_alert_label    = $this->get_label_value( 'pcv_label_modal_alert', 'Seleziona provincia e comune.' );

        $labels = [
            'selectProvince' => $province_placeholder,
            'selectComune'   => $comune_placeholder,
            'modalAlert'     => $modal_alert_label,
        ];

        $this->assets_manager->enqueue_assets( $labels );

        // Messaggi post-submit
        if ( isset( $_GET['pcv_status'] ) ) {
            $status = sanitize_key( wp_unslash( $_GET['pcv_status'] ) );

            if ( $status === 'ok' ) {
                $message = esc_html__( 'Grazie! La tua registrazione è stata inviata correttamente.', self::TEXT_DOMAIN );
                $out .= '<div class="pcv-alert success" role="status" aria-live="polite"><span class="pcv-alert-icon" aria-hidden="true">✓</span><div class="pcv-alert-message">' . $message . '</div></div>';
            } elseif ( $status === 'err' ) {
                $message = esc_html__( 'Si è verificato un errore. Verifica i campi e riprova.', self::TEXT_DOMAIN );
                $out .= '<div class="pcv-alert error" role="alert" aria-live="assertive"><span class="pcv-alert-icon" aria-hidden="true">!</span><div class="pcv-alert-message">' . $message . '</div></div>';
            }
        }

        $nonce = wp_create_nonce( self::NONCE );
        $site_key = esc_attr( get_option( 'pcv_recaptcha_site', '' ) );
        $privacy_notice = get_option( 'pcv_privacy_notice', '' );
        if ( ! $privacy_notice ) {
            $privacy_notice = __( "I dati saranno trattati ai sensi del Reg. UE 2016/679 (GDPR) per la gestione dell'evento e finalità organizzative. Titolare del trattamento: [inserire].", self::TEXT_DOMAIN );
        }

        $participation_label = $this->get_label_value( 'pcv_label_partecipa', 'Sì, voglio partecipare all\'evento' );
        $overnight_label     = $this->get_label_value( 'pcv_label_dorme', 'Mi fermo a dormire' );
        $meals_label         = $this->get_label_value( 'pcv_label_mangia', 'Parteciperò ai pasti' );
        $name_label          = $this->get_label_value( 'pcv_label_nome', 'Nome *' );
        $surname_label       = $this->get_label_value( 'pcv_label_cognome', 'Cognome *' );
        $province_label      = $this->get_label_value( 'pcv_label_provincia', 'Provincia *' );
        $comune_label        = $this->get_label_value( 'pcv_label_comune', 'Comune di provenienza *' );
        $email_label         = $this->get_label_value( 'pcv_label_email', 'Email *' );
        $phone_label         = $this->get_label_value( 'pcv_label_telefono', 'Telefono *' );
        $privacy_field_label = $this->get_label_value( 'pcv_label_privacy', 'Ho letto e accetto l\'Informativa Privacy *' );
        $submit_label        = $this->get_label_value( 'pcv_label_submit', 'Invia iscrizione' );
        $optional_group_aria = $this->get_label_value( 'pcv_label_optional_group', 'Opzioni facoltative' );

        ob_start();
        ?>
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
            <input type="hidden" name="pcv_nonce" value="<?php echo esc_attr( $nonce ); ?>">

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
                <div class="g-recaptcha" data-sitekey="<?php echo esc_attr( $site_key ); ?>"></div>
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

    /**
     * Ottiene valore label con fallback
     *
     * @param string $option
     * @param string $default
     * @return string
     */
    private function get_label_value( $option, $default ) {
        $value = get_option( $option, '' );
        if ( ! is_string( $value ) || $value === '' ) {
            return __( $default, self::TEXT_DOMAIN );
        }

        return $value;
    }
}