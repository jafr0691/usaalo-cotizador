<?php
if (!defined('ABSPATH')) exit;

class USAALO_Frontend {

    public function __construct() {
        add_shortcode('usaalo_cotizador', [$this, 'render_form']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_usaalo_calculate_price', [$this, 'ajax_calculate_price']);
        add_action('wp_ajax_nopriv_usaalo_calculate_price', [$this, 'ajax_calculate_price']);
    }

    public function enqueue_assets() {
        wp_enqueue_style('usaalo-select2', plugin_dir_url(__FILE__) . '../assets/lib/select2.min.css', [], '4.0.13');
        wp_enqueue_style('usaalo-frontend', plugin_dir_url(__FILE__) . '../assets/css/frontend.css', [], '1.0');

        wp_enqueue_script('usaalo-select2', plugin_dir_url(__FILE__) . '../assets/lib/select2.min.js', ['jquery'], '4.0.13', true);
        wp_enqueue_script('usaalo-frontend', plugin_dir_url(__FILE__) . '../assets/js/frontend.js', ['jquery', 'usaalo-select2'], '1.0', true);

        wp_localize_script('usaalo-frontend', 'USAALO_Frontend', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('usaalo_frontend_nonce'),
        ]);
    }

    public function render_form() {
        ob_start();
        ?>
        <div id="usaalo-quote-form">
            <h2><?php _e('Cotiza tu SIM/eSIM Internacional', 'usaalo'); ?></h2>
            <form id="usaalo-quote">
                <!-- Paso 1: País -->
                <div class="step" id="step-1">
                    <label for="country"><?php _e('Selecciona tu país de destino', 'usaalo'); ?></label>
                    <select id="country" name="country" required>
                        <!-- Opciones de países cargadas dinámicamente -->
                    </select>
                </div>

                <!-- Paso 2: Tipo de SIM -->
                <div class="step" id="step-2" style="display:none;">
                    <label for="sim_type"><?php _e('Selecciona el tipo de SIM', 'usaalo'); ?></label>
                    <select id="sim_type" name="sim_type" required>
                        <option value="physical"><?php _e('SIM Física', 'usaalo'); ?></option>
                        <option value="esim"><?php _e('eSIM', 'usaalo'); ?></option>
                    </select>
                </div>

                <!-- Paso 3: Servicios adicionales -->
                <div class="step" id="step-3" style="display:none;">
                    <label for="services"><?php _e('Selecciona servicios adicionales', 'usaalo'); ?></label>
                    <select id="services" name="services[]" multiple>
                        <!-- Opciones de servicios cargadas dinámicamente -->
                    </select>
                </div>

                <!-- Paso 4: Fechas -->
                <div class="step" id="step-4" style="display:none;">
                    <label for="start_date"><?php _e('Fecha de inicio', 'usaalo'); ?></label>
                    <input type="date" id="start_date" name="start_date" required>

                    <label for="end_date"><?php _e('Fecha de finalización', 'usaalo'); ?></label>
                    <input type="date" id="end_date" name="end_date" required>
                </div>

                <!-- Paso 5: Marca y Modelo -->
                <div class="step" id="step-5" style="display:none;">
                    <label for="brand"><?php _e('Selecciona la marca de tu dispositivo', 'usaalo'); ?></label>
                    <select id="brand" name="brand" required>
                        <!-- Opciones de marcas cargadas dinámicamente -->
                    </select>

                    <label for="model"><?php _e('Selecciona el modelo de tu dispositivo', 'usaalo'); ?></label>
                    <select id="model" name="model" required>
                        <!-- Opciones de modelos cargadas dinámicamente -->
                    </select>
                </div>

                <!-- Paso 6: Resumen y Precio -->
                <div class="step" id="step-6" style="display:none;">
                    <h3><?php _e('Resumen de tu cotización', 'usaalo'); ?></h3>
                    <p id="quote-summary"></p>
                    <p id="quote-price"></p>
                    <button type="submit"><?php _e('Confirmar y Comprar', 'usaalo'); ?></button>
                </div>

                <div id="quote-error" style="display:none;">
                    <p><?php _e('Por favor, completa todos los campos correctamente.', 'usaalo'); ?></p>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public function ajax_calculate_price() {
        check_ajax_referer('usaalo_frontend_nonce', 'nonce');

        // Validar y sanitizar datos
        $country = sanitize_text_field($_POST['country']);
        $sim_type = sanitize_text_field($_POST['sim_type']);
        $services = array_map('sanitize_text_field', $_POST['services']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $brand = sanitize_text_field($_POST['brand']);
        $model = sanitize_text_field($_POST['model']);

        // Lógica para calcular el precio
        $base_price = 10; // Precio base por defecto
        $service_price = count($services) * 2; // $2 por cada servicio adicional
        $duration = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24); // Duración en días
        $total_price = $base_price + $service_price + ($duration * 0.5); // $0.5 por cada día adicional

        // Preparar respuesta
        $response = [
            'success' => true,
            'data' => [
                'summary' => sprintf(__('País: %s, Tipo de SIM: %s, Servicios: %s, Fechas: %s a %s, Marca: %s, Modelo: %s', 'usaalo'),
                    $country, $sim_type, implode(', ', $services), $start_date, $end_date, $brand, $model),
                'price' => sprintf(__('Precio total: $%s', 'usaalo'), number_format($total_price, 2)),
            ]
        ];

        wp_send_json($response);
    }
}

// Inicializar
new USAALO_Frontend();
