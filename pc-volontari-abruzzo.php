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

// Carica autoloader
require_once __DIR__ . '/includes/class-autoloader.php';

$autoloader = new PCV_Autoloader( __DIR__ . '/includes' );
$autoloader->register();

// Hook attivazione
register_activation_hook( __FILE__, [ 'PCV_Installer', 'activate' ] );

// Hook disinstallazione
register_uninstall_hook( __FILE__, [ 'PCV_Installer', 'uninstall' ] );

// Inizializza plugin
new PCV_Plugin( __FILE__ );