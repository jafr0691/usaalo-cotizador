<div id="usaalo-cotizador-wizard" class="usaalo-wizard">

            <!-- Indicadores de pasos -->
            <ul class="usaalo-steps">
                <li class="step-indicator active" data-step="1"><?php _e('País & Dispositivo', 'usaalo-cotizador'); ?></li>
                <li class="step-indicator" data-step="2"><?php _e('SIM & Servicios', 'usaalo-cotizador'); ?></li>
                <li class="step-indicator" data-step="3"><?php _e('Fechas', 'usaalo-cotizador'); ?></li>
                <li class="step-indicator" data-step="4"><?php _e('Resumen', 'usaalo-cotizador'); ?></li>
            </ul>

            <form id="usaalo-quote" autocomplete="off">

                <!-- Step 1: País -->
                <div class="step active" id="step-1">
                    <label><?php _e('País(es)', 'usaalo-cotizador'); ?></label>
                    <select id="country" name="country[]" multiple style="width:100%">
                        <?php foreach($countries as $c): ?>
                            <option value="<?php echo esc_attr($c['code2'] ?? $c['code']); ?>"><?php echo esc_html($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label><?php _e('Marca', 'usaalo-cotizador'); ?></label>
                    <select id="brand" name="brand" style="width:100%">
                        <option value=""><?php _e('Selecciona marca','usaalo-cotizador');?></option>
                        <?php foreach($brands as $b): ?>
                            <option value="<?php echo intval($b['id']); ?>"><?php echo esc_html($b['name']); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label><?php _e('Modelo', 'usaalo-cotizador'); ?></label>
                    <select id="model" name="model" style="width:100%"></select>

                    <div id="price-base" class="price-base"></div>
                    <p class="btn-prev-next">
                        <button type="button" class="usaalo-next"><?php _e('Siguiente', 'usaalo-cotizador'); ?></button>
                    </p>
                </div>

                <!-- Step 2: SIM & Servicios -->
                <div class="step" id="step-2">
                    <div class="usaalo-services-buttons">
                        <!-- Aquí se cargan dinámicamente los botones de SIM y servicios -->
                    </div>
                    <p class="btn-prev-next">
                        <button type="button" class="usaalo-back"><?php _e('Atrás', 'usaalo-cotizador'); ?></button>
                        <button type="button" class="usaalo-next"><?php _e('Siguiente', 'usaalo-cotizador'); ?></button>
                    </p>
                </div>

                <!-- Step 3: Fechas & Dispositivo -->
                <div class="step" id="step-3">
                    <label><?php _e('Fecha inicio', 'usaalo-cotizador'); ?></label>
                    <input type="date" id="start_date" name="start_date"  required value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>">

                    <label><?php _e('Número de días', 'usaalo-cotizador'); ?></label>
                    <input type="number" id="num_days" name="num_days" min="1" value="1" required>

                    <label><?php _e('Fecha fin', 'usaalo-cotizador'); ?></label>
                    <input type="date" id="end_date" name="end_date" readonly required>

                    <p class="btn-prev-next">
                        <button type="button" class="usaalo-back"><?php _e('Atrás', 'usaalo-cotizador'); ?></button>
                        <button type="button" class="usaalo-next"><?php _e('Siguiente', 'usaalo-cotizador'); ?></button>
                    </p>
                </div>


                <!-- Step 4: Resumen -->
                <div class="step" id="step-4">
                    <h3><?php _e('Resumen', 'usaalo-cotizador'); ?></h3>
                    <div id="usaalo-summary"></div>

                    <p class="btn-prev-next">
                        <button type="button" class="usaalo-back"><?php _e('Atrás', 'usaalo-cotizador'); ?></button>
                        <button type="submit" class="button button-primary"><?php _e('Confirmar y continuar al pago', 'usaalo-cotizador'); ?></button>
                    </p>
                </div>
                <div class="precie-right">
                    <div id="usaalo-price" class="price-total">0,00$</div>
                </div>
            </form>
        </div>