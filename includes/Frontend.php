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

        add_action('wp_ajax_usaalo_add_multiple_to_cart', [$this, 'ajax_usaalo_add_multiple_to_cart']);
        add_action('wp_ajax_nopriv_usaalo_add_multiple_to_cart', [$this, 'ajax_usaalo_add_multiple_to_cart']);

        add_action('woocommerce_before_calculate_totals', function($cart) {
            foreach ($cart->get_cart() as &$cart_item) { // 🔹 referencia obligatoria
                if (!empty($cart_item['usaalo_data']['custom_price'])) {
                    $cart_item['data']->set_price(floatval($cart_item['usaalo_data']['custom_price']));
                }
            }
        });


        add_filter('woocommerce_get_item_data', function($item_data, $cart_item){
            if (!empty($cart_item['usaalo_data'])) {
                $d = $cart_item['usaalo_data'];
                $item_data[] = ['name'=>'Países','value'=>implode(', ', $d['countries'] ?? [])];
                $item_data[] = ['name'=>'Marca','value'=>$d['brand'] ?? ''];
                $item_data[] = ['name'=>'Modelo','value'=>$d['model'] ?? ''];
                $item_data[] = ['name'=>'Servicios','value'=>implode(', ', $d['services'] ?? [])];
                $item_data[] = ['name'=>'Días','value'=>$d['days'] ?? ''];
                $item_data[] = ['name'=>'SIM','value'=>$d['sim'] ?? ''];
                $item_data[] = ['name'=>'Inicio','value'=>$d['start_date'] ?? ''];
                $item_data[] = ['name'=>'Fin','value'=>$d['end_date'] ?? ''];
            }
            return $item_data;
        }, 10, 2);

        add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order){
            if (!empty($values['usaalo_data'])) {
                foreach ($values['usaalo_data'] as $key=>$val) {
                    if (is_array($val)) $val = implode(', ', $val);
                    $item->add_meta_data('usaalo_'.$key, $val);
                }
            }
        }, 10, 4);

        // ----------------------
        // Quitar envío si todos los productos son eSIM
        // ----------------------
        add_filter('woocommerce_package_rates', function($rates, $package){
            $solo_esim = true;
            foreach (WC()->cart->get_cart() as $cart_item) {
                if (isset($cart_item['usaalo_data']['sim']) && strtolower($cart_item['usaalo_data']['sim']) !== 'esim') {
                    $solo_esim = false;
                    break;
                }
            }

            if ($solo_esim) return []; // no mostrar envíos
            return $rates;
        }, 10, 2);

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
        
        // $shipping_costs = [];
        // foreach ( USAALO_Helpers::get_countries() as $c ) {
        //     $shipping_costs[$c['code']] = USAALO_Helpers::get_shipping_cost($c['code']);
        // }

        wp_localize_script('usaalo-frontend', 'USAALO_Frontend', [
            'ajaxurl'          => admin_url('admin-ajax.php'),
            'nonce'            => wp_create_nonce('usaalo_frontend_nonce'),
            'currency_symbol'  => get_woocommerce_currency_symbol(),
            'allCountries'     => USAALO_Helpers::get_countries_with_availability(),
            'allBrands'        => USAALO_Helpers::get_brands(),
            'allModels'        => USAALO_Helpers::get_all_models(),
            'simPrices'        => USAALO_Helpers::get_sim_prices(),
            'shipping_cost'    => USAALO_Helpers::get_shipping_cost('CO'),
            'i18n'             => [
                'select_country' => __('Selecciona un país', 'usaalo-cotizador'),
                'error'          => __('Ocurrió un error', 'usaalo-cotizador'),
            ],
            'products'         => $cache,
            'TypeServices'     => $services_data,
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

        
    public function ajax_usaalo_add_multiple_to_cart() {
        check_ajax_referer('usaalo_frontend_nonce','nonce');

        $countries_map = $_POST['countries'] ?? [];
        $products      = $_POST['products'] ?? [];

        if (empty($products)) {
            wp_send_json_error(['message'=>'No hay productos para añadir']);
        }

        // Vaciar carrito
        if (WC()->cart) {
            WC()->cart->empty_cart(true);
        }

        $common = [
            'days'       => intval($_POST['days'] ?? 1),
            'brand'      => sanitize_text_field($_POST['brand'] ?? ''),
            'model'      => sanitize_text_field($_POST['model'] ?? ''),
            'sim'        => sanitize_text_field($_POST['sim'] ?? 'eSIM'),
            'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
            'end_date'   => sanitize_text_field($_POST['end_date'] ?? ''),
            'services'   => (array)($_POST['services'] ?? [])
        ];

        $added = [];

        foreach ($products as $p) {
            $product_id = intval($p['product_id']);
            $price      = floatval($p['price']);
            $codes      = $p['countries'] ?? [];

            $countries = [];
            foreach ($codes as $code) {
                $countries[] = sanitize_text_field($countries_map[$code] ?? $code);
            }

            $product = wc_get_product($product_id);
            if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) {
                error_log("⚠️ Producto $product_id no disponible");
                continue;
            }

            $variation_id   = 0;
            $variation_data = [];
            $custom_attr    = '';

            // 🔹 Si es variable, buscar variación según días
            if ($product->is_type('variable')) {
                $ranges = [];
                foreach ($product->get_available_variations() as $var) {
                    $attr = $var['attributes']['attribute_pa_rango-de-dias'] ?? '';
                    if ($attr && preg_match('/^\d+-\d+$/', $attr)) {
                        list($min, $max) = array_map('intval', explode('-', $attr));
                        $ranges[] = ['min'=>$min,'max'=>$max,'variation_id'=>$var['variation_id'],'attr'=>$attr,'price'=>$var['display_price']];
                        // Si está dentro del rango exacto
                        if ($common['days'] >= $min && $common['days'] <= $max) {
                            $variation_id   = $var['variation_id'];
                            $variation_data = ['attribute_pa_rango-de-dias'=>$attr];
                            break;
                        }
                    }
                }

                // 🔹 Si no entra en ningún rango, tomar el rango más alto
                if (!$variation_id && !empty($ranges)) {
                    usort($ranges, fn($a,$b)=>$b['max']-$a['max']); // ordenar descendente
                    $variation_id   = $ranges[0]['variation_id'];
                    $variation_data = ['attribute_pa_rango-de-dias'=>$ranges[0]['attr'].'+']; // indicar que supera
                    // $price se mantiene el precio base del producto
                }
            }

            // 🔹 Añadir al carrito
            $cart_item_key = WC()->cart->add_to_cart(
                $product_id,
                1,
                $variation_id,
                $variation_data,
                [
                    'usaalo_data' => array_merge($common, [
                        'countries'    => $countries,
                        'custom_price' => $price
                    ])
                ]
            );

            if ($cart_item_key) {
                $added[] = $cart_item_key;
                error_log("✅ Producto $product_id añadido al carrito con key $cart_item_key");
            } else {
                error_log("❌ Falló add_to_cart para producto $product_id");
            }
        }

        // Calcular totales
        if (WC()->cart) {
            WC()->cart->calculate_totals();
            WC()->cart->maybe_set_cart_cookies();
        }

        if (empty($added)) {
            wp_send_json_error(['message' => 'No se pudo añadir ningún producto']);
        }

        // Si es eSIM desactivar envíos
        if ($common['sim'] === 'eSIM') {
            add_filter('woocommerce_cart_needs_shipping', '__return_false');
        }

        wp_send_json_success([
            'checkout_url' => wc_get_checkout_url()
        ]);
    }









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
        $brands = USAALO_Helpers::get_brands();
        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/frontend-template.php';
        return ob_get_clean();
    }

}
