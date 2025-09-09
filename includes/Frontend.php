<?php
if (!defined('ABSPATH')) exit;

class USAALO_Frontend {

    public function __construct() {
        add_shortcode('usaalo_cotizador', [$this, 'shortcode_render']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX para cotizador
        add_action('wp_ajax_usaalo_calculate_price', [$this, 'ajax_calculate_price']);
        add_action('wp_ajax_nopriv_usaalo_calculate_price', [$this, 'ajax_calculate_price']);

        add_action('wp_ajax_usaalo_calculate_dynamic_price', [$this, 'ajax_calculate_dynamic_price']);
        add_action('wp_ajax_nopriv_usaalo_calculate_dynamic_price', [$this, 'ajax_calculate_dynamic_price']);

        add_action('wp_ajax_usaalo_calculate_final_price', [$this, 'ajax_calculate_final_price']);
        add_action('wp_ajax_nopriv_usaalo_calculate_final_price', [$this, 'ajax_calculate_final_price']);

        add_action('wp_ajax_usaalo_get_models', [$this, 'ajax_get_models']);
        add_action('wp_ajax_nopriv_usaalo_get_models', [$this, 'ajax_get_models']);

        add_action('wp_ajax_usaalo_get_services', [$this, 'ajax_get_services']);
        add_action('wp_ajax_nopriv_usaalo_get_services', [$this, 'ajax_get_services']);

        add_action('wp_ajax_usaalo_get_country_prices', [$this, 'ajax_get_country_prices']);
        add_action('wp_ajax_nopriv_usaalo_get_country_prices', [$this, 'ajax_get_country_prices']);

    }

    /**
     * Encolar scripts y estilos
     */
    public function enqueue_assets() {
        $base = plugin_dir_url(dirname(__FILE__)) . 'assets/';
        wp_enqueue_style('usaalo-select2', $base . 'lib/select2.min.css', [], '4.1.0');
        wp_enqueue_style('usaalo-frontend', $base . 'css/frontend.css', [], time());

        wp_enqueue_script('usaalo-select2', $base . 'lib/select2.min.js', ['jquery'], '4.1.0', true);
        wp_enqueue_script('usaalo-frontend', $base . 'js/frontend.js', ['jquery','usaalo-select2'], time(), true);

        wp_enqueue_script('tippy', 'https://unpkg.com/@popperjs/core@2', [], null, true);
        wp_enqueue_script('tippyjs', 'https://unpkg.com/tippy.js@6', ['tippy'], null, true);
        wp_enqueue_style('tippycss', 'https://unpkg.com/tippy.js@6/dist/tippy.css', [], null);

        // Cargar el caché de productos
        $cache = USAALO_Cache::load_for_frontend();

        
        $services_data = USAALO_Helpers::servicios_disponibles_todos_modelos();
        


        wp_localize_script('usaalo-frontend', 'USAALO_Frontend', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('usaalo_frontend_nonce'),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'allCountries' => USAALO_Helpers::get_countries_with_availability(),
            'allBrands' => USAALO_Helpers::get_brands(),
            'allModels' => USAALO_Helpers::get_all_models(), // precarga modelos
            'simPrices' => USAALO_Helpers::get_sim_prices(),
            'i18n' => [
                'select_country' => __('Selecciona un país', 'usaalo-cotizador'),
                'error' => __('Ocurrió un error', 'usaalo-cotizador'),
            ],
            'products' => $cache,
            'TypeServices'=>$services_data
        ]);
    }

    // public function ajax_get_country_prices() {
    //     check_ajax_referer('usaalo_frontend_nonce', 'nonce');

    //     $country_code = isset($_POST['countries']) ? (array) $_POST['countries'] : [];
    //     $dias = isset($_POST['dias']) ? $_POST['dias'] : 1;
    //     $sim_fisica = isset($_POST['sim_fisica']) ? $_POST['sim_fisica'] : false;

    //     if(empty($country_code)) return wp_send_json_error();

    //     $price = USAALO_Helpers::get_country_prices($country_code, $dias, $sim_fisica);
    //     return wp_send_json_success($price);
    // }


        public function ajax_get_country_prices() {
            // Validar nonce
            check_ajax_referer('usaalo_frontend_nonce', 'nonce');
            
            // Obtener datos
            $country_codes = isset($_POST['countries']) ? array_map('sanitize_text_field', $_POST['countries']) : [];
            $dias          = isset($_POST['dias']) ? intval($_POST['dias']) : 1;
            $sim_fisica    = !empty($_POST['sim_fisica']);

            if (empty($country_codes)) {
                wp_send_json_error(['message' => 'No se han seleccionado países']);
            }

            // Obtener precios usando caché optimizada
            $result = USAALO_Cache::get_country_prices($country_codes, $dias, $sim_fisica);

            wp_send_json_success($result);
        }

    

    /**
     * AJAX para obtener modelos por marca
     */
    public function ajax_get_models() {
        check_ajax_referer('usaalo_frontend_nonce', 'nonce');
        $brand = isset($_POST['brand']) ? intval($_POST['brand']) : 0;
        if(!$brand) return wp_send_json_error();

        $models = USAALO_Helpers::get_models($brand);
        return wp_send_json_success($models);
    }

    /**
     * AJAX para obtener servicios disponibles según país y modelo
     */
    public function ajax_get_services() {
        check_ajax_referer('usaalo_frontend_nonce', 'nonce');
        $countries = isset($_POST['countries']) ? (array) $_POST['countries'] : [];
        $model_id  = isset($_POST['model_id']) ? intval($_POST['model_id']) : 0;

        if(empty($countries) || !$model_id) return wp_send_json_error();

        $services = USAALO_Helpers::servicios_disponibles_por_countries($countries, $model_id);
        return wp_send_json_success($services);
    }

    /**
     * AJAX para cálculo de precio dinámico (durante selección)
     */
    public function ajax_calculate_dynamic_price() {
        check_ajax_referer('usaalo_frontend_nonce', 'nonce');

        $countries  = isset($_POST['country']) ? (array) $_POST['country'] : [];
        $sim_type   = isset($_POST['sim_type']) ? sanitize_text_field($_POST['sim_type']) : 'esim';
        $services   = isset($_POST['services']) ? (array) $_POST['services'] : [];
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date   = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';

        if(empty($countries)) return wp_send_json_error(['errors' => ['Selecciona al menos un país']]);

        $plan_ids = USAALO_Helpers::get_productos_por_country($countries);
        if(empty($plan_ids)) return wp_send_json_error(['errors'=>['No se encontraron planes']]);

        $dias = max(1, self::calculate_days($start_date, $end_date));
        $sim_fisica = ($sim_type === 'sim');

        $precio_total = USAALO_Helpers::calcular_precio_plan($plan_ids, $dias, $services, $sim_fisica);

        wp_send_json_success([
            'total' => wc_price($precio_total),
            'days'  => $dias,
        ]);
    }

    /**
     * AJAX para cálculo final y guardar cotización en sesión
     */
    public function ajax_calculate_final_price() {
        check_ajax_referer('usaalo_frontend_nonce', 'nonce');

        $countries  = isset($_POST['country']) ? (array) $_POST['country'] : [];
        $sim_type   = isset($_POST['sim_type']) ? sanitize_text_field($_POST['sim_type']) : 'esim';
        $services   = isset($_POST['services']) ? (array) $_POST['services'] : [];
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date   = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $brand      = isset($_POST['brand']) ? intval($_POST['brand']) : null;
        $model      = isset($_POST['model']) ? intval($_POST['model']) : null;

        if(empty($countries)) return wp_send_json_error(['errors'=>['Selecciona al menos un país']]);

        $plan_ids = USAALO_Helpers::get_productos_por_country($countries);
        if(empty($plan_ids)) return wp_send_json_error(['errors'=>['No se encontraron planes']]);

        $dias = max(1, self::calculate_days($start_date, $end_date));
        $sim_fisica = ($sim_type === 'sim');

        $precio_total = USAALO_Helpers::calcular_precio_plan($plan_ids, $dias, $services, $sim_fisica);

        // Guardar en sesión WooCommerce para checkout
        WC()->session->set('usaalo_cotizador', [
            'countries' => $countries,
            'sim_type'  => $sim_type,
            'services'  => $services,
            'start_date'=> $start_date,
            'end_date'  => $end_date,
            'brand'     => $brand,
            'model'     => $model,
            'days'      => $dias,
            'plan_ids'  => $plan_ids,
            'total'     => $precio_total,
        ]);

        wp_send_json_success([
            'total' => wc_price($precio_total),
            'days'  => $dias,
            'plan_ids' => $plan_ids,
        ]);
    }

    /**
     * Calcular días entre fechas
     */
    private static function calculate_days($start, $end){
        if(!$start || !$end) return 1;
        $start_ts = strtotime($start);
        $end_ts = strtotime($end);
        if($start_ts && $end_ts && $end_ts >= $start_ts){
            return ($end_ts - $start_ts)/86400 + 1; // inclusivo
        }
        return 1;
    }

    /**
     * Renderiza el shortcode
     */
    public function shortcode_render($atts = []) {
        $countries = USAALO_Helpers::get_countries();
        $brands = USAALO_Helpers::get_brands();
        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/frontend-template.php';
        return ob_get_clean();
    }

}
