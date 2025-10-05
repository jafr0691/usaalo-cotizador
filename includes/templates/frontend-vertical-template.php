<?php
    $config = get_option('usaalo_cotizador_config', []);

    $simIcon = '<img id="todos-check-icon" src="' . plugin_dir_url(__FILE__) . '../../assets/img/tarjeta-sim.png" width="18" height="18" style="vertical-align:middle;">';

    $titleStep2 = "Elige tu SIM $simIcon y servicios 🌐 para estar siempre conectado";

    // Si todos los servicios están deshabilitados
    if (($config['show_data'] == 0) && ($config['show_voice'] == 0) && ($config['show_sms'] == 0)) {
        $titleStep2 = "Activa tu viaje con la SIM ideal $simIcon 🌍";
    }
?>

<div id="usaalo-cotizador-wizard" class="usaalo-wizard">

    <!-- Indicadores de pasos -->
    <ul class="usaalo-steps">
        <li class="step-indicator active" data-step="1">
            <?php _e('Conéctate al mundo 🌍 tu viaje comienza aquí ✈️', 'usaalo-cotizador'); ?>
        </li>
        <li class="step-indicator" data-step="2">
            <?php _e($titleStep2, 'usaalo-cotizador'); ?>
        </li>
        <li class="step-indicator" data-step="3">
            <?php _e('Confirma tu plan ✅ y prepárate para viajar sin límites ✈️', 'usaalo-cotizador'); ?>
        </li>
    </ul>

    <form id="usaalo-quote" autocomplete="off">
        <!-- Step 1: País -->
        <div class="step active" id="step-1">
            <!-- País destino -->
            <label><?php _e('¿A dónde te llevará tu próximo viaje? 🌍', 'usaalo-cotizador'); ?></label>
            <select id="country" name="country[]" multiple style="width:100%"></select>

            <!-- Marca del equipo -->
            <label id="label-brand"><?php _e('Elige la marca de tu dispositivo 📱', 'usaalo-cotizador'); ?></label>
            <select id="brand" name="brand" style="width:100%">
                <option value=""><?php _e('Selecciona tu marca', 'usaalo-cotizador'); ?></option>
                <?php foreach($brands as $b): ?>
                    <option value="<?php echo intval($b['id']); ?>"><?php echo esc_html($b['name']); ?></option>
                <?php endforeach; ?>
                <option value="other-brand"><?php _e('Otra marca', 'usaalo-cotizador'); ?></option>
            </select>

            <!-- Modelo del equipo -->
            <label id="label-model"><?php _e('¿Cuál es el modelo de tu dispositivo? 📱', 'usaalo-cotizador'); ?></label>
            <select id="model" name="model" style="width:100%"></select>

            <!-- Fecha inicio -->
            <label><?php _e('¿Cuándo quieres empezar tu plan de datos? 📅', 'usaalo-cotizador'); ?></label>
            <div class="flatpickr" data-wrap="true">
                <input 
                    type="text" 
                    id="SIM_dates" 
                    name="start_date" 
                    class="usaalo-date-range" 
                    placeholder="<?php _e('Llegada - Salida', 'usaalo-cotizador'); ?>" 
                    autocomplete="off" 
                    required
                >
            </div>
        </div>


        <!-- Step 2: SIM & Servicios -->
        <div class="step" id="step-2">
            <div class="usaalo-services-buttons">
                <!-- Aquí se cargan dinámicamente los botones de SIM y servicios -->
            </div>
        </div>

        <!-- Step 3: Resumen -->
        <div class="step" id="step-3" style="margin-left: 50px;">
            <h3><?php _e('Resumen', 'usaalo-cotizador'); ?></h3>
            <div id="usaalo-summary"></div>
            <div id="usaalo-loader" class="usaalo-loader hidden">
                <div class="loader-content">
                    <div class="spinner"></div>
                    <p>Procesando tu cotización...</p>
                </div>
            </div>
        </div>

        <!-- Controles y precio -->
        <div class="usaalo-controls">
            <div class="buttons">
                <button type="button" class="usaalo-back btn hidden"><?php _e('Atrás', 'usaalo-cotizador'); ?></button>
                <button type="button" class="usaalo-next btn"><?php _e('Siguiente', 'usaalo-cotizador'); ?></button>
                <button type="submit" class="usaalo-confirm btn hidden">
                    <?php _e('Confirmar y continuar al pago', 'usaalo-cotizador'); ?>
                </button>
            </div>
            <span class="price-label"><?php _e('Precio Cotizado', 'usaalo-cotizador'); ?>:</span>
            <div id="usaalo-price" class="price-total">0,00 $</div>
        </div>
    </form>
</div>
