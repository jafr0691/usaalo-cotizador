<?php if (!defined('ABSPATH')) exit; ?>
<div id="usaalo-cotizador-wizard" class="usaalo-wizard">
    <!-- Paso 1: País(es) -->
    <div class="usaalo-step-panel" data-step="1">
        <h3>Selecciona País(es)</h3>
        <select id="wizard-country" multiple="multiple" style="width:100%">
            <?php
            global $wpdb;
            $countries = $wpdb->get_results("SELECT code2,name FROM {$wpdb->prefix}usaalo_countries WHERE status='enabled'");
            foreach ($countries as $c) {
                echo "<option value='{$c->code2}'>{$c->name}</option>";
            }
            ?>
        </select>
        <div class="usaalo-actions">
            <button type="button" class="usaalo-next">Siguiente</button>
        </div>
    </div>

    <!-- Paso 2: Tipo SIM + Servicios -->
    <div class="usaalo-step-panel" data-step="2" style="display:none">
        <h3>Tipo de SIM y Servicios</h3>
        <label>Tipo de SIM:</label>
        <select id="wizard-sim-type">
            <option value="esim">eSIM (Virtual)</option>
            <option value="physical">SIM Física</option>
        </select>
        <div id="services-container"></div>
        <div class="usaalo-actions">
            <button type="button" class="usaalo-back">Atrás</button>
            <button type="button" class="usaalo-next">Siguiente</button>
        </div>
    </div>

    <!-- Paso 3: Duración, Fechas y Dispositivo -->
    <div class="usaalo-step-panel" data-step="3" style="display:none">
        <h3>Duración y Dispositivo</h3>
        <label>Fecha de inicio:</label>
        <input type="text" id="wizard-start-date" readonly>
        <label>Fecha de fin / Días:</label>
        <input type="text" id="wizard-end-date" readonly>
        <label>Marca:</label>
        <select id="wizard-brand" style="width:100%"></select>
        <label>Modelo:</label>
        <select id="wizard-model" style="width:100%"></select>
        <div class="usaalo-actions">
            <button type="button" class="usaalo-back">Atrás</button>
            <button type="button" class="usaalo-next">Siguiente</button>
        </div>
    </div>

    <!-- Paso 4: Resumen -->
    <div class="usaalo-step-panel" data-step="4" style="display:none">
        <h3>Resumen</h3>
        <div id="wizard-summary"></div>
        <div class="usaalo-actions">
            <button type="button" class="usaalo-back">Atrás</button>
            <button type="button" id="wizard-confirm">Confirmar y continuar al pago</button>
        </div>
    </div>
</div>
