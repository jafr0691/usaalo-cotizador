<?php
if (!defined('ABSPATH')) exit;

class Usaalo_Ajax {

    public function __construct() {
        // add_action('wp_ajax_usaalo_get_plan', [$this, 'ajax_get_plan']);
        // add_action('wp_ajax_usaalo_save_plan', [$this, 'ajax_save_plan']);
        // add_action('wp_ajax_usaalo_delete_plan', [$this, 'ajax_delete_plan']);

        add_action('wp_ajax_get_countries', [$this, 'ajax_get_country_all']);
        add_action('wp_ajax_usaalo_get_country', [$this, 'ajax_get_country']);
        add_action('wp_ajax_usaalo_save_country', [$this, 'ajax_save_country']);
        add_action('wp_ajax_usaalo_delete_country', [$this, 'ajax_delete_country']);

        add_action('wp_ajax_get_brands', [$this, 'ajax_get_brands_all']);
        add_action('wp_ajax_usaalo_get_brand', [$this, 'ajax_get_brand']);
        add_action('wp_ajax_usaalo_save_brand', [$this, 'ajax_save_brand']);
        add_action('wp_ajax_usaalo_delete_brand', [$this, 'ajax_delete_brand']);

        add_action('wp_ajax_get_models', [$this, 'ajax_get_models_all']);
        add_action('wp_ajax_usaalo_get_model', [$this, 'ajax_get_model']);
        add_action('wp_ajax_usaalo_save_model', [$this, 'ajax_save_model']);
        add_action('wp_ajax_usaalo_delete_model', [$this, 'ajax_delete_model']);

        add_action('wp_ajax_get_sim_servicios', [$this, 'ajax_get_sim_servicios_all']);
        add_action('wp_ajax_usaalo_update_service', [$this, 'ajax_update_service']);
        add_action('wp_ajax_usaalo_bulk_update_service', [$this, 'ajax_usaalo_bulk_update_service']);

        add_action('wp_ajax_usaalo_get_products', [$this, 'usaalo_get_products_ajax']);
        add_action('wp_ajax_nopriv_usaalo_get_products', [$this, 'usaalo_get_products_ajax']);

        add_action('wp_ajax_usaalo_get_models_by_country', [$this, 'usaalo_get_models_by_country_ajax']);
        add_action('wp_ajax_nopriv_usaalo_get_models_by_country', [$this, 'usaalo_get_models_by_country_ajax']);


        add_action('wp_ajax_usaalo_save_wc_product', [$this, 'ajax_save_wc_product']);

        add_action('wp_ajax_get_countries_regions', [$this, 'ajax_get_countries_regions']);

        // add_action('wp_ajax_get_plan_data', [$this, 'ajax_get_plan_data']);
        add_action('wp_ajax_get_product_data', [$this, 'ajax_get_product_data']);
        add_action('wp_ajax_delete_products_with_countries', [$this, 'ajax_delete_products_with_countries']);


        add_action('wp_ajax_usaalo_bulk_delete', [$this, 'ajax_usaalo_bulk_delete']);

        add_action('wp_ajax_usaalo_update_config_toggle', [$this, 'ajax_update_config_toggle']);

    }


    // public function ajax_get_plan_data(){
    //     check_ajax_referer('usaalo_admin_nonce','nonce');
    //     if (!current_user_can('manage_options')) return wp_send_json_error(__('Permiso denegado','usaalo-cotizador'), 403);
    //     // Verificar si el método existe en la clase
    //     if (class_exists('USAALO_Helpers') && method_exists('USAALO_Helpers', 'get_plan_data')) {
    //         $productos = USAALO_Helpers::get_plan_datar();
    //         if ($productos) {
    //             return wp_send_json_success($productos);
    //         }
    //     }

    //     wp_send_json_error(__('No encontrado','usaalo-cotizador'));
    // }

    


    

    public static function ajax_update_config_toggle() {
        check_ajax_referer('usaalo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permiso denegado');
        }

        $key   = isset($_POST['key']) ? sanitize_key($_POST['key']) : '';
        $value = isset($_POST['value']) ? intval($_POST['value']) : 0;

        if (!$key) {
            wp_send_json_error('No se envió la clave');
        }

        // Obtener configuración actual
        $config = get_option('usaalo_cotizador_config', []);

        // Actualizar solo si la clave existe
        if (array_key_exists($key, $config)) {
            $config[$key] = $value;
            update_option('usaalo_cotizador_config', $config);
            wp_send_json_success('Configuración actualizada');
        } else {
            wp_send_json_error('Clave no válida');
        }
    }






    public function ajax_delete_products_with_countries() {
        check_ajax_referer('usaalo_admin_nonce','nonce');
        global $wpdb;

        $product_ids = isset($_POST['id']) ? (array) $_POST['id'] : [];
        if (empty($product_ids)) {
            wp_send_json_error(__('No se recibieron productos para eliminar.'));
        }

        $table_product_country = $wpdb->prefix . 'usaalo_product_country';
        $deleted = [];

        foreach ($product_ids as $product_id) {
            $product_id = intval($product_id);
            if (!$product_id) continue;

            // 1️⃣ Borrar relación países-producto
            $wpdb->delete(
                $table_product_country,
                [ 'product_id' => $product_id ],
                [ '%d' ]
            );

            // 2️⃣ Borrar el producto de WooCommerce (eliminar permanentemente)
            $deleted_post = wp_delete_post($product_id, true);

            if ($deleted_post) {
                $deleted[] = $product_id;
            }
        }

        if (!empty($deleted)) {
            wp_send_json_success(__('Productos eliminados correctamente.','usaalo-cotizador'));
        } else {
            wp_send_json_error(__('No se pudo eliminar ningún producto.','usaalo-cotizador'));
        }
    }


    public function ajax_get_product_data() {
        check_ajax_referer('usaalo_admin_nonce','nonce');
        global $wpdb;

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        if (!$product_id) {
            wp_send_json_error(__('ID de producto no válido','usaalo-cotizador'));
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(__('Producto no encontrado','usaalo-cotizador'));
        }

        // ===== Países =====
        $table = $wpdb->prefix . 'usaalo_product_country';
        $countries = $wpdb->get_col($wpdb->prepare(
            "SELECT country_id FROM $table WHERE product_id = %d",
            $product_id
        ));

        // ===== Datos generales =====
        $data = [
            'id'          => $product->get_id(),
            'name'        => $product->get_name(),
            'description' => $product->get_description(),
            'type'        => $product->get_type(), // simple | variable
            'price'       => $product->get_price(),
            'active'      => ($product->get_status() === 'publish'),
            'countries'   => $countries,
            'image_id'    => get_post_thumbnail_id($product_id),
            'image_url'     => wp_get_attachment_url($product->get_image_id()),
        ];

        // ===== RANGOS si es variable =====
        if ($product->is_type('variable')) {
            $ranges = [];
            foreach ($product->get_children() as $vid) {
                $variation = wc_get_product($vid);
                $attrs = $variation->get_attributes();

                // extraer rango de días
                $label = reset($attrs);
                if ($label && preg_match('/^(\d+)-(\d+)$/', $label, $m)) {
                    $ranges[] = [
                        'min_days' => (int) $m[1],
                        'max_days' => (int) $m[2],
                        'price'    => (float) $variation->get_regular_price(),
                    ];
                }
            }
            $data['ranges'] = $ranges;
        }

        wp_send_json_success($data);
    }






    public function ajax_save_wc_product() {
        check_ajax_referer('usaalo_admin_nonce','nonce');

        // ===== Inputs =====
        $name         = sanitize_text_field($_POST['nameWC'] ?? '');
        $desc         = sanitize_textarea_field($_POST['descriptionWC'] ?? '');
        $type         = sanitize_text_field($_POST['product_type'] ?? 'simple'); // simple|variable
        $active       = isset($_POST['active']) ? 1 : 0;
        $todos        = isset($_POST['Todos']) ? 1 : 0;
        $countries    = isset($_POST['countries']) ? array_map('intval', (array)$_POST['countries']) : [];
        $simple_price = isset($_POST['simple_price']) ? (float) $_POST['simple_price'] : 0;
        $ranges       = isset($_POST['ranges']) ? (array) $_POST['ranges'] : [];
        $max_price    = isset($_POST['max_price']) ? (float) $_POST['max_price'] : 0;
        $product_id   = !empty($_POST['product_id']) ? (int) $_POST['product_id'] : 0;

        // Imagen: acepta image_id o product_image_id
        $image_id = 0;
        if (isset($_POST['image_id']))          { $image_id = (int) $_POST['image_id']; }
        if (isset($_POST['product_image_id']))  { $image_id = (int) $_POST['product_image_id']; }

        // ===== Validaciones =====
        if ($name === '') {
            wp_send_json_error(__('El nombre del producto es obligatorio','usaalo-cotizador'));
        }

        if ($type === 'simple') {
            if ($simple_price <= 0) {
                wp_send_json_error(__('El precio del producto simple es obligatorio y debe ser mayor a 0','usaalo-cotizador'));
            }
        } else { // variable
            if (empty($ranges) || $max_price <= 0) {
                wp_send_json_error(__('Debe ingresar rangos de días y un precio base (a partir del último rango)','usaalo-cotizador'));
            }
            $prev_max = 0;
            foreach ($ranges as $i => $r) {
                $min   = isset($r['min_days']) ? (int)$r['min_days'] : 0;
                $max   = isset($r['max_days']) ? (int)$r['max_days'] : 0;
                $price = isset($r['price'])    ? (float)$r['price']    : 0;

                if ($min <= 0 || $max <= 0 || $price <= 0) {
                    wp_send_json_error(__('Los días y precios deben ser mayores a 0','usaalo-cotizador'));
                }
                if ($min < $prev_max) {
                    wp_send_json_error(sprintf(__('El rango %d comienza antes de terminar el rango anterior','usaalo-cotizador'), $i + 1));
                }
                if ($min > $max) {
                    wp_send_json_error(sprintf(__('En el rango %d, "Desde" no puede ser mayor que "Hasta"','usaalo-cotizador'), $i + 1));
                }
                $prev_max = $max;
            }
        }

        // ===== Crear/obtener producto y fijar datos generales =====
        if ($product_id) {
            $product = wc_get_product($product_id);
            if (!$product) wp_send_json_error(__('Producto no encontrado','usaalo-cotizador'));
        } else {
            $product = ($type === 'simple') ? new WC_Product_Simple() : new WC_Product_Variable();
        }

        // Forzar tipo si cambió
        $desired_type = ($type === 'simple') ? 'simple' : 'variable';
        if ($product->get_type() !== $desired_type) {
            wp_set_object_terms($product->get_id(), $desired_type, 'product_type');
            $product = ($desired_type === 'simple') ? new WC_Product_Simple($product->get_id())
                                                    : new WC_Product_Variable($product->get_id());
        }

        $product->set_name($name);
        $product->set_description($desc);
        $product->set_status($active ? 'publish' : 'draft');

        // Inventario: siempre disponible
        $product->set_manage_stock(false);
        $product->set_stock_status('instock');

        // SKU auto si no existe
        if (!$product->get_sku()) {
            $product->set_sku( sanitize_title($name) . '-' . wp_generate_password(6, false) );
        }

        // Vendido individualmente
        $product->set_sold_individually(true);

        // Precios según tipo
        if ($type === 'simple') {
            $product->set_regular_price($simple_price);
            // limpiar posibles atributos/variaciones anteriores
            if ($product instanceof WC_Product_Variable) {
                foreach ($product->get_children() as $vid) { wp_delete_post($vid, true); }
            }
            $product->set_attributes([]);
        } else {
            // Precio base del padre (fuera de rango)
            $product->set_regular_price($max_price);
        }

        // ===== Categoría "SIM" SOLO (evita "Sin categorizar") =====
        $term_name = 'SIM';
        $taxonomy  = 'product_cat';
        // Guardado inicial para asegurar ID
        $product_id = $product->save();

        // Asegurarnos de que existe la taxonomía (WooCommerce activo)
        if ( ! taxonomy_exists( $taxonomy ) ) {
            // WooCommerce no activo o taxonomy no disponible
            error_log( 'usaalo: taxonomy product_cat no existe. ¿WooCommerce activo?' );
        } else {
            // Comprobar si existe el término
            $term = term_exists( $term_name, $taxonomy );

            if ( $term === 0 || $term === null || $term === false ) {
                // No existe -> crear (usamos slug seguro)
                $insert = wp_insert_term( $term_name, $taxonomy, [
                    'slug' => sanitize_title( $term_name )
                ] );

                if ( is_wp_error( $insert ) ) {
                    error_log( 'usaalo: error creando término SIM: ' . $insert->get_error_message() );
                    $term_id = 0;
                } else {
                    $term_id = is_array( $insert ) ? intval( $insert['term_id'] ) : intval( $insert );
                }
            } else {
                // term_exists devolvió array o int: normalizar a term_id
                $term_id = is_array( $term ) ? intval( $term['term_id'] ) : intval( $term );
            }

            // Asignar SOLO esta categoría (reemplaza "Sin categorizar")
            if ( ! empty( $term_id ) ) {
                wp_set_object_terms( (int) $product_id, [ $term_id ], $taxonomy, false );
            } else {
                error_log( 'usaalo: term_id inválido al asignar categoría SIM.' );
            }
        }

        // ===== Imagen destacada =====
        if ($image_id) {
            set_post_thumbnail($product_id, $image_id);
        }

        // ===== Atributos + variaciones (solo variable) =====
        if ($type === 'variable' && !empty($ranges)) {
            // 1) Asegurar atributo global
            $attr_label = 'Rango de días';
            $attr_slug  = wc_sanitize_taxonomy_name($attr_label); // rango-de-dias
            $taxonomy   = 'pa_' . $attr_slug;

            // Crear atributo global si no existe
            if (!taxonomy_exists($taxonomy)) {
                if (!function_exists('wc_create_attribute')) {
                    wp_send_json_error(__('WooCommerce muy antiguo: no se puede crear el atributo','usaalo-cotizador'));
                }
                // ¿Existe en la tabla de atributos?
                $attr_id = wc_attribute_taxonomy_id_by_name($attr_slug);
                if (!$attr_id) {
                    $attr_id = wc_create_attribute([
                        'name'         => $attr_label,
                        'slug'         => $attr_slug,
                        'type'         => 'select',
                        'order_by'     => 'menu_order',
                        'has_archives' => false,
                    ]);
                    delete_transient('wc_attribute_taxonomies');
                }
                // Registrar la taxonomía inmediatamente para este request
                if (!taxonomy_exists($taxonomy)) {
                    register_taxonomy($taxonomy, ['product'], [
                        'hierarchical'          => false,
                        'label'                 => $attr_label,
                        'query_var'             => true,
                        'show_ui'               => false,
                        'show_in_quick_edit'    => false,
                        'public'                => false,
                    ]);
                }
            }

            // 2) Crear términos para cada rango
            $term_ids  = [];
            $term_slugs = [];
            foreach ($ranges as $r) {
                $label = $r['min_days'].'-'.$r['max_days'];
                $term  = term_exists($label, $taxonomy);
                if (!$term) {
                    $term = wp_insert_term($label, $taxonomy);
                }
                if (is_wp_error($term)) {
                    wp_send_json_error(__('Error creando término de atributo','usaalo-cotizador'));
                }
                $tid = is_array($term) ? (int)$term['term_id'] : (int)$term;
                $term_ids[] = $tid;

                $t = get_term($tid, $taxonomy);
                if ($t && !is_wp_error($t)) {
                    $term_slugs[$label] = $t->slug;
                }
            }

            // 3) Asignar términos al producto
            wp_set_object_terms($product_id, $term_ids, $taxonomy);

            // 4) Definir el atributo en el producto (visible + usado para variaciones)
            $attr_obj = new WC_Product_Attribute();
            $attr_obj->set_id( wc_attribute_taxonomy_id_by_name($attr_slug) );
            $attr_obj->set_name( $taxonomy );
            $attr_obj->set_options( $term_ids ); // IDs de términos
            $attr_obj->set_visible( true );
            $attr_obj->set_variation( true );

            $product = wc_get_product($product_id);
            $product->set_attributes( [ $attr_obj ] );
            $product->save();

            // 5) Limpiar variaciones anteriores y crearlas de nuevo
            if ($product instanceof WC_Product_Variable) {
                foreach ($product->get_children() as $vid) { wp_delete_post($vid, true); }
            }

            foreach ($ranges as $r) {
                $label = $r['min_days'].'-'.$r['max_days'];
                $slug  = $term_slugs[$label] ?? wc_sanitize_taxonomy_name($label);

                $variation = new WC_Product_Variation();
                $variation->set_parent_id($product_id);
                $variation->set_regular_price( (float)$r['price'] ); // precio por día (tu lógica de cálculo después)
                $variation->set_price( (float)$r['price'] );
                $variation->set_manage_stock(false);
                $variation->set_stock_status('instock');
                $variation->set_sold_individually(true);
                // atributo → slug del término
                $variation->set_attributes( [ $taxonomy => $slug ] );
                $variation->save();
            }
        }

        // ===== Países =====
        global $wpdb;
        $table = $wpdb->prefix . 'usaalo_product_country';
        $wpdb->delete($table, ['product_id' => $product_id]);

        if ($todos) {
            $all_countries = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}usaalo_countries");
            foreach ($all_countries as $cid) {
                $wpdb->insert($table, ['product_id' => $product_id, 'country_id' => (int)$cid]);
            }
        } else {
            foreach ($countries as $cid) {
                $wpdb->insert($table, ['product_id' => $product_id, 'country_id' => (int)$cid]);
            }
        }
        // ===== GENERAR CACHÉ AUTOMÁTICA =====
        if (class_exists('USAALO_Cache')) {
            USAALO_Cache::build_cache(); // regenerar la caché automáticamente
        }
        wp_send_json_success(['product_id' => $product_id]);
    }

    public function ajax_get_countries_regions() {
        check_ajax_referer('usaalo_admin_nonce','nonce');
        if (class_exists('USAALO_Helpers') && method_exists('USAALO_Helpers', 'get_countries_regions')) {
            $search = sanitize_text_field($_POST['search'] ?? '');
            $product_id = intval($_POST['product_id'] ?? 0);
            return wp_send_json_success(array_values(USAALO_Helpers::get_countries_regions($search, $product_id)));
        }

        wp_send_json_error(__('No encontrado','usaalo-cotizador'));
    }

    public function ajax_get_sim_servicios_all() {
        check_ajax_referer('usaalo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permiso denegado'], 403);
        }

        global $wpdb;

        // Parámetros de DataTables
        $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
        $search = isset($_POST['search']['value']) ? sanitize_text_field($_POST['search']['value']) : '';

        $filter_country = isset($_POST['filter_country']) ? sanitize_text_field($_POST['filter_country']) : '';
        $filter_brand   = isset($_POST['filter_brand']) ? sanitize_text_field($_POST['filter_brand']) : '';
        $filter_model   = isset($_POST['filter_model']) ? sanitize_text_field($_POST['filter_model']) : '';

        $table_device_country = $wpdb->prefix . 'usaalo_device_country';
        $table_device_config  = $wpdb->prefix . 'usaalo_device_config';
        $table_models         = $wpdb->prefix . 'usaalo_models';
        $table_brands         = $wpdb->prefix . 'usaalo_brands';
        $table_countries      = $wpdb->prefix . 'usaalo_countries';

        // Base SQL
        $base_sql = "
            FROM {$table_models} m
            INNER JOIN {$table_brands} b ON b.id = m.brand_id
            CROSS JOIN {$table_countries} c
            LEFT JOIN {$table_device_config} cfg ON cfg.model_id = m.id
            LEFT JOIN {$table_device_country} dc 
                ON dc.model_id = m.id AND dc.country_id = c.id
            WHERE 1=1
        ";

        // Filtros de búsqueda global
        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $base_sql .= $wpdb->prepare(" AND (c.name LIKE %s OR b.name LIKE %s OR m.name LIKE %s)", $like, $like, $like);
        }

        // Filtros personalizados
        if ($filter_country !== '') {
            $base_sql .= $wpdb->prepare(" AND c.name LIKE %s", '%' . $wpdb->esc_like($filter_country) . '%');
        }
        if ($filter_brand !== '') {
            $base_sql .= $wpdb->prepare(" AND b.name LIKE %s", '%' . $wpdb->esc_like($filter_brand) . '%');
        }
        if ($filter_model !== '') {
            $base_sql .= $wpdb->prepare(" AND m.name LIKE %s", '%' . $wpdb->esc_like($filter_model) . '%');
        }

        // Total registros sin filtro
        $recordsTotal = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$table_models} m
            INNER JOIN {$table_brands} b ON b.id = m.brand_id
            CROSS JOIN {$table_countries} c
        ");

        // Total con filtros
        $recordsFiltered = $wpdb->get_var("SELECT COUNT(*) {$base_sql}");

        // Orden
        $order_column_index = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 0;
        $order_dir = isset($_POST['order'][0]['dir']) && $_POST['order'][0]['dir'] === 'desc' ? 'DESC' : 'ASC';

        $columns_map = [
            0 => 'c.name',
            1 => 'b.name',
            2 => 'm.name',
            3 => 'sim_supported',
            4 => 'esim_supported',
            5 => 'data_supported',
            6 => 'voice_supported',
            7 => 'sms_supported'
        ];
        $order_by = isset($columns_map[$order_column_index]) ? $columns_map[$order_column_index] : 'c.name';

        // Query principal
        $sql_select = "
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
            {$base_sql}
            ORDER BY {$order_by} {$order_dir}
            LIMIT %d, %d
        ";

        $rows = $wpdb->get_results($wpdb->prepare($sql_select, $start, $length), ARRAY_A);

        // Respuesta
        wp_send_json([
            "draw" => $draw,
            "recordsTotal" => intval($recordsTotal),
            "recordsFiltered" => intval($recordsFiltered),
            "data" => $rows
        ]);
    }





    public function ajax_update_service(){
        check_ajax_referer('usaalo_admin_nonce','nonce');
        if (!current_user_can('manage_options')) return wp_send_json_error(__('Permiso denegado','usaalo-cotizador'), 403);
        // Verificar si el método existe en la clase
        if (class_exists('USAALO_Helpers') && method_exists('USAALO_Helpers', 'usaalo_update_service')) {
            
            $servicios = USAALO_Helpers::usaalo_update_service($_POST);
            if ($servicios) {
                return wp_send_json_success($servicios);
            }
        }
        wp_send_json_error(__('No encontrado','usaalo-cotizador'));
    }

    public static function ajax_usaalo_bulk_update_service() {
        global $wpdb;

        $table_device_country = $wpdb->prefix . 'usaalo_device_country';
        $table_device_config  = $wpdb->prefix . 'usaalo_device_config';

        // Recibir datos en lote
        $data = isset($_POST['updates']) ? $_POST['updates'] : [];

        if (empty($data)) {
            wp_send_json_error(['message' => 'No se enviaron datos']);
        }

        try {
            // Iniciar transacción
            $wpdb->query("START TRANSACTION");

            $allowed_fields = ['sim_supported','esim_supported','voice_supported','sms_supported','data_supported'];

            foreach ($data as $row) {
                $model_id   = intval($row['model_id']);
                $country_id = intval($row['country_id']);
                $field      = sanitize_key($row['field']);
                $value      = intval($row['value']);

                if (!in_array($field, $allowed_fields)) {
                    throw new Exception("Campo no permitido: $field");
                }

                // Obtener valor global del modelo
                $global_value = $wpdb->get_var($wpdb->prepare(
                    "SELECT {$field} FROM {$table_device_config} WHERE model_id = %d",
                    $model_id
                ));

                // Si no hay valor global, crearlo con defaults
                if ($global_value === null) {
                    $wpdb->insert($table_device_config, [
                        'model_id'       => $model_id,
                        'sim_supported'  => 1,
                        'esim_supported' => 1,
                        'voice_supported'=> 0,
                        'sms_supported'  => 0,
                        'data_supported' => 1
                    ]);
                    $global_value = 1; // valor por defecto
                }

                // Si coincide con global → eliminar override
                if ($value == $global_value) {
                    $wpdb->delete(
                        $table_device_country,
                        ['model_id' => $model_id, 'country_id' => $country_id],
                        ['%d','%d']
                    );
                    continue; // saltar a siguiente update
                }

                // Si difiere → upsert
                $sql = $wpdb->prepare("
                    INSERT INTO {$table_device_country} (model_id, country_id, {$field})
                    VALUES (%d, %d, %d)
                    ON DUPLICATE KEY UPDATE {$field} = VALUES({$field})
                ", $model_id, $country_id, $value);

                $result = $wpdb->query($sql);

                if ($result === false) {
                    throw new Exception("Error al guardar model_id={$model_id}, country_id={$country_id}");
                }
            }

            // Confirmar todo
            $wpdb->query("COMMIT");

            wp_send_json_success(['message' => '✅ Todos los cambios se guardaron correctamente']);
        } catch (Exception $e) {
            $wpdb->query("ROLLBACK");
            wp_send_json_error([
                'message' => '❌ Falló la transacción',
                'error'   => $e->getMessage(),
                'sql'     => $sql ?? null,
                'db_error'=> $wpdb->last_error,
            ]);
        }
    }

    /* ------------------------------ Countrys ------------------------------ */
    public function ajax_get_country_all() {
        check_ajax_referer('usaalo_admin_nonce','nonce');
        if (!current_user_can('manage_options')) return wp_send_json_error(__('Permiso denegado','usaalo-cotizador'), 403);
        // Verificar si el método existe en la clase
        if (class_exists('USAALO_Helpers') && method_exists('USAALO_Helpers', 'get_countries')) {
            $country = USAALO_Helpers::get_countries();
            if ($country) {
                $country = $country ? $country : [];
                return wp_send_json_success($country);
            }
        }

        wp_send_json_error(__('No encontrado','usaalo-cotizador'));
    }

    public function ajax_get_country() {
        check_ajax_referer('usaalo_admin_nonce','nonce');
        if (!current_user_can('manage_options')) return wp_send_json_error(__('Permiso denegado','usaalo-cotizador'), 403);
        // Obtener el código enviado por POST
        $id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : '';

        // Verificar si el método existe en la clase
        if (class_exists('USAALO_Helpers') && method_exists('USAALO_Helpers', 'usaalo_get_country')) {
            $country = USAALO_Helpers::usaalo_get_country($id);
            if ($country) {
                $country = $country ? $country : [];
                return wp_send_json_success($country);
            }
        }

        wp_send_json_error(__('No encontrado','usaalo-cotizador'));
    }

    public function ajax_save_country() {
        check_ajax_referer('usaalo_admin_nonce','nonce');
        if (!current_user_can('manage_options')) return wp_send_json_error(__('Permiso denegado','usaalo-cotizador'),403);
        if (class_exists('USAALO_Helpers') && method_exists('USAALO_Helpers','usaalo_save_country')) {
            $result = USAALO_Helpers::usaalo_save_country($_POST);
            if ($result) return wp_send_json_success(__('País guardado','usaalo-cotizador'));
        }
        wp_send_json_error(__('Error al guardar','usaalo-cotizador'));
    }

    public function ajax_delete_country() {
        check_ajax_referer('usaalo_admin_nonce','nonce');
        if (!current_user_can('manage_options')) return wp_send_json_error(__('Permiso denegado','usaalo-cotizador'),403);
        if (class_exists('USAALO_Helpers') && method_exists('USAALO_Helpers','usaalo_delete_country')) {
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $result = USAALO_Helpers::usaalo_delete_country($id);
            if ($result) return wp_send_json_success(__('País eliminado','usaalo-cotizador'));
        }
        wp_send_json_error(__('Error al eliminar','usaalo-cotizador'));
    }






    /* ------------------------------ Brands ------------------------------ */

    public function ajax_get_brands_all() {
        check_ajax_referer('usaalo_admin_nonce','nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json([ 'data' => [] ]); // devolver array vacío para DataTables
        }

        $brands = [];
        if (class_exists('USAALO_Helpers') && method_exists('USAALO_Helpers', 'get_brands')) {
            $brands = USAALO_Helpers::get_brands();
            $brands = $brands ? $brands : [];
        }

        // Siempre devolver en formato 'data' para DataTables
        wp_send_json([ 'data' => $brands ]);
    }


    public function ajax_get_brand() {
        check_ajax_referer('usaalo_admin_nonce','nonce');
        if (!current_user_can('manage_options')) return wp_send_json_error(__('Permiso denegado','usaalo-cotizador'),403);
        if (class_exists('USAALO_Helpers') && method_exists('USAALO_Helpers','usaalo_get_brand')) {
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $brand = USAALO_Helpers::usaalo_get_brand($id);
            
            if ($brand){
                $brand = $brand ? $brand : [];
                return wp_send_json_success($brand);
            } 
        }
        wp_send_json_error(__('No encontrado','usaalo-cotizador'));
    }

    public function ajax_save_brand() {
        check_ajax_referer('usaalo_admin_nonce','nonce');
        if (!current_user_can('manage_options')) return wp_send_json_error(__('Permiso denegado','usaalo-cotizador'),403);
        if (class_exists('USAALO_Helpers') && method_exists('USAALO_Helpers','usaalo_save_brand')) {
            $result = USAALO_Helpers::usaalo_save_brand($_POST);
            if ($result) return wp_send_json_success(__('Marca guardada','usaalo-cotizador'));
        }
        wp_send_json_error(__('Error al guardar','usaalo-cotizador'));
    }

    public function ajax_delete_brand() {
        check_ajax_referer('usaalo_admin_nonce','nonce');
        if (!current_user_can('manage_options')) return wp_send_json_error(__('Permiso denegado','usaalo-cotizador'),403);
        if (class_exists('USAALO_Helpers') && method_exists('USAALO_Helpers','usaalo_delete_brand')) {
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $result = USAALO_Helpers::usaalo_delete_brand($id);
            if ($result) return wp_send_json_success(__('Marca eliminada','usaalo-cotizador'));
        }
        wp_send_json_error(__('Error al eliminar','usaalo-cotizador'));
    }











    /* ------------------------------ Funciones de accion de los modelos ------------------------------ */

    public function ajax_get_models_all() {
        check_ajax_referer('usaalo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permiso denegado'], 403);
        }

        global $wpdb;

        $draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
        $search = isset($_POST['search']['value']) ? sanitize_text_field($_POST['search']['value']) : '';

        $table_models = $wpdb->prefix . 'usaalo_models';
        $table_brands = $wpdb->prefix . 'usaalo_brands';

        $sql = "FROM {$table_models} m INNER JOIN {$table_brands} b ON b.id = m.brand_id";
        
        $where = [];
        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = $wpdb->prepare("(m.name LIKE %s OR m.slug LIKE %s OR b.name LIKE %s)", $like, $like, $like);
        }
        if ($where) $sql .= " WHERE " . implode(" AND ", $where);

        $recordsTotal = $wpdb->get_var("SELECT COUNT(*) {$sql}");
        
        $order_column_index = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 1;
        $order_dir = isset($_POST['order'][0]['dir']) && $_POST['order'][0]['dir'] === 'desc' ? 'DESC' : 'ASC';

        $columns_map = [
            0 => 'm.id',
            1 => 'b.name',
            2 => 'm.name',
            3 => 'm.slug',
            4 => 'm.id'
        ];
        $order_by = isset($columns_map[$order_column_index]) ? $columns_map[$order_column_index] : 'b.name';

        $sql_select = "
            SELECT 
                m.id,
                b.name AS brand_name,
                m.name,
                m.slug
            {$sql}
            ORDER BY {$order_by} {$order_dir}
            LIMIT %d, %d
        ";
        $rows = $wpdb->get_results($wpdb->prepare($sql_select, $start, $length), ARRAY_A);

        $recordsFiltered = $wpdb->get_var("SELECT COUNT(*) {$sql}");

        wp_send_json([
            "draw" => $draw,
            "recordsTotal" => intval($recordsTotal),
            "recordsFiltered" => intval($recordsFiltered),
            "data" => $rows
        ]);
    }


    public function ajax_get_model() {
        check_ajax_referer('usaalo_admin_nonce','nonce');
        if (!current_user_can('manage_options')) return wp_send_json_error(__('Permiso denegado','usaalo-cotizador'),403);
        if (class_exists('USAALO_Helpers') && method_exists('USAALO_Helpers','usaalo_get_model')) {
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $model = USAALO_Helpers::usaalo_get_model($id);
            if ($model){
                $model = $model ? $model : [];
                return wp_send_json_success($model);
            } 
        }
        wp_send_json_error(__('No encontrado','usaalo-cotizador'));
    }

    public function ajax_save_model() {
        check_ajax_referer('usaalo_admin_nonce','nonce');
        if (!current_user_can('manage_options')) return wp_send_json_error(__('Permiso denegado','usaalo-cotizador'),403);
        if (class_exists('USAALO_Helpers') && method_exists('USAALO_Helpers','usaalo_save_model')) {
            $result = USAALO_Helpers::usaalo_save_model($_POST);
            if ($result) return wp_send_json_success(__('Modelo guardado','usaalo-cotizador'));
        }
        wp_send_json_error(__('Error al guardar','usaalo-cotizador'));
    }

    public function ajax_delete_model() {
        check_ajax_referer('usaalo_admin_nonce','nonce');
        if (!current_user_can('manage_options')) return wp_send_json_error(__('Permiso denegado','usaalo-cotizador'),403);
        if (class_exists('USAALO_Helpers') && method_exists('USAALO_Helpers','usaalo_delete_model')) {
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $result = USAALO_Helpers::usaalo_delete_model($id);
            if ($result) return wp_send_json_success(__('Modelo eliminado','usaalo-cotizador'));
        }
        wp_send_json_error(__('Error al eliminar','usaalo-cotizador'));
    }







    /* ------------------------------ Planes de los productos por pais ------------------------------ */





    /* ------------------------------ Elimina lo seleccionado em las tablas ------------------------------ */

    public function ajax_usaalo_bulk_delete() {
        check_ajax_referer('usaalo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permiso denegado', 'usaalo-cotizador')
            ], 403);
        }

        // Recoger datos del request
        $table = sanitize_text_field($_POST['table'] ?? '');
        $ids   = isset($_POST['ids']) ? array_map('intval', (array) $_POST['ids']) : [];

        if (empty($table) || empty($ids)) {
            wp_send_json_error(['message' => __('Datos incompletos', 'usaalo-cotizador')]);
        }

        // Ejecutar borrado
        if (class_exists('USAALO_Helpers') && method_exists('USAALO_Helpers', 'usaalo_bulk_delete')) {
            $deleted = USAALO_Helpers::usaalo_bulk_delete($table, $ids);

            if ($deleted !== false) {
                wp_send_json_success([
                    'message' => sprintf(__('Se eliminaron %d registros', 'usaalo-cotizador'), $deleted)
                ]);
            }
        }

        wp_send_json_error(['message' => __('No se pudieron eliminar los registros.', 'usaalo-cotizador')]);
    }




    /* ------------------------------ Funciones por revisar ------------------------------ */
    function usaalo_get_products_ajax() {
        check_ajax_referer('usaalo_nonce', 'nonce');

        if (empty($_POST['country_id'])) {
            wp_send_json_error(['message' => 'País no especificado']);
        }

        $country_id = intval($_POST['country_id']);
        $products = USAALO_Helpers::get_products_by_country($country_id);
        if($products){
            $products = $products ? $products : [];
            return wp_send_json_success($products);
        }
    }

    function usaalo_get_models_by_country_ajax() {
        check_ajax_referer('usaalo_nonce', 'nonce');

        if (empty($_POST['country_id'])) {
            wp_send_json_error(['message' => 'País no especificado']);
        }

        $country_id = intval($_POST['country_id']);
        $models = USAALO_Helpers::get_models_by_country($country_id);
        if($models){
            $models = $models ? $models : [];
            return wp_send_json_success($models);
        }
    }
}
