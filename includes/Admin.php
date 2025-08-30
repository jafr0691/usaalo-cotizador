<?php
if (!defined('ABSPATH')) exit;

class USAALO_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX
        add_action('wp_ajax_usaalo_get_rule', [$this, 'ajax_get_rule']);
        add_action('wp_ajax_usaalo_save_rule', [$this, 'ajax_save_rule']);
        add_action('wp_ajax_usaalo_delete_rule', [$this, 'ajax_delete_rule']);
    }

    /**
     * Menú de administración
     */
    public function add_menu() {
        add_menu_page(
            __('Usaalo Cotizador', 'usaalo'),
            __('Cotizador SIM/eSIM', 'usaalo'),
            'manage_options',
            'usaalo-cotizador',
            [$this, 'admin_page'],
            'dashicons-admin-generic',
            56
        );
    }

    /**
     * Enqueue CSS y JS
     */
    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_usaalo-cotizador') return;

        wp_enqueue_style('usaalo-select2', plugin_dir_url(__FILE__).'../assets/lib/select2.min.css', [], '4.0.13');
        wp_enqueue_style('usaalo-admin', plugin_dir_url(__FILE__).'../assets/css/admin.css', [], '1.0');

        wp_enqueue_script('usaalo-select2', plugin_dir_url(__FILE__).'../assets/lib/select2.min.js', ['jquery'], '4.0.13', true);
        wp_enqueue_script('usaalo-admin', plugin_dir_url(__FILE__).'../assets/js/admin.js', ['jquery','usaalo-select2'], '1.0', true);

        wp_localize_script('usaalo-admin', 'USAALO_Admin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('usaalo_admin_nonce'),
        ]);
    }

    /**
     * Página principal de administración
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Cotizador SIM/eSIM Internacional', 'usaalo'); ?></h1>

            <h2><?php _e('Planes', 'usaalo'); ?></h2>
            <table id="usaalo-plans-table" class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Nombre', 'usaalo'); ?></th>
                        <th><?php _e('SIMs', 'usaalo'); ?></th>
                        <th><?php _e('Woo Product', 'usaalo'); ?></th>
                        <th><?php _e('Activo', 'usaalo'); ?></th>
                        <th><?php _e('Acciones', 'usaalo'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                global $wpdb;
                $plans = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}usaalo_plans ORDER BY id DESC");
                foreach($plans as $plan) {
                    $wc_product = $plan->wc_product_id ? get_the_title($plan->wc_product_id) : '-';
                    echo '<tr>';
                    echo '<td>'.esc_html($plan->name).'</td>';
                    echo '<td>'.esc_html($plan->sim_types).'</td>';
                    echo '<td>'.esc_html($wc_product).'</td>';
                    echo '<td>'.($plan->active ? __('Sí','usaalo') : __('No','usaalo')).'</td>';
                    echo '<td>
                        <button class="button edit-plan" data-id="'.$plan->id.'">'.__('Editar','usaalo').'</button>
                        <button class="button delete-plan" data-id="'.$plan->id.'">'.__('Eliminar','usaalo').'</button>
                    </td>';
                    echo '</tr>';
                }
                ?>
                </tbody>
            </table>

            <h2><?php _e('Reglas de Precio', 'usaalo'); ?></h2>
            <table id="usaalo-rules-table" class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Plan', 'usaalo'); ?></th>
                        <th><?php _e('Tipo SIM', 'usaalo'); ?></th>
                        <th><?php _e('Rango de días', 'usaalo'); ?></th>
                        <th><?php _e('Precio base', 'usaalo'); ?></th>
                        <th><?php _e('Voz', 'usaalo'); ?></th>
                        <th><?php _e('SMS', 'usaalo'); ?></th>
                        <th><?php _e('Acciones', 'usaalo'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $rules = $wpdb->get_results("
                    SELECT r.*, p.name as plan_name
                    FROM {$wpdb->prefix}usaalo_pricing_rules r
                    LEFT JOIN {$wpdb->prefix}usaalo_plans p ON r.plan_id = p.id
                    ORDER BY r.id DESC
                ");
                foreach($rules as $rule) {
                    echo '<tr>';
                    echo '<td>'.esc_html($rule->plan_name).'</td>';
                    echo '<td>'.esc_html($rule->sim_type).'</td>';
                    echo '<td>'.$rule->min_days.' - '.$rule->max_days.'</td>';
                    echo '<td>'.$rule->base_price.'</td>';
                    echo '<td>'.$rule->voice_addon.'</td>';
                    echo '<td>'.$rule->sms_addon.'</td>';
                    echo '<td>
                        <button class="button edit-rule" data-id="'.$rule->id.'">'.__('Editar','usaalo').'</button>
                        <button class="button delete-rule" data-id="'.$rule->id.'">'.__('Eliminar','usaalo').'</button>
                    </td>';
                    echo '</tr>';
                }
                ?>
                </tbody>
            </table>

            <!-- Modal para editar regla -->
            <div id="usaalo-rule-modal" style="display:none;">
                <form id="usaalo-rule-form">
                    <input type="hidden" id="rule-id" name="id">
                    <label><?php _e('Plan', 'usaalo'); ?></label>
                    <select id="rule-plan" name="plan_id">
                        <?php
                        foreach($plans as $plan) {
                            echo '<option value="'.$plan->id.'">'.esc_html($plan->name).'</option>';
                        }
                        ?>
                    </select>
                    <label><?php _e('Desde días', 'usaalo'); ?></label>
                    <input type="number" id="rule-from" name="min_days">
                    <label><?php _e('Hasta días', 'usaalo'); ?></label>
                    <input type="number" id="rule-to" name="max_days">
                    <label><?php _e('Precio base', 'usaalo'); ?></label>
                    <input type="text" id="rule-price" name="base_price">
                    <button type="submit" class="button button-primary"><?php _e('Guardar', 'usaalo'); ?></button>
                    <button type="button" class="button cancel-rule"><?php _e('Cancelar', 'usaalo'); ?></button>
                </form>
            </div>

        </div>
        <?php
    }

    /**
     * AJAX: Obtener regla
     */
    public function ajax_get_rule() {
        check_ajax_referer('usaalo_admin_nonce', 'nonce');
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        $rule = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}usaalo_pricing_rules WHERE id=%d", $id));
        wp_send_json($rule ? $rule : []);
    }

    /**
     * AJAX: Guardar regla
     */
    public function ajax_save_rule() {
        check_ajax_referer('usaalo_admin_nonce', 'nonce');
        global $wpdb;

        $id = intval($_POST['id'] ?? 0);
        $plan_id = intval($_POST['plan_id'] ?? 0);
        $min_days = intval($_POST['min_days'] ?? 0);
        $max_days = intval($_POST['max_days'] ?? 0);
        $base_price = floatval($_POST['base_price'] ?? 0);

        if($min_days > $max_days){
            wp_send_json(['success'=>false,'data'=>__('Rango inválido','usaalo')]);
        }

        if($id){
            $wpdb->update(
                "{$wpdb->prefix}usaalo_pricing_rules",
                ['plan_id'=>$plan_id,'min_days'=>$min_days,'max_days'=>$max_days,'base_price'=>$base_price],
                ['id'=>$id],
                ['%d','%d','%d','%f'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                "{$wpdb->prefix}usaalo_pricing_rules",
                ['plan_id'=>$plan_id,'min_days'=>$min_days,'max_days'=>$max_days,'base_price'=>$base_price],
                ['%d','%d','%d','%f']
            );
        }

        wp_send_json(['success'=>true,'data'=>__('Regla guardada','usaalo')]);
    }

    /**
     * AJAX: Eliminar regla
     */
    public function ajax_delete_rule() {
        check_ajax_referer('usaalo_admin_nonce', 'nonce');
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        if($id){
            $wpdb->delete("{$wpdb->prefix}usaalo_pricing_rules", ['id'=>$id], ['%d']);
            wp_send_json(['success'=>true,'data'=>__('Regla eliminada','usaalo')]);
        }
        wp_send_json(['success'=>false,'data'=>__('No se pudo eliminar','usaalo')]);
    }
}

// Inicializar
new USAALO_Admin();
