<?php
/**
 * Autoloader per classi del plugin
 *
 * @package PC_Volontari_Abruzzo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PCV_Autoloader {

    private $includes_dir;

    /**
     * Costruttore
     *
     * @param string $includes_dir Directory includes
     */
    public function __construct( $includes_dir ) {
        $this->includes_dir = $includes_dir;
    }

    /**
     * Registra autoloader
     *
     * @return void
     */
    public function register() {
        spl_autoload_register( [ $this, 'autoload' ] );
    }

    /**
     * Autoload classi
     *
     * @param string $class Nome classe
     * @return void
     */
    private function autoload( $class ) {
        // Solo classi del plugin
        if ( strpos( $class, 'PCV_' ) !== 0 ) {
            return;
        }

        // Mappa classi => file
        $class_map = [
            // Core
            'PCV_Plugin'          => 'class-plugin.php',
            'PCV_Installer'       => 'class-installer.php',
            'PCV_Data_Loader'     => 'class-data-loader.php',
            'PCV_Role_Manager'    => 'class-role-manager.php',
            'PCV_Upgrade_Manager' => 'class-upgrade-manager.php',

            // Data
            'PCV_Database'   => 'data/class-database.php',
            'PCV_Repository' => 'data/class-repository.php',

            // Services
            'PCV_Sanitizer' => 'services/class-sanitizer.php',
            'PCV_Validator' => 'services/class-validator.php',
            'PCV_Notifier'  => 'services/class-notifier.php',

            // Integrations
            'PCV_Recaptcha' => 'integrations/class-recaptcha.php',

            // Import/Export
            'PCV_Importer'    => 'import-export/class-importer.php',
            'PCV_Exporter'    => 'import-export/class-exporter.php',
            'PCV_CSV_Parser'  => 'import-export/parsers/class-csv-parser.php',
            'PCV_XLSX_Parser' => 'import-export/parsers/class-xlsx-parser.php',

            // Frontend
            'PCV_Assets_Manager' => 'frontend/class-assets-manager.php',
            'PCV_Form_Handler'   => 'frontend/class-form-handler.php',
            'PCV_Shortcode'      => 'frontend/class-shortcode.php',

            // Admin
            'PCV_Admin_Menu'       => 'admin/class-admin-menu.php',
            'PCV_Admin_Assets'     => 'admin/class-admin-assets.php',
            'PCV_List_Table'       => 'admin/class-list-table.php',
            'PCV_Settings_Page'    => 'admin/class-settings-page.php',
            'PCV_Import_Page'      => 'admin/class-import-page.php',
            'PCV_Ajax_Handler'     => 'admin/class-ajax-handler.php',
            'PCV_Categories_Page'  => 'admin/class-categories-page.php',
            'PCV_Notes_Page'       => 'admin/class-notes-page.php',
            'PCV_Dashboard_Widget' => 'admin/class-dashboard-widget.php',
            'PCV_Comuni_Normalizer' => 'admin/class-comuni-normalizer.php',

            // Categories
            'PCV_Category_Manager' => 'class-category-manager.php',
            
            // Notes
            'PCV_Notes_Manager'    => 'class-notes-manager.php',
        ];

        if ( isset( $class_map[ $class ] ) ) {
            $file = $this->includes_dir . '/' . $class_map[ $class ];
            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }
    }
}