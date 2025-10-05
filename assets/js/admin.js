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
                    this.active(USAALO_Admin.i18n.multiple, USAALO_Admin.i18n.simple);
                    break;
                case 'usaalo-cotizador_page_usaalo-cotizador-brands-models':
                    this.initBrandsModels();
                    this.active();
                    break;
                case 'usaalo-cotizador_page_usaalo-cotizador-sim-servicio':
                    this.initSimServicio();
                    this.active();
                    break;
                case 'usaalo-cotizador_page_usaalo-cotizador-plans':
                    this.initPlans();
                    this.active(USAALO_Admin.i18n.horizontal, USAALO_Admin.i18n.vertical);
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
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json',
                                emptyTable: "No se encontraron registros en la lista" 
                            },
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

            // Country
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
                usaalo_check_all: '#country-usaalo-check-all',
                delete_selected: '.country.usaalo-delete-selected'
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
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json',
                                emptyTable: "No se encontraron registros en la lista"
                     },
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
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: USAALO_Admin.ajaxurl,
                        type: "POST",
                        data: { action: "get_models", nonce: USAALO_Admin.nonce },
                        error: function(xhr, error, thrown) {
                            console.error("AJAX Error:", error, xhr.responseText);
                        }
                    },
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json',
                                emptyTable: "No se encontraron registros en la lista"
                    },
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
                usaalo_check_all: '#brand-usaalo-check-all',
                delete_selected: '.brand.usaalo-delete-selected'
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
                usaalo_check_all: '#model-usaalo-check-all',
                delete_selected: '.model.usaalo-delete-selected'
            });
        },

        /* =========================
         *   SIM & SERVICIO
         * ========================= */
        initSimServicio: function() {
            if ($.fn.DataTable) {
                let table = $('#sim_servicio_table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: USAALO_Admin.ajaxurl,
                        type: "POST",
                        data: function(d) {
                            d.action = "get_sim_servicios";
                            d.nonce = USAALO_Admin.nonce;
                            d.filter_country = $('#filter-country').val();
                            d.filter_brand   = $('#filter-brand').val();
                            d.filter_model   = $('#filter-model').val();
                        },
                        error: function(xhr, error, thrown) {
                            console.error("AJAX Error:", error, xhr.responseText);
                        }
                    },
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json',
                                emptyTable: "No se encontraron registros en la lista"
                            },
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
                                return '📶<input type="checkbox" class="toggle-service" data-field="esim_supported" data-model="'+row.model_id+'" data-country="'+row.country_id+'" '+(data == 1 ? "checked" : "")+'>';
                            },
                            orderable: false 
                        },
                        { 
                            data: "data_supported",
                            render: function(data, type, row) {
                                return '📡<input type="checkbox" class="toggle-service" data-field="data_supported" data-model="'+row.model_id+'" data-country="'+row.country_id+'" '+(data == 1 ? "checked" : "")+'>';
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
                    searching: true,
                    info: true,
                    // Añadir checkbox en el header
                    initComplete: function () {
                        let api = this.api();

                        // Lista de campos que tienen checkboxes
                        let toggleFields = [
                            {colIndex: 3, field: "sim_supported", label: "SIM"},
                            {colIndex: 4, field: "esim_supported", label: "eSIM"},
                            {colIndex: 5, field: "data_supported", label: "DATOS"},
                            {colIndex: 6, field: "voice_supported", label: "VOZ"},
                            {colIndex: 7, field: "sms_supported", label: "SMS"}
                        ];

                        toggleFields.forEach(cfg => {
                            $(api.column(cfg.colIndex).header()).html(
                                '<input type="checkbox" class="toggle-all-col" data-field="'+cfg.field+'">'+cfg.label
                            );
                        });
                        // Agregar el checkbox de seleccionar todos en el header
                        $('#usaalo-countries-table thead th').eq(0).html(
                            '<input type="checkbox" id="country-usaalo-check-all">'
                        );

                    }
                });

                $('#filter-country, #filter-brand, #filter-model').on('keyup change', function() {
                    table.draw();
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

                // Evento para seleccionar/deseleccionar toda la columna visible
                $(document).on("change", ".toggle-all-col", function() {
                    let field = $(this).data("field");
                    let checked = $(this).is(":checked");

                    let updates = [];

                    // Solo afecta a los checkboxes visibles en la tabla actual
                    $('#sim_servicio_table')
                        .find('input.toggle-service[data-field="'+field+'"]')
                        .each(function() {
                            if ($(this).is(":checked") !== checked) {
                                $(this).prop("checked", checked);
                                updates.push({
                                    model_id: $(this).data("model"),
                                    country_id: $(this).data("country"),
                                    field: field,
                                    value: checked ? 1 : 0
                                });
                            }
                        });

                    if (updates.length > 0) {
                        $.post(USAALO_Admin.ajaxurl, {
                            action: "usaalo_bulk_update_service",
                            nonce: USAALO_Admin.nonce,
                            updates: updates
                        }, function(response) {
                            if (response.success) {
                                console.log("✅ Cambios guardados correctamente");
                            } else {
                                alert("❌ Error: " + (response.data?.msg || "Error desconocido"));
                            }
                        });
                    }
                });

            }

        },

        /* =========================
         *   PLANS
         * ========================= */
        initPlans: function() {


            
            if ($.fn.DataTable) {

                    
                $('#usaalo-plans-table').DataTable({
                    ajax: {
                        url: USAALO_Admin.ajaxurl,
                        type: 'POST',
                        data: {
                            action: "get_plan_data",
                            nonce: USAALO_Admin.nonce
                        },
                        dataSrc: function(json){
                            return json.success ? json.data : [];
                        }
                    },
                    columns: [
                        { 
                            data: "id",
                            render: function(data){
                                return `<input type="checkbox" class="usaalo-check" value="${data}"/>`;
                            },
                            orderable: false
                        },
                        { 
                            data: "image",
                            orderable: false,
                            render: function(data){
                                if(!data) return '-';
                                return `<img src="${data}" style="max-width:50px;max-height:50px"/>`;
                            }
                        },
                        { data: "name" },
                        { 
                            data: 'id',
                            render: function(data, type, row) {
                                return `#${row.id}`;
                            }
                        },
                        { 
                            data: "typeProduct"
                        },
                        {
                            data: "countries",
                            render: function(data, type, row) {
                                if (!row.countries_count || row.countries_count === 0) {
                                    return '-';
                                }

                                if (row.countries_count === row.total_countries) {
                                    return '<span class="badge bg-info">Todos los países</span>';
                                }

                                if (row.countries_count > 5) {
                                    let visible = row.countries_list.slice(0,5).join(", ");
                                    let hidden  = row.countries_list.slice(5).join(", ");
                                    return `
                                        ${visible}
                                        <span class="more-countries" style="display:none;">, ${hidden}</span>
                                        <a href="#" class="toggle-countries">...leer más</a>
                                    `;
                                }

                                return data;
                            }
                        },
                        { 
                            data: "price",
                            render: function(data){
                                return data ? data+' '+USAALO_Admin.currency_symbol : '-';
                            }
                        },
                        { 
                            data: "active",
                            render: function(val){
                                return val ? "Sí" : "No";
                            }
                        },
                        { 
                            data: "id",
                            render: function(data){
                                return `
                                    <button class="button edit-plan" data-id="${data}">Editar</button>
                                    <button class="button delete-plan" data-id="${data}">Eliminar</button>
                                `;
                            },
                            orderable: false
                        }
                    ],
                    order: [[2,'asc']],
                    responsive: true,
                    processing: false,
                    serverSide: false,
                    autoWidth: true,
                    language: {
                        url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json",
                        emptyTable: "No se encontraron registros en la lista"
                    },
                    initComplete: function () {
                        // Insertar checkbox en cabecera
                        $('#usaalo-plans-table thead th').eq(0).html(
                            '<input type="checkbox" id="plan-usaalo-check-all">'
                        );

                        // Manejar "seleccionar todos"
                        $('#plan-usaalo-check-all').on('change', function(){
                            let checked = $(this).is(':checked');
                            $('.usaalo-check').prop('checked', checked);
                        });

                        // Si un checkbox de fila cambia → actualizar el estado del header
                        $('#usaalo-plans-table tbody').on('change', '.usaalo-check', function(){
                            let allChecked = $('.usaalo-check').length === $('.usaalo-check:checked').length;
                            $('#plan-usaalo-check-all').prop('checked', allChecked);
                        });
                    }
                });


                $('#usaalo-plans-table').on('click', '.toggle-countries', function(e) {
                    e.preventDefault();
                    let $link = $(this);
                    let $hidden = $link.prev('.more-countries');

                    if ($hidden.is(':visible')) {
                        $hidden.hide();
                        $link.text('...leer más');
                    } else {
                        $hidden.show();
                        $link.text('leer menos');
                    }
                });


                $(document).on('click', '.edit-plan', function(){
                    let id = $(this).data('id');

                    $.post(USAALO_Admin.ajaxurl, {
                        action: 'get_product_data',
                        nonce: USAALO_Admin.nonce,
                        product_id: id
                    }, function(res){
                        if(!res.success){
                            alert(res.data.message || 'Error al obtener producto');
                            return;
                        }

                        let p = res.data;

                        // General
                        $('#product_id').val(p.id);
                        $('#nameWC').val(p.name);
                        $('#descriptionWC').val(p.description);

                        // Tipo de producto (radios)
                        $(`input[name="product_type"][value="${p.type}"]`).prop('checked', true).trigger('change');

                        // Precio según tipo
                        if (p.type === 'simple') {
                            $('#simple_price').val(p.price);
                            $('#max_price').val(''); // limpiar si es simple
                        } else if (p.type === 'variable') {
                            $('#max_price').val(p.price);
                            $('#simple_price').val(''); // limpiar si es variable
                        }

                        // Activo
                        $('input[name="active"]').prop('checked', p.active);

                        // Imagen
                        if (p.image_id && p.image_url) {
                            $('#image_preview img').attr('src', p.image_url).css('display', 'block');
                            $('#product_image_id').val(p.image_id);
                        } else {
                            $('#image_preview img').css('display', 'none');
                            $('#product_image_id').val('false');
                        }

                        // Países (select2 múltiple)
                        $('#countries-select').val(p.countries).trigger('change');

                        // Rangos (si es variable)
                        if (p.type === 'variable' && p.ranges) {
                            let $container = $('#plan-ranges');

                            // 🔹 Resetear: mantener solo el primer grupo
                            let $firstGroup = $container.find('.plan-range-group:first');
                            $container.find('.plan-range-group').not(':first').remove();

                            // 🔹 Si hay al menos un rango, rellenar el primero
                            if (p.ranges.length > 0) {
                                let r0 = p.ranges[0];
                                $firstGroup.find('input[name="ranges[0][min_days]"]').val(r0.min_days);
                                $firstGroup.find('input[name="ranges[0][max_days]"]').val(r0.max_days);
                                $firstGroup.find('input[name="ranges[0][price]"]').val(r0.price);
                            } else {
                                // Si no hay rangos, limpiar el primero
                                $firstGroup.find('input').val('');
                                $firstGroup.find('input[name="ranges[0][min_days]"]').val(1); // mantener el mínimo en 1
                            }

                            // 🔹 Los demás rangos se agregan
                            for (let i = 1; i < p.ranges.length; i++) {
                                let r = p.ranges[i];
                                $container.append(`
                                    <div class="plan-range-group">
                                        <input type="number" name="ranges[${i}][min_days]" value="${r.min_days}" placeholder="Desde" min="1" step="1" readonly>
                                        <input type="number" name="ranges[${i}][max_days]" value="${r.max_days}" placeholder="Hasta" min="1" step="1">
                                        <input type="number" name="ranges[${i}][price]" value="${r.price}" placeholder="Precio/día" step="0.01" min="0">
                                        <button type="button" class="remove-range">×</button>
                                    </div>
                                `);
                            }
                        }


                        // Abrir modal
                        App.openModal('#usaalo-plan-modal', '#usaalo-plan-form', '#usaalo-plan-overlay');

                    });
                });

            }

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

                // Buscar el último rango
                let $lastRange = $('#plan-ranges .plan-range-group').last();

                if ($lastRange.length) {
                    let lastMax = $lastRange.find('input[name$="[max_days]"]').val();
                    let lastPrice = $lastRange.find('input[name$="[price]"]').val();

                    // Validar que los dos estén llenos
                    if (!lastMax || !lastPrice) {
                        alert("Debes llenar 'Hasta' y 'Precio/día' antes de agregar otro rango.");
                        return; // 🚫 Detiene la creación del nuevo campo
                    }
                }

                // Calcular min_days en base al último max_days
                let prevMax = 1;
                if ($lastRange.length) {
                    let lastMax = parseInt($lastRange.find('input[name$="[max_days]"]').val());
                    if (!isNaN(lastMax) && lastMax > 0) {
                        prevMax = lastMax;
                    }
                }

                // Crear el nuevo rango
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

            if ($select.length) {
                $select.select2({
                    placeholder: "Selecciona países",
                    width: '100%',
                    closeOnSelect: false,
                    allowClear: true,
                    dropdownParent: $modal.length ? $modal : undefined,
                    data: USAALO_Admin.countries_regions,
                    matcher: function(params, data) {
                        // sin búsqueda -> mostrar todo
                        if ($.trim(params.term) === '') {
                            return data;
                        }

                        let term = params.term.toLowerCase();

                        // Si es grupo (region)
                        if (data.children && data.children.length) {
                            // si la región coincide -> devolver todo el grupo con sus hijos
                            if (data.text.toLowerCase().includes(term)) {
                                return data;
                            }

                            // si alguno de los hijos coincide -> devolver solo los hijos que coincidan
                            let filteredChildren = [];
                            $.each(data.children, function(i, child) {
                                if (child.text.toLowerCase().includes(term)) {
                                    filteredChildren.push(child);
                                }
                            });

                            if (filteredChildren.length) {
                                let modifiedData = $.extend({}, data, true);
                                modifiedData.children = filteredChildren;
                                return modifiedData;
                            }

                            // nada coincide
                            return null;
                        }

                        // si es hijo (país)
                        if (data.text.toLowerCase().includes(term)) {
                            return data;
                        }

                        return null;
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

        // Plan
            this.crudEntity({
                addBtn: '.add-plan',
                cancelBtn: '.cancel-plan',
                formSel: '#usaalo-plan-form',
                modalSel: '#usaalo-plan-modal',
                overlaySel: '#usaalo-plan-overlay',
                dataTable: 'usaalo-plans-table',
                usaalo_check_all: '#plan-usaalo-check-all',
                deleteBtn: '.delete-plan',
                tabla: 'usaalo_product_country',
                deleteAction: 'delete_products_with_countries',
                editBtn: 'false',
                delete_selected: 'false'
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
                $(cfg.usaalo_check_all).prop('checked', all === checked);
            });

            // Eliminar seleccionados
            $(document).on('click', cfg.delete_selected, function () {
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
                let action = "usaalo_bulk_delete";
                if(cfg.deleteAction == 'delete_products_with_countries'){
                    action = cfg.deleteAction
                }

                $.ajax({
                    url: USAALO_Admin.ajaxurl,
                    type: "POST",
                    dataType: "json", // fuerza JSON para evitar errores raros
                    data: {
                        action: action,
                        nonce: USAALO_Admin.nonce,
                        table: cfg.tabla, 
                        ids: ids
                    },
                    success: function (response) {
                        if (response.success) {
                            alert(response.data.message);
                            if(cfg.dataTable === 'usaalo-brands-table'){
                                $('#usaalo-models-table').DataTable().ajax.reload();
                            }
                            $('#'+cfg.dataTable).DataTable().ajax.reload();
                            $('#usaalo-check-all').prop('checked', false); // resetear checkbox maestro
                        } else {
                            alert("Error en la respuesta: " + response.data.message);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("AJAX Error:", status, error);
                        console.error("Respuesta del servidor:", xhr.responseText);
                        alert("Error en el servidor: " + error + "\nRevisa la consola para más detalles.");
                    },
                    complete: function () {
                        console.log("AJAX finalizado (éxito o error).");
                    }
                });
            });

        },
        openModal: function(modalSel, formSel, overlaySel, isEdit = false) {
            if (overlaySel) $(overlaySel).fadeIn(150);
            $(modalSel).fadeIn(200);
            // 🔹 Focus en el primer input visible
            $(formSel).find('input,select,textarea').filter(':visible:first').focus();
        },

        closeModal: function(modalSel, formSel, overlaySel) {
            $(modalSel).fadeOut(150);
            if (overlaySel) $(overlaySel).fadeOut(200);

            $(formSel)[0].reset();
            if(formSel == '#usaalo-plan-form'){
                // 🔹 Resetear: mantener solo el primer grupo
                $('#plan-ranges').find('.plan-range-group').not(':first').remove();
                $('#image_preview img').css('display', 'none');
                $('#product_image_id').val('false');
                $('#simple-config').show().find('input').prop('required', true);
                $('#variable-config').hide().find('input').prop('required', false);
            }
            // 🔹 Limpiar inputs hidden manualmente
            $(formSel).find('input[type="hidden"]').val('');
            $(formSel).find('select').trigger('change');
        },
        updateStatus: function(element, standar = USAALO_Admin.i18n.mostrar, cambio = USAALO_Admin.i18n.ocultar) {
            const shortName = element.data("key").split('_')[1];
            const status = document.getElementById(shortName + "-status");
            status.textContent = element.is(":checked") ? standar : cambio;
        },

        active: function(standar = USAALO_Admin.i18n.mostrar, cambio = USAALO_Admin.i18n.ocultar) {
            const self = this; 

            // Mostrar estado al cargar
            jQuery(".usaalo-toggle").each(function(){
                const key = $(this).data("key");
                self.updateStatus($(this), standar, cambio);
            });

            // Actualizar estado al cambiar
            jQuery(document).on("change", ".usaalo-toggle", function(){
                let key   = $(this).data("key");
                let value = $(this).is(":checked") ? 1 : 0;

                self.updateStatus($(this), standar, cambio);

                // Si desactivo marca → modelo también se desactiva automáticamente
                if(key === "show_brand" && value === 0){
                    let $modelo = $('.usaalo-toggle[data-key="show_model"]');
                    $modelo.prop("checked", false).trigger("change");
                }

                $.ajax({
                    url: USAALO_Admin.ajaxurl,
                    method: "POST",
                    data: {
                        action: "usaalo_update_config_toggle",
                        nonce: USAALO_Admin.nonce,
                        key: key,
                        value: value
                    },
                    success: function(res){
                        if(res.success){
                            console.log("✅ Configuración actualizada:", key, value);
                        } else {
                            console.error("❌ Error al actualizar:", res.data);
                        }
                    },
                    error: function(xhr){
                        console.error("⚠️ Error AJAX:", xhr.responseText);
                    }
                });
            });
        }


    };

    $(function(){ App.init(); });

})(jQuery);
