<?php
if (!defined('ABSPATH')) exit;

class USAC_Frontend {
    public static function register_shortcodes(){
        add_shortcode('usaalo_cotizador', [__CLASS__, 'shortcode']);
    }

    public static function enqueue_assets(){
        // Select2 desde CDN (ligero y estable)
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);

        // Estilos + Wizard JS
        wp_enqueue_style('usac-wizard', USAC_URL.'assets/css/wizard.css', [], USAC_VER);
        wp_enqueue_script('usac-polyfills', USAC_URL.'assets/js/polyfills.js', [], USAC_VER, true);
        wp_enqueue_script('usac-wizard', USAC_URL.'assets/js/wizard.js', ['jquery','select2'], USAC_VER, true);

        wp_localize_script('usac-wizard', 'USAC', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('usac_nonce'),
            'currency' => get_option('usac_currency','USD'),
        ]);
    }

    public static function shortcode(){
        ob_start(); ?>
        <div id="usac-wizard" class="usac-wizard">
          <div class="usac-steps">
            <div class="usac-step is-active" data-step="1">1. País & Dispositivo</div>
            <div class="usac-step" data-step="2">2. SIM & Servicios</div>
            <div class="usac-step" data-step="3">3. Duración & Fechas</div>
            <div class="usac-step" data-step="4">4. Resumen</div>
          </div>

          <div class="usac-panels">
            <!-- Paso 1 -->
            <section class="usac-panel" data-step="1">
              <label>País(es) destino</label>
              <select id="usac-countries" multiple style="width:100%"></select>

              <div class="usac-columns">
                <div>
                  <label>Marca</label>
                  <select id="usac-brand" style="width:100%"></select>
                </div>
                <div>
                  <label>Modelo</label>
                  <select id="usac-model" style="width:100%"></select>
                </div>
              </div>
              <div id="usac-compat-state" class="usac-note"></div>
              <div class="usac-actions">
                <button class="button button-primary usac-next" data-next="2" disabled>Continuar</button>
              </div>
            </section>

            <!-- Paso 2 -->
            <section class="usac-panel" data-step="2" hidden>
              <label>Tipo de SIM</label>
              <div class="usac-row">
                <label><input type="radio" name="usac-simtype" value="esim"> eSIM (virtual)</label>
                <label><input type="radio" name="usac-simtype" value="physical"> SIM física</label>
              </div>

              <div id="usac-esim-fields" class="usac-box" hidden>
                <input type="text" id="usac-eid" placeholder="EID">
                <input type="text" id="usac-imei-esim" placeholder="IMEI (opcional)">
                <small>¿Dónde encuentro mi EID? (guía)</small>
              </div>

              <div id="usac-physical-fields" class="usac-box" hidden>
                <input type="text" id="usac-imei-phy" placeholder="IMEI (obligatorio)">
                <small>La SIM física se envía a domicilio y tiene costo de envío.</small>
              </div>

              <fieldset class="usac-box">
                <legend>Servicios</legend>
                <label><input type="checkbox" id="svc-data" checked disabled> Datos</label>
                <label><input type="checkbox" id="svc-voice"> Voz</label>
                <label><input type="checkbox" id="svc-sms"> SMS</label>
                <div id="usac-inbound-col" hidden>
                  <label>¿Llamadas entrantes desde Colombia?
                    <select id="usac-inbound-colombia"><option value="no">No</option><option value="yes">Sí</option></select>
                  </label>
                </div>
              </fieldset>
              <div class="usac-actions">
                <button class="button usac-prev" data-prev="1">Atrás</button>
                <button class="button button-primary usac-next" data-next="3" disabled>Siguiente</button>
              </div>
            </section>

            <!-- Paso 3 -->
            <section class="usac-panel" data-step="3" hidden>
              <div class="usac-columns">
                <label>Fecha inicio <input type="date" id="usac-start"></label>
                <label>Días <input type="number" id="usac-days" min="1" value="7"></label>
                <label>Fecha fin <input type="date" id="usac-end"></label>
              </div>
              <div id="usac-activation" class="usac-note"></div>

              <div id="usac-quote" class="usac-total"></div>

              <div class="usac-actions">
                <button class="button usac-prev" data-prev="2">Atrás</button>
                <button class="button button-primary usac-next" data-next="4" disabled>Siguiente</button>
              </div>
            </section>

            <!-- Paso 4 -->
            <section class="usac-panel" data-step="4" hidden>
              <h3>Resumen</h3>
              <div id="usac-summary"></div>
              <div class="usac-actions">
                <button class="button usac-prev" data-prev="3">Editar</button>
                <button class="button button-primary" id="usac-to-cart">Confirmar y continuar al pago</button>
              </div>
            </section>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
