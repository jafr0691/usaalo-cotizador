<?php
if (!defined('ABSPATH')) exit;

class USAALO_Admin {

    private $helpers;

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        // Hook al eliminar un producto
        add_action('before_delete_post', [self::class, 'delete_product_country_records']);

        // Helpers: carga si no existe
        if ( ! class_exists('USAALO_Helpers') ) {
            require_once USAALO_PATH . 'includes/helpers.php';
        }
        $this->helpers = new USAALO_Helpers();

        // Nota: endpoints AJAX se registran en includes/ajax.php (mejor mantener separados)
    }

    /**
     * Añade el menú y submenús, la URL principal abre Countries
     */
    public function add_menu() {
        // Menú principal "Cotizador" que abre directamente "Países"
        add_menu_page(
            __('Usaalo Cotizador', 'usaalo-cotizador'),
            __('Usaalo Cotizador', 'usaalo-cotizador'),
            'manage_options',
            'usaalo-cotizador',          // slug
            [$this, 'render_countries_page'],
            'dashicons-cart',
            56
        );

        // Submenús: usan como parent el slug del menú principal
        add_submenu_page(
            'usaalo-cotizador',
            __('Países', 'usaalo-cotizador'),
            __('Países', 'usaalo-cotizador'),
            'manage_options',
            'usaalo-cotizador-countries',
            [$this, 'render_countries_page']
        );

        add_submenu_page(
            'usaalo-cotizador',
            __('Marcas y Modelos', 'usaalo-cotizador'),
            __('Marcas y Modelos', 'usaalo-cotizador'),
            'manage_options',
            'usaalo-cotizador-brands-models',
            [$this, 'render_brands_models_page']
        );

        add_submenu_page(
            'usaalo-cotizador',
            __('Tipo SIM y Servicios', 'usaalo-cotizador'),
            __('Tipo SIM y Servicios', 'usaalo-cotizador'),
            'manage_options',
            'usaalo-cotizador-sim-servicio',
            [$this, 'render_sim_servicio_page']
        );

        add_submenu_page(
            'usaalo-cotizador',
            __('Planes', 'usaalo-cotizador'),
            __('Planes', 'usaalo-cotizador'),
            'manage_options',
            'usaalo-cotizador-plans',
            [$this, 'render_plans_page']
        );
    }

    /**
     * Registra/Enqueue assets solo para las páginas del plugin.
     * Pasa al JS el hook actual para inicialización condicional.
     */
    public function enqueue_assets($hook) {
        // Los $hook devueltos por add_menu_page/add_submenu_page suelen ser:
        // 'toplevel_page_{slug}' para la página top-level,
        // '{parent_slug}_page_{submenu_slug}' para submenus.
        $allowed_hooks = [
            'toplevel_page_usaalo-cotizador',                       // menú principal
            'usaalo-cotizador_page_usaalo-cotizador-countries',    // submenú Countries
            'usaalo-cotizador_page_usaalo-cotizador-brands-models', // submenú Brands & Models
            'usaalo-cotizador_page_usaalo-cotizador-sim-servicio',  // submenú SIM & Services
            'usaalo-cotizador_page_usaalo-cotizador-plans'          // submenú Plans
        ];

        if (!in_array($hook, $allowed_hooks)) {
            return; // no cargar assets fuera de nuestras páginas admin
        }

        // Rutas base
        $base = USAALO_URL . 'assets/';

        // Registrar estilos
        wp_register_style('usaalo-select2', $base . 'lib/select2.min.css', [], '4.1.0');
        wp_register_style('usaalo-datatables', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css', [], '1.13.6');
        wp_register_style('usaalo-admin', $base . 'css/admin.css', [], time());

        // Enqueue estilos
        wp_enqueue_style('usaalo-select2');
        wp_enqueue_style('usaalo-datatables');
        wp_enqueue_style('usaalo-admin');
        wp_enqueue_media();

        // Registrar scripts
        wp_register_script('usaalo-select2', $base . 'lib/select2.min.js', ['jquery'], '4.1.0', true);
        wp_register_script('usaalo-datatables', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', ['jquery'], '1.13.6', true);
        //USAALO_VERSION
        wp_register_script('usaalo-admin', $base . 'js/admin.js', ['jquery','usaalo-select2','usaalo-datatables'], time(), true);

        // Enqueue scripts
        wp_enqueue_script('usaalo-select2');
        wp_enqueue_script('usaalo-datatables');
        wp_enqueue_script('usaalo-admin');

        // Pasar datos útiles al JS: ajaxurl, nonce, traducciones e información del hook actual
        wp_localize_script('usaalo-admin', 'USAALO_Admin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('usaalo_admin_nonce'),
            'page'  => $hook, // <-- importante: pasar el hook real a JS
            'i18n' => [
                'saved' => __('Guardado correctamente', 'usaalo-cotizador'),
                'deleted' => __('Eliminado correctamente', 'usaalo-cotizador'),
                'confirm_delete' => __('¿Seguro que deseas eliminar este elemento?', 'usaalo-cotizador'),
            ],
        ]);

    }


    public static function delete_product_country_records($post_id) {
        // Solo aplicamos si es producto de WooCommerce
        if (get_post_type($post_id) !== 'product') return;

        global $wpdb;
        $table = $wpdb->prefix . 'usaalo_product_country';

        $wpdb->delete(
            $table,
            ['product_id' => $post_id],
            ['%d']
        );
    }


    /* ---------- Renderers de página ---------- */

    public function render_countries_page() {
        if (!current_user_can('manage_options')) wp_die(__('No autorizado','usaalo-cotizador'));
        $countries = method_exists($this->helpers, 'get_countries') ? $this->helpers->get_countries() : [];
        include USAALO_PATH . 'includes/templates/admin-countries-template.php';
    }

    public function render_brands_models_page() {
        if (!current_user_can('manage_options')) wp_die(__('No autorizado','usaalo-cotizador'));
        $brands    = method_exists($this->helpers, 'get_brands') ? $this->helpers->get_brands() : [];
        $models    = method_exists($this->helpers, 'get_models') ? $this->helpers->get_models() : [];
        include USAALO_PATH . 'includes/templates/admin-brands-models-template.php';
    }

    public function render_sim_servicio_page() {
        if (!current_user_can('manage_options')) wp_die(__('No autorizado','usaalo-cotizador'));
        include USAALO_PATH . 'includes/templates/admin-sim_servicio-template.php';
    }

    public function render_plans_page() {
        if (!current_user_can('manage_options')) wp_die(__('No autorizado','usaalo-cotizador'));
        // Nota: Los datos de productos (precio/desc) se consultan en WooCommerce cuando se necesiten.
        include USAALO_PATH . 'includes/templates/admin-plans-template.php';
    }
}

