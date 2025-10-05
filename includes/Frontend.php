<?php
if (!defined('ABSPATH')) exit;

class USAALO_Frontend {

    public function __construct() {
        add_shortcode('usaalo_cotizador_horizontal', [$this, 'shortcode_render_horizontal']);
        add_shortcode('usaalo_cotizador_vertical', [$this, 'shortcode_render_vertical']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_filter('the_posts', [$this, 'enqueue_assets_if_shortcode']);

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

        // Mostrar datos en checkout y carrito
        add_filter('woocommerce_get_item_data', function($item_data, $cart_item){
            if (!empty($cart_item['usaalo_data'])) {
                $config = get_option('usaalo_cotizador_config', []);

                $d = $cart_item['usaalo_data'];

                // Países
                $item_data[] = ['name'=>'Países','value'=>implode(', ', $d['countries'] ?? [])];

                // Marca
                if (!isset($config['show_brand']) || $config['show_brand'] == 1) {
                    $item_data[] = ['name'=>'Marca','value'=>$d['brand'] ?? 'Otro'];
                }

                // Modelo
                if (!isset($config['show_model']) || $config['show_model'] == 1) {
                    $item_data[] = ['name'=>'Modelo','value'=>$d['model'] ?? 'Otro'];
                }

                // Servicios
                if (($config['show_data'] == 1) || ($config['show_voice'] == 1) || ($config['show_sms'] == 1)) {
                    $item_data[] = ['name'=>'Servicios','value'=>implode(', ', $d['services'] ?? ['data'])];
                }

                // Otros campos
                $item_data[] = ['name'=>'Días','value'=>$d['days'] ?? ''];
                $item_data[] = ['name'=>'SIM','value'=>$d['sim'] ?? ''];
                $item_data[] = ['name'=>'Inicio','value'=>$d['start_date'] ?? ''];
                $item_data[] = ['name'=>'Fin','value'=>$d['end_date'] ?? ''];
            }
            return $item_data;
        }, 10, 2);

        if (!is_admin()) {
            // Carrito
            add_filter('woocommerce_cart_item_name', function($product_name, $cart_item, $cart_item_key){
                if (is_cart()) {
                    return $product_name . $this->usaalo_format_item_data($cart_item);
                }
                return $product_name;
            }, 10, 3);

            // Checkout
            add_filter('woocommerce_checkout_cart_item_quantity', function($quantity_html, $cart_item, $cart_item_key){
                if (is_checkout()) {
                    return $quantity_html . $this->usaalo_format_item_data($cart_item);
                }
                return $quantity_html;
            }, 10, 3);
        }

        // Guardar en el pedido
        add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order){
            if (!empty($values['usaalo_data'])) {
                $config = get_option('usaalo_cotizador_config', []);
                foreach ($values['usaalo_data'] as $key=>$val) {
                    if (is_array($val)) $val = implode(', ', $val);

                    // No guardar marca/modelo si están ocultos
                    // if ($key === 'brand' && isset($config['show_brand']) && $config['show_brand'] == 0) continue;
                    // if ($key === 'model' && isset($config['show_model']) && $config['show_model'] == 0) continue;
                    // if ($key === 'model' && isset($config['show_data']) && $config['show_data'] == 0) continue;
                    // if ($key === 'services' && isset($config['show_data']) && $config['show_data'] == 0) continue;
                    // if ($key === 'services' && isset($config['show_voice']) && $config['show_voice'] == 0) continue;
                    // if ($key === 'services' && isset($config['show_sms']) && $config['show_sms'] == 0) continue;

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
     * Función para formatear y devolver los datos de usaalo_data
     */
    private function usaalo_format_item_data($cart_item) {
        if (empty($cart_item['usaalo_data'])) return '';

        $config = get_option('usaalo_cotizador_config', []);
        $d = $cart_item['usaalo_data'];

        $html = '<ul class="usaalo-item-data" style="margin:5px 0; padding-left:15px; font-size:0.9em;">';
        $html .= '<li><strong>Países:</strong> '.implode(', ', $d['countries'] ?? []).'</li>';

        if (!isset($config['show_brand']) || $config['show_brand'] == 1) {
            $html .= '<li><strong>Marca:</strong> '.esc_html($d['brand'] ?? '').'</li>';
        }

        if (!isset($config['show_model']) || $config['show_model'] == 1) {
            $html .= '<li><strong>Modelo:</strong> '.esc_html($d['model'] ?? '').'</li>';
        }
        if (($config['show_data'] == 1) || ($config['show_voice'] == 1) || ($config['show_sms'] == 1)) {
            $html .= '<li><strong>Servicios:</strong> '.implode(', ', $d['services'] ?? []).'</li>';
        }
        
        $html .= '<li><strong>Días:</strong> '.intval($d['days'] ?? 0).'</li>';
        $html .= '<li><strong>SIM:</strong> '.esc_html($d['sim'] ?? '').'</li>';
        $html .= '<li><strong>Inicio:</strong> '.esc_html($d['start_date'] ?? '').'</li>';
        $html .= '<li><strong>Fin:</strong> '.esc_html($d['end_date'] ?? '').'</li>';
        $html .= '</ul>';

        return $html;
    }

    public static $last_mode = 'vertical'; // default

    /**
     * Encolar scripts y estilos
     */
    // Se ejecuta SIEMPRE, registra los assets
    public function register_assets() {
        $base = plugin_dir_url(dirname(__FILE__)) . 'assets/';
        // USAALO_VERSION
        wp_register_style('fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', [], '6.5.1');
        // comunes
        wp_register_style('usaalo-select2', $base.'lib/select2.min.css', [], time());
        wp_register_script('usaalo-select2', $base.'lib/select2.min.js', ['jquery'], time(), true);
        wp_register_style('flatpickrcss', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');
        wp_register_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true);
        wp_register_script('flatpickrES','https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js', [], USAALO_VERSION, true);
        wp_register_style('tippycss', 'https://unpkg.com/tippy.js@6/dist/tippy.css', [], '6.3.7');
        wp_register_script('tippy', 'https://unpkg.com/@popperjs/core@2', [], '2.11.8', true);
        wp_register_script('tippyjs', 'https://unpkg.com/tippy.js@6', ['tippy'], '6.3.7', true);

        // específicos
        wp_register_style('usaalo-frontend-horizontal', $base.'css/frontend-horizontal.css', ['fontawesome', 'usaalo-select2','flatpickrcss','tippycss'], time());
        wp_register_script('usaalo-frontend-horizontal', $base.'js/frontend-horizontal.js', ['jquery','usaalo-select2','flatpickr','tippyjs'], time(), true);

        wp_register_style('usaalo-frontend-vertical', $base.'css/frontend-vertical.css', ['fontawesome', 'usaalo-select2','flatpickrcss','tippycss'], time());
        wp_register_script('usaalo-frontend-vertical', $base.'js/frontend-vertical.js', ['jquery','usaalo-select2','flatpickr','tippyjs'], time(), true);
    }

        
    public function ajax_usaalo_add_multiple_to_cart() {
        check_ajax_referer('usaalo_frontend_nonce','nonce');
    
        $countries_map = $_POST['countries'] ?? [];
        $products      = $_POST['products'] ?? [];
    
        if (empty($products)) {
            wp_send_json_error(['message'=>'No hay productos para añadir']);
        }
    
        // Vaciar carrito antes de añadir
        if (WC()->cart) {
            WC()->cart->empty_cart(true);
        }
    
        $common = [
            'days'       => max(1, intval($_POST['days'] ?? 1)), // nunca menos de 1 día
            'brand'      => sanitize_text_field($_POST['brand'] ?? ''),
            'model'      => sanitize_text_field($_POST['model'] ?? ''),
            'sim'        => sanitize_text_field($_POST['sim'] ?? 'eSIM'),
            'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
            'end_date'   => sanitize_text_field($_POST['end_date'] ?? ''),
            'services'   => (array)($_POST['services'] ?? [])
        ];
    
        if ($common['days'] > 30) {
            wp_send_json_error(['message' => '⚠️ No se puede seleccionar más de 30 días']);
        }
    
        $added = [];
    
        foreach ($products as $p) {
            $product_id = intval($p['product_id'] ?? 0);
            $price      = floatval($p['price'] ?? 0);
            $codes      = $p['countries'] ?? [];
    
            if (!$product_id) {
                continue;
            }
    
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
    
            // 🔹 Si es variable, buscar variación según días
            if ($product->is_type('variable')) {
                $ranges = [];
                foreach ($product->get_available_variations() as $var) {
                    $attr = $var['attributes']['attribute_pa_rango-de-dias'] ?? '';
                    if ($attr && preg_match('/^\d+-\d+$/', $attr)) {
                        list($min, $max) = array_map('intval', explode('-', $attr));
                        $ranges[] = [
                            'min'          => $min,
                            'max'          => $max,
                            'variation_id' => $var['variation_id'],
                            'attr'         => $attr,
                            'price'        => $var['display_price']
                        ];
                        // si los días entran en el rango exacto
                        if ($common['days'] >= $min && $common['days'] <= $max) {
                            $variation_id   = $var['variation_id'];
                            $variation_data = ['attribute_pa_rango-de-dias'=>$attr];
                            break;
                        }
                    }
                }
    
                // 🔹 Si no entra en ningún rango, tomar el rango más alto disponible
                if (!$variation_id && !empty($ranges)) {
                    usort($ranges, fn($a,$b)=>$b['max'] - $a['max']); // ordenar descendente por max
                    $variation_id   = $ranges[0]['variation_id'];
                    $variation_data = ['attribute_pa_rango-de-dias'=>$ranges[0]['attr']];
                    // aquí NO inventamos "+", WooCommerce solo acepta valores definidos
                }
            }
    
            // 🔹 Añadir al carrito con metadatos
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
    
        // 🔹 Si es eSIM, desactivar envíos condicionalmente
        if ($common['sim'] === 'eSIM') {
            add_filter('woocommerce_cart_needs_shipping', function($needs_shipping){
                foreach (WC()->cart->get_cart() as $item) {
                    if (!empty($item['usaalo_data']['sim']) && $item['usaalo_data']['sim'] !== 'eSIM') {
                        return true;
                    }
                }
                return false;
            });
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

    public function enqueue_assets_if_shortcode($posts) {
        if (empty($posts)) return $posts;

        $found = false;
        foreach ($posts as $post) {
            if (has_shortcode($post->post_content, 'usaalo_cotizador_horizontal') ||
                has_shortcode($post->post_content, 'usaalo_cotizador_vertical')) {
                $found = true;
                break;
            }
        }

        if ($found) {
            wp_enqueue_style('usaalo-select2');
            wp_enqueue_style('flatpickrcss');
            wp_enqueue_style('tippycss');

            wp_enqueue_script('flatpickrES');
            wp_enqueue_script('usaalo-select2');
            wp_enqueue_script('flatpickr');
            wp_enqueue_script('tippyjs');
        }

        return $posts;
    }
    



    /**
     * Renderiza el shortcode
     */
    public function shortcode_render_horizontal() {
        // Forzar fontawesome
        if (!wp_style_is('fontawesome', 'enqueued') && !wp_style_is('fontawesome', 'done')) {
            wp_enqueue_style('fontawesome');
        }
        $brands = USAALO_Helpers::get_brands();
        wp_enqueue_style('usaalo-frontend-horizontal');
        wp_enqueue_script('usaalo-frontend-horizontal');

        // Datos para JS
        $cache = USAALO_Cache::load_for_frontend();
        $config = get_option('usaalo_cotizador_config', []);

        wp_localize_script('usaalo-frontend-horizontal', 'USAALO_Frontend', [
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
                'sim'          => __('Tu SIM para tu viaje 🌍', 'usaalo-cotizador'),
                'servicio'          => __('Opciones disponibles para ti 🌟', 'usaalo-cotizador'),
                'costo_sim'          => __('La SimCard física genera un cobro adicional por el envío '.get_woocommerce_currency_symbol().USAALO_Helpers::get_shipping_cost('CO'), 'usaalo-cotizador')
            ],
            'modo'    => 'horizontal',
            'products'         => $cache,
            'Config'           => $config,
            'img_sim_fisica'         => '<i class="fa-thin fa-sim-card"></i>',
            'img_sim_virtual'         => '<i class="fa-solid fa-microchip"></i>'
        ]);

        ob_start();
        include USAALO_PATH.'includes/templates/frontend-horizontal-template.php';
        return ob_get_clean();
    }

    public function shortcode_render_vertical() {
        // Forzar fontawesome
        if (!wp_style_is('fontawesome', 'enqueued') && !wp_style_is('fontawesome', 'done')) {
            wp_enqueue_style('fontawesome');
        }
        wp_enqueue_style('usaalo-frontend-vertical');
        wp_enqueue_script('usaalo-frontend-vertical');
        $brands = USAALO_Helpers::get_brands();
        // Datos para JS
        $cache = USAALO_Cache::load_for_frontend();
        // ⚡ Cachear servicios masivos para evitar error 500
        $services_data = USAALO_Helpers::servicios_disponibles_todos_modelos();
        $services_json = wp_json_encode($services_data); // convertir a JSON
        $services_json = gzcompress($services_json, 9); // comprimir
        $config = get_option('usaalo_cotizador_config', []);

        wp_localize_script('usaalo-frontend-vertical', 'USAALO_Frontend', [
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
                'sim'          => __('Tu SIM para tu viaje 🌍', 'usaalo-cotizador'),
                'servicio'          => __('Opciones disponibles para ti 🌟', 'usaalo-cotizador'),
            ],
            'modo'    => 'vertical',
            'products'         => $cache,
            'TypeServices'     => $services_json,
            'Config'           => $config,
            'img_chip'         => '<img id="todos-check-icon" src="' . USAALO_URL . '/assets/img/tarjeta-sim.png" width="18" height="18" style="vertical-align:middle;">'
        ]);

        ob_start();
        include USAALO_PATH.'includes/templates/frontend-vertical-template.php';
        return ob_get_clean();
    }


}