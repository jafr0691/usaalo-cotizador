jQuery(document).ready(function($){

    // Inicializar select2
    $('#country, #services, #brand, #model').select2({
        width: '100%'
    });

    // Control de pasos
    var currentStep = 1;
    function showStep(step){
        $('.step').removeClass('active');
        $('#step-' + step).addClass('active');
        currentStep = step;
    }

    showStep(1); // Mostrar primer paso

    // Función para avanzar al siguiente paso
    function nextStep(){
        if(currentStep < 6){
            showStep(currentStep + 1);
        }
    }

    // Función para retroceder
    function prevStep(){
        if(currentStep > 1){
            showStep(currentStep - 1);
        }
    }

    // Validación simple antes de avanzar
    function validateStep(step){
        var valid = true;
        $('#step-' + step + ' [required]').each(function(){
            if($(this).val() === '' || $(this).val() === null){
                valid = false;
            }
        });
        return valid;
    }

    // Cambio de pasos al seleccionar país
    $('#country').on('change', function(){
        if(validateStep(1)){
            nextStep();
        } else {
            alert('Por favor selecciona al menos un país');
        }
    });

    // Cambio de pasos al seleccionar SIM
    $('#sim_type').on('change', function(){
        if(validateStep(2)){
            nextStep();
        }
    });

    // Cambio de pasos al seleccionar servicios
    $('#services').on('change', function(){
        nextStep();
    });

    // Cambio de pasos al seleccionar fechas
    $('#end_date').on('change', function(){
        var start = $('#start_date').val();
        var end = $('#end_date').val();
        if(start && end && start <= end){
            nextStep();
        } else {
            alert('La fecha de fin debe ser igual o posterior a la fecha de inicio');
        }
    });

    // Cambio de pasos al seleccionar marca y modelo
    $('#model').on('change', function(){
        if(validateStep(5)){
            nextStep();
            calculateQuote();
        }
    });

    // Función para calcular el precio mediante AJAX
    function calculateQuote(){
        var data = {
            action: 'usaalo_calculate_price',
            nonce: USAALO_Frontend.nonce,
            country: $('#country').val(),
            sim_type: $('#sim_type').val(),
            services: $('#services').val(),
            start_date: $('#start_date').val(),
            end_date: $('#end_date').val(),
            brand: $('#brand').val(),
            model: $('#model').val()
        };
        $.post(USAALO_Frontend.ajaxurl, data, function(resp){
            if(resp.success){
                $('#quote-summary').html(resp.data.summary);
                $('#quote-price').html(resp.data.price);
            } else {
                $('#quote-error').show().text('No se pudo calcular el precio. Intenta nuevamente.');
            }
        });
    }

    // Enviar cotización al checkout
    $('#usaalo-quote').on('submit', function(e){
        e.preventDefault();
        calculateQuote();
        alert('Tu cotización ha sido confirmada, ahora puedes continuar al pago');
        // Aquí puedes redirigir a WooCommerce Checkout si deseas
        // window.location.href = '/checkout';
    });

});
