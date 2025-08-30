<?php
if (!defined('ABSPATH')) exit;

/**
 * Ajax.php
 * Endpoints AJAX para Usaalo Cotizador
 *
 * - Usa USAALO_Helpers para lógica común
 * - Respuestas estandarizadas con wp_send_json_success / wp_send_json_error
 * - Nonces y capacidades comprobadas donde aplica
 */

if (!class_exists('USAALO_Ajax')) {

class USAALO_Ajax {

    public static function init() {
        // Frontend (nopriv + auth)
        add_action('wp_ajax_usaalo_get_countries', [__CLASS__, 'get_countries']);
        add_action('wp_ajax_nopriv_usaalo_get_countries', [__CLASS__, 'get_countries']);

        add_action('wp_ajax_usaalo_get_brands', [__CLASS__, 'get_brands']);
        add_action('wp_ajax_nopriv_usaalo_get_brands', [__CLASS__, 'get_brands']);

        add_action('wp_ajax_usaalo_get_models', [__CLASS__, 'get_models']);
        add_action('wp_ajax_nopriv_usaalo_get_models', [__CLASS__, 'get_models']);

        add_action('wp_ajax_usaalo_get_services', [__CLASS__, 'get_services']);
        add_action('wp_ajax_nopriv_usaalo_get_services', [__CLASS__, 'get_services']);

        add_action('wp_ajax_usaalo_calculate_price', [__CLASS__, 'calculate_price']);
        add_action('wp_ajax_nopriv_usaalo_calculate_price', [__CLASS__, 'calculate_price']);

        add_action('wp_ajax_usaalo_get_pricing_rules', [__CLASS__, 'get_pricing_rules']);
        add_action('wp_ajax_nopriv_usaalo_get_pricing_rules', [__CLASS__, 'get_pricing_rules']);

        // Admin-only endpoints
        add_action('wp_ajax_usaalo_create_product', [__CLASS__, 'create_product_from_plan']); // admin
    }

    /* ---------------------------
     * Helpers / endpoints
     * --------------------------- */

    public static function get_countries() {
        $rows = USAALO_Helpers::get_countries();
        wp_send_json_success($rows);
    }

    public static function get_brands() {
        $rows = USAALO_Helpers::get_brands();
        wp_send_json_success($rows);
    }

    public static function get_models() {
        $brand_id = isset($_POST['brand_id']) ? intval($_POST['brand_id']) : 0;
        if (!$brand_id) return wp_send_json_success([]);
        $rows = USAALO_Helpers::get_models_by_brand($brand_id);
        wp_send_json_success($rows);
    }

    public static function get_services() {
        $country = isset($_POST['country_code']) ? sanitize_text_field($_POST['country_code']) : '';
        if (!$country) {
            // default: only data
            return wp_send_json_success(['services' => ['data']]);
        }
        $c = USAALO_Helpers::get_country($country);
        if (!$c) return wp_send_json_success(['services' => ['data']]);
        $services = (isset($c['supports_voice_sms']) && intval($c['supports_voice_sms']) === 1) ? ['data','voice','sms'] : ['data'];
        wp_send_json_success(['services' => $services]);
    }

    public static function get_pricing_rules() {
        $plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
        if (!$plan_id) return wp_send_json_error(__('Plan ID required', 'usaalo-cotizador'));
        $rules = USAALO_Helpers::get_pricing_rules($plan_id);
        wp_send_json_success($rules);
    }

    /**
     * calculate_price (frontend)
     * Uses USAALO_Helpers::calculate_price()
     */
    public static function calculate_price() {
        check_ajax_referer('usaalo_frontend_nonce', 'nonce');

        $countries = isset($_POST['country']) ? (array) $_POST['country'] : [];
        $sim_type = isset($_POST['sim_type']) ? sanitize_text_field($_POST['sim_type']) : 'esim';
        $services = isset($_POST['services']) ? (array) $_POST['services'] : [];
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $brand = isset($_POST['brand']) ? intval($_POST['brand']) : null;
        $model = isset($_POST['model']) ? intval($_POST['model']) : null;

        $res = USAALO_Helpers::calculate_price($countries, $sim_type, $services, $start_date, $end_date, $brand, $model);

        if (!$res['success']) {
            return wp_send_json_error(['errors' => $res['errors'] ?? [__('No se pudo calcular el precio','usaalo-cotizador')]]);
        }

        // success: return breakdown + total + days + compatibility
        return wp_send_json_success([
            'breakdown' => $res['breakdown'],
            'total' => $res['total'],
            'days' => $res['days'],
            'compatibility' => $res['compatibility'],
        ]);
    }

    /**
     * create_product_from_plan (ADMIN only)
     * Creates a WooCommerce variable product from plan_id or provided rules
     */
    public static function create_product_from_plan() {
        check_ajax_referer('usaalo_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            return wp_send_json_error(__('No tienes permisos para esta acción.', 'usaalo-cotizador'), 403);
        }

        if (!class_exists('WooCommerce')) {
            return wp_send_json_error(__('WooCommerce no está activo.', 'usaalo-cotizador'), 400);
        }

        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $countries = isset($_POST['countries']) ? (array) $_POST['countries'] : [];
        $countries = array_map('sanitize_text_field', $countries);
        $sim_type = isset($_POST['sim_type']) ? sanitize_text_field($_POST['sim_type']) : 'esim';
        $plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
        $save_meta = isset($_POST['save_price_per_day_meta']) ? boolval($_POST['save_price_per_day_meta']) : true;

        if (empty($countries) && !$plan_id) {
            return wp_send_json_error(__('Debes indicar al menos países o un plan.', 'usaalo-cotizador'));
        }

        global $wpdb;
        $rules = [];

        if ($plan_id) {
            $rules = USAALO_Helpers::get_pricing_rules($plan_id, $sim_type);
            if (empty($rules)) {
                return wp_send_json_error(__('No hay reglas de precio para el plan y tipo SIM indicados.', 'usaalo-cotizador'));
            }
        } else {
            // admin could pass custom 'pricing_rules' array (validate)
            if (isset($_POST['pricing_rules']) && is_array($_POST['pricing_rules'])) {
                foreach ($_POST['pricing_rules'] as $r) {
                    // Basic validation
                    if (!isset($r['min_days'], $r['max_days'], $r['base_price'])) continue;
                    $rules[] = [
                        'min_days' => intval($r['min_days']),
                        'max_days' => intval($r['max_days']),
                        'base_price' => floatval($r['base_price']),
                        'voice_addon' => floatval($r['voice_addon'] ?? 0),
                        'sms_addon' => floatval($r['sms_addon'] ?? 0),
                        'region_surcharge' => floatval($r['region_surcharge'] ?? 0),
                        'id' => intval($r['id'] ?? 0)
                    ];
                }
            }
            if (empty($rules)) return wp_send_json_error(__('No hay reglas de precio válidas proporcionadas.', 'usaalo-cotizador'));
        }

        // product title fallback
        $product_title = $title ?: sprintf(__('Plan %s', 'usaalo-cotizador'), implode(',', $countries));

        // Use helper to create WC product
        $created = USAALO_Helpers::create_wc_product_from_plan($product_title, $countries, $sim_type, $rules, ['save_price_per_day_meta' => $save_meta]);

        if (!$created['success']) {
            return wp_send_json_error($created['message']);
        }

        $product_id = intval($created['product_id']);
        $product_url = get_edit_post_link($product_id, ''); // admin edit link

        // If plan_id was provided, update plan record
        if ($plan_id) {
            $wpdb->update("{$wpdb->prefix}usaalo_plans", ['wc_product_id' => $product_id], ['id' => $plan_id], ['%d'], ['%d']);
        }

        return wp_send_json_success(['product_id' => $product_id, 'product_url' => $product_url]);
    }
}

USAALO_Ajax::init();
}
