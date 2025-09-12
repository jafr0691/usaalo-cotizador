(function($){
    $(function(){

        const summary = (window.USAALO_Checkout && USAALO_Checkout.summary) ? USAALO_Checkout.summary : {};

        // POBLAR campos readonly / hidden desde carrito
        function populateFromCart() {
            if (summary.start_date) $('#billing_start_date').val(summary.start_date);
            if (summary.end_date) $('#billing_end_date').val(summary.end_date);
            if (summary.countries_names && summary.countries_names.length) {
                $('#billing_paises_display').val(summary.countries_names.join(', '));
                $('#billing_paises_codes').val(summary.countries_codes.join(','));
            }
            if (summary.brand) $('#billing_marca').val(summary.brand);
            if (summary.model) $('#billing_modelo').val(summary.model);
            if (summary.services && summary.services.length) $('#billing_servicio_elegido').val(summary.services.join(','));
            if (summary.sim) $('#billing_tipo_sim').val(summary.sim);
        }

        function toggleFields() {
            const tipo_sim = ($('#billing_tipo_sim').val() || '').toLowerCase();
            const servicios = ($('#billing_servicio_elegido').val() || '').split(',').map(s=>s.trim());
            const es_agencia = ($('#billing_es_agencia').val() || '').toLowerCase();

            // EID -> solo visible si eSIM
            if (tipo_sim === 'esim' || tipo_sim === 'e-sim' || tipo_sim === 'e_sim') {
                $('#billing_eid_field').show();
                $('#billing_eid').prop('required', true);
            } else {
                $('#billing_eid_field').hide();
                $('#billing_eid').prop('required', false).val('');
            }

            // Mostrar llamada entrante solo si servicio incluye 'voz'
            if (servicios.includes('voz') || servicios.includes('llamadas')) {
                $('#billing_llamada_entrante_field').show();
            } else {
                $('#billing_llamada_entrante_field').hide().find('select').val('');
            }

            // Agencia vs puntos colombia
            if (es_agencia === 'si') {
                $('#billing_nombre_agencia_field, #billing_asesor_comercial_field').show();
                $('#billing_puntos_colombia_field').hide().find('input').val('');
            } else if (es_agencia === 'no') {
                $('#billing_nombre_agencia_field, #billing_asesor_comercial_field').hide().find('input').val('');
                $('#billing_puntos_colombia_field').show();
            } else {
                // ocultar hasta seleccionar
                $('#billing_nombre_agencia_field, #billing_asesor_comercial_field, #billing_puntos_colombia_field').hide();
            }
        }

        // Initial populate & toggle
        populateFromCart();
        toggleFields();

        // Re-ejecutar en eventos (user changes)
        $(document).on('change', '#billing_es_agencia, #billing_servicio_elegido', function(){
            toggleFields();
        });

        // Re-evaluar cuando checkout actualiza
        $(document.body).on('updated_checkout', function(){
            populateFromCart();
            toggleFields();
        });

    });
})(jQuery);
