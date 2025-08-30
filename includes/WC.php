<?php
if (!defined('ABSPATH')) exit;

class USAC_WC {
    public static function ensure_product($payload){
        if (!function_exists('wc_get_product')) return 0;
        $sku = 'USAC-GENERIC';
        $product_id = wc_get_product_id_by_sku($sku);
        $price = max(0.01, floatval($payload['quote']['total'] ?? 0));

        if (!$product_id){
            $p = new WC_Product_Simple();
            $p->set_name('Plan Internacional (Cotizador)');
            $p->set_sku($sku);
            $p->set_price($price);
            $p->set_regular_price($price);
            $p->set_virtual(true);
            $p->set_catalog_visibility('hidden');
            $product_id = $p->save();
        } else {
            $p = wc_get_product($product_id);
            $p->set_price($price);
            $p->set_regular_price($price);
            $p->save();
        }
        return $product_id;
    }

    public static function add_cart_item_data($data,$product_id,$variation_id){
        if (!empty($_POST['usac_payload'])){
            $payload = json_decode(stripslashes($_POST['usac_payload']), true);
            if (is_array($payload)) $data['usac'] = $payload;
        }
        return $data;
    }

    public static function save_order_item_meta($item,$cart_item_key,$values,$order){
        if (empty($values['usac'])) return;
        $p = $values['usac'];

        $map = [
            'countries' => implode(',', $p['countries'] ?? []),
            'brand_id'  => $p['device']['brand_id'] ?? '',
            'model_id'  => $p['device']['model_id'] ?? '',
            'sim_type'  => $p['sim_type'] ?? '',
            'eid'       => $p['device']['eid'] ?? '',
            'imei'      => $p['device']['imei'] ?? '',
            'services'  => json_encode($p['services'] ?? []),
            'start'     => $p['dates']['start'] ?? '',
            'end'       => $p['dates']['end'] ?? '',
            'days'      => $p['dates']['days'] ?? '',
            'quote'     => json_encode($p['quote'] ?? []),
        ];
        foreach ($map as $k=>$v){
            if ($v==='') continue;
            $item->add_meta_data('USAC '.$k, $v, true);
        }
    }
}
