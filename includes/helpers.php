<?php
if (!defined('ABSPATH')) exit;

/**
 * includes/helpers.php
 * Funciones helper comunes para Usaalo Cotizador
 * 
 * Mejoras:
 * - Modular y eficiente
 * - Compatible con WooCommerce
 * - Maneja compatibilidad de países, SIM y servicios
 */

class USAALO_Helpers {

    /* -----------------------------
     * Configuración del plugin
     * ----------------------------- */
    public static function get_settings(): array {
        $defaults = [
            'color_primary' => '#111827',
            'color_button' => '#111827',
            'text_next' => __('Siguiente', 'usaalo-cotizador'),
            'text_back' => __('Atrás', 'usaalo-cotizador'),
        ];
        $opts = get_option('usaalo_cotizador_settings', []);
        return wp_parse_args(is_array($opts) ? $opts : [], $defaults);
    }

    public static function update_settings(array $data): bool {
        return update_option('usaalo_cotizador_settings', $data);
    }

    // Conexión a la base de datos
    public static function db() {
        global $wpdb;
        return $wpdb;
    }

    /* ------------------------------ Countrys ------------------------------ */

    // Obtener todos los países
    public static function get_countries(): array {
        $wpdb = self::db();
        $table = $wpdb->prefix . 'usaalo_countries';
        return $wpdb->get_results("SELECT id, code, name, region FROM $table ORDER BY name ASC", ARRAY_A);
    }


    // Obtener el países
    public static function usaalo_get_countries($id): array {
        $wpdb = self::db();
        $table = $wpdb->prefix . 'usaalo_countries';
        return $wpdb->get_results("SELECT id, code, name, region FROM $table ORDER BY name ASC WHERE id = %d", $id, ARRAY_A);
    }

    // Obtener un país por código
    public static function usaalo_get_country($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'usaalo_countries';
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id),
            ARRAY_A
        );
    }


    // Guardar o actualizar un país
    public static function usaalo_save_country($data) {
        $wpdb  = self::db();
        $table = $wpdb->prefix . 'usaalo_countries';

        // Si viene un ID -> actualizar
        if (!empty($data['id'])) {
            $id = intval($data['id']);
            $fields = [
                'code'   => isset($data['code']) ? sanitize_text_field($data['code']) : '',
                'name'   => isset($data['name']) ? sanitize_text_field($data['name']) : '',
                'region' => isset($data['region']) ? sanitize_text_field($data['region']) : '',
            ];
            return $wpdb->update($table, $fields, ['id' => $id]);
        }

        // Crear: separar name, code y region si vienen con comas
        if (!empty($data['name']) && !empty($data['code'])) {
            $names   = array_map('trim', explode(',', sanitize_text_field($data['name'])));
            $codes   = array_map('trim', explode(',', sanitize_text_field($data['code'])));
            $regions = !empty($data['region'])
                ? array_map('trim', explode(',', sanitize_text_field($data['region'])))
                : [];

            $insert_ids = [];
            foreach ($names as $i => $name) {
                if ($name === '') continue;

                $nameFormatted = ucfirst(strtolower($name));
                $code         = isset($codes[$i]) ? strtoupper($codes[$i]) : strtoupper(substr($nameFormatted, 0, 3));
                $region       = isset($regions[$i]) ? $regions[$i] : ($regions[0] ?? '');

                // Validar duplicados (por nombre o code)
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table WHERE name = %s OR code = %s",
                    $nameFormatted, $code
                ));
                if ($exists) continue;

                $insert_data = [
                    'code'   => $code,
                    'name'   => $nameFormatted,
                    'region' => $region,
                ];

                $wpdb->insert($table, $insert_data);
                $insert_ids[] = $wpdb->insert_id;
            }

            return $insert_ids;
        }

        return false;
    }

    // Eliminar un país
    public static function usaalo_delete_country($id) {
        $wpdb = self::db();
        $table = $wpdb->prefix . 'usaalo_countries';
        return $wpdb->delete($table, ['id' => $id]);
    }









/* ------------------------------ Brands ------------------------------ */

// Obtener marcas disponibles
    public static function get_brands(): array {
        $wpdb = self::db();
        $table = $wpdb->prefix . 'usaalo_brands';
        return $wpdb->get_results("SELECT id, name, slug FROM $table ORDER BY name ASC", ARRAY_A);
    }
// Obtener una marca por ID
    public static function usaalo_get_brand($brand_id) {
        $wpdb = self::db();
        $table = $wpdb->prefix . 'usaalo_brands';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $brand_id), ARRAY_A);
    }

    // Guardar o actualizar una marca
    public static function usaalo_save_brand($data) {
        $wpdb = self::db();
        $table = $wpdb->prefix . 'usaalo_brands';

        // Si viene un ID, actualizamos
        if (!empty($data['id'])) {
            $id = intval($data['id']);
            $update_data = $data;
            unset($update_data['id']); // eliminar id del array de datos para update

            // Formatear name y slug
            if (!empty($update_data['name'])) {
                $nameFormatted = ucfirst(strtolower(trim($update_data['name'])));
                $update_data['name'] = $nameFormatted;
                $update_data['slug'] = strtolower(trim($update_data['name']));
            }

            return $wpdb->update($table, $update_data, ['id' => $id]);
        }

        // Crear: si viene name con comas, separar e insertar cada uno
        if (!empty($data['name'])) {
            $names = array_map('trim', explode(',', $data['name'])); // separar y limpiar espacios
            $insert_ids = [];

            foreach ($names as $name) {
                if ($name === '') continue; // ignorar cadenas vacías
                $nameFormatted = ucfirst(strtolower($name));
                $slug = strtolower($name);

                // Verificar si ya existe la marca
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table WHERE name = %s",
                    $nameFormatted
                ));

                if ($exists) continue; // si ya existe, no insertar

                $insert_data = [
                    'name' => $nameFormatted,
                    'slug' => $slug,
                ];

                $wpdb->insert($table, $insert_data);
                $insert_ids[] = $wpdb->insert_id;
            }

            return $insert_ids; // retorna array con los IDs insertados
        }

        return false; // si no hay nombre, no hacer nada
    }


    // Eliminar una marca
    public static function usaalo_delete_brand($brand_id) {
        $wpdb = self::db();
        $table = $wpdb->prefix . 'usaalo_brands';
        return $wpdb->delete($table, ['id' => intval($brand_id)]);
    }



    



    

    /* ------------------------------ Models ------------------------------ */

    // Obtener modelos por marca y países
    public static function get_models(int $brand_id = null, array $countries = []): array {
        $wpdb = self::db();
        $table_models = $wpdb->prefix . 'usaalo_models';
        $table_compat = $wpdb->prefix . 'usaalo_device_country';
        $table_brands = $wpdb->prefix . 'usaalo_brands';

        $where = [];
        $params = [];

        if ($brand_id) {
            $where[] = "m.brand_id = %d";
            $params[] = $brand_id;
        }

        if (!empty($countries)) {
            $placeholders = implode(',', array_fill(0, count($countries), '%s'));
            $where[] = "d.country_id IN ($placeholders)";
            $params = array_merge($params, $countries);
        }

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT DISTINCT m.id, m.name, m.slug, b.name AS brand_name,
                d.country_id, d.esim_supported, d.voice_supported, d.sms_supported, d.data_supported
            FROM $table_models m
            LEFT JOIN $table_compat d ON m.id = d.model_id
            LEFT JOIN $table_brands b ON m.brand_id = b.id
            $where_sql
            ORDER BY m.name ASC
        ";

        return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) ?: [];
    }

    // Obtener un modelo por ID
    public static function usaalo_get_model($model_id) {
        $wpdb = self::db();
        $table = $wpdb->prefix . 'usaalo_models';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $model_id), ARRAY_A);
    }
    // Guardar o actualizar un modelo
    public static function usaalo_save_model($data) {
        $wpdb = self::db();
        $table = $wpdb->prefix . 'usaalo_models';
        $config_table = $wpdb->prefix . 'usaalo_device_config';

        // Actualizar modelo existente
        if (!empty($data['id'])) {
            $id = intval($data['id']);
            $update_data = $data;
            unset($update_data['id'], $update_data['nonce'], $update_data['action']);

            // Formatear name y slug
            if (!empty($update_data['name'])) {
                $nameFormatted = ucfirst(strtolower(trim($update_data['name'])));
                $update_data['name'] = $nameFormatted;
                $update_data['slug'] = strtolower(trim($update_data['name']));
            }

            return $wpdb->update($table, $update_data, ['id' => $id]);
        }

        // Crear nuevos modelos si viene name con comas
        if (!empty($data['name']) && !empty($data['brand_id'])) {
            $names = array_map('trim', explode(',', $data['name']));
            $insert_ids = [];
            $brand_id = intval($data['brand_id']);

            foreach ($names as $name) {
                if ($name === '') continue;
                $nameFormatted = ucfirst(strtolower($name));
                $slug = strtolower($name);

                // Verificar si ya existe el modelo para esa marca
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table WHERE name = %s AND brand_id = %d",
                    $nameFormatted,
                    $brand_id
                ));

                if ($exists) continue;

                // Insertar modelo
                $wpdb->insert($table, [
                    'brand_id' => $brand_id,
                    'name' => $nameFormatted,
                    'slug' => $slug,
                ]);

                $model_id = $wpdb->insert_id;
                $insert_ids[] = $model_id;

                // Insertar configuración global inicial (solo al crear)
                $wpdb->insert($config_table, [
                    'model_id' => $model_id,
                    'sim_supported' => 1,
                    'esim_supported' => 1,
                    'voice_supported' => 0,
                    'sms_supported' => 0,
                    'data_supported' => 1,
                ]);
            }

            return $insert_ids;
        }

        return false;
    }

    // Eliminar un modelo
    public static function usaalo_delete_model($model_id) {
        $wpdb = self::db();
        $table = $wpdb->prefix . 'usaalo_models';
        return $wpdb->delete($table, ['id' => intval($model_id)]);
    }


    public static function get_sim_servicios() {
        $wpdb = self::db();

        $table_device_country = $wpdb->prefix . 'usaalo_device_country';
        $table_device_config  = $wpdb->prefix . 'usaalo_device_config';
        $table_models         = $wpdb->prefix . 'usaalo_models';
        $table_brands         = $wpdb->prefix . 'usaalo_brands';
        $table_countries      = $wpdb->prefix . 'usaalo_countries';

        $sql = "
            SELECT 
                c.id AS country_id,
                m.id AS model_id,
                c.name AS country_name,
                b.name AS brand_name,
                m.name AS model_name,
                COALESCE(dc.sim_supported, cfg.sim_supported, 1)   AS sim_supported,
                COALESCE(dc.esim_supported, cfg.esim_supported, 1) AS esim_supported,
                COALESCE(dc.voice_supported, cfg.voice_supported, 0) AS voice_supported,
                COALESCE(dc.sms_supported, cfg.sms_supported, 0)    AS sms_supported,
                COALESCE(dc.data_supported, cfg.data_supported, 1)  AS data_supported
            FROM {$table_models} m
            INNER JOIN {$table_brands} b ON b.id = m.brand_id
            CROSS JOIN {$table_countries} c
            LEFT JOIN {$table_device_config} cfg ON cfg.model_id = m.id
            LEFT JOIN {$table_device_country} dc 
                ON dc.model_id = m.id AND dc.country_id = c.id
            ORDER BY c.name, b.name, m.name
        ";

        $results = $wpdb->get_results($sql . " LIMIT 50", ARRAY_A);

        return $results;
    }

    public static function usaalo_update_service($data) {
        $wpdb = self::db();

        $table_device_config  = $wpdb->prefix . 'usaalo_device_config';
        $table_device_country = $wpdb->prefix . 'usaalo_device_country';

        $model_id   = intval($data['model_id']);
        $country_id = intval($data['country_id']);
        $field      = sanitize_key($data['field']);
        $value      = intval($data['value']);

        // Validar campo y IDs
        $allowed_fields = ['sim_supported','esim_supported','voice_supported','sms_supported','data_supported'];
        if (!in_array($field, $allowed_fields) || $model_id <= 0 || $country_id <= 0) return false;

        // Valor global
        $global_value = $wpdb->get_var($wpdb->prepare(
            "SELECT $field FROM $table_device_config WHERE model_id = %d",
            $model_id
        ));

        if ($global_value === null) {
            // Crear registro global por defecto si no existe
            $wpdb->insert($table_device_config, [
                'model_id' => $model_id,
                'sim_supported' => 1,
                'esim_supported' => 1,
                'voice_supported' => 0,
                'sms_supported' => 0,
                'data_supported' => 1
            ]);
            $global_value = 1;
        }

        // Si el valor nuevo == global → eliminar override
        if ($value == $global_value) {
            $wpdb->delete($table_device_country, [
                'model_id' => $model_id,
                'country_id' => $country_id
            ], ['%d','%d']);
            return true;
        }

        // Si difiere, insert/update
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_device_country WHERE model_id = %d AND country_id = %d",
            $model_id, $country_id
        ));

        if ($exists) {
            return $wpdb->update($table_device_country, [$field => $value], ['id' => $exists], ['%d'], ['%d']);
        } else {
            return $wpdb->insert($table_device_country, [
                'model_id' => $model_id,
                'country_id' => $country_id,
                $field => $value
            ], ['%d','%d','%d']);
        }
    }






    /* ------------------------------ Planes producto ------------------------------ */
    

    // public static function get_plan_data() {
    //     global $wpdb;

    //     $table_product_country = $wpdb->prefix . 'usaalo_product_country';
    //     $table_countries       = $wpdb->prefix . 'usaalo_countries';

    //     // 🔹 Total de países en la tabla usaalo_countries
    //     $total_countries = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_countries");

    //     $args = [
    //         'post_type'      => 'product',
    //         'posts_per_page' => -1,
    //         'post_status'    => ['publish','draft'],
    //         'tax_query'      => [
    //             [
    //                 'taxonomy' => 'product_cat',
    //                 'field'    => 'slug',
    //                 'terms'    => 'sim',
    //             ]
    //         ]
    //     ];
    //     $query = new WP_Query($args);

    //     $data = [];

    //     foreach ($query->posts as $post) {
    //         $wc_product = wc_get_product($post->ID);
    //         if (!$wc_product) continue;

    //         $rows = $wpdb->get_results($wpdb->prepare("
    //             SELECT c.name 
    //             FROM $table_product_country pc
    //             INNER JOIN $table_countries c ON c.id = pc.country_id
    //             WHERE pc.product_id = %d
    //         ", $wc_product->get_id()));

    //         $country_names = $rows ? wp_list_pluck($rows, 'name') : [];
    //         $countries_str = $country_names ? implode(', ', $country_names) : '';

    //         $data[] = [
    //             'id'              => $wc_product->get_id(),
    //             'image'           => wp_get_attachment_url($wc_product->get_image_id()),
    //             'name'            => $wc_product->get_name(),
    //             'typeProduct'     => $wc_product->get_type(), // simple | variable
    //             'countries'       => $countries_str,         // Texto completo
    //             'countries_list'  => $country_names,         // Array
    //             'countries_count' => count($country_names),  // Cantidad
    //             'total_countries' => $total_countries,       // 🔹 Total global
    //             'price'           => $wc_product->get_price(),
    //             'active'          => $wc_product->get_status() === 'publish',
    //         ];
    //     }

    //     return $data;
    // }







    /* ------------------------------ Elimina lo seleccionado en las tablas ------------------------------ */

    // Delete bulk from table (dinámico con PK configurable)
    public static function usaalo_bulk_delete($table, $idsall, $pk = 'id') {
        $wpdb = self::db();

        // Tablas permitidas
        $allowed_tables = [
            $wpdb->prefix . 'usaalo_countries',
            $wpdb->prefix . 'usaalo_brands',
            $wpdb->prefix . 'usaalo_models',
            $wpdb->prefix . 'usaalo_device_country',
            $wpdb->prefix . 'usaalo_product_country',
        ];

        $table_name = $wpdb->prefix . $table;

        if (!in_array($table_name, $allowed_tables, true)) {
            wp_send_json_error(['message' => 'Tabla no permitida']);
        }

        // Validar que la columna sea segura (solo letras, números y guion bajo)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $pk)) {
            wp_send_json_error(['message' => 'Columna PK no válida']);
        }

        // Normalizar IDs
        $ids = array_map('intval', (array) $idsall);

        if (empty($ids)) {
            return false; // nada que eliminar
        }

        // Query dinámica
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = "DELETE FROM $table_name WHERE $pk IN ($placeholders)";
        $deleted = $wpdb->query($wpdb->prepare($sql, $ids));

        return $deleted !== false ? $deleted : false;
    }
















    /**
     * Obtener precios por tipo de SIM (sim física y esim).
     * - Lee opción 'usaalo_sim_prices' si existe (array con keys 'sim' y 'esim')
     * - Si no existe, sim = coste de envío por defecto; esim = promedio de meta '_usaalo_servicio_precio_esim'
     *
     * @return array ['sim' => float, 'esim' => float]
     */
    public static function get_sim_prices(): array {
        // 1) Intentar leer opción (admin)
        $opt = get_option('usaalo_sim_prices', false);
        if (is_array($opt) && (isset($opt['sim']) || isset($opt['esim']))) {
            $sim_val  = isset($opt['sim']) ? floatval($opt['sim']) : 0.0;
            $esim_val = isset($opt['esim']) ? floatval($opt['esim']) : 0.0;
            return [
                'sim'  => round($sim_val, 2),
                'esim' => round($esim_val, 2),
            ];
        }

        // 2) Si no hay opción, calcular valores por defecto
        $sim_price = 0.0;
        $esim_price = 0.0;

        // 2.a) obtener coste de envío por defecto para SIM física
        $sim_price = self::get_default_shipping_cost();

        // 2.b) calcular precio promedio de _usaalo_servicio_precio_esim en productos publicados
        $wpdb = self::db();
        $meta_key = '_usaalo_servicio_precio_esim';

        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT pm.meta_value
                FROM {$wpdb->prefix}postmeta pm
                INNER JOIN {$wpdb->prefix}posts p ON pm.post_id = p.ID
                WHERE pm.meta_key = %s
                AND p.post_type = 'product'
                AND p.post_status = 'publish'
                ",
                $meta_key
            )
        );

        $sum = 0.0;
        $count = 0;
        if (!empty($rows)) {
            foreach ($rows as $val) {
                $v = floatval($val);
                if ($v > 0) {
                    $sum += $v;
                    $count++;
                }
            }
        }

        if ($count > 0) {
            $esim_price = round($sum / $count, 2);
        } else {
            // fallback: 0 (puedes cambiar a un valor por defecto)
            $esim_price = 0.0;
        }

        return [
            'sim'  => round(floatval($sim_price), 2),
            'esim' => round(floatval($esim_price), 2),
        ];
    }

    /**
     * Helper privado: intenta obtener un coste de envío por defecto (primera tarifa válida encontrada).
     * Devuelve 0.0 si no puede determinar un coste.
     *
     * @return float
     */
    private static function get_default_shipping_cost(): float {
        if (!class_exists('WC_Shipping_Zones') || !function_exists('WC')) {
            return 0.0;
        }

        $shipping_cost = 0.0;

        // Buscar entre zonas y métodos la primera tarifa con costo > 0
        try {
            $zones = WC_Shipping_Zones::get_zones();

            foreach ($zones as $zone) {
                // cada $zone es un array con 'id'
                if (empty($zone['id'])) continue;
                $zone_obj = new WC_Shipping_Zone($zone['id']);
                $zone_methods = $zone_obj->get_shipping_methods();

                foreach ($zone_methods as $method) {
                    if (isset($method->enabled) && $method->enabled === 'yes') {
                        // intentar obtener opción 'cost' (para flat_rate u otros)
                        if (method_exists($method, 'get_option')) {
                            $cost = floatval($method->get_option('cost', 0));
                        } else {
                            $cost = floatval($method->settings['cost'] ?? 0);
                        }

                        if ($cost > 0) {
                            $shipping_cost = $cost;
                            break 2; // salimos al encontrar la primera tarifa válida
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // silent fail: devolver 0
            $shipping_cost = 0.0;
        }

        return round($shipping_cost, 2);
    }












    public static function get_country_prices(array $country_codes, int $dias = 1, bool $sim_fisica = false): array {
        $prices = [];

        if (empty($country_codes) || $dias < 1) return [];

        // Normalizar codes
        $country_codes = array_map('strtoupper', $country_codes);

        // Obtener productos del cache por code
        $products = USAALO_cache::get_productos_por_country($country_codes);
        if (empty($products)) return [];

        $total_price = 0;
        $products_selected = [];

        foreach ($products as $p) {
            $price_for_days = 0;

            if ($p['type'] === 'variable' && !empty($p['ranges'])) {
                // Crear índice de rangos: clave = min-max, valor = price
                $range_map = [];
                foreach ($p['ranges'] as $r) {
                    $range_map[$r['min'].'-'.$r['max']] = floatval($r['price']);
                }

                // Buscar rango correcto usando comparación directa
                foreach ($range_map as $range => $price) {
                    list($min, $max) = explode('-', $range);
                    if ($dias >= intval($min) && $dias <= intval($max)) {
                        $price_for_days = $price * $dias;
                        break;
                    }
                }

                // Si no encuentra rango, usar base_price
                if ($price_for_days === 0) {
                    $price_for_days = $p['base_price'] * $dias;
                }

            } else {
                $price_for_days = $p['base_price'] * $dias;
            }

            $total_price += $price_for_days;

            $products_selected[] = [
                'product_id' => $p['product_id'],
                'name'       => $p['name'],
                'price'      => round($price_for_days, 2),
                'price_html' => wc_price(round($price_for_days, 2)),
                'countries'  => $p['matches'], // id y code
            ];
        }

        // SIM física → agregar envío
        if ($sim_fisica) {
            $shipping_cost = 0;
            $shipping_methods = WC()->shipping()->get_shipping_methods();
            foreach ($shipping_methods as $method) {
                if ($method->enabled === 'yes') {
                    $zones = WC_Shipping_Zones::get_zones();
                    foreach ($zones as $zone) {
                        $zone_obj = new WC_Shipping_Zone($zone['id']);
                        $zone_methods = $zone_obj->get_shipping_methods();
                        foreach ($zone_methods as $zm) {
                            if ($zm->enabled === 'yes') {
                                $shipping_cost = floatval($zm->get_instance_form_fields()['cost']['default'] ?? 0);
                                break 3;
                            }
                        }
                    }
                }
            }
            $total_price += $shipping_cost;
        }

        return [
            'total_price'   => round($total_price, 2),
            'total_html'    => wc_price(round($total_price, 2)),
            'products'      => $products_selected
        ];
    }










    /**
     * Obtener todos los modelos agrupados por marca
     * @return array
     */
    public static function get_all_models(): array {
        $wpdb = self::db();
        $table = $wpdb->prefix . 'usaalo_models';
        $table_brands = $wpdb->prefix . 'usaalo_brands';

        $rows = $wpdb->get_results("
            SELECT m.id, m.name, m.slug, m.brand_id, b.name AS brand_name
            FROM $table m
            INNER JOIN $table_brands b ON m.brand_id = b.id
            ORDER BY b.name ASC, m.name ASC
        ", ARRAY_A);

        $grouped = [];
        foreach ($rows as $row) {
            $brandId = $row['brand_id'];
            if (!isset($grouped[$brandId])) {
                $grouped[$brandId] = [];
            }
            $grouped[$brandId][] = [
                'id'   => $row['id'],
                'name' => $row['name'],
                'slug' => $row['slug'],
            ];
        }

        return $grouped;
    }



    /**
     * Obtiene todos los países con disponibilidad de productos
     */
    public static function get_countries_with_availability(bool $only_available = false): array {
        $wpdb = self::db();

        $table_countries       = $wpdb->prefix . 'usaalo_countries';
        $table_product_country = $wpdb->prefix . 'usaalo_product_country';
        $table_posts           = $wpdb->prefix . 'posts';
        $table_term_rel        = $wpdb->prefix . 'term_relationships';
        $table_term_tax        = $wpdb->prefix . 'term_taxonomy';
        $table_terms           = $wpdb->prefix . 'terms';

        // 🔹 Obtenemos países y verificamos si tienen productos publicados en categoría SIM
        $query = "
            SELECT c.id, c.code, c.name,
                CASE WHEN COUNT(p.ID) > 0 THEN 1 ELSE 0 END AS disponible
            FROM $table_countries c
            LEFT JOIN $table_product_country pc ON c.id = pc.country_id
            LEFT JOIN $table_posts p 
                ON p.ID = pc.product_id 
                AND p.post_type = 'product' 
                AND p.post_status = 'publish'
            LEFT JOIN $table_term_rel tr ON tr.object_id = p.ID
            LEFT JOIN $table_term_tax tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            LEFT JOIN $table_terms t ON t.term_id = tt.term_id
                AND tt.taxonomy = 'product_cat'
                AND (t.slug = 'sim' OR t.name = 'Sim' OR t.name = 'SIM')
            GROUP BY c.id, c.code, c.name
            ORDER BY c.name ASC
        ";

        $countries = $wpdb->get_results($query, ARRAY_A);

        if (!$countries) {
            return [];
        }

        // Procesar resultados
        foreach ($countries as &$c) {
            $c['disponible'] = (bool) $c['disponible'];
            $c['mensaje']    = $c['disponible'] ? '' : __('Próximamente', 'usaalo-cotizador');
        }

        // Solo devolver los disponibles si se solicita
        if ($only_available) {
            $countries = array_filter($countries, fn($c) => $c['disponible']);
        }

        return $countries;
    }


    public static function get_shipping_cost( $country_code = 'CO' ) {
        if ( ! class_exists('WooCommerce') ) return 0;

        // Obtener todas las zonas de envío
        $shipping_zones = WC_Shipping_Zones::get_zones();

        $found_rates = [];

        foreach ( $shipping_zones as $zone ) {
            // Buscar la zona llamada SIM
            if ( stripos($zone['zone_name'], 'SIM') !== false ) {
                foreach ( $zone['shipping_methods'] as $method ) {
                    if ( $method->enabled === 'yes' ) {
                        // Capturar el costo del método
                        if ( isset($method->cost) && $method->cost !== '' ) {
                            $found_rates[] = floatval( $method->cost );
                        } elseif ( isset($method->instance_settings['cost']) && $method->instance_settings['cost'] !== '' ) {
                            $found_rates[] = floatval( $method->instance_settings['cost'] );
                        }
                    }
                }
            }
        }

        // Si no encuentra nada, devuelve -1 para depurar
        if ( empty($found_rates) ) {
            return -1;
        }

        // Siempre el más barato
        return min($found_rates);
    }





    public static function get_productos_por_country($country_ids): array {
        $cache = USAALO_Cache::get_cache();
        $country_ids = is_array($country_ids) ? $country_ids : [$country_ids];
        
        $productos = [];
        foreach ($cache as $p) {
            $coverage = count(array_intersect($p['countries'], $country_ids));
            if ($coverage > 0) {
                $productos[] = [
                    'product_id'     => $p['product_id'],
                    'base_price'     => $p['base_price'],
                    'variations'     => $p['variations'],
                    'coverage_count' => $coverage,
                ];
            }
        }

        // aplicar lógica avanzada aquí (como ya te lo dejé en la otra respuesta)
        return $productos;
    }






    /**
     * Obtener servicios disponibles por países y modelo
     */
    public static function servicios_disponibles_por_countries(array $country_codes, int $model_id = null): array {
        $wpdb = self::db();
        if (empty($country_codes) || !$model_id) return ['sin configuración'];

        $table_config    = $wpdb->prefix . 'usaalo_device_config';
        $table_country   = $wpdb->prefix . 'usaalo_device_country';
        $table_countries = $wpdb->prefix . 'usaalo_countries';

        $placeholders = implode(',', array_fill(0,count($country_codes),'%s'));
        $country_rows = $wpdb->get_results($wpdb->prepare("SELECT id, code FROM $table_countries WHERE code IN ($placeholders)", ...$country_codes), ARRAY_A);
        if (!$country_rows) return ['sin configuración'];
        $country_ids = array_column($country_rows,'id');

        $global = $wpdb->get_row($wpdb->prepare("SELECT sim_supported, esim_supported, voice_supported, sms_supported, data_supported FROM $table_config WHERE model_id=%d",$model_id));
        if (!$global) return ['sin configuración'];

        $placeholders_ids = implode(',', array_fill(0,count($country_ids),'%d'));
        $sql = "SELECT country_id,
                COALESCE(sim_supported,%d) AS sim,
                COALESCE(esim_supported,%d) AS esim,
                COALESCE(voice_supported,%d) AS voice,
                COALESCE(sms_supported,%d) AS sms,
                COALESCE(data_supported,%d) AS data
                FROM $table_country
                WHERE model_id=%d AND country_id IN ($placeholders_ids)";

        $params = array_merge([$global->sim_supported,$global->esim_supported,$global->voice_supported,$global->sms_supported,$global->data_supported,$model_id],$country_ids);
        $results = $wpdb->get_results($wpdb->prepare($sql,...$params));

        $services = [];
        foreach ($country_rows as $c) {
            $row = null;
            foreach ($results as $r) { if ((int)$r->country_id === (int)$c['id']) { $row=$r; break; } }
            if (!$row) $row = (object)[
                'sim'=>$global->sim_supported,'esim'=>$global->esim_supported,
                'voice'=>$global->voice_supported,'sms'=>$global->sms_supported,
                'data'=>$global->data_supported
            ];

            $country_services=['code'=>$c['code'],'id'=>$c['id'],'services'=>[]];
            if($row->data) $country_services['services'][]='datos';
            if($row->voice) $country_services['services'][]='llamadas';
            if($row->sms) $country_services['services'][]='sms';
            if($row->esim) $country_services['services'][]='esim';
            if($row->sim) $country_services['services'][]='sim';

            $services[$c['id']] = !empty($country_services['services']) ? $country_services : ['code'=>$c['code'],'id'=>$c['id'],'services'=>['sin configuración']];
        }

        return $services;
    }

    public static function servicios_disponibles_todos_modelos(): array {
        $wpdb = self::db();

        // Obtener todos los modelos
        $models = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}usaalo_device_config");

        // Obtener todos los países
        $countries = $wpdb->get_results("SELECT id, code FROM {$wpdb->prefix}usaalo_countries");

        $services_cache = [];

        foreach ($models as $model) {
            $model_id = (int)$model->id;

            // Obtener configuración global del modelo
            $global = $wpdb->get_row($wpdb->prepare(
                "SELECT sim_supported, esim_supported, voice_supported, sms_supported, data_supported 
                FROM {$wpdb->prefix}usaalo_device_config 
                WHERE model_id=%d",
                $model_id
            ));

            if (!$global) continue;

            // Obtener configuraciones por país
            $country_ids = array_column((array)$countries, 'id');
            $placeholders = implode(',', array_fill(0, count($country_ids), '%d'));

            $sql = "SELECT country_id,
                        COALESCE(sim_supported,%d) AS sim,
                        COALESCE(esim_supported,%d) AS esim,
                        COALESCE(voice_supported,%d) AS voice,
                        COALESCE(sms_supported,%d) AS sms,
                        COALESCE(data_supported,%d) AS data
                    FROM {$wpdb->prefix}usaalo_device_country
                    WHERE model_id=%d AND country_id IN ($placeholders)";

            $params = array_merge(
                [$global->sim_supported, $global->esim_supported, $global->voice_supported, $global->sms_supported, $global->data_supported, $model_id],
                $country_ids
            );

            $results = $wpdb->get_results($wpdb->prepare($sql, ...$params));

            // Mapear resultados por country_id
            $results_map = [];
            foreach ($results as $r) {
                $results_map[$r->country_id] = $r;
            }

            // Generar servicios por país
            foreach ($countries as $c) {
                $row = $results_map[$c->id] ?? (object)[
                    'sim'=>$global->sim_supported,
                    'esim'=>$global->esim_supported,
                    'voice'=>$global->voice_supported,
                    'sms'=>$global->sms_supported,
                    'data'=>$global->data_supported
                ];

                $country_services = ['code'=>$c->code, 'id'=>$c->id, 'services'=>[]];
                if ($row->data) $country_services['services'][] = 'datos';
                if ($row->voice) $country_services['services'][] = 'llamadas';
                if ($row->sms) $country_services['services'][] = 'sms';
                if ($row->esim) $country_services['services'][] = 'esim';
                if ($row->sim) $country_services['services'][] = 'sim';

                $services_cache[$model_id][$c->code] = !empty($country_services['services']) 
                    ? $country_services 
                    : ['code'=>$c->code, 'id'=>$c->id, 'services'=>['sin configuración']];
            }
        }

        return $services_cache;
    }

    /**
     * Calcular precio de plan WooCommerce por días, servicios y SIM física
     */
    public static function calcular_precio_plan($plan_id, $dias=1, $servicios=[], $sim_fisica=false): float {
        $plan_ids = is_array($plan_id) ? $plan_id : [$plan_id];
        $precio_total=0;

        foreach ($plan_ids as $product_id) {
            if (!$product_id) continue;
            $product = wc_get_product($product_id);
            if (!$product) continue;

            $precio_plan=0;

            // Producto variable según rango de días
            if ($product->is_type('variable')) {
                $variations = $product->get_available_variations();
                $found=false;
                foreach ($variations as $v) {
                    if (!empty($v['attributes']['attribute_rango_de_dias'])) {
                        $range=explode('-',$v['attributes']['attribute_rango_de_dias']);
                        $min=intval($range[0]);
                        $max=intval($range[1]??$min);
                        if($dias>=$min && $dias<=$max){ $precio_plan=floatval($v['display_price']); $found=true; break; }
                    }
                }
                if(!$found && !empty($variations)) $precio_plan=floatval($variations[0]['display_price']);
            } else { // simple
                $precio_plan=floatval($product->get_price())*$dias;
            }

            // Servicios extra
            foreach ($servicios as $s) {
                if(in_array($s,['llamadas','sms','esim'])){
                    $meta='_usaalo_servicio_precio_'.$s;
                    $precio_plan+=floatval(get_post_meta($product_id,$meta,true));
                }
            }

            // SIM física → agregar costo envío
            if($sim_fisica){
                $shipping_cost=0;
                $methods = WC()->shipping()->get_shipping_methods();
                foreach($methods as $method){
                    if($method->enabled==='yes'){
                        $zones = WC_Shipping_Zones::get_zones();
                        foreach($zones as $zone){
                            $zone_obj = new WC_Shipping_Zone($zone['id']);
                            $zone_methods = $zone_obj->get_shipping_methods();
                            foreach($zone_methods as $zm){
                                if($zm->enabled==='yes'){
                                    $shipping_cost=floatval($zm->get_instance_form_fields()['cost']['default'] ?? 0);
                                    break 3;
                                }
                            }
                        }
                    }
                }
                $precio_plan+=$shipping_cost;
            }

            $precio_total+=$precio_plan;
        }

        return round($precio_total,2);
    }

    public static function get_countries_regions(string $search = '', int $product_id = 0): array {
        $wpdb = self::db();

        $table_countries = $wpdb->prefix . 'usaalo_countries';

        $sql = "SELECT id, name, code, region
                FROM $table_countries
                WHERE 1=1";
        $params = [];

        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $sql .= " AND (name LIKE %s OR region LIKE %s)";
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= " ORDER BY region, name";

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params));

        if (!$rows) return [];

        // Agrupar por la columna `region`
        $grouped = [];
        foreach ($rows as $row) {
            $region = $row->region ?: __('Sin región', 'usaalo-cotizador');
            if (!isset($grouped[$region])) {
                $grouped[$region] = [
                    'text' => $region,
                    'children' => []
                ];
            }
            $grouped[$region]['children'][] = [
                'id'   => $row->id,
                'text' => $row->name,
                'code' => $row->code
            ];
        }

        return array_values($grouped);
    }




}
