(function($){
    'use strict';

    const App = {
        init: function() {
            const page = (window.USAALO_Admin && window.USAALO_Admin.page) ? window.USAALO_Admin.page : '';

            this.initSelect2();

            switch(page) {
                case 'toplevel_page_usaalo-cotizador':
                case 'usaalo-cotizador_page_usaalo-cotizador-countries':
                    this.initCountries();
                    break;
                case 'usaalo-cotizador_page_usaalo-cotizador-brands-models':
                    this.initBrandsModels();
                    break;
                case 'usaalo-cotizador_page_usaalo-cotizador-sim-servicio':
                    this.initSimServicio();
                    break;
                case 'usaalo-cotizador_page_usaalo-cotizador-plans':
                    this.initPlans();
                    break;
            }
        },
        initSelect2: function() {
            if ($.fn.select2) {
                $('select').not('.no-select2').each(function() {
                    let $modal = $(this).closest('.usaalo-modal'); // detecta el modal más cercano
                    if ($modal.length) {
                        $(this).select2({
                            width: '100%',
                            dropdownParent: $modal
                        });
                    } else {
                        // si no está dentro de un modal, usa body (comportamiento normal)
                        $(this).select2({
                            width: '100%'
                        });
                    }
                });
            }
        },

        /* =========================
         *   COUNTRIES
         * ========================= */
        initCountries: function() {
            if ($.fn.DataTable) {

                $('#usaalo-countries-table').DataTable({
                    ajax: {
                        url: USAALO_Admin.ajaxurl,
                        type: "POST",
                        data: { action: "get_countries", nonce: USAALO_Admin.nonce }
                    },
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
                    columns: [
                            { 
                                data: "id",
                                render: function(data) {
                                    return '<input type="checkbox" class="usaalo-check" value="'+data+'">';
                                },
                                orderable: false
                            },
                            { data: "code" },
                            { data: "name" },
                            { data: "region" },
                            { 
                                data: "id",
                                render: function(data) {
                                    return '<button class="button edit-country" data-id="'+data+'">Editar</button>' +
                                            '<button class="button delete-country" data-id="'+data+'">Eliminar</button>';
                                },
                                orderable: false
                            }
                        ],
                    order: [[1, "asc"]],
                    responsive: true,
                    autoWidth: true,
                    paging: true,
                    searching: true,
                    info: true,
                    // Añadir checkbox en el header
                    initComplete: function () {
                        // Agregar el checkbox de seleccionar todos en el header
                        $('#usaalo-countries-table thead th').eq(0).html(
                            '<input type="checkbox" id="country-usaalo-check-all">'
                        );
                    }
                });

            }

            $('#country-code').on('input', function() {
                this.value = this.value.replace(/[^a-zA-Z]/g, '').substr(0, 2);
            });

            // Brand
            this.crudEntity({
                addBtn: '.add-country',
                editBtn: '.edit-country',
                deleteBtn: '.delete-country',
                cancelBtn: '.cancel-country',
                formSel: '#usaalo-country-form',
                modalSel: '#usaalo-country-modal',
                overlaySel: '#usaalo-country-overlay',
                getAction: 'usaalo_get_country',
                saveAction: 'usaalo_save_country',
                deleteAction: 'usaalo_delete_country',
                tabla: 'usaalo_countries',
                dataTable: 'usaalo-countries-table',
                usaalo_check_all: '#country-usaalo-check-all'
            });

        },

        /* =========================
         *   BRANDS & MODELS
         * ========================= */
        initBrandsModels: function() {
            if ($.fn.DataTable) {
                $('#usaalo-brands-table').DataTable({
                    ajax: {
                        url: USAALO_Admin.ajaxurl,
                        type: "POST",
                        data: { action: "get_brands", nonce: USAALO_Admin.nonce }
                    },
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
                    columns: [
                            { 
                                data: "id",
                                render: function(data) {
                                    return '<input type="checkbox" class="usaalo-check" value="'+data+'">';
                                },
                                orderable: false
                            },
                            { data: "name" },
                            { data: "slug" },
                            { 
                                data: "id",
                                render: function(data) {
                                    return '<button class="button edit-brand" data-id="'+data+'">Editar</button>' +
                                            '<button class="button delete-brand" data-id="'+data+'">Eliminar</button>';
                                },
                                orderable: false
                            }
                        ],
                    order: [[1, "asc"]],
                    responsive: true,
                    autoWidth: true,
                    paging: true,
                    searching: true,
                    info: true,
                    // Añadir checkbox en el header
                    initComplete: function () {
                        // Agregar el checkbox de seleccionar todos en el header
                        $('#usaalo-brands-table thead th').eq(0).html(
                            '<input type="checkbox" id="brand-usaalo-check-all">'
                        );
                    }
                });

                $('#usaalo-models-table').DataTable({
                    ajax: {
                        url: USAALO_Admin.ajaxurl,
                        type: "POST",
                        data: { action: "get_models", nonce: USAALO_Admin.nonce }
                    },
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
                    columns: [
                            { 
                                data: "id",
                                render: function(data) {
                                    return '<input type="checkbox" class="usaalo-check" value="'+data+'">';
                                },
                                orderable: false
                            },
                            { data: "brand_name" },
                            { data: "name" },
                            { data: "slug" },
                            { 
                                data: "id",
                                render: function(data) {
                                    return '<button class="button edit-model" data-id="'+data+'">Editar</button>' +
                                            '<button class="button delete-model" data-id="'+data+'">Eliminar</button>';
                                },
                                orderable: false
                            }
                        ],
                    order: [[1, "asc"]],
                    responsive: true,
                    autoWidth: true,
                    paging: true,
                    searching: true,
                    info: true,
                    // Añadir checkbox en el header
                    initComplete: function () {
                        // Agregar el checkbox de seleccionar todos en el header
                        $('#usaalo-models-table thead th').eq(0).html(
                            '<input type="checkbox" id="model-usaalo-check-all">'
                        );
                    }
                });
            }

            // Brand
            this.crudEntity({
                addBtn: '.add-brand',
                editBtn: '.edit-brand',
                deleteBtn: '.delete-brand',
                cancelBtn: '.cancel-brand',
                formSel: '#usaalo-brand-form',
                modalSel: '#usaalo-brand-modal',
                overlaySel: '#usaalo-brand-overlay',
                getAction: 'usaalo_get_brand',
                saveAction: 'usaalo_save_brand',
                deleteAction: 'usaalo_delete_brand',
                tabla: 'usaalo_brands',
                dataTable: 'usaalo-brands-table',
                usaalo_check_all: '#brand-usaalo-check-all'
            });

            // Model
            this.crudEntity({
                addBtn: '.add-model',
                editBtn: '.edit-model',
                deleteBtn: '.delete-model',
                cancelBtn: '.cancel-model',
                formSel: '#usaalo-model-form',
                modalSel: '#usaalo-model-modal',
                overlaySel: '#usaalo-model-overlay',
                getAction: 'usaalo_get_model',
                saveAction: 'usaalo_save_model',
                deleteAction: 'usaalo_delete_model',
                tabla: 'usaalo_models',
                dataTable: 'usaalo-models-table',
                usaalo_check_all: '#model-usaalo-check-all'
            });
        },

        /* =========================
         *   SIM & SERVICIO
         * ========================= */
        initSimServicio: function() {
            if ($.fn.DataTable) {
                let table = $('#sim_servicio_table').DataTable({
                    ajax: {
                        url: USAALO_Admin.ajaxurl,
                        type: "POST",
                        data: function(d) {
                            // Añadimos filtros personalizados
                            d.action = "get_sim_servicios";
                            d.nonce = USAALO_Admin.nonce;
                        }
                    },
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
                    processing: false,
                    serverSide: false,
                    columns: [
                        { data: "country_name" },
                        { data: "brand_name" },
                        { data: "model_name" },
                        { 
                            data: "sim_supported",
                            render: function(data, type, row) {
                                return '💳<input type="checkbox" class="toggle-service" data-field="sim_supported" data-model="'+row.model_id+'" data-country="'+row.country_id+'" '+(data == 1 ? "checked" : "")+'>';
                            },
                            orderable: false 
                        },
                        { 
                            data: "esim_supported",
                            render: function(data, type, row) {
                                return '📱<input type="checkbox" class="toggle-service" data-field="esim_supported" data-model="'+row.model_id+'" data-country="'+row.country_id+'" '+(data == 1 ? "checked" : "")+'>';
                            },
                            orderable: false 
                        },
                        { 
                            data: "data_supported",
                            render: function(data, type, row) {
                                return '📶<input type="checkbox" class="toggle-service" data-field="data_supported" data-model="'+row.model_id+'" data-country="'+row.country_id+'" '+(data == 1 ? "checked" : "")+'>';
                            },
                            orderable: false 
                        },
                        { 
                            data: "voice_supported",
                            render: function(data, type, row) {
                                return '📞<input type="checkbox" class="toggle-service" data-field="voice_supported" data-model="'+row.model_id+'" data-country="'+row.country_id+'" '+(data == 1 ? "checked" : "")+'>';
                            },
                            orderable: false 
                        },
                        { 
                            data: "sms_supported",
                            render: function(data, type, row) {
                                return '✉️<input type="checkbox" class="toggle-service" data-field="sms_supported" data-model="'+row.model_id+'" data-country="'+row.country_id+'" '+(data == 1 ? "checked" : "")+'>';
                            },
                            orderable: false 
                        },
                    ],
                    order: [[1, "asc"]],
                    responsive: true,
                    autoWidth: true,
                    paging: true,
                    searching: true, // usamos filtros personalizados
                    info: true,
                });

                // 🔎 Filtro por columnas personalizadas
                $('#filter-country').on('keyup change', function() {
                    table.column(0).search(this.value).draw();
                });

                $('#filter-brand').on('keyup change', function() {
                    table.column(1).search(this.value).draw();
                });

                $('#filter-model').on('keyup change', function() {
                    table.column(2).search(this.value).draw();
                });

                // Evento para guardar cambios
                $(document).on("change", ".toggle-service", function() {
                    let model_id = $(this).data("model");
                    let country_id = $(this).data("country");
                    let field = $(this).data("field");
                    let value = $(this).is(":checked") ? 1 : 0;

                    $.post(USAALO_Admin.ajaxurl, {
                        action: "usaalo_update_service",
                        nonce: USAALO_Admin.nonce,
                        model_id: model_id,
                        country_id: country_id,
                        field: field,
                        value: value
                    }, function(response) {
                        if (!response.success) {
                            alert("Error al guardar cambios");
                        }
                    });
                });
            }

        },

        /* =========================
         *   PLANS
         * ========================= */
        initPlans: function() {
            // if ($.fn.DataTable) {
            //     $('#usaalo-plans-table').DataTable({
            //         paging: true,
            //         searching: true,
            //         info: false,
            //         language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
            //         data: "id",
            //         render: function(data) {
            //                 return '<input type="checkbox" class="usaalo-check" value="'+data+'">';
            //             },
            //         orderable: false
            //     });
            // }

            const $form = $('#usaalo-plan-form');

            // Toggle entre simple y variable
            $('input[name="product_type"]').on('change', function() {
                if ($(this).val() === 'simple') {
                $('#simple-config').show().find('input').prop('required', true);
                $('#variable-config').hide().find('input').prop('required', false);
                } else {
                $('#simple-config').hide().find('input').prop('required', false);
                $('#variable-config').show().find('input').prop('required', true);
                }
            });

            // Checkbox "Todos los países"
            $('#todosPais').on('change', function() {
                if ($(this).is(':checked')) {
                $('#countries-select').val(null).trigger('change').prop('disabled', true);
                } else {
                $('#countries-select').prop('disabled', false);
                }
            });
            // Agregar rango dinámico
            $(document).on('click', '.add-range', function() {
                let index = $('#plan-ranges .plan-range-group').length;

                // Buscar el valor del max_days del último rango agregado
                let prevMax = 1;
                let $lastRange = $('#plan-ranges .plan-range-group').last();
                if ($lastRange.length) {
                    let lastMax = parseInt($lastRange.find('input[name$="[max_days]"]').val());
                    if (!isNaN(lastMax) && lastMax > 0) {
                        prevMax = lastMax;
                    }
                }

                let rangeHtml = `
                    <div class="plan-range-group">
                        <input type="number" name="ranges[${index}][min_days]" value="${prevMax}" placeholder="Desde" min="1" step="1" readonly>
                        <input type="number" name="ranges[${index}][max_days]" placeholder="Hasta" min="1" step="1" required>
                        <input type="number" name="ranges[${index}][price]" placeholder="Precio/día" step="0.01" min="0" required>
                        <button type="button" class="remove-range">&times;</button>
                    </div>
                `;
                $('#plan-ranges').append(rangeHtml);
            });

            // Eliminar rango
            $(document).on('click', '.remove-range', function() {
                $(this).closest('.plan-range-group').remove();
            });

            // === Validar campos numéricos ===
            $(document).on('input', 'input[type=number]', function(){
                let val = $(this).val();
                if($(this).attr('step') == '1'){ // enteros
                    val = val.replace(/\D/g,'');
                } else { // decimales WC
                    val = val.replace(/[^0-9\.]/g,'');
                    // permitir solo un punto decimal
                    const parts = val.split('.');
                    if(parts.length > 2){
                        val = parts[0] + '.' + parts[1];
                    }
                }
                $(this).val(val);
            });






            // === Enviar formulario AJAX ===
            $form.on('submit', function(e){
                e.preventDefault();

                // Validaciones básicas
                const type = $('input[name="product_type"]:checked').val();
                if(type === 'simple'){
                    if(!$('#simple_price').val() || parseFloat($('#simple_price').val()) <= 0){
                        alert('Ingresa un precio válido para el producto simple');
                        return;
                    }
                } else {
                    let valid = true;
                    let prev_max = 0;
                    $('#plan-ranges .plan-range-group').each(function(i){
                        const min = parseInt($(this).find('input[name*="[min_days]"]').val());
                        const max = parseInt($(this).find('input[name*="[max_days]"]').val());
                        const price = parseFloat($(this).find('input[name*="[price]"]').val());

                        if(isNaN(min) || isNaN(max) || isNaN(price) || min <= 0 || max <= 0 || price <= 0){
                            valid = false;
                        }
                        if(min < prev_max){
                            alert(`Rango ${i+1} comienza antes del final del rango anterior`);
                            valid = false;
                            return false;
                        }
                        if(min > max){
                            alert(`Rango ${i+1} "Desde" no puede ser mayor que "Hasta"`);
                            valid = false;
                            return false;
                        }
                        prev_max = max;
                    });
                    if(!valid){
                        return;
                    }
                    if(!$('#max_price').val() || parseFloat($('#max_price').val()) <= 0){
                        alert('Ingresa un precio máximo válido');
                        return;
                    }
                }


                let data = $(this).serializeArray();

                // Añadir manualmente action y nonce
                data.push(
                    { name: 'action', value: 'usaalo_save_wc_product' },
                    { name: 'nonce', value: USAALO_Admin.nonce }
                );

                console.log(data)

                $.post(USAALO_Admin.ajaxurl, data, function(res){
                    if(res.success){
                        alert('Producto guardado correctamente');
                        location.reload();
                    } else {
                        alert(res.data || 'Error al guardar');
                    }
                }, 'json');

        });







function getFlagURL(code){ return 'https://flagcdn.com/'+code.toLowerCase()+'.svg'; }


        if ($.fn.select2) {
            let $select = $('#countries-select');
            let $modal = $select.closest('.usaalo-modal');

            if (!$select.length) return;

            $select.select2({
                placeholder: "Selecciona países",
                width: '100%',
                closeOnSelect: false,
                allowClear: true,
                dropdownParent: $modal.length ? $modal : undefined,
                ajax: {
                    url: USAALO_Admin.ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: 'get_countries_regions',
                            nonce: USAALO_Admin.nonce,
                            search: params.term || '',
                            product_id: $('#product_id').val() || 0
                        };
                    },
                    processResults: function(response) {
                        return { results: response.data || [] };
                    }
                },
                templateResult: function(state) {
                    if (!state.id) return state.text;
                    let flag = state.code ? `<img src="${getFlagURL(state.code)}" class="country-flag"/>` : '';
                    return $(`<span>${flag} ${state.text}</span>`);
                },
                templateSelection: function(state) {
                    if (!state.id) return state.text;
                    let flag = state.code ? `<img src="${getFlagURL(state.code)}" class="country-flag"/>` : '';
                    return $(`<span>${flag} ${state.text}</span>`);
                }
    });

        }

        // Checkbox “Todos”
        $('#todosPais').on('change', function() {
            let isChecked = $(this).is(':checked');
            $('#todos-check-icon').toggle(isChecked);
            $('#countries-select').prop('disabled', isChecked);
            $('#todos-check-icon').toggle($(this).is(':checked'));
        });

        // Media uploader de WP
        let mediaUploader;
        $('#upload_image_button').click(function(e) {
            e.preventDefault();
            if(mediaUploader){
                mediaUploader.open();
                return;
            }

            mediaUploader = wp.media.frames.file_frame = wp.media({
                title: 'Seleccionar imagen',
                button: { text: 'Usar esta imagen'},
                multiple: false
            });

            mediaUploader.on('select', function(){
                let attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#product_image_id').val(attachment.id);
                $('#image_preview img').attr('src', attachment.url).show();
            });

            mediaUploader.open();
        });



            this.crudEntity({
                addBtn: '.add-plan',
                editBtn: '.edit-plan',
                deleteBtn: '.delete-plan',
                cancelBtn: '.cancel-plan',
                formSel: '#usaalo-plan-form',
                modalSel: '#usaalo-plan-modal',
                overlaySel: '#usaalo-plan-overlay',
                getAction: 'usaalo_get_plan',
                saveAction: 'usaalo_save_plan',
                deleteAction: 'usaalo_delete_plan',
                tabla: 'usaalo_plan_country',
                dataTable: 'usaalo-plans-table',
                usaalo_check_all: '#plan-usaalo-check-all'
            });
        },

        /* =========================
         *   CRUD GENERIC HANDLER
         * ========================= */
        crudEntity: function(cfg) {
            // Abrir modal en blanco
            $(document).on('click', cfg.addBtn, () => this.openModal(cfg.modalSel, cfg.formSel, cfg.overlaySel));
            // Cancelar modal
            $(document).on('click', cfg.cancelBtn, () => this.closeModal(cfg.modalSel, cfg.formSel, cfg.overlaySel));

            // Editar
            $(document).on('click', cfg.editBtn, function(){
                $.post(USAALO_Admin.ajaxurl, {
                    action: cfg.getAction, id:$(this).data('id'), nonce:USAALO_Admin.nonce
                }, function(res){
                    if (res.success) {
                        for (const key in res.data) {
                            $(cfg.formSel + ' [name="'+key+'"]').val(res.data[key]).trigger('change');
                        }
                        App.openModal(cfg.modalSel, cfg.formSel, cfg.overlaySel);
                    } else alert(res.data || 'Error');
                }, 'json');
            });

            // Eliminar
            $(document).on('click', cfg.deleteBtn, function(){
                if (!confirm(USAALO_Admin.i18n.confirm_delete)) return;
                $.post(USAALO_Admin.ajaxurl, {
                    action: cfg.deleteAction, id:$(this).data('id'), nonce:USAALO_Admin.nonce
                }, function(res){
                    if (res.success) { alert(USAALO_Admin.i18n.deleted); location.reload(); }
                    else alert(res.data || 'Error');
                }, 'json');
            });

            // Guardar
            $(document).on('submit', cfg.formSel, function(e){
                e.preventDefault();
                const payload = $(this).serialize() + '&action=' + cfg.saveAction + '&nonce=' + USAALO_Admin.nonce;
                $.post(USAALO_Admin.ajaxurl, payload, function(res){
                    if (res.success) { alert(USAALO_Admin.i18n.saved); App.closeModal(cfg.modalSel, cfg.formSel, cfg.overlaySel); location.reload(); }
                    else alert(res.data || 'Error');
                }, 'json');
            });

            // Evento: seleccionar/deseleccionar todos
            $(document).on('change', cfg.usaalo_check_all, function () {
                let checked = this.checked;
                $('#'+cfg.dataTable+' .usaalo-check').prop('checked', checked);
            });

            // Si quieres que el "seleccionar todos" se actualice
            // cuando se desmarque uno individual
            $(document).on('change', '.usaalo-check', function () {
                let all = $('.usaalo-check').length;
                let checked = $('.usaalo-check:checked').length;
                $(cfg.usaalo-check-all).prop('checked', all === checked);
            });

            // Eliminar seleccionados
            $(document).on('click', '.usaalo-delete-selected', function () {
                let ids = [];
                $('#'+cfg.dataTable+' .usaalo-check:checked').each(function () {
                    ids.push($(this).val());
                });

                if (ids.length === 0) {
                    alert("Por favor, selecciona al menos un registro.");
                    return;
                }

                if (!confirm("¿Seguro que quieres eliminar los registros seleccionados?")) {
                    return;
                }

                $.ajax({
                    url: USAALO_Admin.ajaxurl,
                    type: "POST",
                    data: {
                        action: "usaalo_bulk_delete",
                        nonce: USAALO_Admin.nonce,
                        table: cfg.tabla, 
                        ids: ids
                    },
                    success: function (response) {
                        if (response.success) {
                            alert(response.data.message);
                            $('#'+cfg.dataTable).DataTable().ajax.reload();
                            $('#usaalo-check-all').prop('checked', false); // resetear checkbox maestro
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            });

        },

        openModal: function(modalSel, formSel, overlaySel) {
            if (overlaySel) $(overlaySel).fadeIn(150);
            $(modalSel).fadeIn(200);
            $(formSel).find('input,select,textarea').filter(':visible:first').focus();
        },

        closeModal: function(modalSel, formSel, overlaySel) {
            $(modalSel).fadeOut(150);
            if (overlaySel) $(overlaySel).fadeOut(200);
            $(formSel)[0].reset();
            $(formSel).find('select').trigger('change');
        }
    };

    $(function(){ App.init(); });

})(jQuery);
