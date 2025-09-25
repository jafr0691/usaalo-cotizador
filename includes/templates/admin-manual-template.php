<!-- ===========================
     Manual de Uso y Funciones — Usaalo Cotizador
=========================== -->
<div class="usaalo-manual-wrap">
  <h1>📘 Manual de Uso & Funciones — <em>Usaalo Cotizador</em></h1>

  <div class="usaalo-tabs">
    <button class="tab-btn active" data-tab="usuario">👤 Manual de Usuario</button>
    <button class="tab-btn" data-tab="tecnico">⚙️ Manual Técnico</button>
  </div>
  <!-- ==========================
  Manual de Usuario
  ========================== -->
  <section id="usuario" class="usaalo-tab active">
    <h2>Resumen general</h2>
    <p>
      Usaalo Cotizador permite a tus clientes seleccionar países, marcas y modelos, tipo de SIM y servicios, y calcular el precio total de un plan por número de días. 
      Para mostrar el cotizador en tu sitio web se utiliza el shortcode: 
      <code>[usaalo_cotizador]</code>.
    </p>

    <div class="usaalo-card">
      <h3>Información clave para usuario</h3>
      <ul>
        <li><strong>Marca y Modelo:</strong> Esta sección permite filtrar la compatibilidad de servicios por marca y modelo de dispositivo. <em>No afecta el precio final</em>, solo ayuda a identificar rápidamente qué servicios están disponibles para cada combinación de marca y modelo.</li>
        <li><strong>SIM física:</strong> tiene costo y envío (configurado en WooCommerce, nombre <strong>SIM</strong>).</li>
        <li><strong>eSIM:</strong> virtual, no tiene costo ni envío.</li>
        <li><strong>Servicios:</strong> Esta pestaña permite configurar la <em>compatibilidad de servicios por país y modelo</em>. <strong>Nota:</strong> Solo la SIM física (💳) afecta el precio; los demás servicios <em>no modifican el precio final</em>.</li>
        <ul>
            <li><strong>SIM (💳):</strong> Soporte de SIM física. <strong>Impacta en el precio</strong>.</li>
            <li><strong>eSIM (📶):</strong> Soporte de eSIM. Solo informativo.</li>
            <li><strong>Datos (📡):</strong> Compatibilidad con datos móviles. Solo informativo.</li>
            <li><strong>Voz (📞):</strong> Permite llamadas de voz. Solo informativo.</li>
            <li><strong>SMS (✉️):</strong> Permite mensajes de texto. Solo informativo.</li>
        </ul>
        <li><strong>Funciones principales:</strong></li>
        <ul>
            <li>Checkbox individual: marcar/desmarcar un servicio por modelo y país. Se guarda automáticamente.</li>
            <li>Checkbox en encabezado de columna: seleccionar/desmarcar <em>todos los visibles</em> de la columna sin recargar la página.</li>
            <li>Actualización segura: cambios realizados en <strong>transacción</strong> para evitar inconsistencias o registros duplicados.</li>
            <li>Filtros de búsqueda: filtra por país, marca o modelo para localizar rápidamente registros.</li>
        </ul>
      </ul>
    </div>

    <h2>Administración — CRUD de las pestañas del plugin</h2>
    <p>Las pestañas permiten crear, editar y eliminar registros almacenados. Se detallan a continuación:</p>

    <ul>
        <li><strong>Paises:</strong> solo administra los países almacenados (código ISO 2 letras, nombre, región). Muestra en tabla y permite eliminar o editar cada país.</li>
        <li>
          <strong>Marcas y Modelos:</strong> administra las marcas y modelos guardados en el sistema. 
          Primero debes crear una marca y luego podrás asociarle uno o varios modelos. 
          Desde aquí también puedes editar o eliminar cada registro según sea necesario.  
          Además, tienes la posibilidad de <em>activar o desactivar</em> el uso de los campos de marca y modelo en el formulario.  
          Si desactivas un campo, automáticamente se asignará un valor por defecto 
          (<code>marca_default</code> o <code>modelo_default</code>) para que la cotización siga funcionando sin necesidad de selección manual.
        </li>

        <li>
          <strong>Tipo de SIM y Servicios:</strong> gestiona qué servicios estarán disponibles para cada combinación de país, marca y modelo.  
          En la parte superior ahora se incluyen <em>checkboxes globales</em> para activar o desactivar por completo las opciones de:
          <code>SIM</code>, <code>eSIM</code>, <code>Datos</code>, <code>Voz</code> y <code>SMS</code>.  
          Si un servicio es desactivado globalmente, no aparecerá en el formulario de cotización, 
          incluso si el modelo tiene ese servicio disponible en su configuración.
          <br><br>
          Funciones principales:
          <ul>
            <li><strong>Checkbox individual:</strong> marcar o desmarcar un servicio por modelo y país. Se guarda automáticamente.</li>
            <li><strong>Checkbox en encabezado de columna:</strong> seleccionar o desmarcar todos los visibles de la columna sin necesidad de recargar la página.</li>
            <li><strong>Actualización segura:</strong> todos los cambios se aplican mediante transacciones, evitando inconsistencias o duplicados en la base de datos.</li>
            <li><strong>Filtros de búsqueda:</strong> permite filtrar rápidamente por país, marca o modelo para localizar y administrar registros de forma eficiente.</li>
          </ul>
        </li>
        <li><strong>Planes (productos):</strong> 
            Aquí se relacionan los productos con los países, marcas, modelos y tipos de SIM. Es donde se define el precio por día para SIM física (simple o variación). 
            <br><br>
            <strong>Lógica de selección de productos según países:</strong>
            <ul>
                <li><strong>Si se busca un solo país:</strong> el sistema devuelve automáticamente el producto de menor precio disponible para ese país.</li>
                <li><strong>Si se buscan varios países:</strong>
                <ul>
                    <li>El producto debe coincidir con al menos uno de los países seleccionados.</li>
                    <li>Se prioriza el producto que tenga mayor cobertura dentro del conjunto de países buscados.</li>
                    <li>Si hay empate en cobertura, gana el producto de menor precio.</li>
                    <li>Si aún hay empate, el sistema devuelve el primero.</li>
                </ul>
                </li>
            </ul>
            <br>
            Esta lógica permite que el cliente siempre vea las mejores opciones disponibles y el sistema seleccione automáticamente el producto más adecuado según los países seleccionados y el precio por día.
        </li>

    </ul>

    <h2>Configuración de WooCommerce para SIM física</h2>
    <p>
        Para que el plugin obtenga el precio de envío de la SIM física debes:
    </p>
    <ol>
        <li>Ir a <strong>WooCommerce → Ajustes → Envío</strong>.</li>
        <li>Crear o editar la zona de envío correspondiente.</li>
        <li>Añadir método: <strong>Tarifa plana (Flat Rate)</strong>.</li>
        <li>Editar el método y colocar como <strong>Nombre</strong>: <code>SIM</code> y configurar el costo.</li>
        <li>Guardar cambios.</li>
    </ol>
    <p class="note">El plugin solo toma el costo de la SIM física. La eSIM no genera costo ni envío.</p>

    <h2>Uso del cotizador — Flujo paso a paso</h2>
    <p>El cotizador tiene 4 pasos para el cliente:</p>

    <ol class="usaalo-steps">
        <li>
            <strong>Paso 1 — Países, Marcas y Modelos</strong>
            <p>Selecciona uno o varios países. Luego, selecciona la marca y modelo del dispositivo. El sistema filtra los servicios habilitados según la compatibilidad configurada.</p>
        </li>
        <li>
            <strong>Paso 2 — Tipo de SIM y Servicios</strong>
            <p>Selecciona SIM física o eSIM. Selecciona los servicios (datos, llamadas, SMS) que estén habilitados según la configuración. Los servicios opacos no están disponibles.</p>
        </li>
        <li>
            <strong>Paso 3 — Fechas</strong>
            <p>Ingresa la fecha de inicio y el número de días. La fecha final se calcula automáticamente y se muestra, no puede modificarse manualmente.</p>
        </li>
        <li>
            <strong>Paso 4 — Resumen</strong>
            <p>Se muestra un resumen con:</p>
            <ul>
            <li>Pais(es) seleccionados</li>
            <li>SIM elegida</li>
            <li>Servicios elegidos</li>
            <li>Marca y Modelo</li>
            <li>Fechas: <code>fecha_inicio - fecha_final</code> (Número de días)</li>
            <li>Precio total</li>
            </ul>
            <p>Botones: <strong>Atrás</strong> y <strong>Confirmar y continuar al pago</strong>.</p>
        </li>
    </ol>

    <h2>Ejemplo práctico de flujo</h2>
    <p>
        Cliente selecciona España y México, Marca X, Modelo Y → el sistema habilita SIM física y eSIM según compatibilidad → selecciona servicios disponibles → ingresa fecha inicio y número de días → visualiza resumen y precio total → confirma para ir al checkout de WooCommerce.
    </p>
  </section>

  <!-- ==========================
       Manual Técnico
       ========================== -->
  <section id="tecnico" class="usaalo-tab">
    <h2>Tablas principales y relaciones</h2>
    <ul>
      <li><code>usaalo_countries</code>: id, code, name, region</li>
      <li><code>usaalo_brands</code>: id, name, slug</li>
      <li><code>usaalo_models</code>: id, brand_id, name, slug</li>
      <li><code>usaalo_device_config</code>: model_id, sim_supported, esim_supported, voice_supported, sms_supported, data_supported</li>
      <li><code>usaalo_device_country</code>: model_id, country_id, sim_supported, esim_supported, voice_supported, sms_supported, data_supported</li>
      <li><code>usaalo_product_country</code>: product_id, country_id</li>
    </ul>

    <h3>Relaciones importantes</h3>
    <ul>
      <li>Model ↔ Brand: <code>model.brand_id → brands.id</code></li>
      <li>Configuración global por modelo: <code>usaalo_device_config</code></li>
      <li>Overrides por país: <code>usaalo_device_country</code></li>
      <li>Producto ↔ País: <code>usaalo_product_country</code> determina disponibilidad</li>
    </ul>

    <h2>Funciones PHP clave</h2>
    <ul>
      <li><code>USAALO_Helpers::get_all_services()</code>: devuelve todos los servicios.</li>
      <li><code>USAALO_Helpers::servicios_disponibles_por_countries($countries, $model_id)</code>: devuelve servicios por país y modelo.</li>
      <li><code>USAALO_Helpers::get_productos_por_country($country_codes)</code>: devuelve productos habilitados por país.</li>
      <li><code>USAALO_Helpers::calcular_precio_plan($plan_ids, $dias, $servicios, $sim_fisica)</code>: calcula precio total por días y SIM física.</li>
    </ul>
    <h2>Checkout y WooCommerce - Campos del formulario y datos enviados</h2>
    <p>El plugin USAALO cotizador inyecta un conjunto de campos adicionales en el checkout de WooCommerce bajo la sección <strong>Datos del Viajero - USAALO</strong>. Estos campos permiten capturar información necesaria para procesar la cotización y enviar los datos al servidor .NET.</p>

    <h3>Campos inyectados en el formulario de WooCommerce</h3>
    <ul>
    <li><strong>tipo_id:</strong> Tipo de documento (Cédula, Cédula de Extranjería, Tarjeta de Identidad, Pasaporte, NIT). Campo obligatorio.</li>
    <li><strong>documento_id:</strong> Número del documento correspondiente al tipo seleccionado. Campo obligatorio.</li>
    <li><strong>whatsapp:</strong> Número de WhatsApp del viajero. Campo obligatorio.</li>
    <li><strong>motivo_viaje:</strong> Motivo del viaje (Turismo, Trabajo, Estudio, Otros). Campo obligatorio.</li>
    <li><strong>imei:</strong> Número IMEI del dispositivo. Campo obligatorio.</li>
    <li><strong>eid:</strong> Número EID, solo aplicable para eSIM. Se muestra u oculta según el tipo de SIM seleccionado mediante JS.</li>
    <li><strong>en_crucero:</strong> Checkbox que indica si el viajero estará en crucero.</li>
    <li><strong>llamada_entrante:</strong> Selección para la llamada entrante en Colombia (solo si el plan incluye VOZ). Se oculta mediante JS si no aplica.</li>
    <li><strong>es_agencia:</strong> Indica si la compra es realizada por una agencia.</li>
    <li><strong>nombre_agencia:</strong> Nombre de la agencia (si aplica).</li>
    <li><strong>asesor_comercial:</strong> Nombre del asesor comercial que gestiona la venta.</li>
    <li><strong>puntos_colombia:</strong> Número de socio Puntos Colombia (solo si NO es agencia).</li>
    <li><strong>responsabilidad:</strong> Checkbox obligatorio que confirma que el usuario es responsable del uso del servicio.</li>
    <li><strong>terminos:</strong> Checkbox obligatorio para aceptar los términos y condiciones.</li>
    <li><strong>cookies:</strong> Checkbox obligatorio para aceptar el uso de cookies.</li>
    <li><strong>activacion_unica:</strong> Checkbox obligatorio que indica aceptación de activación única por dispositivo.</li>
    <li><strong>acepto_dispositivo:</strong> Checkbox obligatorio para aceptar el uso del dispositivo para el servicio.</li>
    </ul>

    <h3>Datos que se muestran en el carrito / resumen del pedido (woocommerce_get_item_data)</h3>
    <p>Además, el plugin agrega datos específicos del cotizador a cada producto en el carrito de WooCommerce para que el usuario vea un resumen detallado antes de realizar el pedido:</p>

    <ul>
    <li><strong>Países:</strong> Lista de países seleccionados.</li>
    <li><strong>Marca:</strong> Marca del dispositivo o plan seleccionado.</li>
    <li><strong>Modelo:</strong> Modelo del dispositivo o plan seleccionado.</li>
    <li><strong>Servicios:</strong> Servicios habilitados según la selección (datos, llamadas, SMS, SIM, eSIM).</li>
    <li><strong>Días:</strong> Número de días que cubre la cotización.</li>
    <li><strong>SIM:</strong> Tipo de SIM seleccionada (física o eSIM).</li>
    <li><strong>Inicio:</strong> Fecha de inicio del servicio.</li>
    <li><strong>Fin:</strong> Fecha final del servicio, calculada automáticamente según los días seleccionados.</li>
    </ul>

    <h3>Order meta y envío de datos al servidor .NET</h3>
    <p>Cuando el usuario completa el checkout, estos datos del formulario y del carrito se validan y se agregan como <strong>order meta</strong> del pedido:</p>

    <ul>
    <li>countries</li>
    <li>sim_type</li>
    <li>services</li>
    <li>start_date / end_date / days</li>
    <li>plan_ids</li>
    <li>total</li>
    </ul>

    <p>Los datos se envían al servidor .NET organizados por país, y se repiten según la configuración de SIM y servicios seleccionados. Esto asegura que el sistema externo reciba toda la información necesaria para procesar correctamente la cotización.</p>

    <h2>Integración y adaptación al servidor .NET</h2>

<p>El plugin realiza un mapeo entre la estructura flexible del cotizador en WordPress y la estructura rígida que espera el servidor .NET. A continuación se detalla la adaptación aplicada.</p>

<h3>Servicios</h3>
<p>En .NET:</p>
<ul>
  <li><strong>VOZ Y DATOS</strong> → ID = 1</li>
  <li><strong>DATOS</strong> → ID = 2</li>
  <li>No existe SMS como servicio independiente</li>
</ul>

<p>En Cotizador WordPress:</p>
<ul>
  <li><strong>DATOS</strong></li>
  <li><strong>VOZ</strong></li>
  <li><strong>SMS</strong> (más flexible y abierto, se pueden combinar y ampliar en el futuro)</li>
</ul>

<p><strong>Adaptación aplicada:</strong></p>
<ul>
  <li>VOZ → se envía como VOZ Y DATOS (ID = 1)</li>
  <li>SMS → se envía como VOZ Y DATOS (ID = 1)</li>
  <li>DATOS → se envía como DATOS (ID = 2)</li>
</ul>

<h3>Planes</h3>
<p>En .NET:</p>
<ul>
  <li>Planes definidos con IDs fijos y condiciones especiales:</li>
  <li>Plan 1 → Estados Unidos → ID = 1</li>
  <li>Plan 2 → Estados Unidos → ID = 2</li>
  <li>Plan 3 → Estados Unidos + México + Canadá → ID = 3</li>
  <li>Plan 4 (Resto del Mundo con capacidades de internet):</li>
  <ul>
    <li>500 MB → ID = 5</li>
    <li>1 GB → ID = 6</li>
    <li>2 GB → ID = 7</li>
    <li>12 GB Reino Unido → ID = 8</li>
    <li>40 GB España → ID = 9</li>
  </ul>
</ul>

<p><strong>Limitaciones en .NET:</strong> planes cerrados y predeterminados, dependen de la capacidad de datos, país, cantidad de días, y tipo de SIM (física o eSIM).</p>

<p>En Cotizador WordPress:</p>
<ul>
  <li>No existen restricciones por capacidad de internet (MB/GB).</li>
  <li>No existen limitaciones de días o condiciones según SIM/eSIM.</li>
  <li>Los planes son abiertos y dinámicos; se pueden crear con cualquier combinación de países.</li>
</ul>

<p><strong>Adaptación aplicada:</strong></p>
<ul>
  <li><strong>Estados Unidos:</strong> si el cliente elige solo EE.UU. → se asigna Plan 1 o 2.</li>
  <li><strong>México y Canadá:</strong> si el cliente elige México o Canadá (solos o combinados con EE.UU.) → se asigna Plan 3.</li>
  <li><strong>Reino Unido:</strong> se asigna Plan 4 con ID = 8.</li>
  <li><strong>España:</strong> se asigna Plan 4 con ID = 9.</li>
  <li><strong>Otros países:</strong></li>
  <ul>
    <li>1 país → Plan 4 con ID = 5 (500 MB)</li>
    <li>2 países → Plan 4 con ID = 6 (1 GB)</li>
    <li>3 o más países → Plan 4 con ID = 7 (2 GB)</li>
  </ul>
</ul>

<h3>Fechas y días</h3>
<p>En el envío de datos al servidor .NET, <strong>solo se envían las fechas de inicio y fin</strong> (start_date y end_date). El campo "días" se calcula localmente en el cotizador, pero no se envía al servidor.</p>


    <p><strong>Nota técnica:</strong> Si la plantilla del tema sobrescribe el checkout y no utiliza los hooks nativos de WooCommerce, los campos inyectados por el plugin no se mostrarán. Para que funcionen correctamente, la plantilla debe usar hooks nativos o adaptarse para incluir estos campos.</p>


  </section>

  <footer class="usaalo-footer">
    <p>Manual creado para administradores y desarrolladores del plugin. Incluye instrucciones de uso, configuración y recomendaciones técnicas.</p>
  </footer>
</div>

<!-- ===========================
     Estilos Admin Modernos
=========================== -->
<style>
.usaalo-manual-wrap { font-family: system-ui,-apple-system,"Segoe UI",Roboto,Arial; color:#111827; max-width:1100px; margin:16px auto; }
.usaalo-manual-wrap h1 { font-size:22px; color:#0b5ea8; margin-bottom:12px; }
.usaalo-tabs { margin:12px 0 18px; }
.tab-btn { background:#f3f4f6; border:1px solid #e6e9ee; padding:8px 14px; border-radius:6px 6px 0 0; margin-right:8px; cursor:pointer; font-weight:600; }
.tab-btn.active { background:#0b5ea8; color:#fff; border-color:#0b5ea8; }
.usaalo-tab { display:none; background:#fff; border:1px solid #e6e9ee; padding:18px; border-top:none; border-radius:0 6px 6px 6px; box-shadow:0 6px 20px rgba(2,6,23,0.06); }
.usaalo-tab.active { display:block; }
.usaalo-card { background:#f8fafc; border:1px solid #eef2f6; padding:12px; border-radius:8px; margin:10px 0; }
.usaalo-steps li { margin:12px 0; }
.note { background:#fff8e5; border-left:4px solid #f59e0b; padding:10px; margin-top:8px; border-radius:4px; }
pre { background:#f3f4f6; padding:12px; border-radius:6px; overflow:auto; }
.usaalo-footer { margin-top:18px; color:#475569; font-size:14px; }
</style>

<!-- ===========================
     JS mínimo para tabs
=========================== -->
<script>
(function(){
  document.addEventListener('DOMContentLoaded', function(){
    var tabs = document.querySelectorAll('.tab-btn');
    tabs.forEach(function(btn){
      btn.addEventListener('click', function(){
        tabs.forEach(b=>b.classList.remove('active'));
        document.querySelectorAll('.usaalo-tab').forEach(t=>t.classList.remove('active'));
        btn.classList.add('active');
        var target = document.getElementById(btn.getAttribute('data-tab'));
        if(target) target.classList.add('active');
      });
    });
  });
})();
</script>
