<?php
/**
 * Plugin Name: Usaalo Cotizador
 * Description: Wizard SIM/eSIM internacional con compatibilidad por país/dispositivo, reglas de precio por rangos y conexión WooCommerce.
 * Author: Usaalo
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC tested up to: 8.9
 */

if (!defined('ABSPATH')) exit;

define('USAC_PATH', plugin_dir_path(__FILE__));
define('USAC_URL', plugin_dir_url(__FILE__));
define('USAC_VER', '1.0.0');

// Autoload simple
spl_autoload_register(function($class){
    if (strpos($class, 'USAC_') !== 0) return;
    $file = USAC_PATH . 'includes/' . str_replace('USAC_', '', $class) . '.php';
    if (file_exists($file)) require_once $file;
});

// Hooks
register_activation_hook(__FILE__, ['USAC_Installer', 'activate']);
register_deactivation_hook(__FILE__, ['USAC_Installer', 'deactivate']);

// Public + Shortcodes
add_action('init', ['USAC_Frontend', 'register_shortcodes']);
add_action('wp_enqueue_scripts', ['USAC_Frontend', 'enqueue_assets']);

// Admin
add_action('admin_menu', ['USAC_Admin', 'menu']);
add_action('admin_init', ['USAC_Admin', 'register_settings']);
add_action('admin_enqueue_scripts', ['USAC_Admin', 'enqueue_assets']);

// Importador
add_action('admin_post_usac_import_countries', ['USAC_Importer', 'import_countries']);
add_action('admin_post_usac_import_devices', ['USAC_Importer', 'import_devices']);

// AJAX (guest + logged)
foreach (['nopriv',''] as $scope) {
    add_action("wp_ajax_{$scope}_usac_get_countries", ['USAC_Ajax','get_countries']);
    add_action("wp_ajax_{$scope}_usac_get_brands", ['USAC_Ajax','get_brands']);
    add_action("wp_ajax_{$scope}_usac_get_models", ['USAC_Ajax','get_models']);
    add_action("wp_ajax_{$scope}_usac_check_compat", ['USAC_Ajax','check_compat']);
    add_action("wp_ajax_{$scope}_usac_quote", ['USAC_Ajax','quote']);
    add_action("wp_ajax_{$scope}_usac_add_to_cart", ['USAC_Ajax','add_to_cart']);
}

// WooCommerce meta
add_filter('woocommerce_add_cart_item_data', ['USAC_WC','add_cart_item_data'], 10, 3);
add_action('woocommerce_checkout_create_order_line_item', ['USAC_WC','save_order_item_meta'], 10, 4);


/**
 * Declarar compatibilidad automática con HPOS
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables', 
            __FILE__, 
            true // true = 100% compatible
        );
    }
});

/**
 * Activar automáticamente HPOS al activar el plugin
 */
register_activation_hook( __FILE__, function() {
    // Obtenemos las funciones experimentales actuales
    $features = get_option( 'woocommerce_feature_enabled', [] );

    if ( ! is_array( $features ) ) {
        $features = [];
    }

    // Forzamos activación de HPOS
    if ( ! in_array( 'custom_order_tables', $features, true ) ) {
        $features[] = 'custom_order_tables';
        update_option( 'woocommerce_feature_enabled', $features );
    }
});
