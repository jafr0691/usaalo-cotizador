<?php
if ( ! defined('ABSPATH') ) exit;

class USAALO_Checkout_Fields {

    public function __construct() {
        add_action('woocommerce_after_checkout_billing_form', [$this, 'mostrar_campos']);
        add_action('woocommerce_checkout_process', [$this, 'validar_campos']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'guardar_campos']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('woocommerce_order_status_completed', [$this, 'handle_order_completed'], 10, 1);
    }

    /**
     * Encola JS y CSS en checkout
     */
    public function enqueue_scripts() {
        if ( ! function_exists('is_checkout') || ! is_checkout() ) return;

        wp_enqueue_script(
            'usaalo-checkout',
            plugin_dir_url(__FILE__) . '../assets/js/usaalo-checkout.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'usaalo-checkout',
            plugin_dir_url(__FILE__) . '../assets/css/usaalo-checkout.css',
            [],
            '1.0.0'
        );

        // Pasar datos al JS
        wp_localize_script('usaalo-checkout', 'USAALO_Checkout', [
            'cart_summary' => $this->get_cart_summary(),
            'i18n' => [
                'required_field' => __('Este campo es obligatorio','usaalo-cotizador'),
                'invalid_eid' => __('El EID debe tener al menos 32 dígitos','usaalo-cotizador')
            ]
        ]);
    }

    /**
     * Campos adicionales en checkout
     */
    public function mostrar_campos($checkout) {
        echo '<div id="usaalo_custom_fields"><h3>' . __('Datos del Viajero - USAALO') . '</h3>';

        // Tipo de documento
        woocommerce_form_field('tipo_id', [
            'type' => 'select',
            'label' => __('Tipo de documento'),
            'required' => true,
            'options' => [
                '' => 'Seleccionar...',
                'CC' => 'Cédula',
                'CE' => 'Cédula de Extranjería',
                'TI' => 'Tarjeta de Identidad',
                'PA' => 'Pasaporte',
                'NIT'=> 'NIT',
            ],
            'class' => ['form-row-first'],
        ], $checkout->get_value('tipo_id'));

        // Número documento
        woocommerce_form_field('documento_id', [
            'type' => 'text',
            'label' => __('Número de documento'),
            'required' => true,
            'class' => ['form-row-last'],
        ], $checkout->get_value('documento_id'));

        // WhatsApp
        woocommerce_form_field('whatsapp', [
            'type' => 'tel',
            'label' => __('Número de WhatsApp'),
            'required' => true,
            'class' => ['form-row-wide'],
        ], $checkout->get_value('whatsapp'));

        // Motivo de viaje
        woocommerce_form_field('motivo_viaje', [
            'type' => 'select',
            'label' => __('Motivo del viaje'),
            'required' => true,
            'options' => [
                '' => 'Seleccionar...',
                'turismo' => 'Turismo',
                'trabajo' => 'Trabajo',
                'estudio' => 'Estudio',
                'otros'   => 'Otros',
            ]
        ], $checkout->get_value('motivo_viaje'));

        // IMEI
        woocommerce_form_field('imei', [
            'type' => 'text',
            'label' => __('Número IMEI'),
            'required' => true,
        ], $checkout->get_value('imei'));

        // EID (solo eSIM, se oculta por JS)
        woocommerce_form_field('eid', [
            'type' => 'text',
            'label' => __('Número EID (solo eSIM)'),
            'required' => false,
        ], $checkout->get_value('eid'));

        // ¿En crucero?
        woocommerce_form_field('en_crucero', [
            'type' => 'checkbox',
            'label' => __('¿El viajero estará en crucero?'),
        ], $checkout->get_value('en_crucero'));

        // Llamada entrante (solo si plan incluye VOZ, se oculta por JS)
        woocommerce_form_field('llamada_entrante', [
            'type' => 'select',
            'label' => __('Para la llamada entrante de Colombia'),
            'options' => [
                '' => 'Seleccionar...',
                'no' => 'No',
                'transferencia' => 'Transferencia de llamadas',
                'fijo' => 'Número fijo en Colombia'
            ]
        ], $checkout->get_value('llamada_entrante'));

        // Agencia
        woocommerce_form_field('es_agencia', [
            'type' => 'select',
            'label' => __('¿Es agencia?'),
            'options' => [
                '' => 'Seleccionar...',
                'no' => 'No',
                'si' => 'Sí',
            ]
        ], $checkout->get_value('es_agencia'));

        // Nombre de agencia
        woocommerce_form_field('nombre_agencia', [
            'type' => 'text',
            'label' => __('Nombre de la agencia'),
        ], $checkout->get_value('nombre_agencia'));

        // Asesor comercial
        woocommerce_form_field('asesor_comercial', [
            'type' => 'text',
            'label' => __('Asesor comercial'),
        ], $checkout->get_value('asesor_comercial'));

        // Puntos Colombia (solo si NO es agencia)
        woocommerce_form_field('puntos_colombia', [
            'type' => 'text',
            'label' => __('Número de socio Puntos Colombia'),
        ], $checkout->get_value('puntos_colombia'));

        // LifeMiles
        woocommerce_form_field('lifemiles', [
            'type' => 'text',
            'label' => __('Número LifeMiles'),
        ], $checkout->get_value('lifemiles'));

        // Observaciones
        woocommerce_form_field('observacion', [
            'type' => 'textarea',
            'label' => __('Observación'),
        ], $checkout->get_value('observacion'));

        // Valor plan cotizado (precargado)
        woocommerce_form_field('valor_plan', [
            'type' => 'text',
            'label' => __('Valor plan cotizado'),
            'required' => false,
            'custom_attributes' => ['readonly' => 'readonly'],
        ], $checkout->get_value('valor_plan'));

        // Checkboxes obligatorios
        woocommerce_form_field('responsabilidad', [
            'type' => 'checkbox',
            'label' => __('Soy el único responsable de la utilización del servicio.'),
            'required' => true,
        ], $checkout->get_value('responsabilidad'));

        woocommerce_form_field('terminos', [
            'type' => 'checkbox',
            'label' => __('He leído y acepto los términos y condiciones.'),
            'required' => true,
        ], $checkout->get_value('terminos'));

        woocommerce_form_field('cookies', [
            'type' => 'checkbox',
            'label' => __('Acepto el uso de cookies.'),
            'required' => true,
        ], $checkout->get_value('cookies'));

        woocommerce_form_field('activacion_unica', [
            'type' => 'checkbox',
            'label' => __('Acepto que la activación es única para el dispositivo.'),
            'required' => true,
        ], $checkout->get_value('activacion_unica'));

        woocommerce_form_field('acepto_dispositivo', [
            'type' => 'checkbox',
            'label' => __('Acepto el uso de este dispositivo para el servicio.'),
            'required' => true,
        ], $checkout->get_value('acepto_dispositivo'));

        echo '</div>';
    }

    /**
     * Validar campos obligatorios
     */
    public function validar_campos() {
        $requeridos = ['tipo_id','documento_id','whatsapp','motivo_viaje','marca_telefono','modelo_telefono','imei','responsabilidad','terminos','cookies','activacion_unica','acepto_dispositivo'];

        foreach ($requeridos as $campo) {
            if ( empty($_POST[$campo]) ) {
                wc_add_notice(sprintf(__('El campo %s es obligatorio.','usaalo-cotizador'), $campo), 'error');
            }
        }

        if ( isset($_POST['eid']) && !empty($_POST['eid']) && strlen($_POST['eid']) < 32 ) {
            wc_add_notice(__('El EID debe tener al menos 32 dígitos.','usaalo-cotizador'), 'error');
        }
    }

    /**
     * Guardar en order meta
     */
    public function guardar_campos($order_id) {
        $campos = ['tipo_id','documento_id','whatsapp','motivo_viaje','marca_telefono','modelo_telefono','imei','eid','en_crucero','llamada_entrante','es_agencia','nombre_agencia','asesor_comercial','puntos_colombia','lifemiles','observacion','valor_plan','responsabilidad','terminos','cookies','activacion_unica','acepto_dispositivo'];

        foreach ($campos as $campo) {
            if ( isset($_POST[$campo]) ) {
                update_post_meta($order_id, $campo, sanitize_text_field($_POST[$campo]));
            }
        }
    }

    /**
     * Obtener resumen del carrito (datos cotizador)
     */
    private function get_cart_summary() {
        $summary = [
            'sim' => '',
            'servicio' => '',
            'valor_plan' => '',
        ];
        if ( ! WC()->cart ) return $summary;
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( isset($cart_item['tipo_sim']) ) $summary['sim'] = $cart_item['tipo_sim'];
            if ( isset($cart_item['servicio']) ) $summary['servicio'] = $cart_item['servicio'];
            if ( isset($cart_item['valor_plan']) ) $summary['valor_plan'] = $cart_item['valor_plan'];
        }
        return $summary;
    }

    /**
     * Obtener resumen del carrito (datos cotizador)
     */
    public function handle_order_completed($order_id) {
        if (empty($order_id)) return;

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log("USAALO: order $order_id not found.");
            return;
        }

        // --- Datos básicos del pedido ---
        $firstname   = $order->get_billing_first_name();
        $lastname    = $order->get_billing_last_name();
        $email       = $order->get_billing_email();
        $admin_email = get_option('admin_email');
        $payment     = $order->get_payment_method_title();
        $order_total = $order->get_total();
        $order_sub   = $order->get_subtotal();

        // Keys posibles donde puede estar guardado el id de Puntos Colombia (adáptalo si usas otro)
        $pco_meta_keys = ['PCO_id','_PCO_id','puntos_colombia_id'];
        $puntos_colombia_id = null;
        foreach ($pco_meta_keys as $k) {
            $v = $order->get_meta($k);
            if ($v) { $puntos_colombia_id = $v; break; }
        }

        $uuid = $order->get_meta('uuid') ?: $order->get_meta('_uuid');

        // --- Intenta obtener token de Puntos Colombia ---
        $client_id = defined('PCO_CLIENT_ID') ? PCO_CLIENT_ID : '';
        $client_secret = defined('PCO_CLIENT_SECRET') ? PCO_CLIENT_SECRET : '';

        $access_token = null;
        if ($client_id && $client_secret) {
            $token_url = 'https://api.puntoscolombia.com/auth/oauth/v2/token';
            $token_resp = wp_remote_post($token_url, [
                'timeout' => 20,
                'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
                'body'    => [
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'grant_type'    => 'client_credentials'
                ],
            ]);

            if (!is_wp_error($token_resp) && wp_remote_retrieve_response_code($token_resp) === 200) {
                $body = json_decode(wp_remote_retrieve_body($token_resp), true);
                if (!empty($body['access_token'])) {
                    $access_token = $body['access_token'];
                } else {
                    error_log('USAALO: token PCO no contiene access_token: ' . wp_remote_retrieve_body($token_resp));
                }
            } else {
                error_log('USAALO: fallo al pedir token PCO: ' . (is_wp_error($token_resp) ? $token_resp->get_error_message() : wp_remote_retrieve_response_code($token_resp)));
            }
        } else {
            error_log('USAALO: PCO client_id/secret no definidos, se usará cálculo local de puntos.');
        }

        // --- Intenta consultar transacción en Puntos Colombia (si tenemos token + id o uuid) ---
        $tx_data = null;
        if ($access_token && $puntos_colombia_id) {
            $tx_by_id_url = 'https://api.puntoscolombia.com/paas/v2/pay-button/transactions/' . rawurlencode($puntos_colombia_id);
            $resp = wp_remote_get($tx_by_id_url, [
                'timeout' => 20,
                'headers' => [ 'Authorization' => 'Bearer ' . $access_token ]
            ]);
            if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
                $tx_data = json_decode(wp_remote_retrieve_body($resp), true);
            } else {
                error_log('USAALO: no se pudo obtener transacción por id PCO: ' . (is_wp_error($resp) ? $resp->get_error_message() : wp_remote_retrieve_response_code($resp)));
            }
        }

        // fallback: intentar por externalTransactionId (uuid) con endpoint de consulta si existe
        if (!$tx_data && $access_token && $uuid) {
            // Endpoint genérico de búsqueda — si tu API utiliza otro, cámbialo
            $tx_query_url = 'https://api.puntoscolombia.com/paas/v2/pay-button/transactions/query';
            $resp = wp_remote_post($tx_query_url, [
                'timeout' => 20,
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json'
                ],
                'body' => wp_json_encode(['externalTransactionId' => $uuid])
            ]);
            if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
                $body = json_decode(wp_remote_retrieve_body($resp), true);
                // Dependiendo de la respuesta puede ser una lista u objeto
                if (!empty($body['data'])) $tx_data = $body['data'];
                elseif (!empty($body)) $tx_data = $body;
            } else {
                error_log('USAALO: búsqueda por uuid en PCO falló: ' . (is_wp_error($resp) ? $resp->get_error_message() : wp_remote_retrieve_response_code($resp)));
            }
        }

        // --- Determinar puntos reales ---
        $points_reported = null;
        $amount_reported = null;
        if (!empty($tx_data)) {
            // buscar campos comunes
            if (isset($tx_data['points'])) {
                $points_reported = intval($tx_data['points']);
            } elseif (isset($tx_data['paymentInformation']['amount'])) {
                $amount_reported = floatval($tx_data['paymentInformation']['amount']);
            } elseif (isset($tx_data['amount'])) {
                $amount_reported = floatval($tx_data['amount']);
            }
        }

        // Si no hay info desde API, usar meta/calculo local
        $tasalifimille = floatval( $order->get_meta('tasalifimille') ?: 1 );
        $_PCO_total_TOPOINT = floatval( $order->get_meta('_PCO_total_TOPOINT') ?: 0 );

        // calcular points: preferir puntos reportados, sino si amount_reported existe usar formula, sino usar _PCO_total_TOPOINT
        if ($points_reported !== null) {
            $points = $points_reported;
        } elseif ($amount_reported !== null && $amount_reported > 0) {
            $points = floor( ($amount_reported * $tasalifimille) / 800 );
        } else {
            $points = floor( ($_PCO_total_TOPOINT * $tasalifimille) / 800 );
        }

        // --- Construir listado de items con detalle (países por item, marca/modelo, días, sim, servicios) ---
        $items_html = '<ul style="margin:0; padding:0; list-style:none;">';
        foreach ($order->get_items() as $item) {
            $product_name = $item->get_name();
            $qty = $item->get_quantity();
            $line_total = wc_price($item->get_total());
            // intentar obtener países / marca / modelo escritos en meta del item (vienen de usaalo_* si se guardaron)
            $countries_meta = $item->get_meta('usaalo_countries', true);
            if (empty($countries_meta)) $countries_meta = $item->get_meta('countries', true);
            if (is_string($countries_meta) && strpos($countries_meta, ',') !== false) {
                $countries = array_map('trim', explode(',', $countries_meta));
            } elseif (is_array($countries_meta)) {
                $countries = $countries_meta;
            } else {
                $countries = $countries_meta ? [$countries_meta] : [];
            }

            $brand = $item->get_meta('usaalo_brand', true) ?: $item->get_meta('brand', true) ?: '';
            $model = $item->get_meta('usaalo_model', true) ?: $item->get_meta('model', true) ?: '';
            $sim_type = $item->get_meta('usaalo_sim', true) ?: '';
            $services = $item->get_meta('usaalo_services', true) ?: '';

            $start_date = $item->get_meta('usaalo_start_date', true) ?: '';
            $end_date   = $item->get_meta('usaalo_end_date', true) ?: '';

            $items_html .= "<li style='margin:12px 0; color:#121359;'>
                <strong>{$product_name}</strong> × {$qty} — {$line_total}
                <div style='font-size:13px; color:#54595F; margin-top:6px;'>
                    Países: " . (empty($countries) ? '-' : esc_html(implode(', ', $countries))) . "<br>
                    Marca / Modelo: " . esc_html(trim($brand . ' ' . $model)) . "<br>
                    SIM: " . esc_html($sim_type) . " — Servicios: " . esc_html($services) . "<br>
                    Fechas: " . esc_html($start_date) . " → " . esc_html($end_date) . "
                </div>
            </li>";
        }
        $items_html .= '</ul>';

        // --- Plantilla base profesional (colores que pediste) ---
        $template_wrap = function($title, $content_html) {
            $c_orange = '#F39300';
            $c_dark   = '#121359';
            $c_gray   = '#54595F';
            $c_text   = '#7A7A7A';
            return "
            <div style='font-family:Arial, sans-serif; background:#f6f6f6; padding:20px;'>
                <table width='600' align='center' cellpadding='0' cellspacing='0' style='background:#fff;border-radius:8px;overflow:hidden;'>
                    <tr><td style='background:{$c_dark};padding:18px;text-align:center;'>
                        <img src='https://grupozona.es/wp-content/uploads/2024/06/logo-usaalo.png' alt='UsaAlo' style='max-width:180px;'>
                    </td></tr>
                    <tr><td style='padding:18px;text-align:center;background:{$c_orange};color:#fff;font-weight:700;font-size:18px;'>{$title}</td></tr>
                    <tr><td style='padding:20px;color:{$c_gray};font-size:14px;line-height:1.6;'>{$content_html}</td></tr>
                    <tr><td style='background:{$c_dark};color:#fff;text-align:center;padding:14px;font-size:13px;'>
                        © " . date('Y') . " UsaAlo &nbsp; • &nbsp; <span style='color:{$c_text};font-size:12px;'>Correo automático, por favor no responda</span>
                    </td></tr>
                </table>
            </div>";
        };

        // --- Enviar correos (cliente y admin) ---
        $headers = [ 'Content-Type: text/html; charset=UTF-8', 'From: UsaAlo <gerenciacomercialusaalo@gmail.com>' ];

        if ($puntos_colombia_id) {
            $subject_client = "¡Gracias por tu compra #{$order_id}! Has ganado {$points} puntos Puntos Colombia";
            $body_client = "
                <p>Hola <strong>{$firstname} {$lastname}</strong>,</p>
                <p>Gracias por tu compra en <strong>UsaAlo</strong>. Tu pedido <strong>#{$order_id}</strong> ha sido completado.</p>
                <p><strong>Puntos acreditados:</strong> <span style='color:#F39300; font-weight:700;'>{$points}</span></p>
                <h4 style='color:#121359;margin-top:12px;'>Resumen del pedido</h4>
                {$items_html}
                <p><strong>Subtotal:</strong> " . wc_price($order_sub) . "</p>
                <p><strong>Total pagado:</strong> " . wc_price($order_total) . "</p>
                <p><strong>Método de pago:</strong> {$payment}</p>
            ";
            wp_mail($email, $subject_client, $template_wrap($subject_client, $body_client), $headers);

            $subject_admin = "Pedido #{$order_id} - Puntos Colombia ({$points} pts)";
            $body_admin = "
                <p><strong>Cliente:</strong> {$firstname} {$lastname} ({$email})</p>
                <p><strong>Order ID:</strong> {$order_id} | <strong>UUID:</strong> " . esc_html($uuid) . " | <strong>PCO ID:</strong> " . esc_html($puntos_colombia_id) . "</p>
                <p><strong>Puntos calculados:</strong> {$points}</p>
                <h4 style='color:#121359;'>Productos</h4>
                {$items_html}
                <p><strong>Total:</strong> " . wc_price($order_total) . "</p>
            ";
            wp_mail($admin_email, $subject_admin, $template_wrap($subject_admin, $body_admin), $headers);

        } else {
            // Sin Puntos Colombia -> enviar resumen normal
            $subject_client = "Gracias por tu compra en UsaAlo #{$order_id}";
            $body_client = "
                <p>Hola <strong>{$firstname} {$lastname}</strong>,</p>
                <p>Tu pedido <strong>#{$order_id}</strong> ha sido completado correctamente.</p>
                <h4 style='color:#121359;'>Resumen del pedido</h4>
                {$items_html}
                <p><strong>Subtotal:</strong> " . wc_price($order_sub) . "</p>
                <p><strong>Total pagado:</strong> " . wc_price($order_total) . "</p>
                <p><strong>Método de pago:</strong> {$payment}</p>
            ";
            wp_mail($email, $subject_client, $template_wrap($subject_client, $body_client), $headers);

            $subject_admin = "Pedido #{$order_id} completado (sin Puntos Colombia)";
            $body_admin = "
                <p><strong>Cliente:</strong> {$firstname} {$lastname} ({$email})</p>
                <p><strong>Order ID:</strong> {$order_id} | <strong>UUID:</strong> " . esc_html($uuid) . "</p>
                <h4 style='color:#121359;'>Productos</h4>
                {$items_html}
                <p><strong>Total:</strong> " . wc_price($order_total) . "</p>
            ";
            wp_mail($admin_email, $subject_admin, $template_wrap($subject_admin, $body_admin), $headers);
        }

        // log resultado
        error_log("USAALO: emails enviados para order $order_id (PCO_id: " . esc_html($puntos_colombia_id) . ", points: $points)");
    }


}
