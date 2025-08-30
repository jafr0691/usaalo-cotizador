(function($){
  const $wiz = $('#usac-wizard');
  if (!$wiz.length) return;

  // Estado local
  const state = {
    countries: [],
    device: { brand_id:null, model_id:null, eid:'', imei:'' },
    sim_type: null,
    services: { data:true, voice:false, sms:false, inbound_colombia:'no' },
    dates: { start:'', end:'', days:7 },
    quote: null,
    compat: null
  };

  // Helpers UI
  function go(step){
    $('.usac-step').removeClass('is-active');
    $('.usac-step[data-step="'+step+'"]').addClass('is-active');
    $('.usac-panel').attr('hidden', true);
    $('.usac-panel[data-step="'+step+'"]').attr('hidden', false);
  }
  function enableNext(step, enable){
    $('.usac-panel[data-step="'+step+'"] .usac-next').prop('disabled', !enable);
  }

  // Select2
  const ajaxCfg = (action)=>({
    url: USAC.ajax,
    dataType: 'json',
    delay: 200,
    data: params => ({ action, nonce: USAC.nonce, q: params.term || '' }),
    processResults: data => data,
    cache: true
  });

  // Países (multi)
  $('#usac-countries').select2({
    ajax: ajaxCfg('usac_get_countries'),
    placeholder: 'Selecciona país(es)',
    width: 'resolve'
  }).on('change', function(){
    state.countries = ($(this).val()||[]);
    validateStep1();
  });

  // Marca
  $('#usac-brand').select2({
    ajax: ajaxCfg('usac_get_brands'),
    placeholder: 'Marca',
    width: 'resolve'
  }).on('change', function(){
    state.device.brand_id = $(this).val();
    // reset modelo
    $('#usac-model').val(null).trigger('change');
    state.device.model_id = null;
    validateStep1();
  });

  // Modelos dependientes
  $('#usac-model').select2({
    placeholder: 'Modelo',
    width: 'resolve',
    ajax: {
      url: USAC.ajax,
      dataType: 'json',
      delay: 200,
      data: params => ({
        action: 'usac_get_models',
        nonce: USAC.nonce,
        brand_id: $('#usac-brand').val(),
        q: params.term || ''
      }),
      processResults: data => data
    }
  }).on('change', function(){
    state.device.model_id = $(this).val();
    checkCompat();
  });

  // Compatibilidad
  function checkCompat(){
    if (!state.countries.length || !state.device.model_id) return;
    $.post(USAC.ajax, {
      action:'usac_check_compat', nonce:USAC.nonce,
      countries: state.countries, brand_id: state.device.brand_id, model_id: state.device.model_id
    }, res => {
      if (!res || !res.success) return;
      state.compat = res.data;
      const box = $('#usac-compat-state');
      let txt = '';
      if (state.compat.overall === 'not_compatible') {
        txt = '❌ No compatible.';
      } else if (state.compat.overall === 'data_only') {
        txt = '⚠️ Solo datos. Voz/SMS desactivados.';
        $('#svc-voice, #svc-sms').prop('checked', false).prop('disabled', true);
      } else {
        txt = '✅ Compatible.';
        $('#svc-voice, #svc-sms').prop('disabled', false);
      }
      box.text(txt);
      validateStep1();
    }, 'json');
  }

  function validateStep1(){
    const ok = state.countries.length > 0 && !!state.device.brand_id && !!state.device.model_id;
    enableNext(1, ok);
  }

  // Paso 2 – SIM y servicios
  $wiz.on('change','input[name="usac-simtype"]', function(){
    state.sim_type = this.value;
    $('#usac-esim-fields').prop('hidden', state.sim_type!=='esim');
    $('#usac-physical-fields').prop('hidden', state.sim_type!=='physical');
    validateStep2();
  });

  $('#usac-eid').on('input', function(){ state.device.eid = $(this).val(); validateStep2(); });
  $('#usac-imei-esim').on('input', function(){ state.device.imei = $(this).val(); });
  $('#usac-imei-phy').on('input', function(){ state.device.imei = $(this).val(); validateStep2(); });

  $('#svc-voice').on('change', function(){
    state.services.voice = this.checked;
    $('#usac-inbound-col').prop('hidden', !this.checked);
  });
  $('#svc-sms').on('change', function(){ state.services.sms = this.checked; });
  $('#usac-inbound-colombia').on('change', function(){ state.services.inbound_colombia = $(this).val(); });

  function validateStep2(){
    if (!state.sim_type) return enableNext(2,false);
    // reglas mínimas
    if (state.sim_type==='esim'){
      if (state.compat && !state.compat.esim_supported_all) return enableNext(2,false);
      const ok = (state.device.eid || '').trim().length >= 10; // básico
      enableNext(2, ok);
    } else {
      const ok = (state.device.imei || '').trim().length >= 10;
      enableNext(2, ok);
    }
  }

  // Paso 3 – Fechas/Días
  $('#usac-start').on('change', function(){ state.dates.start = $(this).val(); recalcDates(); });
  $('#usac-end').on('change', function(){ state.dates.end   = $(this).val(); recalcDates(); });
  $('#usac-days').on('input', function(){ state.dates.days  = Math.max(1, parseInt($(this).val()||'1')); recalcDates(); });

  function recalcDates(){
    // Validaciones básicas fin >= inicio
    const s = state.dates.start ? new Date(state.dates.start) : null;
    const e = state.dates.end ? new Date(state.dates.end) : null;
    if (s && e && e < s){
      $('#usac-activation').text('⚠️ La fecha fin no puede ser menor a la de inicio.');
      enableNext(3,false);
      return;
    } else {
      $('#usac-activation').text('');
    }
    quoteNow();
  }

  function quoteNow(){
    const payload = { countries:state.countries, sim_type:state.sim_type, services:state.services, dates:state.dates, device:state.device };
    $.post(USAC.ajax, { action:'usac_quote', nonce:USAC.nonce, payload }, res=>{
      if (!res || !res.success) { enableNext(3,false); return; }
      state.quote = res.data;
      $('#usac-quote').text(`Total: ${USAC.currency} ${state.quote.total} (${state.quote.days} días)`);
      enableNext(3, state.quote.total > 0);
    }, 'json');
  }

  // Navegación
  $wiz.on('click','.usac-next', function(){
    go($(this).data('next'));
  });
  $wiz.on('click','.usac-prev', function(){
    go($(this).data('prev'));
  });

  // Resumen + enviar al carrito
  $('#usac-to-cart').on('click', function(){
    const payload = { countries:state.countries, sim_type:state.sim_type, services:state.services, dates:state.dates, device:state.device, quote:state.quote };
    const masked = { ...payload, device:{...payload.device, eid: payload.device.eid ? '****'+payload.device.eid.slice(-4):'', imei: payload.device.imei ? '****'+payload.device.imei.slice(-4):'' } };
    $('#usac-summary').text(JSON.stringify(masked, null, 2));

    $.post(USAC.ajax, { action:'usac_add_to_cart', nonce:USAC.nonce, payload }, res=>{
      if (res && res.success && res.data.redirect) window.location = res.data.redirect;
    }, 'json');
  });

  // Init paso 1
  go(1);
})(jQuery);
