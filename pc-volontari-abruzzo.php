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

if ( ! defined( 'ABSPATH' ) ) exit;

// Definisci costante percorso file principale
define( 'PCV_PLUGIN_FILE', __FILE__ );

// Hook attivazione - deve essere registrato PRIMA di caricare le classi
register_activation_hook( __FILE__, 'pcv_activate_plugin' );

/**
 * Funzione di attivazione del plugin
 *
 * @return void
 */
function pcv_activate_plugin() {
    // Verifica versione PHP minima
    if ( version_compare( PHP_VERSION, '7.0', '<' ) ) {
        deactivate_plugins( plugin_basename( PCV_PLUGIN_FILE ) );
        wp_die(
            esc_html__( 'Questo plugin richiede PHP 7.0 o superiore.', 'pc-volontari-abruzzo' ),
            esc_html__( 'Errore Attivazione Plugin', 'pc-volontari-abruzzo' ),
            [ 'back_link' => true ]
        );
    }
    
    // Verifica versione WordPress minima
    if ( version_compare( get_bloginfo( 'version' ), '5.0', '<' ) ) {
        deactivate_plugins( plugin_basename( PCV_PLUGIN_FILE ) );
        wp_die(
            esc_html__( 'Questo plugin richiede WordPress 5.0 o superiore.', 'pc-volontari-abruzzo' ),
            esc_html__( 'Errore Attivazione Plugin', 'pc-volontari-abruzzo' ),
            [ 'back_link' => true ]
        );
    }
    
    // Carica le classi necessarie per l'attivazione
    require_once __DIR__ . '/includes/data/class-database.php';
    require_once __DIR__ . '/includes/class-role-manager.php';
    require_once __DIR__ . '/includes/class-installer.php';
    
    // Esegui l'attivazione
    PCV_Installer::activate();
}

// Hook disinstallazione
register_uninstall_hook( __FILE__, 'pcv_uninstall_plugin' );

/**
 * Funzione di disinstallazione del plugin
 *
 * @return void
 */
function pcv_uninstall_plugin() {
    // Carica le classi necessarie per la disinstallazione
    require_once __DIR__ . '/includes/data/class-database.php';
    require_once __DIR__ . '/includes/class-role-manager.php';
    require_once __DIR__ . '/includes/class-installer.php';
    
    // Esegui la disinstallazione
    PCV_Installer::uninstall();
}

// Carica autoloader
require_once __DIR__ . '/includes/class-autoloader.php';

$autoloader = new PCV_Autoloader( __DIR__ . '/includes' );
$autoloader->register();

/**
 * Inizializza il plugin quando WordPress è completamente caricato
 *
 * @return void
 */
function pcv_init_plugin() {
    new PCV_Plugin( PCV_PLUGIN_FILE );
}

// Inizializza plugin dopo che WordPress è completamente caricato
add_action( 'plugins_loaded', 'pcv_init_plugin' );