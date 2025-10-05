<?php
if (!defined('ABSPATH')) exit;

class USAALO_MSSQL_Sync {

    private $pdo;
    private $log_file;

    public function __construct() {
        $this->init_log();
        $this->init_connection();
        add_action('woocommerce_order_status_completed', [$this, 'sync_order_to_mssql'], 20, 1);
    }

    private function init_log() {
        $upload = wp_upload_dir();
        $this->log_file = trailingslashit($upload['basedir']) . 'usaalo_mssql.log';
    }

    private function log($msg) {
        $line = '[' . gmdate('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
        // también enviar a error_log para envíos rápidos
        error_log($msg);
        @file_put_contents($this->log_file, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Crear conexión PDO con MSSQL usando dblib
     */
    private function init_connection() {
        $server   = "sql5110.site4now.net";
        $port     = 1433;
        $database = "db_a9d2cf_usaaloapp";
        $username = "db_a9d2cf_usaaloapp_admin";
        $password = "ujKVTXAE2za";

        $dsn = "dblib:host=$server:$port;dbname=$database;charset=UTF-8";

        try {
            $this->pdo = new PDO($dsn, $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // $this->log("✅ Conexión a MSSQL establecida correctamente");
        } catch (PDOException $e) {
            $this->pdo = null;
            $this->log("❌ Error de conexión MSSQL: " . $e->getMessage());
        }
        
        // $nombre_buscar = 'user marin'; // el nombre que quieres buscar

        // try {
        //     $stmt = $this->pdo->prepare("SELECT * FROM formulario WHERE NombreViajero = :nombre");
        //     $stmt->execute([':nombre' => $nombre_buscar]);
            
        //     $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        //     if (count($resultados) > 0) {
        //         $this->log("✅ Se encontraron " . count($resultados) . " registros con NombreViajero = '{$nombre_buscar}':\n");

        //     } else {
        //         $this->log("❌ No se encontró ningún registro con NombreViajero = '{$nombre_buscar}'.");
        //     }
        
        // } catch (PDOException $e) {
        //     $this->log("❌ Error en consulta MSSQL: " . $e->getMessage());
        // }
        
//         $nombre_eliminar = 'user marin';

// try {
//     $stmt = $this->pdo->prepare("DELETE FROM [formulario] WHERE [NombreViajero] = :nombre");
//     $stmt->execute([':nombre' => $nombre_eliminar]);

//     $filas_afectadas = $stmt->rowCount();
//     $this->log("✅ Se eliminaron {$filas_afectadas} registros con NombreViajero = '{$nombre_eliminar}'.");

// } catch (PDOException $e) {
//     $this->log("❌ Error al eliminar registros MSSQL: " . $e->getMessage());
// }



    }

    /**
     * Mapea el servicio al ID según las reglas:
     * - SMS => VOZ Y DATOS (1)
     * - VOZ => 1
     * - VOZ Y DATOS => 1
     * - DATOS => 2
     *
     * Acepta cadenas (por ejemplo 'datos' / 'VOZ') o arrays.
     */
    private function map_service_to_id($service_meta) {
        if (empty($service_meta)) return null;

        // normalizar a array de tokens
        if (!is_array($service_meta)) {
            // puede venir como "datos" o "voz y datos" o "VOZ"
            $service_meta = [ $service_meta ];
        }

        $tokens = [];
        foreach ($service_meta as $s) {
            $s = mb_strtolower(trim((string)$s));
            // dividir por coma o espacios
            $parts = preg_split('/[\s,;]+/', $s);
            foreach ($parts as $p) {
                if ($p !== '') $tokens[] = $p;
            }
        }
        $tokens = array_unique($tokens);

        // reglas
        $hasDatos = in_array('datos', $tokens) || in_array('data', $tokens);
        $hasVoz   = in_array('voz', $tokens) || in_array('voice', $tokens);
        $hasSms   = in_array('sms', $tokens);

        if ($hasSms || $hasVoz) {
            return 1; // VOZ Y DATOS
        }
        if ($hasDatos && !$hasVoz) {
            return 2; // DATOS
        }

        // fallback: si aparece 'voz y datos' literal
        foreach ($service_meta as $s) {
            $sLower = mb_strtolower((string)$s);
            if (strpos($sLower, 'voz') !== false && strpos($sLower, 'dato') !== false) return 1;
        }

        return null;
    }

    /**
     * Normaliza nombres de países para comparaciones
     */
    private function normalize_country($country) {
        $c = trim($country);
        $c = str_replace(['á','à','ä','â','Á','À','Ä','Â'], 'a', $c);
        $c = str_replace(['é','è','ë','ê','É','È','Ë','Ê'], 'e', $c);
        $c = str_replace(['í','ì','ï','î','Í','Ì','Ï','Î'], 'i', $c);
        $c = str_replace(['ó','ò','ö','ô','Ó','Ò','Ö','Ô'], 'o', $c);
        $c = str_replace(['ú','ù','ü','û','Ú','Ù','Ü','Û'], 'u', $c);
        $c = mb_strtolower($c);
        $c = trim($c);
        // normalizaciones comunes
        $map = [
            'eeuu' => 'estados unidos',
            'usa'  => 'estados unidos',
            'united states' => 'estados unidos',
            'united states of america' => 'estados unidos',
            'canada' => 'canada',
            'méxico' => 'mexico',
            'mexico' => 'mexico',
            'reino unido' => 'reino unido',
            'spain' => 'espana',
            'españa' => 'espana',
            'espana' => 'espana'
        ];
        if (isset($map[$c])) return $map[$c];
        return $c;
    }

    /**
     * Determina el ID del plan según la lógica que diste.
     *
     * Reglas implementadas:
     * - Si solo Estados Unidos -> id 1
     * - Si México o Canadá presentes -> id 3
     * - Si Reino Unido presente -> id 8
     * - Si España presente -> id 9
     * - Resto del mundo -> id 5/6/7 según cantidad de países (1->5,2->6,>=3->7)
     */
    private function get_plan_id_by_paises(array $raw_paises) {
        $paises_norm = [];
        foreach ($raw_paises as $p) {
            $n = $this->normalize_country($p);
            if ($n !== '') $paises_norm[] = $n;
        }
        $paises_norm = array_values(array_unique($paises_norm));
        $count = count($paises_norm);

        // si no hay países válidos
        if ($count === 0) return null;

        // chequeos prioritarios
        // 1) Solo Estados Unidos
        if ($count === 1 && in_array('estados unidos', $paises_norm)) {
            return 1; // o 2, elegimos 1 por defecto
        }

        // 2) México o Canadá -> plan 3
        if (in_array('mexico', $paises_norm) || in_array('canada', $paises_norm) || in_array('canadá', $paises_norm)) {
            return 3;
        }

        // 3) Reino Unido -> 8
        if (in_array('reino unido', $paises_norm) || in_array('united kingdom', $paises_norm)) {
            return 8;
        }

        // 4) España -> 9
        if (in_array('espana', $paises_norm) || in_array('españa', $paises_norm)) {
            return 9;
        }

        // 5) Resto del mundo -> 5/6/7 por cantidad
        if ($count === 1) return 5;
        if ($count === 2) return 6;
        if ($count >= 3) return 7;

        return null;
    }

    /**
     * Formatea fechas a 'Y-m-d' si es posible, o retorna null.
     */
    private function format_date_for_sql($date_value) {
        if (empty($date_value)) return null;

        // Si ya es timestamp o DateTime
        if ($date_value instanceof DateTime) {
            return $date_value->format('Y-m-d');
        }

        // intentar parsear
        $date = date_create($date_value);
        if ($date === false) return null;
        return $date->format('Y-m-d');
    }

    /**
     * Comprueba que una cadena sea solo dígitos (IMEI/EID)
     */
    private function only_digits_or_null($val) {
        $val = trim((string)$val);
        if ($val === '') return null;
        return preg_match('/^\d+$/', $val) ? $val : null;
    }

    /**
     * Insertar datos de WooCommerce en MSSQL
     */
    public function sync_order_to_mssql($order_id) {
        if (!$this->pdo) {
            $this->log("⚠ No hay conexión a MSSQL - abortando sync para order {$order_id}");
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            $this->log("⚠ Pedido no encontrado: {$order_id}");
            return;
        }

        // Datos globales del checkout (normalizar)
        $correo         = sanitize_email($order->get_billing_email());
        $nombre_viajero = trim($order->get_formatted_billing_full_name());
        $whatsapp       = trim(get_post_meta($order_id, 'whatsapp', true) ?: $order->get_billing_phone());
        $motivo_viaje = sanitize_text_field( get_post_meta( $order_id, 'motivo_viaje', true ) ?: 'otros' );
        $serial_sim     = ''; // vacío por defecto
        $es_agencia     = get_post_meta($order_id, 'es_agencia', true) ? 1 : 0;
        $nombre_agencia = $es_agencia ? sanitize_text_field(get_post_meta($order_id, 'nombre_agencia', true)) : '';
        $asesor_com     = $es_agencia ? sanitize_text_field(get_post_meta($order_id, 'asesor_comercial', true)) : '';
        $numero_eid     = $tipo_sim === 'esim' ? $this->only_digits_or_null(get_post_meta($order_id, 'eid', true)) : null;
        $numero_imei    = $this->only_digits_or_null(get_post_meta($order_id, 'imei', true));
        $lifemiles      = sanitize_text_field(get_post_meta($order_id, 'puntos_colombia', true));
        $soy_resp       = get_post_meta($order_id, 'soy_responsable', true) ? 1 : 0;
        $he_leido       = get_post_meta($order_id, 'he_leido', true) ? 1 : 0;
        $acepto_disp    = get_post_meta($order_id, 'acepto_dispositivo', true) ? 1 : 0;
        $tipo_doc       = sanitize_text_field(get_post_meta($order_id, 'tipo_id', true));
        $num_doc        = sanitize_text_field(get_post_meta($order_id, 'documento_id', true));
        $comments       = sanitize_text_field($order->get_customer_note());
        $medio_pago     = $order->get_payment_method_title() ?: $order->get_payment_method() ?: 'Pagina WEB';

        // recorre los items del pedido
        foreach ($order->get_items() as $item) {
            $plan_meta      = $item->get_meta('usaalo_plan_id'); // si guardaste ID en el meta
            $plan_name_meta = $item->get_name();
            $countries_raw  = $item->get_meta('usaalo_countries'); // puede ser "País1, País2"
            $brand          = sanitize_text_field($item->get_meta('usaalo_brand'));
            $model          = sanitize_text_field($item->get_meta('usaalo_model'));
            $services_meta  = $item->get_meta('usaalo_services', true); // puede ser 'datos' o array
            $start_raw      = $item->get_meta('usaalo_start_date');
            $end_raw        = $item->get_meta('usaalo_end_date');
            $sim_type       = sanitize_text_field($item->get_meta('usaalo_sim'));
            $valor_plan     = floatval($item->get_total());
            
            $recibir_sim_domicilio = ($sim_type === 'sim') ? 1 : 0;
            // Para pedidos tipo 'sim'
            if ( $sim_type === 'sim' ) {
            
                // Obtenemos dirección de envío
                $direccion_envio = trim( $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2() );
            
                // Si está vacía, usamos la dirección de facturación
                if ( empty( $direccion_envio ) ) {
                    $direccion_envio = trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() );
                }
            
                $dir_entrega_sim = $direccion_envio;
            
            } else {
                $dir_entrega_sim = '';
            }
            
            $numero_eid     = $sim_type === 'esim' ? $this->only_digits_or_null(get_post_meta($order_id, 'eid', true)) : null;

            // normalizar países a array
            if (is_array($countries_raw)) {
                $countries = $countries_raw;
            } else {
                $countries = array_filter(array_map('trim', explode(',', (string)$countries_raw)));
            }

            // Servicio ID
            $servicio_id = $this->map_service_to_id($services_meta);

            // Plan ID: preferir si existe 'usaalo_plan_id' meta, si no, calcular por países
            if (!empty($plan_meta) && is_numeric($plan_meta)) {
                $plan_id = intval($plan_meta);
            } else {
                $plan_id = $this->get_plan_id_by_paises($countries);
            }

            // Formatear fechas
            $fecha_salida = $this->format_date_for_sql($start_raw) ?: null;
            $fecha_regreso = $this->format_date_for_sql($end_raw) ?: null;

            // Validaciones mínimas
            if (!$servicio_id) {
                $this->log("❌ Order {$order_id} - No se pudo mapear servicio para item '{$plan_name_meta}' (meta: " . print_r($services_meta, true) . ")");
                continue;
            }
            if (!$plan_id) {
                $this->log("❌ Order {$order_id} - No se pudo determinar plan para item '{$plan_name_meta}' (paises: " . implode(', ', $countries) . ")");
                continue;
            }

            // Preparar INSERT (coincide con estructura del modelo .NET)
            $query = "INSERT INTO formulario (
                        Correo, NombreViajero, TipoDocumento, NumeroDocumento, WhatsApp,
                        PaisDestino, MotivoViaje, FechaSalida, FechaRegreso, [Plan],
                        [Servicio], EnCrucero, Desvio_Llamadas, MarcaTelefono, Observaciones,
                        LifeMiles, FechaFormulario, SoyResponsable, HeLeido, AceptaCookies,
                        IPEquipo, Id_Canal, AsesorComercial, Valor_Plan_Cotizado, SerialSIM,
                        RecibirSimDomicilio, DirEntregaSIM, Ciudad, CodigoPostal, MedioPago,
                        ComoSeEntero, FacturaNombreDe, [Estado], Notas, LlamadaInternacional,
                        [ModeloCelular], NumeroIMEI, TipoSimCard, NumeroEID, EsAgencia,
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

            try {
                $stmt = $this->pdo->prepare($query);

                // Para cada país individual insertamos una fila (como pediste antes)
                foreach ($countries as $pais_raw) {
                    $pais = trim($pais_raw);
                    if ($pais === '') continue;

                    // parámetros, con sanitización adicional cuando aplica
                    $params = [
                        ':Correo'              => $correo,
                        ':NombreViajero'       => $nombre_viajero,
                        ':TipoDocumento'       => $tipo_doc,
                        ':NumeroDocumento'     => $num_doc,
                        ':WhatsApp'            => $whatsapp,
                        ':PaisDestino'         => $pais,
                        ':MotivoViaje'         => $motivo_viaje,
                        ':FechaSalida'         => $fecha_salida,
                        ':FechaRegreso'        => $fecha_regreso,
                        ':Plan'                => $plan_id,
                        ':Servicio'            => $servicio_id,
                        ':EnCrucero'           => get_post_meta($order_id, 'en_crucero', true) ? 1 : 0,
                        ':Desvio_Llamadas'     => sanitize_text_field(get_post_meta($order_id, 'desvio_llamadas', true)),
                        ':MarcaTelefono'       => $brand,
                        ':Observaciones'       => $comments,
                        ':LifeMiles'           => $lifemiles,
                        ':SoyResponsable'      => $soy_resp,
                        ':HeLeido'             => $he_leido,
                        ':AceptaCookies'       => 1,
                        ':IPEquipo'            => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                        ':Id_Canal'            => 28,
                        ':AsesorComercial'     => $asesor_com,
                        ':Valor_Plan_Cotizado' => $valor_plan,
                        ':SerialSIM'           => $serial_sim,
                        ':RecibirSimDomicilio' => $recibir_sim_domicilio,
                        ':DirEntregaSIM'       => $dir_entrega_sim,
                        ':Ciudad'              => $order->get_shipping_city(),
                        ':CodigoPostal'        => get_post_meta( $order->get_id(), '_shipping_postcode', true ),
                        ':MedioPago'           => $medio_pago,
                        ':ComoSeEntero'        => 'Otros',
                        ':FacturaNombreDe'     => get_post_meta( $order->get_id(), '_shipping_company', true )?:$nombre_viajero,
                        ':Estado'              => 0,
                        ':Notas'               => $order->get_customer_note(),
                        ':LlamadaInternacional'=> 0,
                        ':ModeloCelular'       => $model,
                        ':NumeroIMEI'          => $numero_imei,
                        ':TipoSimCard'         => ( $sim_type === 'sim' ) ? 'SIM Fisica' : 'E-Sim (Virtual)',
                        ':NumeroEID'           => $numero_eid,
                        ':EsAgencia'           => $es_agencia,
                        ':NombreAgencia'       => $nombre_agencia,
                        ':AceptoDispositivo'   => $acepto_disp
                    ];
                    $this->log("Order {$order_id} - '{$plan_name_meta}' (datos para enviar: " . print_r($params, true) . ")");
                    $stmt->execute($params);

                    $this->log("✅ Pedido {$order_id} insertado a MSSQL - PlanID={$plan_id}, ServicioID={$servicio_id}, Pais='{$pais}'");
                }

            } catch (PDOException $e) {
                $this->log("❌ Error MSSQL Pedido {$order_id}: " . $e->getMessage() . " -- PlanID={$plan_id}, ServicioID={$servicio_id}, Paises=" . implode(',', $countries));
            }
        } // foreach item
    } // sync
} // class
