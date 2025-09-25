<?php
/**
 * Plugin Name: Usaalo Cotizador
 * Plugin URI:  https://github.com/jafr0691/usaalo-cotizador
 * Description: Cotizador de planes SIM/eSIM internacional con integración WooCommerce, variaciones por país, tipo de SIM y rango de días.
 * Version:     1.0.0
 * Author:      Jesus Farias
 * Author URI:  https://github.com/jafr0691
 * Text Domain: usaalo-cotizador
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit; // Evitar acceso directo

// Definir constantes
define( 'MSSQL_DSN', 'dblib:host=sql5110.site4now.net,1433;dbname=db_a9d2cf_usaaloapp' );
define( 'MSSQL_USERNAME', 'db_a9d2cf_usaaloapp_admin' );
define( 'MSSQL_PASSWORD', 'ujKVTXAE2za' );
define('USAALO_PATH', plugin_dir_path(__FILE__));
define('USAALO_URL', plugin_dir_url(__FILE__));
define('USAALO_VERSION', '1.0.0');

// Cargar traducciones
function usaalo_load_textdomain() {
    load_plugin_textdomain('usaalo-cotizador', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'usaalo_load_textdomain');

// Incluir archivos necesarios
require_once USAALO_PATH . 'includes/Install.php';
require_once USAALO_PATH . 'includes/Helpers.php';
require_once USAALO_PATH . 'includes/Admin.php';
require_once USAALO_PATH . 'includes/Frontend.php';
require_once USAALO_PATH . 'includes/Ajax.php';
require_once USAALO_PATH . 'includes/class-usaalo-cache.php';
require_once USAALO_PATH . 'includes/WC.php';
require_once USAALO_PATH . 'includes/class-usaalo-mssql-sync.php';

// Activación del plugin
register_activation_hook(__FILE__, ['USAALO_Installer', 'activate']);

// Desactivación del plugin
register_deactivation_hook(__FILE__, ['USAALO_Installer', 'deactivate']);

// Desinstalación del plugin
register_uninstall_hook(__FILE__, ['USAALO_Installer', 'uninstall']);

// Inicializar clases según el contexto (admin o frontend)
add_action('init', function() {
    // USAALO_Cache::build_cache();
    if (is_admin()) {
        if (class_exists('USAALO_Admin')) {
            new USAALO_Admin();
        }
    }
    if (class_exists('USAALO_Frontend')) {
        new USAALO_Frontend();
    }
    if (class_exists('USAALO_Ajax')) {
        new USAALO_Ajax();
    }
    if (class_exists('WooCommerce')) {
        new USAALO_Checkout_Fields(); // campos en checkout
        new USAALO_MSSQL_Sync();      // sincronización MSSQL
    }

});





