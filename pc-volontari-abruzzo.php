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

// Hook attivazione - deve essere registrato PRIMA di caricare le classi
register_activation_hook( __FILE__, 'pcv_activate_plugin' );

/**
 * Funzione di attivazione del plugin
 *
 * @return void
 */
function pcv_activate_plugin() {
    // Carica le classi necessarie per l'attivazione
    require_once __DIR__ . '/includes/data/class-database.php';
    require_once __DIR__ . '/includes/class-role-manager.php';
    require_once __DIR__ . '/includes/class-installer.php';
    
    // Esegui l'attivazione
    PCV_Installer::activate();
}

// Carica autoloader
require_once __DIR__ . '/includes/class-autoloader.php';

$autoloader = new PCV_Autoloader( __DIR__ . '/includes' );
$autoloader->register();

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

// Inizializza plugin
new PCV_Plugin( __FILE__ );