<?php
if (!defined('ABSPATH')) exit;

class USAALO_MSSQL_Sync {

    private $pdo;

    public function __construct() {
        $this->init_connection();
        // Se ejecuta al marcar un pedido como COMPLETADO
        add_action('woocommerce_order_status_completed', [$this, 'sync_order_to_mssql'], 20, 1);
    }

    /**
     * Crear conexión PDO con MSSQL
     */
    private function init_connection() {
        try {
            $this->pdo = new PDO(
                'dblib:host=sql5110.site4now.net,1433;dbname=db_a9d2cf_usaaloapp',
                'db_a9d2cf_usaaloapp_admin',
                'ujKVTXAE2za',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (Exception $e) {
            error_log('❌ Error de conexión MSSQL: ' . $e->getMessage());
        }
    }

    /**
     * Insertar datos de WooCommerce en MSSQL
     */
    public function sync_order_to_mssql($order_id) {
        if (!$this->pdo) {
            error_log("⚠ No hay conexión a MSSQL");
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log("⚠ Pedido no encontrado: $order_id");
            return;
        }

        // Datos globales del checkout
        $correo         = $order->get_billing_email();
        $nombre_viajero = $order->get_formatted_billing_full_name();
        $whatsapp       = $order->get_billing_phone();
        $motivo_viaje   = get_post_meta($order_id, '_usaalo_motivo_viaje', true);
        $fecha_salida   = get_post_meta($order_id, '_usaalo_fecha_inicio', true);
        $fecha_regreso  = get_post_meta($order_id, '_usaalo_fecha_fin', true);

        $tipo_sim       = get_post_meta($order_id, '_usaalo_tipo_sim', true);
        $serial_sim     = ''; // siempre vacío
        $recibir_sim_domicilio = ($tipo_sim === 'SIM') ? 1 : 0;
        $dir_entrega_sim = ($tipo_sim === 'SIM') ? $order->get_shipping_address_1() : '';

        $es_agencia     = get_post_meta($order_id, '_usaalo_es_agencia', true) ? 1 : 0;
        $nombre_agencia = $es_agencia ? get_post_meta($order_id, '_usaalo_nombre_agencia', true) : '';
        $asesor_com     = $es_agencia ? get_post_meta($order_id, '_usaalo_asesor_comercial', true) : '';

        $numero_eid     = ($tipo_sim === 'eSIM') ? get_post_meta($order_id, '_usaalo_numero_eid', true) : '';
        $numero_imei    = get_post_meta($order_id, '_usaalo_numero_imei', true);
        $lifemiles      = get_post_meta($order_id, '_usaalo_lifemiles', true);

        $soy_resp       = get_post_meta($order_id, '_usaalo_soy_responsable', true) ? 1 : 0;
        $he_leido       = get_post_meta($order_id, '_usaalo_he_leido', true) ? 1 : 0;
        $acepto_disp    = get_post_meta($order_id, '_usaalo_acepto_dispositivo', true) ? 1 : 0;

        $tipo_doc       = get_post_meta($order_id, '_billing_tipo_documento', true);
        $num_doc        = get_post_meta($order_id, '_billing_numero_documento', true);

        $medio_pago     = $order->get_payment_method_title();
        $estado         = $order->get_status();

        // Recorrer productos
        foreach ($order->get_items() as $item) {
            $plan      = $item->get_name();
            $countries = explode(',', $item->get_meta('usaalo_countries'));
            $brand     = $item->get_meta('usaalo_brand');
            $model     = $item->get_meta('usaalo_model');
            $services  = $item->get_meta('usaalo_services');
            $start     = $item->get_meta('usaalo_start_date') ?: $fecha_salida;
            $end       = $item->get_meta('usaalo_end_date') ?: $fecha_regreso;
            $sim_type  = $item->get_meta('usaalo_sim');
            $valor_plan= $item->get_total();

            foreach ($countries as $pais) {
                $pais = trim($pais);
                if (empty($pais)) continue;

                try {
                    $query = "INSERT INTO formulario (
                        Correo, NombreViajero, TipoDocumento, NumeroDocumento, WhatsApp,
                        PaisDestino, MotivoViaje, FechaSalida, FechaRegreso, Plan,
                        Servicio, EnCrucero, Desvio_Llamadas, MarcaTelefono, Observaciones,
                        LifeMiles, FechaFormulario, SoyResponsable, HeLeido, AceptaCookies,
                        IPEquipo, Id_Canal, AsesorComercial, Valor_Plan_Cotizado, SerialSIM,
                        RecibirSimDomicilio, DirEntregaSIM, Ciudad, CodigoPostal, MedioPago,
                        ComoSeEntero, FacturaNombreDe, Estado, Notas, LlamadaInternacional,
                        ModeloCelular, NumeroIMEI, TipoSimCard, NumeroEID, EsAgencia,
                        NombreAgencia, AceptoDispositivo
                    ) VALUES (
                        :Correo, :NombreViajero, :TipoDocumento, :NumeroDocumento, :WhatsApp,
                        :PaisDestino, :MotivoViaje, :FechaSalida, :FechaRegreso, :Plan,
                        :Servicio, :EnCrucero, :Desvio_Llamadas, :MarcaTelefono, :Observaciones,
                        :LifeMiles, GETDATE(), :SoyResponsable, :HeLeido, :AceptaCookies,
                        :IPEquipo, :Id_Canal, :AsesorComercial, :Valor_Plan_Cotizado, :SerialSIM,
                        :RecibirSimDomicilio, :DirEntregaSIM, :Ciudad, :CodigoPostal, :MedioPago,
                        :ComoSeEntero, :FacturaNombreDe, :Estado, :Notas, :LlamadaInternacional,
                        :ModeloCelular, :NumeroIMEI, :TipoSimCard, :NumeroEID, :EsAgencia,
                        :NombreAgencia, :AceptoDispositivo
                    )";

                    $stmt = $this->pdo->prepare($query);

                    $stmt->execute([
                        ':Correo'              => $correo,
                        ':NombreViajero'       => $nombre_viajero,
                        ':TipoDocumento'       => $tipo_doc,
                        ':NumeroDocumento'     => $num_doc,
                        ':WhatsApp'            => $whatsapp,
                        ':PaisDestino'         => $pais,
                        ':MotivoViaje'         => $motivo_viaje,
                        ':FechaSalida'         => $start,
                        ':FechaRegreso'        => $end,
                        ':Plan'                => $plan,
                        ':Servicio'            => $services,
                        ':EnCrucero'           => get_post_meta($order_id, '_usaalo_en_crucero', true),
                        ':Desvio_Llamadas'     => get_post_meta($order_id, '_usaalo_desvio_llamadas', true),
                        ':MarcaTelefono'       => $brand,
                        ':Observaciones'       => get_post_meta($order_id, '_usaalo_observaciones', true),
                        ':LifeMiles'           => $lifemiles,
                        ':SoyResponsable'      => $soy_resp,
                        ':HeLeido'             => $he_leido,
                        ':AceptaCookies'       => 1,
                        ':IPEquipo'            => $_SERVER['REMOTE_ADDR'],
                        ':Id_Canal'            => 'WooCommerce',
                        ':AsesorComercial'     => $asesor_com,
                        ':Valor_Plan_Cotizado' => $valor_plan,
                        ':SerialSIM'           => $serial_sim,
                        ':RecibirSimDomicilio' => $recibir_sim_domicilio,
                        ':DirEntregaSIM'       => $dir_entrega_sim,
                        ':Ciudad'              => $order->get_shipping_city(),
                        ':CodigoPostal'        => $order->get_shipping_postcode(),
                        ':MedioPago'           => $medio_pago,
                        ':ComoSeEntero'        => get_post_meta($order_id, '_usaalo_como_se_entero', true),
                        ':FacturaNombreDe'     => $order->get_billing_company(),
                        ':Estado'              => $estado,
                        ':Notas'               => $order->get_customer_note(),
                        ':LlamadaInternacional'=> get_post_meta($order_id, '_usaalo_llamada_internacional', true),
                        ':ModeloCelular'       => $model,
                        ':NumeroIMEI'          => $numero_imei,
                        ':TipoSimCard'         => $sim_type,
                        ':NumeroEID'           => $numero_eid,
                        ':EsAgencia'           => $es_agencia,
                        ':NombreAgencia'       => $nombre_agencia,
                        ':AceptoDispositivo'   => $acepto_disp
                    ]);

                    error_log("✅ Pedido $order_id → Plan=$plan, País=$pais enviado a MSSQL");

                } catch (Exception $e) {
                    error_log("❌ Error MSSQL Pedido $order_id: " . $e->getMessage());
                }
            }
        }
    }
}
