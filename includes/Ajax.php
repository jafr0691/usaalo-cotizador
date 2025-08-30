<?php
if (!defined('ABSPATH')) exit;

class USAC_Ajax {
    private static function v(){ check_ajax_referer('usac_nonce','nonce'); }

    public static function get_countries(){
        self::v(); global $wpdb;
        $q = sanitize_text_field($_POST['q'] ?? '');
        $rows = $wpdb->get_results($wpdb->prepare("
          SELECT code2 as id, CONCAT(name, ' (', UPPER(status), ')') as text, status
          FROM {$wpdb->prefix}usac_countries
          WHERE name LIKE %s OR code2 LIKE %s
          ORDER BY status='enabled' DESC, name ASC
          LIMIT 50", "%$q%","%$q%"), ARRAY_A);
        wp_send_json(['results'=>$rows]);
    }

    public static function get_brands(){
        self::v(); global $wpdb;
        $q = sanitize_text_field($_POST['q'] ?? '');
        $rows = $wpdb->get_results($wpdb->prepare("
          SELECT id, name as text FROM {$wpdb->prefix}usac_brands
          WHERE name LIKE %s ORDER BY name LIMIT 50", "%$q%"), ARRAY_A);
        wp_send_json(['results'=>$rows]);
    }

    public static function get_models(){
        self::v(); global $wpdb;
        $brand_id = absint($_POST['brand_id'] ?? 0);
        $q = sanitize_text_field($_POST['q'] ?? '');
        if (!$brand_id) wp_send_json(['results'=>[]]);
        $rows = $wpdb->get_results($wpdb->prepare("
          SELECT id, name as text FROM {$wpdb->prefix}usac_models
          WHERE brand_id=%d AND name LIKE %s ORDER BY name LIMIT 50", $brand_id, "%$q%"), ARRAY_A);
        wp_send_json(['results'=>$rows]);
    }

    public static function check_compat(){
        self::v();
        $countries = array_map('sanitize_text_field', $_POST['countries'] ?? []);
        $brand_id = absint($_POST['brand_id'] ?? 0);
        $model_id = absint($_POST['model_id'] ?? 0);

        $status = USAC_Rules::check_compatibility($brand_id,$model_id,$countries);
        wp_send_json_success($status);
    }

    public static function quote(){
        self::v();
        $payload = wp_unslash($_POST['payload'] ?? []);
        $quote = USAC_Rules::calculate_quote($payload);
        wp_send_json_success($quote);
    }

    public static function add_to_cart(){
        self::v();
        if (!class_exists('WC')) wp_send_json_error(['message'=>'WooCommerce requerido']);

        $payload = wp_unslash($_POST['payload'] ?? []);
        $product_id = USAC_WC::ensure_product($payload);
        if (!$product_id) wp_send_json_error(['message'=>'No se pudo preparar el producto']);

        $cart_item_data = [ 'usac' => $payload ];
        $key = WC()->cart->add_to_cart($product_id, 1, 0, [], $cart_item_data);
        if (!$key) wp_send_json_error(['message'=>'No se pudo agregar al carrito']);

        wp_send_json_success(['redirect'=>wc_get_cart_url()]);
    }
}
