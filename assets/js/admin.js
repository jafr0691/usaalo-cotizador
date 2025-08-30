jQuery(document).ready(function($){
    // Abrir modal al editar
    $('.edit-rule').on('click', function(){
        var id = $(this).data('id');
        $.post(UA_Rules.ajaxurl, {action:'ua_get_rule', id:id, nonce:UA_Rules.nonce}, function(resp){
            if(resp){
                $('#rule-id').val(resp.id);
                $('#rule-plan').val(resp.plan_id);
                $('#rule-from').val(resp.range_from);
                $('#rule-to').val(resp.range_to);
                $('#rule-price').val(resp.price);
                $('#ua-rule-modal').fadeIn();
            }
        });
    });

    // Cancelar modal
    $('.cancel-rule').on('click', function(){
        $('#ua-rule-modal').fadeOut();
    });

    // Guardar regla
    $('#ua-rule-form').on('submit', function(e){
        e.preventDefault();
        var from = parseInt($('#rule-from').val());
        var to = parseInt($('#rule-to').val());
        if(from > to){
            alert('Rango desde no puede ser mayor que hasta');
            return false;
        }

        var data = $(this).serialize() + '&action=ua_save_rule&nonce='+UA_Rules.nonce;
        $.post(UA_Rules.ajaxurl, data, function(resp){
            if(resp.success){
                alert(resp.data);
                location.reload(); // Refrescar la tabla
            }else{
                alert(resp.data);
            }
        });
    });
});
