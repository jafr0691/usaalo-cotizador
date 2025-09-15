jQuery(function($){
    let cart = USAALO_Checkout.cart_summary;

    // Mostrar EID solo si es eSIM
    if(cart.sim !== 'esim'){
        $('#eid_field').hide();
    }

    // Mostrar llamada entrante solo si incluye VOZ
    if(cart.servicio !== 'voz' && cart.servicio !== 'voz_datos'){
        $('#llamada_entrante_field').hide();
    }

    // Mostrar campos agencia
    $('#es_agencia').on('change', function(){
        if($(this).val() === 'si'){
            $('#nombre_agencia_field, #asesor_comercial_field').show();
            $('#puntos_colombia_field').hide();
        } else {
            $('#nombre_agencia_field, #asesor_comercial_field').hide();
            $('#puntos_colombia_field').show();
        }
    }).trigger('change');

    // Cargar valor plan desde PHP
    if(cart.valor_plan){
        $('#valor_plan').val(cart.valor_plan);
    }
});
