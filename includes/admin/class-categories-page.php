<?php
/**
 * Pagina gestione categorie
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Categories_Page {

    const TEXT_DOMAIN = 'pc-volontari-abruzzo';

    /**
     * Renderizza la pagina categorie
     *
     * @return void
     */
    public function render() {
        if ( ! PCV_Role_Manager::can_manage_settings() ) {
            return;
        }

        // Gestione azioni
        $message = '';
        $error = '';

        if ( isset( $_POST['pcv_add_category'] ) && check_admin_referer( 'pcv_add_category_nonce' ) ) {
            $new_category = isset( $_POST['new_category'] ) ? sanitize_text_field( wp_unslash( $_POST['new_category'] ) ) : '';
            
            if ( ! empty( $new_category ) ) {
                if ( PCV_Category_Manager::add_category( $new_category ) ) {
                    $message = __( 'Categoria aggiunta con successo.', self::TEXT_DOMAIN );
                } else {
                    $error = __( 'La categoria esiste già o il nome non è valido.', self::TEXT_DOMAIN );
                }
            } else {
                $error = __( 'Il nome della categoria non può essere vuoto.', self::TEXT_DOMAIN );
            }
        }

        if ( isset( $_POST['pcv_delete_category'] ) && check_admin_referer( 'pcv_delete_category_nonce' ) ) {
            $category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
            
            if ( ! empty( $category ) ) {
                if ( PCV_Category_Manager::delete_category( $category ) ) {
                    $message = __( 'Categoria eliminata con successo.', self::TEXT_DOMAIN );
                } else {
                    $error = __( 'Impossibile eliminare la categoria.', self::TEXT_DOMAIN );
                }
            }
        }

        if ( isset( $_POST['pcv_rename_category'] ) && check_admin_referer( 'pcv_rename_category_nonce' ) ) {
            $old_name = isset( $_POST['old_name'] ) ? sanitize_text_field( wp_unslash( $_POST['old_name'] ) ) : '';
            $new_name = isset( $_POST['new_name'] ) ? sanitize_text_field( wp_unslash( $_POST['new_name'] ) ) : '';
            
            if ( ! empty( $old_name ) && ! empty( $new_name ) ) {
                if ( PCV_Category_Manager::rename_category( $old_name, $new_name ) ) {
                    $message = __( 'Categoria rinominata con successo. I volontari associati sono stati aggiornati.', self::TEXT_DOMAIN );
                } else {
                    $error = __( 'Impossibile rinominare la categoria.', self::TEXT_DOMAIN );
                }
            }
        }

        if ( isset( $_POST['pcv_reset_categories'] ) && check_admin_referer( 'pcv_reset_categories_nonce' ) ) {
            PCV_Category_Manager::save_categories( PCV_Category_Manager::get_default_categories() );
            $message = __( 'Categorie ripristinate ai valori predefiniti.', self::TEXT_DOMAIN );
        }

        $categories = PCV_Category_Manager::get_categories();
        $counts = PCV_Category_Manager::count_volunteers_by_category();
        $used_categories = PCV_Category_Manager::get_used_categories();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Gestione Categorie', self::TEXT_DOMAIN ); ?></h1>

            <?php if ( $message ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html( $message ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( $error ) : ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html( $error ); ?></p>
                </div>
            <?php endif; ?>

            <div class="pcv-categories-wrapper" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                
                <!-- Colonna sinistra: Aggiungi categoria -->
                <div class="pcv-add-category-box">
                    <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e( 'Aggiungi Nuova Categoria', self::TEXT_DOMAIN ); ?></span></h2>
                        <div class="inside">
                            <form method="post">
                                <?php wp_nonce_field( 'pcv_add_category_nonce' ); ?>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="new_category"><?php esc_html_e( 'Nome Categoria', self::TEXT_DOMAIN ); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" id="new_category" name="new_category" class="regular-text" required>
                                            <p class="description"><?php esc_html_e( 'Inserisci il nome della nuova categoria.', self::TEXT_DOMAIN ); ?></p>
                                        </td>
                                    </tr>
                                </table>
                                <?php submit_button( __( 'Aggiungi Categoria', self::TEXT_DOMAIN ), 'primary', 'pcv_add_category' ); ?>
                            </form>
                        </div>
                    </div>

                    <!-- Statistiche -->
                    <div class="postbox" style="margin-top: 20px;">
                        <h2 class="hndle"><span><?php esc_html_e( 'Statistiche Categorie', self::TEXT_DOMAIN ); ?></span></h2>
                        <div class="inside">
                            <?php if ( ! empty( $counts ) ) : ?>
                                <table class="widefat striped">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e( 'Categoria', self::TEXT_DOMAIN ); ?></th>
                                            <th><?php esc_html_e( 'Numero Volontari', self::TEXT_DOMAIN ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $counts as $cat => $count ) : ?>
                                            <tr>
                                                <td><strong><?php echo esc_html( $cat ); ?></strong></td>
                                                <td><?php echo esc_html( $count ); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else : ?>
                                <p><?php esc_html_e( 'Nessun volontario categorizzato.', self::TEXT_DOMAIN ); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Colonna destra: Elenco categorie -->
                <div class="pcv-categories-list-box">
                    <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e( 'Categorie Predefinite', self::TEXT_DOMAIN ); ?></span></h2>
                        <div class="inside">
                            <?php if ( ! empty( $categories ) ) : ?>
                                <table class="widefat striped">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e( 'Nome', self::TEXT_DOMAIN ); ?></th>
                                            <th><?php esc_html_e( 'Volontari', self::TEXT_DOMAIN ); ?></th>
                                            <th><?php esc_html_e( 'Azioni', self::TEXT_DOMAIN ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $categories as $category ) : ?>
                                            <tr>
                                                <td><strong><?php echo esc_html( $category ); ?></strong></td>
                                                <td><?php echo isset( $counts[ $category ] ) ? esc_html( $counts[ $category ] ) : '0'; ?></td>
                                                <td>
                                                    <button type="button" class="button button-small pcv-rename-btn" data-category="<?php echo esc_attr( $category ); ?>">
                                                        <?php esc_html_e( 'Rinomina', self::TEXT_DOMAIN ); ?>
                                                    </button>
                                                    <form method="post" style="display: inline;">
                                                        <?php wp_nonce_field( 'pcv_delete_category_nonce' ); ?>
                                                        <input type="hidden" name="category" value="<?php echo esc_attr( $category ); ?>">
                                                        <button type="submit" name="pcv_delete_category" class="button button-small button-link-delete" 
                                                            onclick="return confirm('<?php echo esc_js( __( 'Sei sicuro di voler eliminare questa categoria? I volontari non saranno eliminati.', self::TEXT_DOMAIN ) ); ?>');">
                                                            <?php esc_html_e( 'Elimina', self::TEXT_DOMAIN ); ?>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                                <form method="post" style="margin-top: 15px;">
                                    <?php wp_nonce_field( 'pcv_reset_categories_nonce' ); ?>
                                    <button type="submit" name="pcv_reset_categories" class="button" 
                                        onclick="return confirm('<?php echo esc_js( __( 'Ripristinare le categorie predefinite? Le categorie personalizzate saranno eliminate.', self::TEXT_DOMAIN ) ); ?>');">
                                        <?php esc_html_e( 'Ripristina Categorie Predefinite', self::TEXT_DOMAIN ); ?>
                                    </button>
                                </form>
                            <?php else : ?>
                                <p><?php esc_html_e( 'Nessuna categoria definita.', self::TEXT_DOMAIN ); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Categorie in uso non predefinite -->
                    <?php
                    $unlisted = array_diff( $used_categories, $categories );
                    if ( ! empty( $unlisted ) ) :
                    ?>
                        <div class="postbox" style="margin-top: 20px;">
                            <h2 class="hndle"><span><?php esc_html_e( 'Categorie Usate (non predefinite)', self::TEXT_DOMAIN ); ?></span></h2>
                            <div class="inside">
                                <p class="description">
                                    <?php esc_html_e( 'Queste categorie sono usate da alcuni volontari ma non sono nell\'elenco predefinito.', self::TEXT_DOMAIN ); ?>
                                </p>
                                <ul style="list-style: disc; margin-left: 20px;">
                                    <?php foreach ( $unlisted as $cat ) : ?>
                                        <li>
                                            <strong><?php echo esc_html( $cat ); ?></strong> 
                                            (<?php echo isset( $counts[ $cat ] ) ? esc_html( $counts[ $cat ] ) : '0'; ?> 
                                            <?php esc_html_e( 'volontari', self::TEXT_DOMAIN ); ?>)
                                            <form method="post" style="display: inline;">
                                                <?php wp_nonce_field( 'pcv_add_category_nonce' ); ?>
                                                <input type="hidden" name="new_category" value="<?php echo esc_attr( $cat ); ?>">
                                                <button type="submit" name="pcv_add_category" class="button button-small">
                                                    <?php esc_html_e( 'Aggiungi a predefinite', self::TEXT_DOMAIN ); ?>
                                                </button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Modal Rinomina Categoria -->
        <div id="pcv-rename-modal" class="pcv-admin-modal" style="display: none;">
            <div class="pcv-modal-content" style="max-width: 500px;">
                <span class="pcv-modal-close">&times;</span>
                <h2><?php esc_html_e( 'Rinomina Categoria', self::TEXT_DOMAIN ); ?></h2>
                <form method="post" id="pcv-rename-form">
                    <?php wp_nonce_field( 'pcv_rename_category_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e( 'Nome Attuale', self::TEXT_DOMAIN ); ?></label>
                            </th>
                            <td>
                                <input type="text" id="pcv-old-name" name="old_name" class="regular-text" readonly>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="pcv-new-name"><?php esc_html_e( 'Nuovo Nome', self::TEXT_DOMAIN ); ?></label>
                            </th>
                            <td>
                                <input type="text" id="pcv-new-name" name="new_name" class="regular-text" required>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" name="pcv_rename_category" class="button button-primary">
                            <?php esc_html_e( 'Rinomina', self::TEXT_DOMAIN ); ?>
                        </button>
                        <button type="button" class="button pcv-modal-cancel">
                            <?php esc_html_e( 'Annulla', self::TEXT_DOMAIN ); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>

        <script>
        (function($) {
            $(document).ready(function() {
                // Apri modal rinomina
                $('.pcv-rename-btn').on('click', function() {
                    var category = $(this).data('category');
                    $('#pcv-old-name').val(category);
                    $('#pcv-new-name').val(category);
                    $('#pcv-rename-modal').show();
                });

                // Chiudi modal
                $('.pcv-modal-close, .pcv-modal-cancel').on('click', function() {
                    $('#pcv-rename-modal').hide();
                });

                // Chiudi cliccando fuori
                $(window).on('click', function(e) {
                    if ($(e.target).is('#pcv-rename-modal')) {
                        $('#pcv-rename-modal').hide();
                    }
                });
            });
        })(jQuery);
        </script>
        <?php
    }
}

