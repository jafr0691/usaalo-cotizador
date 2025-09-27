jQuery(document).ready(function($){
    'use strict';

    /* =========================
       Config / Estado
       ========================= */
    const TOTAL_STEPS = 3;
    let currentStep = 1;

    // Short helpers
    const $wizard = $('#usaalo-cotizador-wizard');
    const $summary = $('#usaalo-summary');
    const $price = $('#usaalo-price');
    const $servicesContainer = $('.usaalo-services-buttons');
    const $loader = $('#usaalo-loader');

    // Defensive globals
    const CONFIG = window.USAALO_Frontend && USAALO_Frontend.Config ? USAALO_Frontend.Config : {};
    const PRODUCTS_OBJ = window.USAALO_Frontend && USAALO_Frontend.products ? USAALO_Frontend.products : {};
    const ALL_MODELS = window.USAALO_Frontend && USAALO_Frontend.allModels ? USAALO_Frontend.allModels : {};
    const TYPE_SERVICES = window.USAALO_Frontend && USAALO_Frontend.TypeServices ? USAALO_Frontend.TypeServices : {};
    const SIM_PRICES = window.USAALO_Frontend && USAALO_Frontend.simPrices ? USAALO_Frontend.simPrices : {};
    const SHIPPING_COST = parseFloat(window.USAALO_Frontend && USAALO_Frontend.shipping_cost ? USAALO_Frontend.shipping_cost : 0);
    const CURRENCY = window.USAALO_Frontend && USAALO_Frontend.currency_symbol ? USAALO_Frontend.currency_symbol : '';

    /* =========================
       Helpers generales
       ========================= */
    function showMessage(msg, type = 'info') {
        let $msg = $('#usaalo-message');
        if (!$msg.length) {
            $wizard.prepend('<div id="usaalo-message"></div>');
            $msg = $('#usaalo-message');
        }
        $msg.stop(true, true)
            .html(msg)
            .attr('class', type)
            .fadeIn(200).delay(3500).fadeOut(300);
    }

    const getFlagURL = code => code ? `https://flagcdn.com/${String(code).toLowerCase()}.svg` : '';

    // Normaliza valor del select country siempre a array de strings (códigos)
    function getSelectedCountries() {
        let v = $('#country').val() || [];
        if (!Array.isArray(v)) {
            // si viene vacío string -> []
            if (v === null || v === '') return [];
            v = [v];
        }
        // limpiar y normalizar mayúsculas
        return v.map(c => String(c).toUpperCase()).filter(Boolean);
    }

    // Normaliza array/obj/etc a array seguro
    function toArray(x) {
        if (!x && x !== 0) return [];
        if (Array.isArray(x)) return x;
        if (typeof x === 'object') return Object.values(x);
        return [x];
    }

    /* =========================
       Select2 - Carga de países
       ========================= */
    function formatCountry(state){
        if(!state.id) return state.text;
        const flag = $(state.element).data('flag') || getFlagURL(state.id);
        const enabled = $(state.element).data('enabled') ?? true;
        const disabledClass = enabled ? '' : 'country-disabled';
        return $(
            `<span class="${disabledClass}">
                <img src="${flag}" class="country-flag" /> ${state.text}${enabled ? '' : ' (Próximamente)'}
            </span>`
        );
    }
    function loadCountries() {
        const $country = $('#country').empty();
        const raw = window.USAALO_Frontend && USAALO_Frontend.allCountries ? USAALO_Frontend.allCountries : [];
        const list = toArray(raw);

        // Agregamos un <option> vacío solo para permitir el placeholder (no es seleccionable)
        $country.append($('<option/>').val(''));

        // Resto de países
        list.forEach(c => {
            const code = c.code || c.country || c.id || '';
            const opt = $('<option/>')
                .val(code)
                .text(c.name || c.label || code)
                .attr('data-flag', getFlagURL(code))
                .attr('data-enabled', !!c.disponible);
            if (!c.disponible) {
                opt.prop('disabled', true).on('mousedown', e => {
                    e.preventDefault();
                    showMessage((c.name || code) + ' estará disponible próximamente.', 'info');
                });
            }
            $country.append(opt);
        });

        // select multiple o simple según config
        if (CONFIG.select_pais == 0) {
            $country.removeAttr('multiple');
        } else {
            $country.attr('multiple', 'multiple');
        }

        // initialize select2 con placeholder
        if ($country.hasClass('select2-hidden-accessible')) $country.select2('destroy');
        $country.select2({
            width: '100%',
            placeholder: '🌎 Elige tu país para empezar', // <<--- AQUÍ
            templateResult: formatCountry,
            templateSelection: formatCountry,
            escapeMarkup: m => m,
            language: 'es',
            allowClear: true // permite limpiar si es necesario
        });
    }

    loadCountries();



    /* =========================
    Marca / Modelo
    ========================= */
    function initBrandModel() {
        if ($('#brand').hasClass('select2-hidden-accessible')) $('#brand').select2('destroy');
        if ($('#model').hasClass('select2-hidden-accessible')) $('#model').select2('destroy');

        $('#brand').select2({ width: '100%', placeholder: 'Selecciona una marca' });
        $('#model').select2({ width: '100%', placeholder: 'Selecciona un modelo' });

        // Ocultar / forzar "otro" si corresponde
        function handleVisibility($field, $label, configKey, defaultValue, defaultLabel) {
            if (CONFIG[configKey] == 0) {
                if ($field.hasClass('select2-hidden-accessible')) $field.select2('destroy');
                $field.closest('.form-group, .field').hide();
                $label.hide();
                $field.hide();
                if ($field.find(`option[value="${defaultValue}"]`).length === 0) {
                    $field.append(`<option value="${defaultValue}">${defaultLabel}</option>`);
                }
                $field.val(defaultValue).trigger('change').data('locked', true);
            }
        }
        handleVisibility($('#brand'), $('#label-brand'), 'show_brand', 'other-brand', 'Otra marca');
        handleVisibility($('#model'), $('#label-model'), 'show_model', 'other-model', 'Otro modelo');
    }
    initBrandModel();

    function filterModels(brandId) {
        const $model = $('#model');
        if ($model.data('locked')) return;

        if (!brandId || !ALL_MODELS[brandId]) {
            $model.html('<option value="">Selecciona un modelo</option>').prop('disabled', true).trigger('change');
            renderServiceButtons();
            return;
        }

        let options = '<option value="">Selecciona un modelo</option>';
        toArray(ALL_MODELS[brandId]).forEach(m => {
            options += `<option value="${m.id}" data-name="${m.name}">${m.name}</option>`;
        });
        options += `<option value="other-model">Otro modelo</option>`;

        $model.html(options).prop('disabled', false).trigger('change');
        renderServiceButtons();
    }

    $('#brand').on('change', function(){ filterModels($(this).val()); });

    /* =========================
       Flatpickr fechas
       ========================= */
    if (typeof flatpickr === 'function') {
        flatpickr(".flatpickr", {
            wrap: true,
            minDate: "today",
            altInput: true,
            altFormat: "d / m / Y",
            dateFormat: "Y-m-d",
            defaultDate: "today",
            disableMobile: false
        });
    }

    // function validateDates() {
    //     const start = $('#start_date').val();
    //     if (!start) return false;
    //     const today = new Date(); today.setHours(0,0,0,0);
    //     const sel = new Date(start); sel.setHours(0,0,0,0);
    //     if (sel < today) {
    //         showMessage('La fecha de inicio no puede ser anterior a hoy', 'error');
    //         $('#start_date').val(new Date().toISOString().split('T')[0]);
    //         updateEndDate();
    //         return false;
    //     }
    //     return true;
    // }

    function validateDates() {
        const startDateInput = document.getElementById('start_date');

        // Obtener hoy y restarle 1 día
        const today = new Date();
        today.setDate(today.getDate() - 1);
        today.setHours(0, 0, 0, 0); // ignorar hora

        const selectedDate = new Date(startDateInput.value);
        selectedDate.setHours(0, 0, 0, 0); // ignorar hora también

        if (selectedDate < today) {
            showMessage('La fecha de inicio no puede ser anterior a hoy', 'error');
            // reset a hoy
            startDateInput.value = new Date().toISOString().split('T')[0];
            updateEndDate(); // recalcular fecha final
            return false;
        }

        return true;
    }

    // function updateEndDate(){
    //     const start = $('#start_date').val();
    //     const numDays = parseInt($('#num_days').val()) || 1;
    //     if (!start) return;
    //     const s = new Date(start);
    //     // Si quieres que duración N incluya el día inicial, usar + (numDays-1). Aquí uso sin +1.
    //     const end = new Date(s.getTime() + (numDays - 1) * 86400000);
    //     $('#end_date').val(end.toISOString().split('T')[0]);
    // }

    function updateEndDate() {
        if(document.getElementById('start_date')){
            let startDate = document.getElementById('start_date').value;
            let numDays = parseInt(document.getElementById('num_days').value);

            if (startDate && numDays > 0) {
                let start = new Date(startDate);
                let end = new Date(start.getTime() + (numDays + 1) * 24 * 60 * 60 * 1000); // restamos 1 porque el primer día ya cuenta
                let yyyy = end.getFullYear();
                let mm = String(end.getMonth() + 1).padStart(2, '0');
                let dd = String(end.getDate()).padStart(2, '0');
                document.getElementById('end_date').value = `${yyyy}-${mm}-${dd}`;
            }
        }
    }
    updateEndDate();
    $('#start_date, #num_days').on('change input', updateEndDate);

    /* =========================
       getPrices (DP + Greedy) con soporte other-brand/model
       ========================= */
    function getPrices(countryCodes, dias, opts = {}) {
        // Normalizar países
        const codesArray = Array.isArray(countryCodes) ? countryCodes : (countryCodes ? [countryCodes] : []);
        const uniqueCodes = Array.from(new Set(codesArray.map(c => String(c).toUpperCase()).filter(Boolean)));
        if (!uniqueCodes.length) return { total_price: 0, products: [] };

        const greedyThreshold = opts.greedyThreshold ?? 20;
        const maxCountriesForMask = opts.maxCountriesForMask ?? 30;
        if (uniqueCodes.length > maxCountriesForMask) {
            console.warn('Many countries selected — using greedy fallback due to bitmask limits.');
        }

        const daysQty = Math.max(1, parseInt(dias) || 1);

        // Preparar productos candidatos según países
        const products = Object.values(PRODUCTS_OBJ || []);
        const codeIndex = {};
        uniqueCodes.forEach((c, i) => codeIndex[c] = i);
        const fullMask = uniqueCodes.length <= maxCountriesForMask ? ((1 << uniqueCodes.length) - 1) : null;

        const candidates = [];

        for (const p of products) {
            if (!p) continue;

            // Normalizar p.countries a array seguro
            let pCountries = [];
            if (Array.isArray(p.countries)) pCountries = p.countries;
            else if (typeof p.countries === 'string' && p.countries.trim()) pCountries = [p.countries];
            else if (typeof p.countries === 'object' && p.countries !== null) pCountries = Object.values(p.countries);

            // Filtrar matches con uniqueCodes
            const matches = pCountries.map(c => {
                const code = (c && (c.code || c)).toString().toUpperCase();
                return { code, raw: c };
            }).filter(m => uniqueCodes.includes(m.code));

            if (!matches.length) continue;

            // calcular pricePerDay
            let pricePerDay = parseFloat(p.base_price || 0);
            if (p.type === 'variable' && Array.isArray(p.ranges) && p.ranges.length) {
                const found = p.ranges.find(r => Number(dias) >= Number(r.min) && Number(dias) <= Number(r.max));
                if (found) pricePerDay = parseFloat(found.price);
                else if (typeof p.min_price !== 'undefined') pricePerDay = parseFloat(p.min_price);
            }

            const totalCost = pricePerDay * daysQty;

            // máscara
            let pmask = 0;
            if (fullMask !== null) {
                for (const m of matches) {
                    const idx = codeIndex[m.code];
                    if (typeof idx === 'number') pmask |= (1 << idx);
                }
            }

            candidates.push({ product: p, matches, pmask, pricePerDay, totalCost });
        }

        if (!candidates.length) {
            // fallback: usar SIM genérica si no hay coincidencias
            const simKey = Object.keys(SIM_PRICES)[0] ?? 'default';
            const simPricePerDay = parseFloat(SIM_PRICES[simKey] ?? SIM_PRICES.default ?? 0) || 0;
            const total = simPricePerDay * daysQty;
            return {
                total_price: Math.round(total * 100) / 100,
                products: [{
                    product_id: null,
                    name: 'SIM Genérica',
                    price: Math.round(total * 100) / 100,
                    price_per_day: Math.round(simPricePerDay * 100) / 100,
                    countries: uniqueCodes
                }]
            };
        }

        // Reducir candidates si son demasiados
        let pool = candidates.slice();
        if (pool.length > greedyThreshold || fullMask === null) {
            pool.sort((a,b) => (b.matches.length - a.matches.length) || (a.pricePerDay - b.pricePerDay));
            pool = pool.slice(0, greedyThreshold);
        }

        // DP óptimo si tenemos máscara válida
        if (fullMask !== null) {
            const nMask = 1 << uniqueCodes.length;
            const dp = new Array(nMask).fill(null);
            dp[0] = { cost: 0, count: 0, items: [] };

            for (let i = 0; i < pool.length; i++) {
                const c = pool[i];
                const snapshot = dp.slice();
                for (let mask = 0; mask < nMask; mask++) {
                    if (!snapshot[mask]) continue;
                    const newmask = mask | c.pmask;
                    const newCost = snapshot[mask].cost + c.totalCost;
                    const newCount = snapshot[mask].count + 1;
                    const prev = dp[newmask];
                    if (!prev || newCost < prev.cost || (newCost === prev.cost && newCount < prev.count)) {
                        dp[newmask] = { cost: newCost, count: newCount, items: snapshot[mask].items.concat(i) };
                    }
                }
            }

            const result = dp[fullMask];
            if (result) {
                const selected = result.items.map(i => pool[i]);
                const out = selected.map(s => ({
                    product_id: s.product.product_id ?? s.product.id ?? null,
                    name: s.product.name ?? s.product.title ?? 'Producto',
                    price: Math.round(s.totalCost * 100) / 100,
                    price_per_day: Math.round(s.pricePerDay * 100) / 100,
                    countries: s.matches.map(m => m.code)
                }));
                return { total_price: Math.round(result.cost * 100) / 100, products: out };
            }
        }

        // Greedy fallback
        const neededSet = new Set(uniqueCodes);
        const chosen = [];
        let total = 0;
        let candidatesPool = pool.slice();

        while (neededSet.size > 0) {
            candidatesPool.forEach(c => {
                c.currentMatches = c.matches.map(m => m.code).filter(cd => neededSet.has(cd));
                c.currentCoverage = c.currentMatches.length;
            });
            const possible = candidatesPool.filter(c => c.currentCoverage > 0);
            if (!possible.length) break;
            possible.sort((a,b) => (b.currentCoverage - a.currentCoverage) || (a.totalCost - b.totalCost));
            const sel = possible[0];
            chosen.push(sel);
            total += sel.totalCost;
            sel.currentMatches.forEach(cd => neededSet.delete(cd));
            candidatesPool = candidatesPool.filter(c => c !== sel);
        }

        const output = chosen.map(s => ({
            product_id: s.product.product_id ?? s.product.id ?? null,
            name: s.product.name ?? s.product.title ?? 'Producto',
            price: Math.round(s.totalCost * 100) / 100,
            price_per_day: Math.round(s.pricePerDay * 100) / 100,
            countries: s.matches.map(m => m.code)
        }));

        return { total_price: Math.round(total * 100) / 100, products: output };
    }

    /* =========================
       renderServiceButtons
       ========================= */
    function renderServiceButtons() {
        let countries = getSelectedCountries(); // array normalizado
        let modelId = $('#model').val() || null;

        if (!countries.length || !modelId) {
            $servicesContainer.html('<p>No hay servicios disponibles.</p>');
            return;
        }

        $servicesContainer.html('<p>Cargando servicios...</p>');

        let data = null;
        if (String(modelId) === 'other-model') {
            data = {};
            countries.forEach(c => {
                data[c] = { services: [] };
                if (CONFIG.show_sim  != 0) data[c].services.push('sim');
                if (CONFIG.show_esim != 0) data[c].services.push('esim');
                if (CONFIG.show_data != 0) data[c].services.push('datos');
                if (CONFIG.show_voice != 0) data[c].services.push('llamadas');
                if (CONFIG.show_sms  != 0) data[c].services.push('sms');
            });
        } else {
            data = TYPE_SERVICES[modelId] || null;
        }

        if (!data) {
            $servicesContainer.html('<p>No hay servicios disponibles.</p>');
            return;
        }

        const grouped = { sim: [], esim: [], datos: [], llamadas: [], sms: [] };
        countries.forEach(c => {
            if (!data[c] || !Array.isArray(data[c].services)) return;
            data[c].services.forEach(s => {
                if (grouped[s]) grouped[s].push(c);
            });
        });

        let html = '';

        // ----- SIM -----
        if (CONFIG.show_sim != 0 || CONFIG.show_esim != 0) {
            html += `<div class="sim-section"><p><strong>${USAALO_Frontend.i18n.sim}</strong></p><div class="service-type" style="display:flex; gap:10px; flex-wrap:wrap;">`;
            ['sim','esim'].forEach(type => {
                if ((type==='sim' && CONFIG.show_sim==0) || (type==='esim' && CONFIG.show_esim==0)) return;
                const activeCountries = grouped[type] || [];
                const icon = (type==='sim') ? '💳' : '📶';
                const label = (type==='sim') ? 'SIM física' : 'eSIM';

                if (activeCountries.length) {
                    const flags = activeCountries.map(c=>`<img class="flag-small" src="https://flagcdn.com/${c.toLowerCase()}.svg" style="width:16px;height:11px;margin:0 2px;">`).join('');
                    html += `<label class="sim-option tooltip" style="display:inline-block; cursor:pointer;font-size: 13px;">
                                <input type="radio" name="sim_type" value="${type}">
                                ${icon} ${label}
                                <span class="tooltip-text"> 
                                    ${type==='sim'?`Envío adicional: ${SHIPPING_COST} ${CURRENCY}<br>`:'(SIM virtual)<br>'}Disponible en: ${flags}
                                </span>
                            </label>`;
                } else {
                    html += `<label class="sim-option disabled tooltip" style="display:inline-block; cursor:not-allowed; opacity:0.5;">
                                <input type="radio" disabled>
                                ${icon} ${label}
                                <span class="tooltip-text">No disponible en los países seleccionados</span>
                            </label>`;
                }
            });
            html += '</div></div>';
        }

        // ----- Servicios adicionales -----
        if (CONFIG.show_data!=0 || CONFIG.show_voice!=0 || CONFIG.show_sms!=0) {
            html += `<div class="services-section"><p><strong>${USAALO_Frontend.i18n.servicio}</strong></p><div class="service-type" style="display:flex; gap:10px; flex-wrap:wrap;">`;
            [
                ['datos','Datos','📡','show_data'],
                ['llamadas','Llamadas','📞','show_voice'],
                ['sms','SMS','✉️','show_sms']
            ].forEach(([key,label,icon,configKey])=>{
                if(CONFIG[configKey]==0) return;
                const activeCountries = grouped[key] || [];
                if(activeCountries.length){
                    const flags = activeCountries.map(c=>`<img class="flag-small" src="https://flagcdn.com/${c.toLowerCase()}.svg" style="width:16px;height:11px;margin:0 2px;">`).join('');
                    html += `<label class="service-option tooltip" style="display:inline-block; cursor:pointer;font-size: 13px;">
                                <input type="checkbox" name="services[]" value="${key}" checked>
                                ${icon} ${label}
                                <span class="tooltip-text">
                                    ${key==='datos'?`Internet 1GB <br>`:''}Disponible en: ${flags}
                                </span>
                            </label>`;
                } else {
                    html += `<label class="service-option disabled tooltip" style="display:inline-block; cursor:not-allowed; opacity:0.5;">
                                <input type="checkbox" disabled>
                                ${icon} ${label}
                                <span class="tooltip-text">No disponible en los países seleccionados</span>
                            </label>`;
                }
            });
            html += '</div></div>';
        }

        $servicesContainer.html(html);

        // Delegated events
        $servicesContainer.find('input[name="sim_type"]').on('change', calculateQuote);
        $servicesContainer.find('input[name="services[]"]').on('change', calculateQuote);
    }

    // Delegación para países/model changes
    $('#country, #model').on('change', function(){ renderServiceButtons(); calculateQuote(); });

    /* =========================
       Cálculo de cotización
       ========================= */
    function calculateQuote() {
        const countries = getSelectedCountries();
        if (!countries.length) {
            $price.text(`0,00 ${CURRENCY}`);
            $summary.empty();
            return;
        }

        if (!validateDates()) return;

        // calcular days usando start/end
        let days = 1;
        const start = $('#start_date').val();
        const end = $('#end_date').val();
        if (start && end) {
            days = Math.max(1, Math.round((new Date(end) - new Date(start)) / 86400000)); // +1 para incluir ambos dias
        } else {
            days = parseInt($('#num_days').val()) || 1;
        }

        // Mostrar loader visual ligero
        // $price.stop(true).fadeOut(100, function(){ $(this).text(`0,00 ${CURRENCY}`).fadeIn(150); });

        const brandVal = $('#brand').val();
        const modelVal = $('#model').val();

        const result = getPrices(countries, days, {}, brandVal, modelVal);
        let total = parseFloat(result.total_price || 0);

        // Coste de envío si SIM física
        const simType = $('input[name="sim_type"]:checked').val();
        if (simType === 'sim' && SHIPPING_COST > 0) total += SHIPPING_COST;

        updateSummary({ data: { total: total.toFixed(2), days: days } });
    }

    /* =========================
       Resumen visual
       ========================= */
    function updateSummary(resp) {
        const data = resp && resp.data ? resp.data : { total: 0, days: 0 };
        // select multiple o simple según config
        let ResumenPais = CONFIG.select_pais == 0 ? '🌎 País:':'🌎 País(es):';
        let html = '<p><strong>'+ResumenPais+'</strong> ';
        $('#country option:selected').each(function(){
            html += `<span class="highlight"><img src="${$(this).data('flag')}" class="country-flag"/> ${$(this).text()}</span> `;
        });
        html += '</p>';
        const simType = $('input[name="sim_type"]:checked').val() || '-';
        let simLabel = '-';

        if(simType === 'sim') simLabel = '💳 SIM física';
        else if(simType === 'esim') simLabel = '📶 eSIM (Virtual)';

        html += `<p><strong>`+USAALO_Frontend.img_chip+` SIM elegida:</strong> <span class="highlight">${simLabel}</span></p>`;

        if (CONFIG.show_data != 0 || CONFIG.show_voice != 0 || CONFIG.show_sms != 0) {
            const servicesSelected = $('input[name="services[]"]:checked').map((i,el) => $(el).val()).get();
            html += `<p><strong>📡 Servicios:</strong> <span class="highlight">${servicesSelected.length ? servicesSelected.join(', ') : 'Ninguno'}</span></p>`;
        }

        if (CONFIG.show_brand != 0) {
            html += `<p><strong>📱 Marca:</strong> <span class="highlight"> ${$('#brand option:selected').text() || '-'}</span></p>`;
        }
        if (CONFIG.show_model != 0) {
            html += `<p><strong>📱 Modelo:</strong> <span class="highlight"> ${$('#model option:selected').data('name') || $('#model option:selected').text() || '-'}</span></p>`;
        }

        html += `<p><strong>📅 Fechas:</strong> <span class="highlight">${$('#start_date').val() || '-'} → ${$('#end_date').val() || '-'} (${data.days || 0} días)</span></p>`;
        html += `<p class="price-summary"><strong>💰 Precio total:</strong> <span class="highlight price">${data.total || '0.00'} ${CURRENCY}</span></p>`;

        $summary.fadeOut(150, function(){ $(this).html(html).fadeIn(200); });
        $price.fadeOut(150, function(){ $(this).text((data.total || '0.00') + ' ' + CURRENCY).fadeIn(200); });
    }

    /* =========================
       Wizard controls
       ========================= */
    function updateControls() {
        const $back = $('.usaalo-back');
        const $next = $('.usaalo-next');
        const $confirm = $('.usaalo-confirm');

        // Reset
        $back.addClass('hidden');
        $next.addClass('hidden');
        $confirm.addClass('hidden');

        if (currentStep > 1) $back.removeClass('hidden');
        if (currentStep < TOTAL_STEPS) $next.removeClass('hidden');
        if (currentStep === TOTAL_STEPS) $confirm.removeClass('hidden');
    }

    function showStep(step) {
        if (step < 1 || step > TOTAL_STEPS) return;

        // Validaciones
        if (step === 2) {
            const countries = getSelectedCountries();
            if (!countries.length) { showMessage('Selecciona al menos un país', 'error'); $('#country').select2('open'); return; }
            if (CONFIG.show_brand != 0 && !$('#brand').val()) { showMessage('Selecciona la marca', 'error'); $('#brand').select2('open'); return; }
            if (CONFIG.show_model != 0 && !$('#model').val()) { showMessage('Selecciona el modelo', 'error'); $('#model').select2('open'); return; }
            if (!validateDates()) return;
        }

        if (step === 3 && !$('input[name="sim_type"]:checked').val()) { showMessage('Selecciona el Tipo de SIM', 'error'); return; }

        $('.step').hide().removeClass('active');
        $('#step-' + step).fadeIn(250).addClass('active');
        $('.step-indicator').removeClass('active');
        $('.step-indicator[data-step="' + step + '"]').addClass('active');

        currentStep = step;
        updateControls();
        if (currentStep === TOTAL_STEPS) calculateQuote();
    }

    // Botones
    $(document).on('click', '.usaalo-next', function(){ showStep(currentStep + 1); });
    $(document).on('click', '.usaalo-back', function(){ showStep(currentStep - 1); });

    // Confirm (botón final)
    $(document).on('click', '.usaalo-confirm', function(e){
        e.preventDefault();
        $('#usaalo-quote').trigger('submit');
    });

    updateControls(); // init

    /* =========================
       Checkout / Añadir a carrito
       ========================= */
    function checkoutQuote() {
        const countries = getSelectedCountries();
        const days = parseInt($('#num_days').val()) || 1;
        const simFisica = $('input[name="sim_type"]:checked').val() === 'sim';
        const brand = $('#brand option:selected').text() || '';
        const model = $('#model option:selected').data('name') || $('#model option:selected').text() || '';
        const services = $('input[name="services[]"]:checked').map((i,el) => $(el).val()).get();

        const result = getPrices(countries, days, {}, $('#brand').val(), $('#model').val());
        if (!result.products || !result.products.length) {
            showMessage('No hay productos disponibles para los países seleccionados.', 'error');
            return;
        }

        // Mostrar loader
        $loader.removeClass('hidden');

        const countryNames = {};
        $('#country option:selected').each(function(){ countryNames[$(this).val()] = $(this).text(); });

        $.ajax({
            url: window.USAALO_Frontend.ajaxurl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'usaalo_add_multiple_to_cart',
                nonce: window.USAALO_Frontend.nonce,
                products: result.products,
                countries: countryNames,
                days: days,
                brand: brand,
                model: model,
                sim: simFisica ? 'SIM' : 'eSIM',
                start_date: $('#start_date').val(),
                end_date: $('#end_date').val(),
                services: services
            }
        }).done(function(res){
            $loader.addClass('hidden');
            if (res && res.success && res.data && res.data.checkout_url) {
                window.location.href = res.data.checkout_url;
            } else {
                showMessage(res.data && res.data.message ? res.data.message : 'Error al procesar la cotización', 'error');
            }
        }).fail(function(xhr){
            $loader.addClass('hidden');
            console.error(xhr.responseText || xhr);
            showMessage('Error de conexión con el servidor', 'error');
        });
    }

    // Form submit
    $('#usaalo-quote').on('submit', function(e){
        e.preventDefault();
        checkoutQuote();
    });

    /* =========================
       Auto-update & listeners
       ========================= */
    // Update quote when relevant inputs change (delegated)
    $(document).on('change', '#country, #model, #brand, input[name="sim_type"], input[name="services[]"], #start_date, #num_days', function(){
        // only recalc when sensible, debounce optionally
        calculateQuote();
    });

    // also update when user types days
    $(document).on('input', '#num_days', function(){ calculateQuote(); });

    // Initial render
    renderServiceButtons();
    calculateQuote();
});
