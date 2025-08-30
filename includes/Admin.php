<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin.php
 * Panel de administración para Usaalo Cotizador
 * - Listado de Plans, Rules, Countries, Brands
 * - Formulario para crear/editar Rules (modal)
 * - Enqueue select2, datatables y admin.js
 * - AJAX: usaalo_get_rule, usaalo_save_rule, usaalo_delete_rule (registered here)
 */

class USAALO_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX admin endpoints
        add_action('wp_ajax_usaalo_get_rule', [$this, 'ajax_get_rule']);
        add_action('wp_ajax_usaalo_save_rule', [$this, 'ajax_save_rule']);
        add_action('wp_ajax_usaalo_delete_rule', [$this, 'ajax_delete_rule']);
    }

    public function add_menu() {
        add_menu_page(
            __('Usaalo Cotizador', 'usaalo-cotizador'),
            __('Cotizador', 'usaalo-cotizador'),
            'manage_options',
            'usaalo-cotizador',
            [$this, 'render_admin_page'],
            'dashicons-cart',
            56
        );
    }

    public function enqueue_assets($hook) {
        // Only load on our plugin admin page
        if ($hook !== 'toplevel_page_usaalo-cotizador') return;

        $base = plugin_dir_url(dirname(__FILE__)) . 'assets/';

        wp_enqueue_style('usaalo-select2', $base . 'lib/select2.min.css', [], '4.1.0');
        wp_enqueue_style('usaalo-datatables', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css', [], '1.13.6');
        wp_enqueue_style('usaalo-admin', $base . 'css/admin.css', [], USAALO_VERSION);

        wp_enqueue_script('usaalo-select2', $base . 'lib/select2.min.js', ['jquery'], '4.1.0', true);
        wp_enqueue_script('usaalo-datatables', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', ['jquery'], '1.13.6', true);
        wp_enqueue_script('usaalo-admin', $base . 'js/admin.js', ['jquery','usaalo-select2','usaalo-datatables'], USAALO_VERSION, true);

        wp_localize_script('usaalo-admin', 'USAALO_Admin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('usaalo_admin_nonce'),
            'i18n' => [
                'saved' => __('Guardado correctamente', 'usaalo-cotizador'),
                'deleted' => __('Eliminado correctamente', 'usaalo-cotizador'),
            ],
        ]);
    }

    public function render_admin_page() {
        global $wpdb;
        $plans = USAALO_Helpers::get_plans();
        $rules = $wpdb->get_results("SELECT r.*, p.name as plan_name FROM {$wpdb->prefix}usaalo_pricing_rules r LEFT JOIN {$wpdb->prefix}usaalo_plans p ON r.plan_id = p.id ORDER BY r.id DESC", ARRAY_A);
        ?>
        <div class="wrap">
            <h1><?php _e('Usaalo Cotizador', 'usaalo-cotizador'); ?></h1>
            <p><?php _e('Gestiona planes, reglas y productos WooCommerce', 'usaalo-cotizador'); ?></p>

            <h2><?php _e('Planes', 'usaalo-cotizador'); ?></h2>
            <table id="usaalo-plans-table" class="widefat striped">
                <thead><tr><th><?php _e('Nombre','usaalo-cotizador');?></th><th><?php _e('SIMs','usaalo-cotizador');?></th><th><?php _e('Producto WC','usaalo-cotizador');?></th><th><?php _e('Activo','usaalo-cotizador');?></th></tr></thead>
                <tbody>
                <?php foreach($plans as $p): ?>
                    <tr>
                        <td><?php echo esc_html($p['name']); ?></td>
                        <td><?php echo esc_html($p['sim_types']); ?></td>
                        <td><?php echo $p['wc_product_id'] ? esc_html(get_the_title($p['wc_product_id'])) : '-'; ?></td>
                        <td><?php echo $p['active'] ? __('Sí','usaalo-cotizador') : __('No','usaalo-cotizador'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2><?php _e('Reglas de precio', 'usaalo-cotizador'); ?></h2>
            <table id="usaalo-rules-table" class="widefat striped">
                <thead><tr>
                    <th><?php _e('Plan','usaalo-cotizador');?></th>
                    <th><?php _e('Tipo SIM','usaalo-cotizador');?></th>
                    <th><?php _e('Rango','usaalo-cotizador');?></th>
                    <th><?php _e('Base','usaalo-cotizador');?></th>
                    <th><?php _e('Voz','usaalo-cotizador');?></th>
                    <th><?php _e('SMS','usaalo-cotizador');?></th>
                    <th><?php _e('Acciones','usaalo-cotizador');?></th>
                </tr></thead>
                <tbody>
                <?php foreach($rules as $r): ?>
                    <tr>
                        <td><?php echo esc_html($r['plan_name']); ?></td>
                        <td><?php echo esc_html($r['sim_type']); ?></td>
                        <td><?php echo intval($r['min_days']).' - '.intval($r['max_days']); ?></td>
                        <td><?php echo esc_html($r['base_price']); ?></td>
                        <td><?php echo esc_html($r['voice_addon']); ?></td>
                        <td><?php echo esc_html($r['sms_addon']); ?></td>
                        <td>
                            <button class="button edit-rule" data-id="<?php echo intval($r['id']); ?>"><?php _e('Editar','usaalo-cotizador');?></button>
                            <button class="button delete-rule" data-id="<?php echo intval($r['id']); ?>"><?php _e('Eliminar','usaalo-cotizador');?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Modal: rule editor (simple) -->
            <div id="usaalo-rule-modal" style="display:none;">
                <form id="usaalo-rule-form">
                    <input type="hidden" id="rule-id" name="id" value="">
                    <p><label><?php _e('Plan','usaalo-cotizador');?></label>
                        <select id="rule-plan" name="plan_id">
                            <?php foreach($plans as $p): ?>
                                <option value="<?php echo intval($p['id']); ?>"><?php echo esc_html($p['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p><label><?php _e('Desde días','usaalo-cotizador');?></label><input type="number" id="rule-from" name="min_days" required></p>
                    <p><label><?php _e('Hasta días','usaalo-cotizador');?></label><input type="number" id="rule-to" name="max_days" required></p>
                    <p><label><?php _e('Precio base (por día)','usaalo-cotizador');?></label><input type="text" id="rule-price" name="base_price" required></p>
                    <p><label><?php _e('Voz addon (por día)','usaalo-cotizador');?></label><input type="text" id="rule-voice" name="voice_addon"></p>
                    <p><label><?php _e('SMS addon (por día)','usaalo-cotizador');?></label><input type="text" id="rule-sms" name="sms_addon"></p>
                    <p><button class="button button-primary" type="submit"><?php _e('Guardar','usaalo-cotizador');?></button>
                    <button class="button cancel-rule" type="button"><?php _e('Cancelar','usaalo-cotizador');?></button></p>
                </form>
            </div>

        </div>
        <?php
    }

    /* ---------- AJAX handlers ---------- */

    public function ajax_get_rule() {
        check_ajax_referer('usaalo_admin_nonce','nonce');
        if (!current_user_can('manage_options')) return wp_send_json_error(__('Permiso denegado','usaalo-cotizador'),403);
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return wp_send_json_error(__('ID inválido','usaalo-cotizador'));
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}usaalo_pricing_rules WHERE id=%d", $id), ARRAY_A);
        if (!$row) return wp_send_json_error(__('No encontrada','usaalo-cotizador'));
        wp_send_json_success($row);
    }

    public function ajax_save_rule() {
        check_ajax_referer('usaalo_admin_nonce','nonce');
        if (!current_user_can('manage_options')) return wp_send_json_error(__('Permiso denegado','usaalo-cotizador'),403);
        global $wpdb;
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
        $min_days = isset($_POST['min_days']) ? intval($_POST['min_days']) : 0;
        $max_days = isset($_POST['max_days']) ? intval($_POST['max_days']) : 0;
        $base_price = isset($_POST['base_price']) ? floatval($_POST['base_price']) : 0;
        $voice_addon = isset($_POST['voice_addon']) ? floatval($_POST['voice_addon']) : 0;
        $sms_addon = isset($_POST['sms_addon']) ? floatval($_POST['sms_addon']) : 0;

        if ($min_days > $max_days) return wp_send_json_error(__('Rango inválido','usaalo-cotizador'));

        if ($id) {
            $wpdb->update("{$wpdb->prefix}usaalo_pricing_rules",
                ['plan_id'=>$plan_id,'min_days'=>$min_days,'max_days'=>$max_days,'base_price'=>$base_price,'voice_addon'=>$voice_addon,'sms_addon'=>$sms_addon],
                ['id'=>$id], ['%d','%d','%d','%f','%f','%f'], ['%d']);
        } else {
            $wpdb->insert("{$wpdb->prefix}usaalo_pricing_rules",
                ['plan_id'=>$plan_id,'min_days'=>$min_days,'max_days'=>$max_days,'base_price'=>$base_price,'voice_addon'=>$voice_addon,'sms_addon'=>$sms_addon],
                ['%d','%d','%d','%f','%f','%f']);
        }
        wp_send_json_success(__('Regla guardada','usaalo-cotizador'));
    }

    public function ajax_delete_rule() {
        check_ajax_referer('usaalo_admin_nonce','nonce');
        if (!current_user_can('manage_options')) return wp_send_json_error(__('Permiso denegado','usaalo-cotizador'),403);
        global $wpdb;
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return wp_send_json_error(__('ID inválido','usaalo-cotizador'));
        $wpdb->delete("{$wpdb->prefix}usaalo_pricing_rules", ['id'=>$id], ['%d']);
        wp_send_json_success(__('Regla eliminada','usaalo-cotizador'));
    }

}

// initialize
new USAALO_Admin();
