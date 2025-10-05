<?php
$config = get_option('usaalo_cotizador_config', []);
?>
<div id="usaalo-loader" class="usaalo-loader hidden">
    <div class="loader-content">
        <div class="spinner"></div>
    </div>
</div>
<div id="usaalo-cotizador-horizontal" class="usaalo-horizontal hidden" role="form" style="position:relative;">
    <div id="usaalo-loader-mini" class="mini-loader">Cargando...</div>
    <form id="usaalo-quote-horizontal" class="usaalo-form usaalo-form-horizontal" autocomplete="off" style="display:none;">
        
        <!-- País -->
        <fieldset class="field country-block field-wrapper">
            <legend><?php _e('¿Dondé viajas?', 'usaalo-cotizador'); ?></legend>
            <label for="country" class="sr-only"><?php _e('Selecciona país', 'usaalo-cotizador'); ?></label>
            <div class="input-wrapper">
                <select id="country" name="country[]" multiple class="usaalo-select" aria-describedby="services-help"></select>
                <div id="usaalo-services-inline" class="services" aria-live="polite"></div>
            </div>
        </fieldset>

        <!-- Fechas -->
        <fieldset class="field date-block field-wrapper">
            <legend><?php _e('Fechas', 'usaalo-cotizador'); ?></legend>
            <label for="SIM_dates" class="sr-only">
                <?php _e('Selecciona fechas de tu viaje', 'usaalo-cotizador'); ?>
            </label>
            
            <div class="date-input-wrapper">
                <input 
                    type="text" 
                    id="SIM_dates" 
                    name="SIM_dates" 
                    class="usaalo-date-range" 
                    placeholder="<?php _e('Llegada - Salida', 'usaalo-cotizador'); ?>" 
                    autocomplete="off" 
                    required
                >
                <button type="button" id="openCalendar" class="calendar-btn">
                    <i class="fa-regular fa-calendar"></i>
                </button>
            </div>
        </fieldset>


        <!-- Marca -->
        <?php if (!empty($config['show_brand'])): ?>
        <fieldset class="field brand-block field-wrapper">
            <legend><?php _e('Dispositivo que usarás', 'usaalo-cotizador'); ?></legend>
            <label for="brand" class="sr-only"><?php _e('Selecciona tu marca', 'usaalo-cotizador'); ?></label>
            <div class="input-wrapper">
                <select id="brand" name="brand" class="usaalo-select">
                    <option value=""><?php _e('Selecciona tu marca', 'usaalo-cotizador'); ?></option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?php echo intval($b['id']); ?>"><?php echo esc_html($b['name']); ?></option>
                    <?php endforeach; ?>
                    <option value="other-brand"><?php _e('Otra marca', 'usaalo-cotizador'); ?></option>
                </select>
            </div>
            <!-- Modelo -->
            <?php if (!empty($config['show_model'])): ?>
            <fieldset class="field model-block field-wrapper">
                <legend><?php _e('Modelo', 'usaalo-cotizador'); ?></legend>
                <label for="model" class="sr-only"><?php _e('Selecciona tu modelo', 'usaalo-cotizador'); ?></label>
                <div class="input-wrapper">
                    <select id="model" name="model" class="usaalo-select"></select>
                </div>
            </fieldset>
            <?php endif; ?>
        </fieldset>
        <?php endif; ?>

        

        <!-- SIM & Servicios -->
        <fieldset class="field sim-block field-wrapper">
            <legend><?php _e('Tipo de SimCard', 'usaalo-cotizador'); ?></legend>
            <div class="input-wrapper">
                <div id="usaalo-sim-buttons-horizontal" class="sim-options"></div>
            </div>
        </fieldset>

        <!-- Botón precio / checkout -->
        <div class="checkout-block tooltip">
            <button type="submit" class="usaalo-confirm" aria-live="polite" disabled>
                <span id="usaalo-price-inline">0,00$</span><br>
                <span class="arrow">Pagar</span>
            </button>
            <span class="tooltip-text"> 
                Una vez completo el formulario, haga clic aquí para pagar
            </span>
        </div>

    </form>
</div>
