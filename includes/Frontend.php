<?php
if (!defined('ABSPATH')) exit;

/**
 * Frontend.php
 * Shortcode and frontend handlers
 * - Shortcode [usaalo_cotizador]
 * - Enqueue assets and localize
 * - AJAX price calculation uses USAALO_Helpers::calculate_price
 */

class USAALO_Frontend {

    public function __construct() {
        add_shortcode('usaalo_cotizador', [$this, 'shortcode_render']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        add_action('wp_ajax_usaalo_calculate_price', [$this, 'ajax_calculate_price']);
        add_action('wp_ajax_nopriv_usaalo_calculate_price', [$this, 'ajax_calculate_price']);
    }

    public function enqueue_assets() {
        $base = plugin_dir_url(dirname(__FILE__)) . 'assets/';
        wp_enqueue_style('usaalo-select2', $base . 'lib/select2.min.css', [], '4.1.0');
        wp_enqueue_style('usaalo-frontend', $base . 'css/frontend.css', [], USAALO_VERSION);

        wp_enqueue_script('usaalo-select2', $base . 'lib/select2.min.js', ['jquery'], '4.1.0', true);
        wp_enqueue_script('usaalo-frontend', $base . 'js/frontend.js', ['jquery','usaalo-select2'], USAALO_VERSION, true);

        wp_localize_script('usaalo-frontend', 'USAALO_Frontend', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('usaalo_frontend_nonce'),
            'i18n' => [
                'select_country' => __('Selecciona un país', 'usaalo-cotizador'),
                'error' => __('Ocurrió un error', 'usaalo-cotizador'),
            ],
        ]);
    }

    public function shortcode_render($atts = []) {
        // Load necessary data
        $countries = USAALO_Helpers::get_countries();
        $brands = USAALO_Helpers::get_brands();

        ob_start();
        ?>
        <div id="usaalo-cotizador-wizard" class="usaalo-wizard">
            <form id="usaalo-quote" autocomplete="off">
                <!-- Step 1: Countries -->
                <div class="step active" id="step-1">
                    <label><?php _e('País(es)', 'usaalo-cotizador'); ?></label>
                    <select id="country" name="country[]" multiple style="width:100%">
                        <?php foreach($countries as $c): ?>
                            <option value="<?php echo esc_attr($c['code2'] ?? $c['code']); ?>"><?php echo esc_html($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p><button type="button" class="usaalo-next"><?php _e('Siguiente', 'usaalo-cotizador'); ?></button></p>
                </div>

                <!-- Step 2: SIM type & services -->
                <div class="step" id="step-2">
                    <label><?php _e('Tipo de SIM', 'usaalo-cotizador'); ?></label>
                    <select id="sim_type" name="sim_type">
                        <option value="esim"><?php _e('eSIM (virtual)', 'usaalo-cotizador'); ?></option>
                        <option value="physical"><?php _e('SIM física', 'usaalo-cotizador'); ?></option>
                    </select>

                    <label><?php _e('Servicios', 'usaalo-cotizador'); ?></label>
                    <select id="services" name="services[]" multiple style="width:100%"></select>

                    <p><button type="button" class="usaalo-back"><?php _e('Atrás', 'usaalo-cotizador'); ?></button>
                    <button type="button" class="usaalo-next"><?php _e('Siguiente', 'usaalo-cotizador'); ?></button></p>
                </div>

                <!-- Step 3: Dates & device -->
                <div class="step" id="step-3">
                    <label><?php _e('Fecha inicio', 'usaalo-cotizador'); ?></label>
                    <input type="date" id="start_date" name="start_date" required>

                    <label><?php _e('Fecha fin', 'usaalo-cotizador'); ?></label>
                    <input type="date" id="end_date" name="end_date" required>

                    <label><?php _e('Marca', 'usaalo-cotizador'); ?></label>
                    <select id="brand" name="brand" style="width:100%">
                        <option value=""><?php _e('Selecciona marca','usaalo-cotizador');?></option>
                        <?php foreach($brands as $b): ?>
                            <option value="<?php echo intval($b['id']); ?>"><?php echo esc_html($b['name']); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label><?php _e('Modelo', 'usaalo-cotizador'); ?></label>
                    <select id="model" name="model" style="width:100%"></select>

                    <p><button type="button" class="usaalo-back"><?php _e('Atrás', 'usaalo-cotizador'); ?></button>
                    <button type="button" class="usaalo-next"><?php _e('Siguiente', 'usaalo-cotizador'); ?></button></p>
                </div>

                <!-- Step 4: Summary -->
                <div class="step" id="step-4">
                    <h3><?php _e('Resumen', 'usaalo-cotizador'); ?></h3>
                    <div id="usaalo-summary"></div>
                    <div id="usaalo-price"></div>

                    <p><button type="button" class="usaalo-back"><?php _e('Atrás', 'usaalo-cotizador'); ?></button>
                    <button type="submit" class="button button-primary"><?php _e('Confirmar y continuar al pago', 'usaalo-cotizador'); ?></button></p>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public function ajax_calculate_price() {
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
            return wp_send_json_error(['errors' => $res['errors']]);
        }
        return wp_send_json_success([
            'breakdown' => $res['breakdown'],
            'total' => $res['total'],
            'days' => $res['days'],
            'compatibility' => $res['compatibility'],
        ]);
    }
}

// initialize
new USAALO_Frontend();
