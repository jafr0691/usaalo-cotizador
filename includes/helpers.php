<?php
if (!defined('ABSPATH')) exit;

/**
 * includes/helpers.php
 * Funciones helper comunes para Usaalo Cotizador
 *
 * - Consultas a tablas: countries, brands, models, plans, pricing_rules, device_country
 * - Cálculo de días (inclusive)
 * - Lógica de cálculo de precio reutilizable
 * - Comprobación de compatibilidad dispositivo <-> país
 * - Helpers para opciones del plugin
 * - Helper para crear producto variable en WC (con opción de guardar price_per_day en meta)
 *
 * Notas:
 * - Todas las consultas usan $wpdb->prepare cuando es necesario.
 * - Para creación de productos en WooCommerce se comprueba que WC esté activo.
 */

class USAALO_Helpers {

    /**
     * Obtener instancia del DB global.
     * @return wpdb
     */
    protected static function db() {
        global $wpdb;
        return $wpdb;
    }

    /* -----------------------------
     * Opciones del plugin
     * ----------------------------- */

    public static function get_settings() : array {
        $defaults = [
            'color_primary' => '#111827',
            'color_button' => '#111827',
            'text_next' => __('Siguiente', 'usaalo-cotizador'),
            'text_back' => __('Atrás', 'usaalo-cotizador'),
        ];
        $opts = get_option('usaalo_cotizador_settings', []);
        if (!is_array($opts)) $opts = [];
        return wp_parse_args($opts, $defaults);
    }

    public static function update_settings(array $data) : bool {
        return update_option('usaalo_cotizador_settings', $data);
    }

    /* -----------------------------
     * Consultas básicas
     * ----------------------------- */

    public static function get_countries() : array {
        $wpdb = self::db();
        return $wpdb->get_results("SELECT code AS code2, name, region, status, supports_voice_sms FROM {$wpdb->prefix}usaalo_countries ORDER BY name ASC", ARRAY_A) ?: [];
    }

    public static function get_country(string $code) : ?array {
        $wpdb = self::db();
        return $wpdb->get_row($wpdb->prepare("SELECT code AS code2, name, region, status, supports_voice_sms FROM {$wpdb->prefix}usaalo_countries WHERE code = %s LIMIT 1", $code), ARRAY_A) ?: null;
    }

    public static function get_brands() : array {
        $wpdb = self::db();
        return $wpdb->get_results("SELECT id, name, slug FROM {$wpdb->prefix}usaalo_brands ORDER BY name ASC", ARRAY_A) ?: [];
    }

    public static function get_models_by_brand(int $brand_id) : array {
        $wpdb = self::db();
        return $wpdb->get_results($wpdb->prepare("SELECT id, brand_id, name, slug FROM {$wpdb->prefix}usaalo_models WHERE brand_id = %d ORDER BY name ASC", $brand_id), ARRAY_A) ?: [];
    }

    public static function get_model(int $model_id) : ?array {
        $wpdb = self::db();
        return $wpdb->get_row($wpdb->prepare("SELECT id, brand_id, name, slug FROM {$wpdb->prefix}usaalo_models WHERE id = %d LIMIT 1", $model_id), ARRAY_A) ?: null;
    }

    public static function get_plans() : array {
        $wpdb = self::db();
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}usaalo_plans ORDER BY name ASC", ARRAY_A) ?: [];
    }

    public static function get_plan(int $plan_id) : ?array {
        $wpdb = self::db();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}usaalo_plans WHERE id = %d LIMIT 1", $plan_id), ARRAY_A) ?: null;
    }

    public static function get_pricing_rules(int $plan_id, ?string $sim_type = null) : array {
        $wpdb = self::db();
        $sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}usaalo_pricing_rules WHERE plan_id = %d AND active = 1", $plan_id);
        if ($sim_type) {
            $sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}usaalo_pricing_rules WHERE plan_id = %d AND sim_type = %s AND active = 1 ORDER BY min_days ASC", $plan_id, $sim_type);
            return $wpdb->get_results($sql, ARRAY_A) ?: [];
        }
        return $wpdb->get_results($sql . " ORDER BY min_days ASC", ARRAY_A) ?: [];
    }

    /**
     * Obtener compatibilidad del modelo con el país
     * @param int $model_id
     * @param string $country_code
     * @return array|null ['esim_supported'=>0/1,'voice_supported'=>0/1,'sms_supported'=>0/1,'data_supported'=>0/1] o null si no hay fila
     */
    public static function get_device_country_compat(int $model_id, string $country_code) : ?array {
        $wpdb = self::db();
        return $wpdb->get_row($wpdb->prepare("SELECT esim_supported, voice_supported, sms_supported, data_supported FROM {$wpdb->prefix}usaalo_device_country WHERE model_id = %d AND country_code = %s LIMIT 1", $model_id, $country_code), ARRAY_A) ?: null;
    }

    /* -----------------------------
     * Utilidades de fechas/días
     * ----------------------------- */

    /**
     * Calcular días entre dos fechas (inclusive por defecto)
     * @param string $start Y-m-d
     * @param string $end Y-m-d
     * @param bool $inclusive default true
     * @return int número de días >= 1
     */
    public static function days_between(string $start, string $end, bool $inclusive = true) : int {
        $start_ts = strtotime($start);
        $end_ts = strtotime($end);
        if ($start_ts === false || $end_ts === false) return 0;
        $diff = max(0, floor(($end_ts - $start_ts) / DAY_IN_SECONDS));
        return $inclusive ? max(1, $diff + 1) : max(0, $diff);
    }

    /* -----------------------------
     * Cálculo de precio (reutilizable)
     * ----------------------------- */

    /**
     * calculate_price
     * @param array $countries array of country codes (strings)
     * @param string $sim_type 'esim'|'physical'
     * @param array $services e.g. ['data','voice','sms']
     * @param string $start_date 'YYYY-MM-DD'
     * @param string $end_date 'YYYY-MM-DD'
     * @param int|null $brand optional
     * @param int|null $model optional
     * @return array [ 'success'=>bool, 'total'=>float, 'days'=>int, 'breakdown'=>array, 'compatibility'=>string, 'errors'=>array ]
     */
    public static function calculate_price(array $countries, string $sim_type, array $services, string $start_date, string $end_date, ?int $brand = null, ?int $model = null) : array {
        $wpdb = self::db();
        $result = [
            'success' => false,
            'total' => 0.0,
            'days' => 0,
            'breakdown' => [],
            'compatibility' => 'unknown',
            'errors' => []
        ];

        // Validaciones básicas
        $countries = array_map('sanitize_text_field', $countries);
        if (empty($countries)) {
            $result['errors'][] = __('Debe seleccionar al menos un país.', 'usaalo-cotizador');
            return $result;
        }
        $start_ts = strtotime($start_date);
        $end_ts = strtotime($end_date);
        if ($start_ts === false || $end_ts === false || $end_ts < $start_ts) {
            $result['errors'][] = __('Rango de fechas inválido.', 'usaalo-cotizador');
            return $result;
        }

        $days = self::days_between($start_date, $end_date, true);
        $result['days'] = $days;

        $total_price = 0.0;
        $breakdown = [];

        foreach ($countries as $country_code) {
            // Obtener plan vinculado al país
            $plan_id = $wpdb->get_var($wpdb->prepare("SELECT plan_id FROM {$wpdb->prefix}usaalo_plan_country WHERE country_code = %s LIMIT 1", $country_code));
            if (!$plan_id) {
                $breakdown[] = ['country' => $country_code, 'error' => __('No hay plan vinculado a este país', 'usaalo-cotizador')];
                continue;
            }

            // Obtener regla que cubra los días para ese plan y sim_type
            $rule = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}usaalo_pricing_rules WHERE plan_id=%d AND sim_type=%s AND min_days <= %d AND max_days >= %d AND active=1 ORDER BY min_days DESC LIMIT 1", $plan_id, $sim_type, $days, $days), ARRAY_A);

            if (!$rule) {
                // fallback: regla más cercana
                $rule = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}usaalo_pricing_rules WHERE plan_id=%d AND sim_type=%s AND active=1 ORDER BY ABS(((min_days+max_days)/2)-%d) ASC LIMIT 1", $plan_id, $sim_type, $days), ARRAY_A);
            }

            if (!$rule) {
                $breakdown[] = ['country' => $country_code, 'error' => __('No hay reglas de precio configuradas', 'usaalo-cotizador')];
                continue;
            }

            $price_per_day = floatval($rule['base_price']);
            $country_price = $price_per_day * $days;

            $voice_addon = in_array('voice', $services) ? floatval($rule['voice_addon']) : 0.0;
            $sms_addon = in_array('sms', $services) ? floatval($rule['sms_addon']) : 0.0;
            $services_addon_total = ($voice_addon + $sms_addon) * $days;

            $region_surcharge = floatval($rule['region_surcharge']);

            $subtotal = $country_price + $services_addon_total + $region_surcharge;

            $total_price += $subtotal;

            $breakdown[] = [
                'country' => $country_code,
                'plan_id' => intval($plan_id),
                'rule_id' => intval($rule['id']),
                'days' => $days,
                'price_per_day' => number_format($price_per_day, 2, '.', ''),
                'country_price' => number_format($country_price, 2, '.', ''),
                'services_addon' => number_format($services_addon_total, 2, '.', ''),
                'region_surcharge' => number_format($region_surcharge, 2, '.', ''),
                'subtotal' => number_format($subtotal, 2, '.', ''),
            ];
        }

        // Compatibilidad del dispositivo (si proporcionado)
        $compat_status = 'unknown';
        if ($brand && $model) {
            $compatible_all = true;
            $only_data = false;
            foreach ($countries as $country_code) {
                $row = self::get_device_country_compat($model, $country_code);
                if (!$row) {
                    $compatible_all = false;
                    break;
                }
                if (intval($row['esim_supported']) === 0 && $sim_type === 'esim') {
                    $compatible_all = false;
                }
                if (intval($row['voice_supported']) === 0 && in_array('voice', $services)) {
                    $only_data = true;
                }
            }
            if ($compatible_all) $compat_status = 'compatible';
            elseif ($only_data) $compat_status = 'only_data';
            else $compat_status = 'not_compatible';
        }

        $result['success'] = true;
        $result['total'] = floatval(number_format($total_price, 2, '.', ''));
        $result['breakdown'] = $breakdown;
        $result['compatibility'] = $compat_status;
        return $result;
    }

    /* -----------------------------
     * Helper para crear producto variable en WooCommerce
     * - Este helper crea el producto, añade atributos y variaciones
     * - Por seguridad: verifica que WooCommerce esté activo
     *
     * @param string $title
     * @param array $countries array of country codes (strings)
     * @param string $sim_type 'esim'|'physical'
     * @param array $rules array of pricing rules rows (each with min_days,max_days,base_price,...)
     * @param array $options optional: ['save_price_per_day_meta' => true]
     * @return array ['success'=>bool,'product_id'=>int|null,'message'=>string]
     */
    public static function create_wc_product_from_plan(string $title, array $countries, string $sim_type, array $rules, array $options = []) : array {
        if (!class_exists('WC_Product_Variable')) {
            return ['success' => false, 'product_id' => null, 'message' => __('WooCommerce no está activo.', 'usaalo-cotizador')];
        }

        // sanitize
        $countries = array_map('sanitize_text_field', $countries);
        $sim_type = sanitize_text_field($sim_type);
        $title = sanitize_text_field($title);

        try {
            // create product
            $product = new WC_Product_Variable();
            $product->set_name($title);
            $product->set_status('publish');
            $product->set_catalog_visibility('visible');
            $product->save();
            $product_id = $product->get_id();
            if (!$product_id) throw new Exception(__('No se pudo crear el producto.', 'usaalo-cotizador'));

            // build attribute objects
            $attr_objects = [];

            // Country attribute (non-taxonomy)
            $country_values = array_values($countries);
            $attr_country = new WC_Product_Attribute();
            $attr_country->set_name('Country');
            $attr_country->set_options($country_values);
            $attr_country->set_position(0);
            $attr_country->set_visible(1);
            $attr_country->set_variation(1);
            $attr_objects[] = $attr_country;

            // SIM Type
            $attr_sim = new WC_Product_Attribute();
            $attr_sim->set_name('SIM Type');
            $attr_sim->set_options([ucfirst($sim_type)]);
            $attr_sim->set_position(1);
            $attr_sim->set_visible(1);
            $attr_sim->set_variation(1);
            $attr_objects[] = $attr_sim;

            // Days Range
            $ranges = [];
            foreach ($rules as $r) {
                $ranges[] = sprintf('%d-%d', intval($r['min_days']), intval($r['max_days']));
            }
            $attr_days = new WC_Product_Attribute();
            $attr_days->set_name('Days Range');
            $attr_days->set_options(array_values(array_unique($ranges)));
            $attr_days->set_position(2);
            $attr_days->set_visible(1);
            $attr_days->set_variation(1);
            $attr_objects[] = $attr_days;

            $product->set_attributes($attr_objects);
            $product->save();

            // Create variations (cartesian product)
            foreach ($country_values as $country_val) {
                foreach ($attr_days->get_options() as $range_label) {
                    // get corresponding rule
                    list($min, $max) = array_map('intval', explode('-', $range_label));
                    $matched_rule = null;
                    foreach ($rules as $r) {
                        if (intval($r['min_days']) === $min && intval($r['max_days']) === $max) {
                            $matched_rule = $r;
                            break;
                        }
                    }
                    if (!$matched_rule) continue;

                    // Price policy: price for min days (business rule)
                    $price_for_min_days = floatval($matched_rule['base_price']) * $min;

                    $variation = new WC_Product_Variation();
                    $variation->set_parent_id($product_id);
                    $variation_attr = [
                        sanitize_title('country') => $country_val, // variation attribute keys are sanitized form of name
                        sanitize_title('sim type') => ucfirst($sim_type),
                        sanitize_title('days range') => $range_label,
                    ];
                    // Use sanitized names consistent with attributes set earlier: set_attributes expects attribute slugs
                    // For non-taxonomy attributes, Woo expects the attribute names in variation attributes to be the same as set on product,
                    // but the WordPress sanitizer will transform them. We'll match by name lowercased.
                    // Set attributes directly on variation using set_attributes(array)
                    $variation->set_attributes([
                        'country' => $country_val,
                        'sim type' => ucfirst($sim_type),
                        'days range' => $range_label,
                    ]);

                    $variation->set_regular_price((string) number_format($price_for_min_days, 2, '.', ''));
                    $variation->set_stock_status('instock');
                    $variation->save();

                    // optionally store price_per_day meta
                    if (!empty($options['save_price_per_day_meta'])) {
                        update_post_meta($variation->get_id(), '_usaalo_price_per_day', floatval($matched_rule['base_price']));
                        update_post_meta($variation->get_id(), '_usaalo_rule_id', intval($matched_rule['id']));
                    }
                }
            }

            return ['success' => true, 'product_id' => $product_id, 'message' => __('Producto creado', 'usaalo-cotizador')];

        } catch (Exception $e) {
            return ['success' => false, 'product_id' => null, 'message' => $e->getMessage()];
        }
    }

    /* -----------------------------
     * Points Colombia (placeholder)
     * ----------------------------- */

    /**
     * Aplica puntos Colombia al monto dado (placeholder)
     * Implementación recomendada: hook en woocommerce_cart_calculate_fees / woocommerce_checkout_create_order
     *
     * @param float $total
     * @param int $points cantidad de puntos a aplicar
     * @param float $value_per_point (ej: 0.01)
     * @return array ['total_after'=>float,'discount'=>float]
     */
    public static function apply_colombia_points(float $total, int $points, float $value_per_point = 0.01) : array {
        $discount = min($total, $points * $value_per_point);
        $new_total = max(0, $total - $discount);
        return ['total_after' => round($new_total, 2), 'discount' => round($discount, 2)];
    }
}

/* End of includes/helpers.php */
