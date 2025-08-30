<?php
if (!defined('ABSPATH')) exit;

/**
 * includes/ajax.php
 * Endpoints AJAX para Usaalo Cotizador
 *
 * Endpoints implementados:
 * - usaalo_get_countries            (GET)    -> lista países
 * - usaalo_get_brands               (GET)    -> lista marcas
 * - usaalo_get_models               (POST)   -> modelos por marca
 * - usaalo_get_services             (POST)   -> servicios disponibles por país
 * - usaalo_calculate_price          (POST)   -> cálculo del precio (frontend)
 * - usaalo_create_product           (POST)   -> crear producto/variaciones en WooCommerce (admin)
 * - usaalo_get_pricing_rules        (POST)   -> obtener reglas por plan
 *
 * Seguridad:
 * - Comprueba nonces donde procede
 * - Comprueba capacidades para endpoints de administración
 * - Saneamiento de entradas
 */

/* Helper: enviar error estándar */
if (!function_exists('usaalo_json_error')) {
    function usaalo_json_error($msg = 'Error', $status = 400) {
        wp_send_json_error(['message' => $msg], $status);
    }
}

/* Helper: comprobar que WooCommerce está activo cuando hace falta */
if (!function_exists('usaalo_wc_required')) {
    function usaalo_wc_required() {
        if (!class_exists('WooCommerce')) {
            usaalo_json_error(__('WooCommerce is required for this action.', 'usaalo-cotizador'), 400);
        }
    }
}

/**
 * GET: usaalo_get_countries
 * Devuelve la lista de países (frontend)
 */
add_action('wp_ajax_usaalo_get_countries', 'usaalo_get_countries');
add_action('wp_ajax_nopriv_usaalo_get_countries', 'usaalo_get_countries');
function usaalo_get_countries() {
    global $wpdb;
    $rows = $wpdb->get_results("SELECT code, name, region, status, supports_voice_sms FROM {$wpdb->prefix}usaalo_countries ORDER BY name ASC", ARRAY_A);
    wp_send_json_success($rows);
}

/**
 * GET: usaalo_get_brands
 * Devuelve la lista de marcas
 */
add_action('wp_ajax_usaalo_get_brands', 'usaalo_get_brands');
add_action('wp_ajax_nopriv_usaalo_get_brands', 'usaalo_get_brands');
function usaalo_get_brands() {
    global $wpdb;
    $rows = $wpdb->get_results("SELECT id, name, slug FROM {$wpdb->prefix}usaalo_brands ORDER BY name ASC", ARRAY_A);
    wp_send_json_success($rows);
}

/**
 * POST: usaalo_get_models
 * Modelos filtrados por brand_id
 * Params: brand_id (int)
 */
add_action('wp_ajax_usaalo_get_models', 'usaalo_get_models');
add_action('wp_ajax_nopriv_usaalo_get_models', 'usaalo_get_models');
function usaalo_get_models() {
    global $wpdb;
    $brand_id = isset($_POST['brand_id']) ? intval($_POST['brand_id']) : 0;
    if (!$brand_id) {
        wp_send_json_success([]); // vacío en caso de no enviar marca (frontend maneja)
    }
    $rows = $wpdb->get_results($wpdb->prepare("SELECT id, name, slug, brand_id FROM {$wpdb->prefix}usaalo_models WHERE brand_id = %d ORDER BY name ASC", $brand_id), ARRAY_A);
    wp_send_json_success($rows);
}

/**
 * POST: usaalo_get_services
 * Obtiene servicios permitidos por país (ej.: Datos, Voz, SMS)
 * Params: country_code (string)
 */
add_action('wp_ajax_usaalo_get_services', 'usaalo_get_services');
add_action('wp_ajax_nopriv_usaalo_get_services', 'usaalo_get_services');
function usaalo_get_services() {
    global $wpdb;
    $country = isset($_POST['country_code']) ? sanitize_text_field($_POST['country_code']) : '';
    if (!$country) return wp_send_json_success(['services' => ['data']]); // default

    $row = $wpdb->get_row($wpdb->prepare("SELECT supports_voice_sms FROM {$wpdb->prefix}usaalo_countries WHERE code = %s LIMIT 1", $country), ARRAY_A);
    $services = ['data'];
    if ($row && intval($row['supports_voice_sms']) === 1) {
        $services = ['data','voice','sms'];
    }
    wp_send_json_success(['services' => $services]);
}

/**
 * POST: usaalo_get_pricing_rules
 * Devuelve reglas de precio de un plan
 * Params: plan_id (int)
 */
add_action('wp_ajax_usaalo_get_pricing_rules', 'usaalo_get_pricing_rules');
add_action('wp_ajax_nopriv_usaalo_get_pricing_rules', 'usaalo_get_pricing_rules');
function usaalo_get_pricing_rules() {
    global $wpdb;
    $plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
    if (!$plan_id) return usaalo_json_error(__('Plan id required', 'usaalo-cotizador'));
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}usaalo_pricing_rules WHERE plan_id = %d AND active = 1 ORDER BY min_days ASC", $plan_id), ARRAY_A);
    wp_send_json_success($rows);
}

/**
 * POST: usaalo_calculate_price
 * Calcula precio basado en:
 * - países seleccionados (array o single)
 * - sim_type (esim|physical)
 * - services (array)
 * - start_date, end_date
 * - brand, model
 *
 * Devuelve summary y price.
 */
add_action('wp_ajax_usaalo_calculate_price', 'usaalo_calculate_price');
add_action('wp_ajax_nopriv_usaalo_calculate_price', 'usaalo_calculate_price');
function usaalo_calculate_price() {
    check_ajax_referer('usaalo_frontend_nonce', 'nonce');

    global $wpdb;

    // Sanitizar entrada
    $countries = isset($_POST['country']) ? (array) $_POST['country'] : [];
    $countries = array_map('sanitize_text_field', $countries);
    $sim_type = isset($_POST['sim_type']) ? sanitize_text_field($_POST['sim_type']) : 'esim';
    $services = isset($_POST['services']) ? (array) $_POST['services'] : [];
    $services = array_map('sanitize_text_field', $services);
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
    $brand = isset($_POST['brand']) ? intval($_POST['brand']) : 0;
    $model = isset($_POST['model']) ? intval($_POST['model']) : 0;

    // Validaciones básicas
    if (empty($countries)) return usaalo_json_error(__('Selecciona al menos un país', 'usaalo-cotizador'));
    if (empty($start_date) || empty($end_date)) return usaalo_json_error(__('Fechas inválidas', 'usaalo-cotizador'));
    $start_ts = strtotime($start_date);
    $end_ts = strtotime($end_date);
    if ($start_ts === false || $end_ts === false || $end_ts < $start_ts) {
        return usaalo_json_error(__('Rango de fechas inválido', 'usaalo-cotizador'));
    }
    $days = max(1, intval(1 + (($end_ts - $start_ts) / DAY_IN_SECONDS))); // inclusive

    // Determinar plan(s) aplicables — simplificación: si hay múltiples países, aplicamos regla por país y sumamos
    $total_price = 0.0;
    $breakdown = [];

    foreach ($countries as $country_code) {
        // Buscar planes que cubran este país (usaalo_plan_country)
        $plan_id = $wpdb->get_var($wpdb->prepare("
            SELECT plan_id FROM {$wpdb->prefix}usaalo_plan_country
            WHERE country_code = %s LIMIT 1", $country_code
        ));

        if (!$plan_id) {
            // Si no hay plan vinculado -> error o saltar; lo devolvemos en breakdown
            $breakdown[] = ['country' => $country_code, 'error' => __('No hay plan vinculado para este país', 'usaalo-cotizador')];
            continue;
        }

        // Obtener la regla de pricing que cubra la cantidad de days y sim_type
        $rule = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}usaalo_pricing_rules
            WHERE plan_id = %d AND sim_type = %s AND min_days <= %d AND max_days >= %d AND active = 1
            ORDER BY min_days DESC LIMIT 1", $plan_id, $sim_type, $days, $days
        ), ARRAY_A);

        // Si no hay regla exacta, buscar regla que abarque menos (fallback)
        if (!$rule) {
            $rule = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}usaalo_pricing_rules
                WHERE plan_id = %d AND sim_type = %s AND active = 1
                ORDER BY ABS((min_days+max_days)/2 - %d) ASC LIMIT 1", $plan_id, $sim_type, $days
            ), ARRAY_A);
        }

        if (!$rule) {
            $breakdown[] = ['country' => $country_code, 'error' => __('No hay reglas de precio configuradas para el plan', 'usaalo-cotizador')];
            continue;
        }

        // Precio por día según la regla
        $price_per_day = floatval($rule['base_price']);
        $country_price = $price_per_day * $days;

        // Añadir addons por servicio (si aplican)
        $voice_addon = (in_array('voice', $services) ? floatval($rule['voice_addon']) : 0.0);
        $sms_addon = (in_array('sms', $services) ? floatval($rule['sms_addon']) : 0.0);
        $services_addon_total = ($voice_addon + $sms_addon) * $days;

        // region surcharge
        $region_surcharge = floatval($rule['region_surcharge']);

        $subtotal = $country_price + $services_addon_total + $region_surcharge;

        $total_price += $subtotal;

        $breakdown[] = [
            'country' => $country_code,
            'plan_id' => intval($plan_id),
            'rule_id' => intval($rule['id']),
            'days' => $days,
            'price_per_day' => $price_per_day,
            'country_price' => number_format($country_price, 2, '.', ''),
            'services_addon' => number_format($services_addon_total, 2, '.', ''),
            'region_surcharge' => number_format($region_surcharge, 2, '.', ''),
            'subtotal' => number_format($subtotal, 2, '.', ''),
        ];
    }

    // Compatibilidad del dispositivo: comprobación simple
    $compat_status = 'unknown';
    if ($brand && $model) {
        // Comprobar si existe compatibilidad en la tabla device_country para alguno de los países
        $compatible_all = true;
        $only_data = false;
        foreach ($countries as $country_code) {
            $row = $wpdb->get_row($wpdb->prepare("
                SELECT esim_supported, voice_supported, sms_supported, data_supported
                FROM {$wpdb->prefix}usaalo_device_country
                WHERE model_id = %d AND country_code = %s LIMIT 1", $model, $country_code
            ), ARRAY_A);

            if (!$row) {
                $compatible_all = false;
                break;
            }
            if (intval($row['esim_supported']) === 0 && $sim_type === 'esim') {
                $compatible_all = false;
            }
            if (intval($row['voice_supported']) === 0 && in_array('voice', $services)) {
                $only_data = true; // indicates voice not supported
            }
        }

        if ($compatible_all) $compat_status = 'compatible';
        elseif ($only_data) $compat_status = 'only_data';
        else $compat_status = 'not_compatible';
    }

    // Respuesta
    $result = [
        'summary' => $breakdown,
        'total' => number_format($total_price, 2, '.', ''),
        'days' => $days,
        'compatibility' => $compat_status,
    ];

    wp_send_json_success($result);
}

/**
 * POST: usaalo_create_product
 * Crear producto en WooCommerce con variaciones:
 * - Recibe: title (optional), countries (array), sim_type(s), pricing_rules (array) OR plan_id
 *
 * Nota: Este endpoint debe usarse con privilegios administrativos.
 */
add_action('wp_ajax_usaalo_create_product', 'usaalo_create_product');
function usaalo_create_product() {
    check_ajax_referer('usaalo_admin_nonce', 'nonce');

    if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
        return usaalo_json_error(__('No tienes permisos para crear productos.', 'usaalo-cotizador'), 403);
    }

    usaalo_wc_required(); // dispara error si WC no activo

    // Inputs
    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    $countries = isset($_POST['countries']) ? (array) $_POST['countries'] : [];
    $countries = array_map('sanitize_text_field', $countries);
    $sim_type = isset($_POST['sim_type']) ? sanitize_text_field($_POST['sim_type']) : 'esim';
    $plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;

    if (empty($countries) && !$plan_id) {
        return usaalo_json_error(__('Debes indicar al menos un país o un plan.', 'usaalo-cotizador'));
    }

    global $wpdb;

    // Si se pasa plan_id, extraer reglas para construir variaciones
    $rules = [];
    if ($plan_id) {
        $rules = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}usaalo_pricing_rules
            WHERE plan_id = %d AND sim_type = %s AND active = 1
            ORDER BY min_days ASC", $plan_id, $sim_type
        ), ARRAY_A);
        if (empty($rules)) {
            return usaalo_json_error(__('No hay reglas de precio para el plan y tipo SIM indicados.', 'usaalo-cotizador'));
        }
    } else {
        // Si el admin envía pricing_rules manualmente (opcional)
        if (isset($_POST['pricing_rules']) && is_array($_POST['pricing_rules'])) {
            $rules = $_POST['pricing_rules']; // se asume ya saneado por UI admin
        }
    }

    // Preparar nombre del producto
    $product_title = $title ? $title : sprintf(__('Plan %s', 'usaalo-cotizador'), implode(',', $countries));

    // Crear producto variable
    $product = new WC_Product_Variable();
    $product->set_name($product_title);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_regular_price('0'); // el price se maneja por variaciones
    $product->save();

    $product_id = $product->get_id();
    if (!$product_id) {
        return usaalo_json_error(__('No se pudo crear el producto WooCommerce.', 'usaalo-cotizador'));
    }

    // Añadir atributos: Country, SIM Type, Days Range
    $attribute_data = [];

    // Country attribute
    $country_terms = [];
    foreach ($countries as $c) {
        $country_terms[] = trim($c);
    }
    if (!empty($country_terms)) {
        $attr_name = 'Country';
        $taxonomy = wc_attribute_taxonomy_name(sanitize_title($attr_name));
        // register attribute term(s) as custom product attribute (non-global) as values
        $attribute_data[$attr_name] = [
            'name' => $attr_name,
            'value' => implode(' | ', $country_terms),
            'position' => 0,
            'is_visible' => 1,
            'is_variation' => 1,
            'is_taxonomy' => 0,
        ];
    }

    // SIM Type attribute
    $attr_sim = 'SIM Type';
    $attribute_data[$attr_sim] = [
        'name' => $attr_sim,
        'value' => ucfirst($sim_type),
        'position' => 1,
        'is_visible' => 1,
        'is_variation' => 1,
        'is_taxonomy' => 0,
    ];

    // Days Range attribute - use rules to build ranges
    $days_ranges = [];
    foreach ($rules as $r) {
        $label = sprintf('%d-%d', intval($r['min_days']), intval($r['max_days']));
        $days_ranges[] = $label;
    }
    $attr_days = 'Days Range';
    $attribute_data[$attr_days] = [
        'name' => $attr_days,
        'value' => implode(' | ', array_unique($days_ranges)),
        'position' => 2,
        'is_visible' => 1,
        'is_variation' => 1,
        'is_taxonomy' => 0,
    ];

    // Guardar atributos en el producto
    $product_attributes = [];
    $position = 0;
    foreach ($attribute_data as $attr) {
        $attr_obj = new WC_Product_Attribute();
        $attr_obj->set_name($attr['name']);
        $attr_obj->set_options(array_map('trim', explode('|', $attr['value'])));
        $attr_obj->set_position($position);
        $attr_obj->set_visible($attr['is_visible']);
        $attr_obj->set_variation($attr['is_variation']);
        $product_attributes[] = $attr_obj;
        $position++;
    }
    $product->set_attributes($product_attributes);
    $product->save();

    // Crear variaciones: cartesian product: countries x days_ranges (sim_type is fixed)
    $variations = [];
    foreach ($country_terms as $country_term) {
        foreach ($days_ranges as $range_label) {
            // Encontrar regla correspondiente al range_label (min-max)
            list($min, $max) = array_map('intval', explode('-', $range_label));
            $matched_rule = null;
            foreach ($rules as $r) {
                if (intval($r['min_days']) === $min && intval($r['max_days']) === $max) {
                    $matched_rule = $r;
                    break;
                }
            }
            if (!$matched_rule) continue;

            // Precio por día * (min..max) -> para variación guardamos price per day (we'll store price as price for minimal day or decide business rule)
            // Business decision: set variation price = base_price * min_days (or you can set for 1-day price and use meta for per-day)
            $price_for_min_days = floatval($matched_rule['base_price']) * $min;

            $variation_props = [
                'attributes' => [
                    'Country' => $country_term,
                    'SIM Type' => ucfirst($sim_type),
                    'Days Range' => $range_label,
                ],
                'regular_price' => wc_format_decimal($price_for_min_days, '', ''),
                'manage_stock' => 'no',
                'stock_qty' => null,
                'stock_status' => 'instock',
            ];
            $variations[] = $variation_props;
        }
    }

    // Create variations via WC CRUD
    foreach ($variations as $vars) {
        $variation = new WC_Product_Variation();
        $variation->set_parent_id($product_id);

        // Set attributes normalized keys to taxonomy-like names for variation object
        $variation_attributes = [];
        foreach ($vars['attributes'] as $k => $v) {
            $variation_attributes[sanitize_title($k)] = $v;
        }
        $variation->set_attributes($variation_attributes);

        if (!empty($vars['regular_price'])) {
            $variation->set_regular_price((string)$vars['regular_price']);
        }
        if (isset($vars['stock_status'])) $variation->set_stock_status($vars['stock_status']);
        $variation->save();
    }

    // Guardar id del producto en la tabla del plan si se proporcionó plan_id
    if ($plan_id) {
        $wpdb->update("{$wpdb->prefix}usaalo_plans", ['wc_product_id' => $product_id], ['id' => $plan_id], ['%d'], ['%d']);
    }

    // Respuesta con URL al producto creado
    $product_url = get_permalink($product_id);
    wp_send_json_success(['product_id' => $product_id, 'product_url' => $product_url]);
}

/* END of file */
