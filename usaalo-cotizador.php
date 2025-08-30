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
define('USAALO_PATH', plugin_dir_path(__FILE__));
define('USAALO_URL', plugin_dir_url(__FILE__));
define('USAALO_VERSION', '1.0.0');

// Cargar traducciones
function usaalo_load_textdomain() {
    load_plugin_textdomain('usaalo-cotizador', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'usaalo_load_textdomain');

// Incluir archivos necesarios
require_once USAALO_PATH . 'includes/install.php';
require_once USAALO_PATH . 'includes/helpers.php';
require_once USAALO_PATH . 'includes/admin.php';
require_once USAALO_PATH . 'includes/frontend.php';
require_once USAALO_PATH . 'includes/ajax.php';

// Activación del plugin
register_activation_hook(__FILE__, ['USAALO_Installer', 'activate']);

// Desactivación del plugin
register_deactivation_hook(__FILE__, ['USAALO_Installer', 'deactivate']);

// Colocar scripts y estilos
function usaalo_enqueue_scripts() {
    // CSS
    wp_enqueue_style('usaalo-frontend', USAALO_URL . 'assets/css/frontend.css', [], USAALO_VERSION);
    wp_enqueue_style('select2-css', USAALO_URL . 'assets/lib/select2.min.css', [], '4.1.0');

    // JS
    wp_enqueue_script('select2-js', USAALO_URL . 'assets/lib/select2.min.js', ['jquery'], '4.1.0', true);
    wp_enqueue_script('usaalo-frontend', USAALO_URL . 'assets/js/frontend.js', ['jquery', 'select2-js'], USAALO_VERSION, true);

    // Localización para AJAX
    wp_localize_script('usaalo-frontend', 'USAALO_Ajax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('usaalo_nonce'),
    ]);
}
add_action('wp_enqueue_scripts', 'usaalo_enqueue_scripts');

// Colocar scripts y estilos admin
function usaalo_enqueue_admin_scripts($hook) {
    // Solo en las páginas del plugin
    if (strpos($hook, 'usaalo') === false) return;

    wp_enqueue_style('usaalo-admin', USAALO_URL . 'assets/css/admin.css', [], USAALO_VERSION);
    wp_enqueue_script('usaalo-admin', USAALO_URL . 'assets/js/admin.js', ['jquery', 'select2-js'], USAALO_VERSION, true);

    wp_localize_script('usaalo-admin', 'USAALO_Admin', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('usaalo_admin_nonce'),
    ]);
}
add_action('admin_enqueue_scripts', 'usaalo_enqueue_admin_scripts');

// Crear menú en el admin
function usaalo_admin_menu() {
    add_menu_page(
        __('Usaalo Cotizador', 'usaalo-cotizador'),
        __('Cotizador', 'usaalo-cotizador'),
        'manage_options',
        'usaalo-cotizador',
        'usaalo_admin_page',
        'dashicons-cart',
        60
    );
}
add_action('admin_menu', 'usaalo_admin_menu');

function usaalo_admin_page() {
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Usaalo Cotizador', 'usaalo-cotizador') . '</h1>';
    echo '<p>' . esc_html__('Administrar planes, países, SIMs y reglas de precios.', 'usaalo-cotizador') . '</p>';
    // Aquí se incluirán las tablas y formularios desde admin.php
    if (function_exists('usaalo_admin_render')) {
        usaalo_admin_render();
    }
    echo '</div>';
}
