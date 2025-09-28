jQuery(document).ready(function($){
    'use strict';

    /* =========================
       Config / Estado
       ========================= */
    const $divform = $('#usaalo-cotizador-horizontal');
    const $form = $('#usaalo-quote-horizontal');
    const $priceBtn = $('#usaalo-price-inline');
    const $simContainer = $('#usaalo-sim-buttons-horizontal');
    const $loader = $('#usaalo-loader');
    const $loaderMini = $('#usaalo-loader-mini');

    // Defensive globals
    const CONFIG = window.USAALO_Frontend && USAALO_Frontend.Config ? USAALO_Frontend.Config : {};
    const PRODUCTS_OBJ = window.USAALO_Frontend && USAALO_Frontend.products ? USAALO_Frontend.products : {};
    const ALL_MODELS = window.USAALO_Frontend && USAALO_Frontend.allModels ? USAALO_Frontend.allModels : {};
    const SIM_PRICES = window.USAALO_Frontend && USAALO_Frontend.simPrices ? USAALO_Frontend.simPrices : {};
    const SHIPPING_COST = parseFloat(window.USAALO_Frontend && USAALO_Frontend.shipping_cost ? USAALO_Frontend.shipping_cost : 0);
    const CURRENCY = window.USAALO_Frontend && USAALO_Frontend.currency_symbol ? USAALO_Frontend.currency_symbol : '';
    const ALL_COUNTRIES = window.USAALO_Frontend && USAALO_Frontend.allCountries ? USAALO_Frontend.allCountries : [];
    const IMG_SIM_FISICA = window.USAALO_Frontend && USAALO_Frontend.img_sim_fisica ? USAALO_Frontend.img_sim_fisica : '💳';
    const IMG_SIM_VIRTUAL = window.USAALO_Frontend && USAALO_Frontend.img_sim_virtual ? USAALO_Frontend.img_sim_virtual : '📶';
    const COST_ADI_SIM = window.USAALO_Frontend && USAALO_Frontend.i18n.costo_sim ? USAALO_Frontend.i18n.costo_sim : '';
    

    let SIM_START = null;
    let SIM_END = null;
    let SIM_DAYS = 0;

    /* =========================
       Helpers
       ========================= */
    const getSelectedCountries = () => {
        let val = $('#country').val() || [];
        if (!Array.isArray(val)) val = val ? [val] : [];
        return val.map(c => c.toUpperCase()).filter(Boolean);
    };

    const toArray = x => {
        if (!x && x !== 0) return [];
        if (Array.isArray(x)) return x;
        if (typeof x === 'object') return Object.values(x);
        return [x];
    };

    const showMessage = (msg, type='info') => {
        // Revisar si ya existe el contenedor de mensajes global
        let $msg = $('#usaalo-message');
        
        if (!$msg.length) {
            // Crear un div fuera del formulario, antes del formulario en el DOM
            $msg = $('<div id="usaalo-message"></div>');
            $divform.before($msg); 
        }

        // Configurar el mensaje
        $msg.stop(true, true)
            .html(msg)
            .attr('class', type)
            .fadeIn(200)
            .delay(3500)
            .fadeOut(300);
    };


    const getFlagURL = code => code ? `https://flagcdn.com/${code.toLowerCase()}.svg` : '';

    /* =========================
       Cargar países (Select2)
       ========================= */
    function formatCountry(state){
        if(!state.id) return state.text;
        const flag = $(state.element).data('flag') || getFlagURL(state.id);
        const enabled = $(state.element).data('enabled') ?? true;
        const disabledClass = enabled ? '' : 'country-disabled';
        return $(`<span class="${disabledClass}"><img src="${flag}" class="country-flag"/> ${state.text}${enabled?'':' (Próximamente)'}</span>`);
    }

    function loadCountries(){
        const $country = $('#country').empty();
        $country.append($('<option/>').val('')); // placeholder
        toArray(ALL_COUNTRIES).forEach(c=>{
            const code = c.code || c.country || c.id || '';
            const opt = $('<option/>').val(code).text(c.name || code).attr('data-flag', getFlagURL(code)).attr('data-enabled', !!c.disponible);
            if(!c.disponible) opt.prop('disabled',true).on('mousedown',e=>{ e.preventDefault(); showMessage((c.name||code)+' estará disponible próximamente'); });
            $country.append(opt);
        });

        if(CONFIG.select_pais==0) $country.removeAttr('multiple'); else $country.attr('multiple','multiple');
        if($country.hasClass('select2-hidden-accessible')) $country.select2('destroy');
        if(CONFIG.select_pais==0){
            $country.select2({
                width:'100%',
                placeholder: '<svg class="uitk-icon uitk-field-icon" aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M7.953 5.552C8.992 4.592 10.388 4 12 4c1.612 0 3.008.592 4.047 1.552l.007.006A5.88 5.88 0 0 1 18 9.93c0 1.557-.664 3.032-1.641 4.462-.98 1.433-2.418 3.025-4.359 4.774-1.941-1.75-3.38-3.341-4.359-4.774C6.661 12.958 6 11.492 6 9.929a5.88 5.88 0 0 1 1.946-4.37l.007-.007zM12 2c-2.12 0-3.998.785-5.4 2.08A7.88 7.88 0 0 0 4 9.93c0 2.14.908 4.006 1.99 5.59 1.216 1.778 3.001 3.69 5.354 5.735a1 1 0 0 0 1.312 0c2.353-2.045 4.138-3.957 5.354-5.735C19.09 13.938 20 12.063 20 9.93a7.88 7.88 0 0 0-2.6-5.85C15.999 2.785 14.12 2 12 2zm0 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4z" clip-rule="evenodd"></path></svg> País de destino',
                templateResult:formatCountry,
                templateSelection:formatCountry,
                escapeMarkup: m=>m,
                language:'es',
                allowClear:true
            });
        }else{
            $country.select2({
                width:'100%',
                placeholder: 'País de destino',
                templateResult:formatCountry,
                templateSelection:formatCountry,
                escapeMarkup: m=>m,
                language:'es',
                allowClear:true
            });
        }
        // Quitar el title del placeholder
        $('#select2-country-container').removeAttr('title');
    }
    loadCountries();

    // Muestras loader al inicio
    $loaderMini.show();
    $divform.hide(); // ocultamos el formulario

    // Cuando todo esté listo
    $loaderMini.fadeOut(300, function () {
        $divform.fadeIn(300);
        $form.css('display','grid'); // aseguras que se vea en grid
        const grid = document.getElementById('usaalo-quote-horizontal');
        if (!grid) return;
        
        // Contamos solo los campos visibles
        const camposVisibles = Array.from(grid.children).filter(el => el.offsetParent !== null);
        const cantidad = camposVisibles.length;
        
        // Quitamos clases previas
        grid.classList.remove('cols-4', 'cols-5', 'cols-6');
        
        // Asignamos según la cantidad
        if (cantidad === 4) {
            grid.classList.add('cols-4');
        } else if (cantidad === 5) {
            grid.classList.add('cols-5');
        } else {
            grid.classList.add('cols-6'); // 6 o más
        }
    });
    /* =========================
       Marca / Modelo
       ========================= */
    function initBrandModel(){
        if($('#brand').hasClass('select2-hidden-accessible')) $('#brand').select2('destroy');
        if($('#model').hasClass('select2-hidden-accessible')) $('#model').select2('destroy');
        $('#brand').select2({ width:'100%', placeholder:'Selecciona una marca' });
        $('#model').select2({ width:'100%', placeholder:'Selecciona un modelo' });

        if(CONFIG.show_brand==0){ $('#brand').closest('.field').hide(); $('#brand').val('other-brand').trigger('change').data('locked',true); }
        if(CONFIG.show_model==0){ $('#model').closest('.field').hide(); $('#model').val('other-model').trigger('change').data('locked',true); }
    }
    initBrandModel();

    function filterModels(brandId){
        const $model = $('#model');
        if($model.data('locked')) return;
        if(!brandId || !ALL_MODELS[brandId]){
            $model.html('<option value="">Selecciona un modelo</option>').prop('disabled',true).trigger('change');
            renderSim();
            return;
        }
        let opts = '<option value="">Selecciona un modelo</option>';
        toArray(ALL_MODELS[brandId]).forEach(m=> opts += `<option value="${m.id}" data-name="${m.name}">${m.name}</option>`);
        opts += `<option value="other-model">Otro modelo</option>`;
        $model.html(opts).prop('disabled',false).trigger('change');
        renderSim();
    }
    $('#brand').on('change', function(){ filterModels($(this).val()); });

    /* =========================
       Flatpickr fechas
       ========================= */
    if(typeof flatpickr==='function'){
        const picker = flatpickr("#SIM_dates", {
            mode: "range",
            dateFormat: "Y-m-d",   // valor interno para enviar al backend
            altInput: true,        // input visible amigable
            altFormat: "d M",      // visible ej: 05 Oct
            minDate: "today",
            showMonths: 2,
            locale: "es",
            onChange: function(selectedDates) {
                if (selectedDates.length === 2) {
                    SIM_START = selectedDates[0];
                    SIM_END   = selectedDates[1];

                    // Calcular días (incluyendo ambos extremos)
                    SIM_DAYS = Math.round((SIM_END - SIM_START) / (1000*60*60*24));

                    // Recalcular precios cada vez que cambie el rango
                    calculateQuote();
                }
            }
        });
        document.getElementById("openCalendar").addEventListener("click", () => {
            picker.open();
        });
    }

    function updateEndDate(){
        let startDate = $('#start_date').val();
        let numDays = parseInt($('#num_days').val())||1;
        if(startDate && numDays>0){
            let start = new Date(startDate);
            let end = new Date(start.getTime() + (numDays-1)*86400000);
            let yyyy = end.getFullYear();
            let mm = String(end.getMonth()+1).padStart(2,'0');
            let dd = String(end.getDate()).padStart(2,'0');
            $('#end_date').val(`${yyyy}-${mm}-${dd}`);
        }
    }
    $('#start_date,#num_days').on('change input', updateEndDate);

    /* =========================
       Obtener precio
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
       Render SIM / Servicios
       ========================= */
    function renderSim(){
        let html = '';
        
        const sims = [
            { type: 'sim', icon: IMG_SIM_FISICA, enabled: CONFIG.show_sim != 0 },
            { type: 'esim', icon: IMG_SIM_VIRTUAL, enabled: CONFIG.show_esim != 0 }
        ];

        sims.forEach(sim => {
            if(sim.enabled){ // Solo mostrar si está habilitada
                html += `<label class="sim-option tooltip" style="margin-right:10px;">
                            <input type="radio" name="sim_type" value="${sim.type}">
                            ${sim.icon}
                            <span class="tooltip-text"> 
                                ${sim.type==='sim'?COST_ADI_SIM:'(SIM virtual)'}
                            </span>
                        </label>`;
            }
        });

        $simContainer.html(html);

        // Evento para recalcular cotización al cambiar
        $simContainer.find('input[name="sim_type"]').on('change', calculateQuote);
    }

    function renderServices() {
        const $servicesContainer = $('#usaalo-services-inline');
        let html = '';

        const servicios = [
            { id: 'voz', icon: `📞`, enabled: CONFIG.show_voice != 0 },
            { id: 'sms', icon: `✉️`, enabled: CONFIG.show_sms != 0 }
        ];

        servicios.forEach(servicio => {
            if(servicio.enabled){ // Solo renderizar si está habilitado
                html += `<label class="sim-option tooltip" style="margin-right:10px;">
                            <input type="checkbox" name="service_type" value="${servicio.id}">
                            ${servicio.icon}
                            <span class="tooltip-text"> 
                                ${servicio.id==='voz'?'Llamadas':'SMS'}
                            </span>
                        </label>`;
            }
        });

        $servicesContainer.html(html);

        // Evento para recalcular cotización al cambiar
        $servicesContainer.find('input[name="service_type"]').on('change', calculateQuote);
    }

    /* =========================
       Calcular cotización
       ========================= */
    function calculateQuote(){
        const countries = getSelectedCountries();
        if(!countries.length || !SIM_START || !SIM_END){ 
            $('.arrow').text('→')
            $('.usaalo-confirm').prop('disabled', true);
            $priceBtn.text(`${CURRENCY}0,00`); 
            return; 
        }

        const result = getPrices(countries, SIM_DAYS);
        let total = parseFloat(result.total_price||0);
        const simType = $('input[name="sim_type"]:checked').val();
        if(simType==='sim') total+=SHIPPING_COST;
        
        $priceBtn.stop(true).fadeOut(100, function(){ $(this).text(CURRENCY+total.toFixed(2)).fadeIn(150); });
        $('.arrow').text('Pagar')
        $('.usaalo-confirm').prop('disabled', false);
    }

    $('#country,#brand,#model').on('change input', calculateQuote);

    renderServices();
    renderSim();
    calculateQuote();

    function formatDate(date){
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, '0'); // Enero = 0
        const dd = String(date.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    /* =========================
       Checkout
       ========================= */
    $form.on('submit', function(e){
        e.preventDefault();
        const countries = getSelectedCountries();
        if(!countries.length){ showMessage('Selecciona al menos un país','error'); return; }
        if (!SIM_START || !SIM_END) { showMessage('Selecciona un rango de fechas','error'); return; }
        const simType = $('input[name="sim_type"]:checked').val()||'esim';
        const brand = $('#brand option:selected').text()||'';
        const model = $('#model option:selected').data('name')||$('#model option:selected').text()||'';
        const result = getPrices(countries, SIM_DAYS);

        if(!result.products.length){ showMessage('No hay productos disponibles para los países seleccionados','error'); return; }

        const countryNames = {};
        $('#country option:selected').each(function(){ countryNames[$(this).val()] = $(this).text(); });

        $.ajax({
            url: window.USAALO_Frontend.ajaxurl,
            method:'POST',
            dataType:'json',
            data:{
                action:'usaalo_add_multiple_to_cart',
                nonce: window.USAALO_Frontend.nonce,
                products: result.products,
                countries: countryNames,
                days:SIM_DAYS,
                brand:brand,
                model:model,
                sim:simType,
                start_date: formatDate(SIM_START),
                end_date: formatDate(SIM_END),
            },
            beforeSend: ()=> $('#usaalo-loader').removeClass('hidden')
        }).done(res=>{
            // $('#usaalo-loader').addClass('hidden');
            if(res?.success && res.data?.checkout_url) window.location.href = res.data.checkout_url;
            else showMessage(res?.data?.message||'Error al procesar la cotización','error');
        }).fail(()=>{ $('#usaalo-loader').addClass('hidden'); showMessage('Error de conexión con el servidor','error'); });
    });
});
