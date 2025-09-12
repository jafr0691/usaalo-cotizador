<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class USAALO_Checkout_Fields {

    public function __construct() {
        // Añadir campos al grupo billing (se verán junto a los campos nativos)
        add_filter('woocommerce_checkout_fields', [$this, 'add_checkout_fields']);

        // Validar en servidor
        add_action('woocommerce_checkout_process', [$this, 'validate_fields']);

        // Guardar en el pedido (order meta)
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_fields']);

        // Encolar JS para comportamiento dinámico en checkout
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Mostrar datos en admin del pedido
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'show_order_meta_in_admin'], 10, 1);
    }

    /**
     * Construir un resumen desde el carrito (usa usaalo_data de los items)
     */
    private function get_cart_summary(): array {
        $summary = [
            'sim' => null,
            'services' => [],
            'brand' => '',
            'model' => '',
            'countries_codes' => [],
            'countries_names' => [],
            'start_date' => '',
            'end_date' => '',
            'days' => 0
        ];

        if ( ! function_exists('WC') ) return $summary;
        $cart = WC()->cart;
        if ( ! $cart ) return $summary;

        foreach ( $cart->get_cart() as $item ) {
            $d = $item['usaalo_data'] ?? null;
            if (!$d) continue;

            if (empty($summary['sim']) && !empty($d['sim'])) $summary['sim'] = $d['sim'];

            if (!empty($d['brand']) && empty($summary['brand'])) $summary['brand'] = $d['brand'];
            if (!empty($d['model']) && empty($summary['model'])) $summary['model'] = $d['model'];

            if (!empty($d['countries']) && is_array($d['countries'])) {
                foreach ($d['countries'] as $c) {
                    // acepta nombre o código; normalizamos ambos
                    if (is_array($c)) {
                        // si vino como ['code'=>..,'name'=>..]
                        $code = $c['code'] ?? null;
                        $name = $c['name'] ?? null;
                    } else {
                        // si vino como "CO" o "Colombia"
                        $code = $c;
                        $name = $c;
                    }
                    if ($code && !in_array($code, $summary['countries_codes'])) {
                        $summary['countries_codes'][] = $code;
                    }
                    if ($name && !in_array($name, $summary['countries_names'])) {
                        $summary['countries_names'][] = $name;
                    }
                }
            }

            if (!empty($d['services']) && is_array($d['services'])) {
                $summary['services'] = array_unique(array_merge($summary['services'], $d['services']));
            }

            if (!empty($d['start_date']) && empty($summary['start_date'])) $summary['start_date'] = $d['start_date'];
            if (!empty($d['end_date']) && empty($summary['end_date'])) $summary['end_date'] = $d['end_date'];
            if (!empty($d['days']) && empty($summary['days'])) $summary['days'] = intval($d['days']);
        }

        return $summary;
    }

    /**
     * Añadir campos al checkout (grupo billing para mostrarse junto a campos nativos)
     */
    public function add_checkout_fields($fields) {
        $summary = $this->get_cart_summary();

        // prefijos billing_* (para integrarse con WC)
        // prioridad: ajusta el orden donde los quieras ver
        $prio = 110;

        // Tipo ID (select)
        $fields['billing']['billing_tipo_id'] = [
            'type'     => 'select',
            'label'    => __('Tipo de ID', 'usaalo-cotizador'),
            'required' => true,
            'class'    => ['form-row-first'],
            'priority' => $prio,
            'options'  => [
                ''   => __('Selecciona...', 'usaalo-cotizador'),
                'CC' => 'Cédula',
                'CE' => 'Cédula Extranjería',
                'PA' => 'Pasaporte',
                'TI' => 'Tarjeta de Identidad',
                'NIT'=> 'NIT',
            ],
            'default' => ''
        ];

        // Documento
        $fields['billing']['billing_documento_id'] = [
            'type' => 'text',
            'label' => __('Documento (ID / Pasaporte)', 'usaalo-cotizador'),
            'required' => true,
            'class' => ['form-row-last'],
            'priority' => $prio+1
        ];

        // WhatsApp
        $fields['billing']['billing_whatsapp'] = [
            'type' => 'tel',
            'label' => __('Número de WhatsApp', 'usaalo-cotizador'),
            'required' => true,
            'class' => ['form-row-wide'],
            'priority' => $prio+2,
            'placeholder' => '+57 3xx xxx xxxx'
        ];

        // Fechas (hidden / readonly pero visibles)
        $fields['billing']['billing_start_date'] = [
            'type' => 'text',
            'label' => __('Fecha inicio', 'usaalo-cotizador'),
            'required' => false,
            'class' => ['form-row-first'],
            'priority' => $prio+5,
            'custom_attributes' => ['readonly' => 'readonly'],
            'default' => $summary['start_date'] ?: ''
        ];
        $fields['billing']['billing_end_date'] = [
            'type' => 'text',
            'label' => __('Fecha fin', 'usaalo-cotizador'),
            'required' => false,
            'class' => ['form-row-last'],
            'priority' => $prio+6,
            'custom_attributes' => ['readonly' => 'readonly'],
            'default' => $summary['end_date'] ?: ''
        ];

        // Países destino (mostrar como texto, y guardar los códigos en hidden)
        $fields['billing']['billing_paises_display'] = [
            'type' => 'text',
            'label' => __('Países de destino', 'usaalo-cotizador'),
            'required' => false,
            'class' => ['form-row-wide'],
            'priority' => $prio+7,
            'custom_attributes' => ['readonly' => 'readonly'],
            'default' => !empty($summary['countries_names']) ? implode(', ', $summary['countries_names']) : ''
        ];
        $fields['billing']['billing_paises_codes'] = [
            'type' => 'hidden',
            'label' => '',
            'required' => false,
            'priority' => $prio+8,
            'default' => !empty($summary['countries_codes']) ? implode(',', $summary['countries_codes']) : ''
        ];

        // Motivo del viaje
        $fields['billing']['billing_motivo_viaje'] = [
            'type' => 'select',
            'label' => __('Motivo del viaje', 'usaalo-cotizador'),
            'required' => true,
            'class' => ['form-row-first'],
            'priority' => $prio+9,
            'options' => [
                '' => __('Seleccionar...', 'usaalo-cotizador'),
                'turismo' => __('Turismo','usaalo-cotizador'),
                'trabajo' => __('Trabajo','usaalo-cotizador'),
                'estudio' => __('Estudio','usaalo-cotizador'),
                'otros'   => __('Otros','usaalo-cotizador'),
            ]
        ];

        // Servicio elegido (oculto al usuario — lo traemos del carrito)
        $fields['billing']['billing_servicio_elegido'] = [
            'type' => 'hidden',
            'label' => '',
            'priority' => $prio+10,
            'default' => !empty($summary['services']) ? implode(',', $summary['services']) : ''
        ];

        // Marca y Modelo (readonly)
        $fields['billing']['billing_marca'] = [
            'type' => 'text',
            'label' => __('Marca del celular', 'usaalo-cotizador'),
            'required' => false,
            'class' => ['form-row-first'],
            'priority' => $prio+11,
            'custom_attributes' => ['readonly' => 'readonly'],
            'default' => $summary['brand'] ?? ''
        ];
        $fields['billing']['billing_modelo'] = [
            'type' => 'text',
            'label' => __('Modelo del celular', 'usaalo-cotizador'),
            'required' => false,
            'class' => ['form-row-last'],
            'priority' => $prio+12,
            'custom_attributes' => ['readonly' => 'readonly'],
            'default' => $summary['model'] ?? ''
        ];

        // IMEI
        $fields['billing']['billing_imei'] = [
            'type' => 'text',
            'label' => __('Número IMEI', 'usaalo-cotizador'),
            'required' => true,
            'class' => ['form-row-first'],
            'priority' => $prio+13
        ];

        // Tipo de SIM (hidden, lo trae el carrito)
        $fields['billing']['billing_tipo_sim'] = [
            'type' => 'hidden',
            'label' => '',
            'priority' => $prio+14,
            'default' => $summary['sim'] ?: ''
        ];

        // Número EID (solo eSIM: lo validaremos y mostraremos con JS)
        $fields['billing']['billing_eid'] = [
            'type' => 'text',
            'label' => __('Número EID (solo si es eSIM)', 'usaalo-cotizador'),
            'required' => false,
            'class' => ['form-row-wide'],
            'priority' => $prio+15
        ];

        // En crucero
        $fields['billing']['billing_en_crucero'] = [
            'type' => 'checkbox',
            'label' => __('¿El viajero estará en crucero? (ATENCIÓN: puede generar cargos adicionales)', 'usaalo-cotizador'),
            'required' => false,
            'class' => ['form-row-wide'],
            'priority' => $prio+16
        ];

        // Llamada entrante (solo si aplica voice)
        $fields['billing']['billing_llamada_entrante'] = [
            'type' => 'select',
            'label' => __('Para llamada entrante en Colombia', 'usaalo-cotizador'),
            'required' => false,
            'class' => ['form-row-wide'],
            'priority' => $prio+17,
            'options' => [
                '' => __('No aplica / Seleccionar', 'usaalo-cotizador'),
                'no' => __('No', 'usaalo-cotizador'),
                'transferencia' => __('Transferencia de llamadas (Movil/Operador)','usaalo-cotizador'),
                'fijo' => __('Número fijo en Colombia', 'usaalo-cotizador'),
            ]
        ];

        // ¿Es agencia?
        $fields['billing']['billing_es_agencia'] = [
            'type' => 'select',
            'label' => __('¿Es agencia?', 'usaalo-cotizador'),
            'required' => true,
            'class' => ['form-row-first'],
            'priority' => $prio+18,
            'options' => [
                '' => __('Seleccionar...', 'usaalo-cotizador'),
                'no' => __('No', 'usaalo-cotizador'),
                'si' => __('Sí', 'usaalo-cotizador'),
            ]
        ];

        // Nombre agencia / asesor
        $fields['billing']['billing_nombre_agencia'] = [
            'type' => 'text',
            'label' => __('Nombre de agencia', 'usaalo-cotizador'),
            'required' => false,
            'class' => ['form-row-first'],
            'priority' => $prio+19
        ];
        $fields['billing']['billing_asesor_comercial'] = [
            'type' => 'text',
            'label' => __('Asesor comercial', 'usaalo-cotizador'),
            'required' => false,
            'class' => ['form-row-last'],
            'priority' => $prio+20
        ];

        // Punto Colombia (si no es agencia)
        $fields['billing']['billing_puntos_colombia'] = [
            'type' => 'text',
            'label' => __('Número socio Puntos Colombia', 'usaalo-cotizador'),
            'required' => false,
            'class' => ['form-row-wide'],
            'priority' => $prio+21
        ];

        // Declaración responsabilidad (checkbox obligatorio)
        $fields['billing']['billing_responsabilidad'] = [
            'type' => 'checkbox',
            'label' => __('Soy el único responsable de la utilización del servicio', 'usaalo-cotizador'),
            'required' => true,
            'class' => ['form-row-wide'],
            'priority' => $prio+22
        ];

        // Términos y condiciones (obligatorio)
        $fields['billing']['billing_terminos'] = [
            'type' => 'checkbox',
            'label' => __('He leído y acepto los términos y condiciones', 'usaalo-cotizador'),
            'required' => true,
            'class' => ['form-row-wide'],
            'priority' => $prio+23
        ];

        // Cookies (obligatorio)
        $fields['billing']['billing_cookies'] = [
            'type' => 'checkbox',
            'label' => __('Acepto el uso de cookies', 'usaalo-cotizador'),
            'required' => true,
            'class' => ['form-row-wide'],
            'priority' => $prio+24
        ];

        // Aceptación activación única (obligatorio)
        $fields['billing']['billing_activacion_unica'] = [
            'type' => 'checkbox',
            'label' => __('Acepto que la activación es única para el dispositivo', 'usaalo-cotizador'),
            'required' => true,
            'class' => ['form-row-wide'],
            'priority' => $prio+25
        ];

        // Observaciones (textarea)
        $fields['billing']['billing_observacion'] = [
            'type' => 'textarea',
            'label' => __('Observación', 'usaalo-cotizador'),
            'required' => false,
            'class' => ['form-row-wide'],
            'priority' => $prio+26
        ];

        return $fields;
    }

    /**
     * Validaciones del checkout (condicionales)
     */
    public function validate_fields() {
        // Extraer resumen del carrito (sim, servicios, etc)
        $summary = $this->get_cart_summary();

        // Si es eSIM y no se envió EID → error
        if ( strtolower($summary['sim'] ?? '') === 'esim' ) {
            if ( empty( $_POST['billing_eid'] ) ) {
                wc_add_notice( __('Si eligió eSIM debe indicar Número EID.', 'usaalo-cotizador'), 'error' );
            }
        }

        // Si es agencia -> nombre y asesor obligatorios; si no -> puntos_colombia obligatorio
        $es_agencia = sanitize_text_field($_POST['billing_es_agencia'] ?? '');
        if ($es_agencia === 'si') {
            if ( empty($_POST['billing_nombre_agencia']) || empty($_POST['billing_asesor_comercial']) ) {
                wc_add_notice( __('Si indicó que es agencia debe proporcionar nombre de la agencia y asesor comercial.', 'usaalo-cotizador'), 'error' );
            }
        } elseif ($es_agencia === 'no') {
            if ( empty($_POST['billing_puntos_colombia']) ) {
                wc_add_notice( __('Si no es agencia debe indicar el número de socio Puntos Colombia.', 'usaalo-cotizador'), 'error' );
            }
        } else {
            wc_add_notice( __('Debe indicar si es agencia o no.', 'usaalo-cotizador'), 'error' );
        }

        // Checkboxes obligatorios
        $required_checkboxes = [
            'billing_responsabilidad' => __('Debe aceptar la declaración de responsabilidad','usaalo-cotizador'),
            'billing_terminos'       => __('Debe aceptar términos y condiciones','usaalo-cotizador'),
            'billing_cookies'        => __('Debe aceptar uso de cookies','usaalo-cotizador'),
            'billing_activacion_unica'=> __('Debe aceptar condición de activación única','usaalo-cotizador')
        ];
        foreach ($required_checkboxes as $field => $msg) {
            if ( empty($_POST[$field]) ) {
                wc_add_notice($msg, 'error');
            }
        }

        // Validaciones básicas
        if ( empty($_POST['billing_documento_id']) ) {
            wc_add_notice(__('El documento es obligatorio','usaalo-cotizador'), 'error');
        }
        if ( empty($_POST['billing_whatsapp']) ) {
            wc_add_notice(__('WhatsApp es obligatorio','usaalo-cotizador'), 'error');
        }
        if ( empty($_POST['billing_imei']) ) {
            wc_add_notice(__('IMEI es obligatorio','usaalo-cotizador'), 'error');
        }
    }

    /**
     * Guardar campos en la orden (order meta)
     */
    public function save_fields($order_id) {
        $map = [
            'billing_tipo_id' => 'tipo_id',
            'billing_documento_id' => 'documento_id',
            'billing_whatsapp' => 'whatsapp',
            'billing_start_date' => 'start_date',
            'billing_end_date' => 'end_date',
            'billing_paises_codes' => 'paises_codes',
            'billing_paises_display' => 'paises_display',
            'billing_motivo_viaje' => 'motivo_viaje',
            'billing_servicio_elegido' => 'servicio_elegido',
            'billing_marca' => 'marca',
            'billing_modelo' => 'modelo',
            'billing_imei' => 'imei',
            'billing_tipo_sim' => 'tipo_sim',
            'billing_eid' => 'eid',
            'billing_en_crucero' => 'en_crucero',
            'billing_llamada_entrante' => 'llamada_entrante',
            'billing_es_agencia' => 'es_agencia',
            'billing_nombre_agencia' => 'nombre_agencia',
            'billing_asesor_comercial' => 'asesor_comercial',
            'billing_puntos_colombia' => 'puntos_colombia',
            'billing_responsabilidad' => 'responsabilidad',
            'billing_terminos' => 'terminos',
            'billing_cookies' => 'cookies',
            'billing_activacion_unica' => 'activacion_unica',
            'billing_observacion' => 'observacion'
        ];

        foreach ($map as $post_key => $meta_key) {
            if ( isset($_POST[$post_key]) ) {
                // checkbox -> puede venir '1' o 'on'
                $value = is_array($_POST[$post_key]) ? $_POST[$post_key] : sanitize_text_field($_POST[$post_key]);
                update_post_meta($order_id, '_usaalo_'.$meta_key, $value);
            }
        }

        // Guardar servicios (vienen en hidden) y days si existen
        if ( ! empty( $_POST['billing_servicio_elegido'] ) ) {
            update_post_meta($order_id, '_usaalo_servicios', sanitize_text_field($_POST['billing_servicio_elegido']));
        }
        if ( ! empty( $_POST['billing_paises_codes'] ) ) {
            update_post_meta($order_id, '_usaalo_paises_codes', sanitize_text_field($_POST['billing_paises_codes']));
        }
    }

    /**
     * Encolar JS y pasar resumen del carrito para poblar los campos
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

        // Pasar resumen del carrito al JS
        wp_localize_script('usaalo-checkout', 'USAALO_Checkout', [
            'summary' => $this->get_cart_summary(),
            'i18n' => [
                'read_more' => __('Leer más','usaalo-cotizador')
            ]
        ]);
    }

    /**
     * Mostrar en admin del pedido
     */
    public function show_order_meta_in_admin($order) {
        $order_id = $order->get_id();
        echo '<div class="address"><h3>'.__('Datos USAALO','usaalo-cotizador').'</h3><p>';
        $meta_keys = [
            '_usaalo_paises_codes' => 'Países (codes)',
            '_usaalo_paises_display' => 'Países',
            '_usaalo_marca' => 'Marca',
            '_usaalo_modelo' => 'Modelo',
            '_usaalo_servicios' => 'Servicios',
            '_usaalo_start_date' => 'Inicio',
            '_usaalo_end_date' => 'Fin',
            '_usaalo_tipo_sim' => 'Tipo SIM',
            '_usaalo_eid' => 'EID',
            '_usaalo_imei' => 'IMEI'
        ];
        foreach ($meta_keys as $k => $label) {
            $v = get_post_meta($order_id, $k, true);
            if ($v !== '') {
                echo '<strong>'.$label.':</strong> '.esc_html(is_array($v) ? implode(', ', $v) : $v).'<br>';
            }
        }
        echo '</p></div>';
    }
}
