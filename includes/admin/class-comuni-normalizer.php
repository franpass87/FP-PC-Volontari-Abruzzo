<?php
/**
 * Normalizzatore comuni per admin
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Comuni_Normalizer {

    const TEXT_DOMAIN = 'pc-volontari-abruzzo';

    /**
     * Normalizza un nome comune rimuovendo prefissi
     *
     * @param string $comune
     * @return string
     */
    private function normalize_comune_name( $comune ) {
        $comune = trim( $comune );
        
        if ( empty( $comune ) ) {
            return $comune;
        }
        
        // Rimuovi il prefisso "Comune di" se presente
        $comune = preg_replace( '/^Comune\s+di\s+/i', '', $comune );
        
        // Rimuovi anche varianti come "Com. di", "C. di", etc.
        $comune = preg_replace( '/^C(?:om)?\.?\s+di\s+/i', '', $comune );
        
        return trim( $comune );
    }

    /**
     * Ottiene tutti i comuni unici dal database
     *
     * @return array
     */
    public function get_unique_comuni() {
        global $wpdb;
        
        $table = PCV_Database::get_table_name();
        $sql = "SELECT DISTINCT comune FROM {$table} ORDER BY comune";
        return $wpdb->get_col( $sql );
    }

    /**
     * Conta i record che hanno comuni con prefisso "Comune di"
     *
     * @return int
     */
    public function count_comuni_with_prefix() {
        global $wpdb;
        
        $table = PCV_Database::get_table_name();
        $sql = "SELECT COUNT(*) FROM {$table} 
                WHERE comune REGEXP '^Comune\\s+di\\s+' 
                OR comune REGEXP '^C(om)?\\.?\\s+di\\s+'";
        
        return (int) $wpdb->get_var( $sql );
    }

    /**
     * Normalizza tutti i comuni nel database
     *
     * @return array Risultati dell'operazione
     */
    public function normalize_all_comuni() {
        global $wpdb;
        
        $table = PCV_Database::get_table_name();
        $comuni = $this->get_unique_comuni();
        $updated_count = 0;
        $error_count = 0;
        $details = [];
        
        foreach ( $comuni as $comune_originale ) {
            $comune_normalizzato = $this->normalize_comune_name( $comune_originale );
            
            // Se il comune è cambiato, aggiorna il database
            if ( $comune_originale !== $comune_normalizzato ) {
                $result = $wpdb->update(
                    $table,
                    [ 'comune' => $comune_normalizzato ],
                    [ 'comune' => $comune_originale ],
                    [ '%s' ],
                    [ '%s' ]
                );
                
                if ( $result !== false ) {
                    $updated_count += $result;
                    $details[] = [
                        'originale' => $comune_originale,
                        'normalizzato' => $comune_normalizzato,
                        'record_aggiornati' => $result
                    ];
                } else {
                    $error_count++;
                    $details[] = [
                        'originale' => $comune_originale,
                        'normalizzato' => $comune_normalizzato,
                        'errore' => $wpdb->last_error
                    ];
                }
            }
        }
        
        return [
            'updated_count' => $updated_count,
            'error_count' => $error_count,
            'details' => $details
        ];
    }

    /**
     * Mostra un report dei comuni
     *
     * @return array Report dettagliato
     */
    public function get_report() {
        $comuni = $this->get_unique_comuni();
        $with_prefix = [];
        $without_prefix = [];
        
        foreach ( $comuni as $comune ) {
            if ( preg_match( '/^Comune\s+di\s+/i', $comune ) || preg_match( '/^C(?:om)?\.?\s+di\s+/i', $comune ) ) {
                $with_prefix[] = [
                    'originale' => $comune,
                    'normalizzato' => $this->normalize_comune_name( $comune )
                ];
            } else {
                $without_prefix[] = $comune;
            }
        }
        
        return [
            'total_comuni' => count( $comuni ),
            'with_prefix' => $with_prefix,
            'without_prefix' => $without_prefix,
            'records_to_update' => $this->count_comuni_with_prefix()
        ];
    }

    /**
     * Renderizza la pagina admin per la normalizzazione
     */
    public function render_admin_page() {
        $report = $this->get_report();
        
        // Gestisci l'azione di normalizzazione
        if ( isset( $_POST['normalize_comuni'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'pcv_normalize_comuni' ) ) {
            $result = $this->normalize_all_comuni();
            
            if ( $result['updated_count'] > 0 ) {
                echo '<div class="notice notice-success"><p>';
                printf( 
                    esc_html__( 'Normalizzazione completata! %d record aggiornati.', self::TEXT_DOMAIN ), 
                    $result['updated_count'] 
                );
                echo '</p></div>';
                
                if ( $result['error_count'] > 0 ) {
                    echo '<div class="notice notice-warning"><p>';
                    printf( 
                        esc_html__( '%d errori durante la normalizzazione.', self::TEXT_DOMAIN ), 
                        $result['error_count'] 
                    );
                    echo '</p></div>';
                }
            } else {
                echo '<div class="notice notice-info"><p>';
                esc_html_e( 'Nessun comune da normalizzare.', self::TEXT_DOMAIN );
                echo '</p></div>';
            }
            
            // Aggiorna il report dopo la normalizzazione
            $report = $this->get_report();
        }
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Normalizzazione Comuni', self::TEXT_DOMAIN ); ?></h1>
            
            <div class="card">
                <h2><?php esc_html_e( 'Report Comuni', self::TEXT_DOMAIN ); ?></h2>
                <table class="widefat">
                    <tr>
                        <td><strong><?php esc_html_e( 'Totale comuni unici:', self::TEXT_DOMAIN ); ?></strong></td>
                        <td><?php echo esc_html( $report['total_comuni'] ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Comuni con prefisso "Comune di":', self::TEXT_DOMAIN ); ?></strong></td>
                        <td><?php echo esc_html( count( $report['with_prefix'] ) ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Comuni senza prefisso:', self::TEXT_DOMAIN ); ?></strong></td>
                        <td><?php echo esc_html( count( $report['without_prefix'] ) ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Record da aggiornare:', self::TEXT_DOMAIN ); ?></strong></td>
                        <td><?php echo esc_html( $report['records_to_update'] ); ?></td>
                    </tr>
                </table>
            </div>
            
            <?php if ( ! empty( $report['with_prefix'] ) ) : ?>
            <div class="card">
                <h2><?php esc_html_e( 'Comuni che verranno normalizzati', self::TEXT_DOMAIN ); ?></h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Comune originale', self::TEXT_DOMAIN ); ?></th>
                            <th><?php esc_html_e( 'Comune normalizzato', self::TEXT_DOMAIN ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $report['with_prefix'] as $comune ) : ?>
                        <tr>
                            <td><?php echo esc_html( $comune['originale'] ); ?></td>
                            <td><?php echo esc_html( $comune['normalizzato'] ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card">
                <h2><?php esc_html_e( 'Normalizzazione', self::TEXT_DOMAIN ); ?></h2>
                <p><?php esc_html_e( 'Questa operazione rimuoverà il prefisso "Comune di" da tutti i comuni nel database.', self::TEXT_DOMAIN ); ?></p>
                
                <form method="post">
                    <?php wp_nonce_field( 'pcv_normalize_comuni' ); ?>
                    <p>
                        <input type="submit" name="normalize_comuni" class="button button-primary" 
                               value="<?php esc_attr_e( 'Normalizza Comuni', self::TEXT_DOMAIN ); ?>"
                               onclick="return confirm('<?php esc_attr_e( 'Sei sicuro di voler normalizzare tutti i comuni? Questa operazione non può essere annullata.', self::TEXT_DOMAIN ); ?>');">
                    </p>
                </form>
            </div>
            <?php else : ?>
            <div class="notice notice-success">
                <p><?php esc_html_e( 'Tutti i comuni sono già normalizzati correttamente!', self::TEXT_DOMAIN ); ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
