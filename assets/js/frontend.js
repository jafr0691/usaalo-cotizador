jQuery(document).ready(function($){
    const totalSteps = 4;
    let currentStep = 1;

    // ============================
    // Mensajes
    // ============================
    function showMessage(msg, type='info'){
        let $msg = $('#usaalo-message');
        if(!$msg.length){
            $('#usaalo-cotizador-wizard').prepend('<div id="usaalo-message"></div>');
            $msg = $('#usaalo-message');
        }
        $msg.stop(true,true).html(msg).attr('class', type).fadeIn(200).delay(3000).fadeOut(400);
    }

    function getFlagURL(code){ return 'https://flagcdn.com/'+code.toLowerCase()+'.svg'; }

    function formatCountry(state){
        if(!state.id) return state.text;
        let flag = $(state.element).data('flag');
        let enabled = $(state.element).data('enabled');
        let disabledClass = enabled?'':'country-disabled';
        return $('<span class="'+disabledClass+'"><img src="'+flag+'" class="country-flag"/> '+state.text+(enabled?'':' (Próximamente)')+'</span>');
    }

    // ============================
    // Cargar países con Select2
    // ============================
    function loadCountries(){
        let $country = $('#country').empty();
        $.each(USAALO_Frontend.allCountries, function(i,c){
            let option = $('<option></option>').val(c.code).text(c.name)
                .attr('data-flag', getFlagURL(c.code))
                .attr('data-enabled', c.disponible?true:false);
            if(!c.disponible){
                option.prop('disabled',true).on('mousedown', e=>{
                    e.preventDefault();
                    showMessage(c.name+' estará disponible próximamente.', 'info');
                });
            }
            $country.append(option);
        });
        $country.select2({
            width:'100%',
            placeholder:USAALO_Frontend.i18n.select_country,
            minimumResultsForSearch: 0,
            templateResult:formatCountry,
            templateSelection:formatCountry,
            escapeMarkup: function(m){ return m; }, // permite HTML en opciones
            language: 'es'
        });
    }
    loadCountries();

    // ============================
    // Marcas y modelos pre-cargados
    // ============================
    let allModels = USAALO_Frontend.allModels || {};

    $('#brand').select2({ width:'100%', placeholder:'Selecciona una marca' });
    $('#model').select2({ width:'100%', placeholder:'Selecciona un modelo' });

    function filterModels(brandId){
        let $model = $('#model');
        $model.prop('disabled', true).html('<option>Cargando modelos...</option>').trigger('change');
        if(!brandId || !allModels[brandId]){
            $model.html('<option value="">Selecciona un modelo</option>').prop('disabled',true).trigger('change');
            renderServiceButtons();
            return;
        }
        let options = '<option value="">Selecciona un modelo</option>';
        allModels[brandId].forEach(m => {
            options += `<option value="${m.id}" data-name="${m.name}">${m.name}</option>`;
        });
        $model.html(options).prop('disabled',false).trigger('change');
        renderServiceButtons();
    }

    $('#brand').on('change', function(){ filterModels($(this).val()); });
    $('#model').on('change', function(){renderServiceButtons()});
    $('#country').on('change', function(){renderServiceButtons()});


    // ============================
    // Validación fechas
    // ============================
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

    // Llamar al cargar la página
    updateEndDate();

    // Actualizar cada vez que cambien start_date o num_days
    if(document.getElementById('start_date')){
        document.getElementById('start_date').addEventListener('change', updateEndDate);
        document.getElementById('num_days').addEventListener('input', updateEndDate);
    }


        
    /**
 * getBestProducts
 * @param {string[]} countryCodes - array de códigos país (ej: ['US','CA'])
 * @param {number} dias - número de días
 * @param {object} opts - opciones: { greedyThreshold: number, maxCountriesForMask: number }
 * @returns {Object} { total_price: number, products: [ { product_id,name,price,price_per_day,countries } ] }
 */
function getPrices(countryCodes, dias, opts = {}) {
    const productsObj = USAALO_Frontend && USAALO_Frontend.products ? USAALO_Frontend.products : {};
    const products = Object.values(productsObj);
    const greedyThreshold = opts.greedyThreshold ?? 20; // si >20 candidatos usamos greedy
    const maxCountriesForMask = opts.maxCountriesForMask ?? 30; // límite por bitwise (32-bit JS)

    // Normalizar códigos únicos
    const uniqueCodes = Array.from(new Set(countryCodes.map(c => String(c).toUpperCase())));
    if (!uniqueCodes.length) return { total_price: 0, products: [] };
    if (uniqueCodes.length > maxCountriesForMask) {
        console.warn('Many countries selected — using greedy fallback due to bitmask limits.');
    }

    // Map código => índice bit
    const codeIndex = {};
    uniqueCodes.forEach((c, i) => codeIndex[c] = i);
    const fullMask = uniqueCodes.length <= maxCountriesForMask ? ((1 << uniqueCodes.length) - 1) : null;
    const daysQty = Math.max(1, parseInt(dias) || 1);

    // Generar lista de candidatos que cubran al menos 1 country
    let candidates = [];
    for (const p of products) {
        if (!p || !Array.isArray(p.countries)) continue;
        // matches: items of p.countries that exist in uniqueCodes
        const matches = p.countries.filter(c => {
            const code = (c.code || c).toString().toUpperCase();
            return uniqueCodes.includes(code);
        }).map(c => ({ code: (c.code || c).toString().toUpperCase(), raw: c }));

        if (matches.length === 0) continue;

        // calcular price per day: buscar rango que contenga `dias`
        let pricePerDay = parseFloat(p.base_price || 0);
        if (p.type === 'variable' && Array.isArray(p.ranges) && p.ranges.length) {
            const found = p.ranges.find(r => (Number(dias) >= Number(r.min) && Number(dias) <= Number(r.max)));
            if (found) pricePerDay = parseFloat(found.price);
            else if (typeof p.min_price !== 'undefined') pricePerDay = parseFloat(p.min_price);
        }

        const totalCost = pricePerDay * daysQty;

        // máscara (si se puede)
        let pmask = 0;
        if (fullMask !== null) {
            for (const m of matches) {
                const idx = codeIndex[m.code];
                if (typeof idx === 'number') pmask |= (1 << idx);
            }
        }

        candidates.push({
            product: p,
            matches,
            pmask,
            pricePerDay,
            totalCost
        });
    }

    if (candidates.length === 0) return { total_price: 0, products: [] };

    // Si hay demasiados candidatos, recortar por cobertura y precio para DP
    if (candidates.length > greedyThreshold || fullMask === null) {
        // Ordenar por cobertura desc, precio asc y tomar top greedyThreshold
        candidates.sort((a,b) => (b.matches.length - a.matches.length) || (a.pricePerDay - b.pricePerDay));
        candidates = candidates.slice(0, greedyThreshold);
        // Usaremos fallback greedy (pero intentamos DP si tenemos mask)
    }

    // Si tenemos máscaras válidas, intentar DP (optimal)
    if (fullMask !== null) {
        const nMask = 1 << uniqueCodes.length;
        // dp[mask] = { cost, count, items: [indices] } or null
        const dp = new Array(nMask).fill(null);
        dp[0] = { cost: 0, count: 0, items: [] };

        for (let i = 0; i < candidates.length; i++) {
            const c = candidates[i];
            // iterar máscaras en orden ascendente copia snapshot para evitar usar actualizaciones del mismo ciclo
            const snapshot = dp.slice();
            for (let mask = 0; mask < nMask; mask++) {
                if (!snapshot[mask]) continue;
                const newmask = mask | c.pmask;
                const newCost = snapshot[mask].cost + c.totalCost;
                const newCount = snapshot[mask].count + 1;
                const prev = dp[newmask];
                if (!prev || newCost < prev.cost || (newCost === prev.cost && newCount < prev.count)) {
                    dp[newmask] = {
                        cost: newCost,
                        count: newCount,
                        items: snapshot[mask].items.concat(i)
                    };
                }
            }
        }

        const result = dp[fullMask];
        if (result) {
            const selected = result.items.map(i => candidates[i]);
            const out = selected.map(s => ({
                product_id: s.product.product_id ?? s.product.product_id ?? s.product.id ?? null,
                name: s.product.name,
                price: Math.round(s.totalCost * 100) / 100,
                price_per_day: Math.round(s.pricePerDay * 100) / 100,
                countries: s.matches.map(m => m.code)
            }));
            return { total_price: Math.round(result.cost * 100) / 100, products: out };
        }
        // si no encontró cobertura completa con el recorte, caemos a greedy abajo
    }

    // FALLBACK GREEDY (cubre lo máximo posible, preferencia por cobertura y precio)
    // iterar: elegir producto con más países restantes, tiebreaker menor totalCost
    const neededSet = new Set(uniqueCodes);
    const chosen = [];
    let total = 0;

    while (neededSet.size > 0) {
        // recalcular matches por países aún no cubiertos
        candidates.forEach(c => {
            c.currentMatches = c.matches.map(m => m.code).filter(cd => neededSet.has(cd));
            c.currentCoverage = c.currentMatches.length;
        });
        // filtrar candidatos que cubren al menos 1 país restante
        const possible = candidates.filter(c => c.currentCoverage > 0);
        if (possible.length === 0) break;
        possible.sort((a,b) => (b.currentCoverage - a.currentCoverage) || (a.totalCost - b.totalCost));
        const sel = possible[0];
        chosen.push(sel);
        total += sel.totalCost;
        // eliminar países cubiertos
        sel.currentMatches.forEach(cd => neededSet.delete(cd));
        // remover ese candidato para no reusar (opcional)
        candidates = candidates.filter(c => c !== sel);
    }

    const output = chosen.map(s => ({
        product_id: s.product.product_id ?? s.product.id ?? null,
        name: s.product.name,
        price: Math.round(s.totalCost * 100)/100,
        price_per_day: Math.round(s.pricePerDay * 100)/100,
        countries: s.matches.map(m => m.code)
    }));

    return { total_price: Math.round(total * 100) / 100, products: output };
}







    // ============================
    // Cálculo instantáneo de precio
    // ============================
    function calculateQuote(){
        let countries = $('#country').val()||[];
        if(!countries.length) return;

        const simType = $('input[name="sim_type"]:checked').val();
        const simFisica = simType === 'sim';

        if(!validateDates()) return;

        let days = 0;
        let start = $('#start_date').val();
        let end = $('#end_date').val();
        if(start && end) days = (new Date(end) - new Date(start))/(1000*60*60*24);

        // Mostrar "cargando"
        $('#usaalo-summary').fadeOut(100);
        $('#usaalo-price').fadeOut(150,function(){
            $(this).text(('0,00')+' '+USAALO_Frontend.currency_symbol).fadeIn(200);
        });

        const data = getPrices(countries, days);
        let total = parseFloat(data.total_price) || 0;

        // ✅ sumar costo de envío si SIM física
        if(simFisica && USAALO_Frontend.shipping_cost > 0){
            total += parseFloat(USAALO_Frontend.shipping_cost);
        }

        // if (simFisica) {
        //     // const deliveryCountry = $('#delivery_country').val() || 'CO';
        //     if (USAALO_Frontend.shipping_costs[deliveryCountry]) {
        //         total += parseFloat(USAALO_Frontend.shipping_costs[deliveryCountry]);
        //     }
        // }

        updateSummary({
            data: {
                total: total.toFixed(2),
                days: days
            }
        });
    }

    // ============================
    // Actualizar resumen
    // ============================
    function updateSummary(resp){
        let html = '<p><strong>País(es):</strong> ';
        $('#country option:selected').each(function(){
            html += `<span class="highlight"><img src="${$(this).data('flag')}" class="country-flag"/> ${$(this).text()}</span> `;
        }); html += '</p>';

        html += '<p><strong>SIM elegido:</strong> '+($('input[name="sim_type"]:checked').val()||'')+'</p>';
        let servicesSelected = $('input[name="services[]"]:checked').map(function(){return $(this).val();}).get();
        html += '<p><strong>Servicios elegidos:</strong> '+(servicesSelected.length?servicesSelected.join(', '):'Ninguno')+'</p>';
        html += '<p><strong>Marca:</strong> '+$('#brand option:selected').text()+'</p>';
        html += '<p><strong>Modelo:</strong> '+($('#model option:selected').data('name')||'No seleccionado')+'</p>';
        html += '<p><strong>Fechas:</strong> '+($('#start_date').val()||'')+' → '+($('#end_date').val()||'')+' ('+(resp.data.days||0)+' días)</p>';
        html += '<p><strong>Precio total:</strong> '+(resp.data.total||0)+' '+USAALO_Frontend.currency_symbol+'</p>';

        $('#usaalo-summary').fadeOut(150,function(){ $(this).html(html).fadeIn(200); });
        $('#usaalo-price').fadeOut(150,function(){ $(this).text((resp.data.total||0)+' '+USAALO_Frontend.currency_symbol).fadeIn(200); });
    }

    // ============================
    // Renderizar servicios
    // ============================
    function renderServiceButtons() {
        let countries = $("#country").val() || [];
        let modelId = $("#model").val() || null;
        if (!countries.length || !modelId) return;

        let $container = $('.usaalo-services-buttons');
        $container.html('<p>Cargando servicios...</p>');

        // Obtener servicios desde los datos pre-cargados
        let data = USAALO_Frontend.TypeServices[modelId];
        if (!data) {
            $container.html('<p>No hay servicios disponibles.</p>');
            return;
        }

        let html = '';

        // Agrupar países por servicio
        let groupedServices = { sim: [], esim: [], datos: [], llamadas: [], sms: [] };
        countries.forEach(c => {
            if (!data[c]) return;
            data[c].services.forEach(s => {
                if (groupedServices[s]) groupedServices[s].push(c);
            });
        });

        // SIM
        html += '<div class="sim-section"><p><strong>Tipo de SIM</strong></p><div class="service-type">';
        ['sim','esim'].forEach(type => {
            let activeCountries = groupedServices[type];
            let icon = type === 'sim' ? '💳' : '📶';
            let label = type === 'sim' ? 'SIM física' : 'eSIM';

            if(activeCountries.length){
                let flags = activeCountries.map(c => `<img class="flag" src="https://flagcdn.com/${c.toLowerCase()}.svg">`).join(' ');
                html += `<label class="sim-option tooltip">
                            <input type="radio" name="sim_type" value="${type}">
                            ${icon} ${label}`;
                            USAALO_Frontend.shipping_cost
                            if(type === 'sim'){
                                html += `<span class="tooltip-text">Costo adicional de envio: ${USAALO_Frontend.shipping_cost} ${USAALO_Frontend.currency_symbol}<br>Disponible en:<br>${flags}</span>`;
                            }else{
                                html += `<span class="tooltip-text">Disponible en: ${flags}</span>`;
                            }
                            
                        html += `</label>`;
            } else {
                html += `<label class="sim-option disabled tooltip">
                            <input type="radio" disabled>
                            ${icon} ${label}
                            <span class="tooltip-text">No disponible en los países seleccionados</span>
                        </label>`;
            }
        });
        html += '</div></div>';

        // Servicios
        html += '<div class="services-section"><p><strong>Servicios</strong></p><div class="service-type">';
        [['datos','Datos','📡'], ['llamadas','Llamadas','📞'], ['sms','SMS','✉️']].forEach(([key,label,icon])=>{
            let activeCountries = groupedServices[key] || [];
            if(activeCountries.length){
                let flags = activeCountries.map(c => `<img class="flag" src="https://flagcdn.com/${c.toLowerCase()}.svg">`).join(' ');
                html += `<label class="service-option tooltip">
                            <input type="checkbox" name="services[]" value="${key}" checked>
                            ${icon} ${label}
                            <span class="tooltip-text">Disponible en: ${flags}</span>
                        </label>`;
            } else {
                html += `<label class="service-option disabled tooltip">
                            <input type="checkbox" disabled>
                            ${icon} ${label}
                            <span class="tooltip-text">No disponible en los países seleccionados</span>
                        </label>`;
            }
        });
        html += '</div></div>';

        $container.html(html);

        // Recalcular cotización al cambiar
        $('input[name="sim_type"]').on('change', function(){
            calculateQuote();
        });
    }



    
    // ============================
    // Wizard
    // ============================
    function showStep(step){
        if(step>totalSteps||step<1) return;

        // Validaciones con animación
        if(step===2 && ($('#country').val().length===0)){showMessage('Selecciona al menos un país','error'); $('#country').select2('open'); return;}
        if(step===2 && !$('#brand').val()){showMessage('Selecciona la marca','error'); $('#brand').select2('open'); return;}
        if(step===2 && !$('#model').val()){showMessage('Selecciona el modelo','error'); $('#model').select2('open'); return;}
        if(step===3 && !$('input[name="sim_type"]:checked').val()){showMessage('Selecciona el Tipo de SIM','error');  return;}
        if(step===4 && !validateDates()){return;}

        $('.step').fadeOut(200).removeClass('active');
        $('#step-'+step).fadeIn(300).addClass('active');
        $('.step-indicator').removeClass('active');
        $('.step-indicator[data-step="'+step+'"]').addClass('active');

        currentStep = step;
        if(step===4) calculateQuote();
    }

    $('.usaalo-next').on('click', ()=>showStep(currentStep+1));
    $('.usaalo-back').on('click', ()=>showStep(currentStep-1));

    // ============================
    // Confirmar cotización
    // ============================
    $('#confirm-quote').on('click', function(){
        showMessage('Cotización confirmada. Se enviaría al checkout con todos los datos.', 'success');
    });

    function checkoutQuote() {
        let countries = $('#country').val() || [];
        let days = parseInt($('#num_days').val());
        let simFisica = $('input[name="sim_type"]:checked').val() === 'sim';

        let result = getPrices(countries, days);
        if (!result.products.length) {
            alert('No hay productos disponibles');
            return;
        }

        let countryNames = {};
        $('#country option:selected').each(function() {
            countryNames[$(this).val()] = $(this).text();
        });

        // 🔥 Loader moderno
        $('#usaalo-loader').removeClass('hidden');

        $.ajax({
            url: USAALO_Frontend.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'usaalo_add_multiple_to_cart',
                nonce: USAALO_Frontend.nonce,
                products: result.products,
                countries: countryNames,
                days: days,
                brand: $('#brand option:selected').text(),
                model: $('#model option:selected').text(),
                sim: simFisica ? 'SIM' : 'eSIM',
                start_date: $('#start_date').val(),
                end_date: $('#end_date').val(),
                services: $('input[name="services[]"]:checked').map((i,el)=>$(el).val()).get()
            },
            success: function(res){
                $('#usaalo-loader').addClass('hidden');
                if(res.success){
                    // ✅ Redirigir directo al checkout
                    window.location.href = res.data.checkout_url;
                } else {
                    alert(res.data.message || 'Error al procesar la cotización');
                }
            },
            error: function(xhr, status, error){
                $('#usaalo-loader').addClass('hidden');
                console.error(xhr.responseText);
                alert('Error de conexión con el servidor');
            }
        });
    }




    $('#usaalo-quote').on('submit', (e)=>{
        e.preventDefault();
        checkoutQuote()
    })
    // ============================
    // Eventos globales
    // ============================
    $('#country, #model, #start_date, #num_days').on('change', calculateQuote);
    $('#num_days').on('keyup', calculateQuote);

});
