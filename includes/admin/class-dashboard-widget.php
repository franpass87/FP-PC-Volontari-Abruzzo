<?php
/**
 * Widget dashboard statistiche volontari
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Dashboard_Widget {

    const TEXT_DOMAIN = 'pc-volontari-abruzzo';

    /**
     * Registra il widget dashboard
     *
     * @return void
     */
    public static function register() {
        if ( ! PCV_Role_Manager::can_view_volunteers() ) {
            return;
        }

        wp_add_dashboard_widget(
            'pcv_statistics_widget',
            __( 'Statistiche Volontari Abruzzo', self::TEXT_DOMAIN ),
            [ __CLASS__, 'render_widget' ]
        );
    }

    /**
     * Renderizza il widget
     *
     * @return void
     */
    public static function render_widget() {
        $repository = new PCV_Repository();
        
        // Statistiche generali
        $total = $repository->count_volunteers();
        
        // Statistiche per categoria
        $counts_by_category = PCV_Category_Manager::count_volunteers_by_category();
        
        // Statistiche ultimi 7 giorni
        global $wpdb;
        $table = PCV_Database::get_table_name();
        $seven_days_ago = date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
        $recent_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",
            $seven_days_ago
        ) );

        // Provincia con più volontari
        $top_provincia = $wpdb->get_row(
            "SELECT provincia, COUNT(*) as count 
            FROM {$table} 
            WHERE provincia != '' 
            GROUP BY provincia 
            ORDER BY count DESC 
            LIMIT 1"
        );

        ?>
        <div class="pcv-dashboard-stats">
            <div class="pcv-stats-summary" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px;">
                <div class="pcv-stat-box" style="background: #f0f6fc; padding: 15px; border-radius: 4px; border-left: 4px solid #2271b1;">
                    <div style="font-size: 28px; font-weight: bold; color: #2271b1;"><?php echo esc_html( $total ); ?></div>
                    <div style="color: #646970; font-size: 13px;"><?php esc_html_e( 'Totale Volontari', self::TEXT_DOMAIN ); ?></div>
                </div>
                
                <div class="pcv-stat-box" style="background: #f6f7f7; padding: 15px; border-radius: 4px; border-left: 4px solid #72aee6;">
                    <div style="font-size: 28px; font-weight: bold; color: #72aee6;"><?php echo esc_html( $recent_count ); ?></div>
                    <div style="color: #646970; font-size: 13px;"><?php esc_html_e( 'Ultimi 7 giorni', self::TEXT_DOMAIN ); ?></div>
                </div>

                <?php if ( $top_provincia ) : ?>
                <div class="pcv-stat-box" style="background: #f0f6fc; padding: 15px; border-radius: 4px; border-left: 4px solid #00a32a;">
                    <div style="font-size: 20px; font-weight: bold; color: #00a32a;"><?php echo esc_html( $top_provincia->provincia ); ?></div>
                    <div style="color: #646970; font-size: 13px;">
                        <?php 
                        printf( 
                            esc_html__( 'Provincia più attiva (%d)', self::TEXT_DOMAIN ), 
                            $top_provincia->count 
                        ); 
                        ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ( ! empty( $counts_by_category ) ) : ?>
            <div class="pcv-category-stats">
                <h3 style="margin: 0 0 12px 0; font-size: 14px;">
                    <?php esc_html_e( 'Distribuzione per Categoria', self::TEXT_DOMAIN ); ?>
                </h3>
                <table class="widefat" style="border: 1px solid #c3c4c7;">
                    <thead>
                        <tr>
                            <th style="padding: 8px;"><?php esc_html_e( 'Categoria', self::TEXT_DOMAIN ); ?></th>
                            <th style="padding: 8px; text-align: center;"><?php esc_html_e( 'Volontari', self::TEXT_DOMAIN ); ?></th>
                            <th style="padding: 8px; text-align: center;"><?php esc_html_e( 'Percentuale', self::TEXT_DOMAIN ); ?></th>
                            <th style="padding: 8px;"><?php esc_html_e( 'Grafico', self::TEXT_DOMAIN ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Ordina per count decrescente
                        arsort( $counts_by_category );
                        
                        foreach ( $counts_by_category as $cat => $count ) : 
                            $percentage = $total > 0 ? round( ( $count / $total ) * 100, 1 ) : 0;
                        ?>
                        <tr>
                            <td style="padding: 8px;">
                                <strong><?php echo esc_html( $cat ); ?></strong>
                            </td>
                            <td style="padding: 8px; text-align: center;">
                                <span style="background: #2271b1; color: white; padding: 2px 8px; border-radius: 3px; font-size: 12px;">
                                    <?php echo esc_html( $count ); ?>
                                </span>
                            </td>
                            <td style="padding: 8px; text-align: center;">
                                <?php echo esc_html( $percentage ); ?>%
                            </td>
                            <td style="padding: 8px;">
                                <div style="background: #dcdcde; height: 8px; border-radius: 4px; overflow: hidden;">
                                    <div style="background: #2271b1; height: 100%; width: <?php echo esc_attr( $percentage ); ?>%;"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else : ?>
            <p style="color: #646970; font-style: italic;">
                <?php esc_html_e( 'Nessuna categoria assegnata ai volontari.', self::TEXT_DOMAIN ); ?>
            </p>
            <?php endif; ?>

            <div style="margin-top: 15px; padding-top: 12px; border-top: 1px solid #dcdcde;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=pcv-volontari' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Gestisci Volontari', self::TEXT_DOMAIN ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=pcv-volontari-categories' ) ); ?>" class="button">
                    <?php esc_html_e( 'Gestisci Categorie', self::TEXT_DOMAIN ); ?>
                </a>
            </div>
        </div>
        <?php
    }
}

