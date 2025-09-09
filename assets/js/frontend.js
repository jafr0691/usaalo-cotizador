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

        if (selectedDate <= today) {
            showMessage('La fecha de inicio no puede ser anterior a hoy', 'error');
            // reset a hoy
            startDateInput.value = new Date().toISOString().split('T')[0];
            updateEndDate(); // recalcular fecha final
            return false;
        }

        return true;
    }



    function updateEndDate() {
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

    // Llamar al cargar la página
    updateEndDate();

    // Actualizar cada vez que cambien start_date o num_days
    document.getElementById('start_date').addEventListener('change', updateEndDate);
    document.getElementById('num_days').addEventListener('input', updateEndDate);


        
    function getPrices(countryCodes, dias, simFisica = false) {
        const products = USAALO_Frontend.products || {};
        let candidates = [];

        // Filtrar productos por países seleccionados
        Object.values(products).forEach(p => {
            const matches = p.countries.filter(c => countryCodes.includes(c.code));
            if (matches.length) {
                candidates.push({
                    ...p,
                    coverage: matches.length,
                    matches: matches
                });
            }
        });

        if (!candidates.length) return { total_price: 0, products: [] };

        // Ordenar por cobertura y precio mínimo
        candidates.sort((a, b) => (b.coverage - a.coverage) || (a.min_price - b.min_price));
        const maxCoverage = candidates[0].coverage;
        candidates = candidates.filter(p => p.coverage === maxCoverage);

        let total = 0;
        let list = [];

        candidates.forEach(p => {
            let precioBase = p.base_price;

            if (p.type === 'variable' && p.ranges.length) {
                for (const r of p.ranges) {
                    if (dias >= r.min && dias <= r.max) {
                        precioBase = r.price;
                        break;
                    }
                }
            }

            // ✅ Ajustar precio si es SIM física
            if (simFisica && p.shipping_cost) {
                precioBase += parseFloat(p.shipping_cost); // asumimos que shipping_cost viene del JSON
            }

            const precioTotal = precioBase * Math.max(1, dias);
            total += precioTotal;

            list.push({
                product_id: p.product_id,
                name: p.name,
                price: precioTotal,
                countries: p.matches
            });
        });

        return {
            total_price: total,
            products: list
        };
    }

    // ============================
    // Cálculo instantáneo de precio
    // ============================
    function calculateQuote(){
        let countries = $('#country').val()||[];
        if(!countries.length) return;

        const simFisica = $('input[name="sim_type"]:checked').val() === 'fisica';

        if(!validateDates()) return;

        let days = 0;
        let start = $('#start_date').val();
        let end = $('#end_date').val();
        if(start && end) days = (new Date(end) - new Date(start))/(1000*60*60*24);

        // Mostrar "cargando" mientras llega el precio
        $('#usaalo-summary').fadeOut(100);
        $('#usaalo-price').fadeOut(150,function(){ $(this).text(('0,00')+' '+USAALO_Frontend.currency_symbol).fadeIn(200); });

        let total = 0;

        const data = getPrices(countries, days, simFisica);

        updateSummary({data:{total: data.total_price.toFixed(2), days: days}});

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
                            ${icon} ${label}
                            <span class="tooltip-text">Disponible en: ${flags}</span>
                        </label>`;
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
            if(currentStep===totalSteps) calculateQuote();
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

    // ============================
    // Eventos globales
    // ============================
    $('#country, #model, #start_date, #num_days').on('change', calculateQuote);
    $('#num_days').on('keyup', calculateQuote);
    $('input[name="sim_type"]').on('click', calculateQuote);

});
