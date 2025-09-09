<?php
if (!defined('ABSPATH')) exit;

class USAALO_Cache {

    public static $cache_file = WP_CONTENT_DIR . '/uploads/usaalo_products_cache.json';
    public static $transient_key = 'usaalo_products_cache';

    private static $runtime_cache = null;

    /**
     * Cargar caché en memoria para acceso instantáneo
     */
    private static function load_cache(): array {
        if (self::$runtime_cache !== null) return self::$runtime_cache;

        $cache = get_transient(self::$transient_key);
        if (!$cache && file_exists(self::$cache_file)) {
            $cache = json_decode(file_get_contents(self::$cache_file), true);
            set_transient(self::$transient_key, $cache, 12 * HOUR_IN_SECONDS);
        }

        self::$runtime_cache = $cache ?: [];
        return self::$runtime_cache;
    }

    public static function load_for_frontend() {
        $cache = self::load_cache();
        return $cache ?: [];
    }


    /**
     * Construir caché completa de productos
     */
    public static function build_cache(): array {
        if (!function_exists('wc_get_products')) return [];

        global $wpdb;
        $cache = [];

        $args = [
            'status'   => 'publish',
            'limit'    => -1,
            'category' => ['sim'], // categoría de productos
        ];

        $products = wc_get_products($args);

        foreach ($products as $product) {
            $p_id       = $product->get_id();
            $type       = $product->get_type();
            $base_price = floatval($product->get_price());
            $ranges     = [];

            // Procesar variaciones
            if ($type === 'variable') {
                foreach ($product->get_available_variations() as $var) {
                    if (!empty($var['attributes'])) {
                        foreach ($var['attributes'] as $val) {
                            if (preg_match('/^\d+-\d+$/', $val)) {
                                list($min, $max) = explode('-', $val);
                                $ranges[] = [
                                    'min'   => intval($min),
                                    'max'   => intval($max),
                                    'price' => floatval($var['display_price'])
                                ];
                            }
                        }
                    }
                }
                if (empty($ranges)) {
                    $ranges[] = ['min' => 1, 'max' => 9999, 'price' => $base_price];
                }
            }

            $min_price = !empty($ranges) ? min(array_column($ranges, 'price')) : $base_price;

            // Obtener países asociados al producto
            $table_pc = $wpdb->prefix . 'usaalo_product_country';
            $table_c  = $wpdb->prefix . 'usaalo_countries';

            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT pc.country_id, c.code
                 FROM $table_pc pc
                 INNER JOIN $table_c c ON pc.country_id = c.id
                 WHERE pc.product_id = %d",
                $p_id
            ));

            $countries = [];
            foreach ($rows as $r) {
                $countries[] = [
                    'id'   => intval($r->country_id),
                    'code' => $r->code
                ];
            }

            // Calcular costo de envío (si hay tarifa fija de WooCommerce)
            $shipping_cost = 0;
            $product_obj = wc_get_product($p_id);
            if ($product_obj && $product_obj->needs_shipping()) {
                $shipping_cost = floatval($product_obj->get_shipping_class() ? $product_obj->get_shipping_class() : 0);
            }

            $cache[$p_id] = [
                'product_id'    => $p_id,
                'name'          => $product->get_name(),
                'type'          => $type,
                'base_price'    => $base_price,
                'min_price'     => $min_price,
                'ranges'        => $ranges,
                'countries'     => $countries,
                'shipping_cost' => $shipping_cost
            ];
        }

        // Guardar JSON en archivo
        wp_mkdir_p(dirname(self::$cache_file));
        file_put_contents(
            self::$cache_file,
            wp_json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        // Guardar en transient
        set_transient(self::$transient_key, $cache, 12 * HOUR_IN_SECONDS);

        // Guardar en runtime
        self::$runtime_cache = $cache;

        return $cache;
    }

    /**
     * Obtener productos filtrando por códigos de país
     */
    public static function get_productos_por_country(array $country_codes): array {
        if (empty($country_codes)) return [];

        $cache = self::load_cache();
        $candidates = [];

        foreach ($cache as $p) {
            $matches = array_filter($p['countries'], fn($c) => in_array($c['code'], $country_codes));
            if (empty($matches)) continue;

            $candidates[] = array_merge($p, [
                'coverage' => count($matches),
                'matches'  => $matches
            ]);
        }

        if (empty($candidates)) return [];

        // Ordenar por mayor cobertura y menor precio mínimo
        usort($candidates, fn($a,$b) => $b['coverage'] <=> $a['coverage'] ?: $a['min_price'] <=> $b['min_price']);

        $max_coverage = $candidates[0]['coverage'];
        return array_values(array_filter($candidates, fn($p) => $p['coverage'] === $max_coverage));
    }

    /**
     * Obtener precios por país, días y sim física
     */
    public static function get_country_prices(array $country_codes, int $dias = 1, bool $sim_fisica = false): array {
        $prices = [];
        $products = self::get_productos_por_country($country_codes);
        if (empty($products)) return [];

        $total_price = 0;

        foreach ($products as $p) {
            $precio_base = $p['base_price'];

            // Determinar precio según rango
            if ($p['type'] === 'variable' && !empty($p['ranges'])) {
                foreach ($p['ranges'] as $r) {
                    if ($dias >= $r['min'] && $dias <= $r['max']) {
                        $precio_base = floatval($r['price']);
                        break;
                    }
                }
            }

            // Ajuste por SIM física
            if ($sim_fisica) {
                $precio_base += floatval($p['shipping_cost'] ?? 0);
            }

            $precio_total = round($precio_base, 2);
            $total_price += $precio_total;

            $prices[] = [
                'product_id' => $p['product_id'],
                'name'       => $p['name'],
                'price'      => $precio_total,
                'price_html' => wc_price($precio_total),
                'countries'  => $p['matches']
            ];
        }

        return [
            'total_price' => round($total_price,2),
            'total_html'  => wc_price(round($total_price,2)),
            'products'    => $prices
        ];
    }
}
