<?php
/**
 * Servizio per validazione dati
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Validator {

    private $province_data;
    private $comuni_data;

    /**
     * Costruttore
     *
     * @param array $province_data
     * @param array $comuni_data
     */
    public function __construct( array $province_data, array $comuni_data ) {
        $this->province_data = $province_data;
        $this->comuni_data = $comuni_data;
    }

    /**
     * Valida i dati del form di iscrizione
     *
     * @param array $data
     * @return true|array True se valido, array di errori altrimenti
     */
    public function validate_registration( array $data ) {
        $errors = [];

        // Validazione nome e cognome
        if ( empty( $data['nome'] ) || empty( $data['cognome'] ) ) {
            $errors[] = __( 'Nome e cognome sono obbligatori.', 'pc-volontari-abruzzo' );
        }

        // Validazione provincia
        if ( empty( $data['provincia'] ) || ! array_key_exists( $data['provincia'], $this->province_data ) ) {
            $errors[] = __( 'Provincia non valida.', 'pc-volontari-abruzzo' );
        }

        // Validazione comune
        if ( empty( $data['comune'] ) ) {
            $errors[] = __( 'Comune è obbligatorio.', 'pc-volontari-abruzzo' );
        } elseif ( ! empty( $data['provincia'] ) ) {
            $valid_comune = in_array(
                $data['comune'],
                $this->comuni_data[ $data['provincia'] ] ?? [],
                true
            );
            if ( ! $valid_comune ) {
                $errors[] = __( 'Comune non valido per la provincia selezionata.', 'pc-volontari-abruzzo' );
            }
        }

        // Validazione email
        if ( empty( $data['email'] ) || ! is_email( $data['email'] ) ) {
            $errors[] = __( 'Email non valida.', 'pc-volontari-abruzzo' );
        }

        // Validazione telefono
        if ( empty( $data['telefono'] ) ) {
            $errors[] = __( 'Telefono è obbligatorio.', 'pc-volontari-abruzzo' );
        }

        // Validazione privacy
        if ( empty( $data['privacy'] ) || $data['privacy'] !== 1 ) {
            $errors[] = __( 'Devi accettare la privacy policy.', 'pc-volontari-abruzzo' );
        }

        return empty( $errors ) ? true : $errors;
    }

    /**
     * Valida checkbox valore (deve essere 1 o assente)
     *
     * @param mixed $value
     * @return bool
     */
    public function validate_checkbox_value( $value ) {
        if ( $value === null ) {
            return true;
        }

        return (string) $value === '1';
    }
}