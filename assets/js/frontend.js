jQuery(document).ready(function($){
    // Inicializar Select2
    $('#wizard-country, #wizard-brand, #wizard-model').select2();

    let currentStep = 1;
    const totalSteps = 4;

    function showStep(step){
        $('.usaalo-step-panel').hide();
        $(`.usaalo-step-panel[data-step="${step}"]`).show();
    }

    function updateSummary(){
        let countries = $('#wizard-country').val() || [];
        let simType = $('#wizard-sim-type').val();
        let brand = $('#wizard-brand option:selected').text();
        let model = $('#wizard-model option:selected').text();
        let startDate = $('#wizard-start-date').val();
        let endDate = $('#wizard-end-date').val();
        $('#wizard-summary').html(`
            <p><strong>País(es):</strong> ${countries.join(', ')}</p>
            <p><strong>Tipo de SIM:</strong> ${simType}</p>
            <p><strong>Marca/Modelo:</strong> ${brand} / ${model}</p>
            <p><strong>Duración:</strong> ${startDate} - ${endDate}</p>
        `);
    }

    $('.usaalo-next').on('click', function(){
        if(currentStep < totalSteps){
            currentStep++;
            showStep(currentStep);
            if(currentStep === 4) updateSummary();
        }
    });

    $('.usaalo-back').on('click', function(){
        if(currentStep > 1){
            currentStep--;
            showStep(currentStep);
        }
    });

    // Cargar marcas y modelos vía AJAX
    function loadBrands(){
        $.post(UA_Cotizador.ajaxurl, {action:'usaalo_get_brands', nonce:UA_Cotizador.nonce}, function(resp){
            if(resp.success){
                $('#wizard-brand').html('');
                resp.data.forEach(b => $('#wizard-brand').append(`<option value="${b.id}">${b.name}</option>`));
                $('#wizard-brand').trigger('change');
            }
        });
    }

    function loadModels(brandId){
        $.post(UA_Cotizador.ajaxurl, {action:'usaalo_get_models', brand:brandId, nonce:UA_Cotizador.nonce}, function(resp){
            if(resp.success){
                $('#wizard-model').html('');
                resp.data.forEach(m => $('#wizard-model').append(`<option value="${m.id}">${m.name}</option>`));
            }
        });
    }

    $('#wizard-brand').on('change', function(){ loadModels($(this).val()); });

    // Confirmar y crear producto / pasar a checkout
    $('#wizard-confirm').on('click', function(){
        let data = {
            action:'usaalo_create_product',
            nonce: UA_Cotizador.nonce,
            countries: $('#wizard-country').val(),
            sim_type: $('#wizard-sim-type').val(),
            brand: $('#wizard-brand').val(),
            model: $('#wizard-model').val(),
            start_date: $('#wizard-start-date').val(),
            end_date: $('#wizard-end-date').val()
        };
        $.post(UA_Cotizador.ajaxurl, data, function(resp){
            if(resp.success){
                window.location.href = resp.checkout_url;
            } else alert(resp.data);
        });
    });

    // Inicializar marcas
    loadBrands();
});
