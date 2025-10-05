<?php
    $config = get_option('usaalo_cotizador_config', []); // Obtiene todo el array
    $select_pais = isset($config['select_pais']) ? $config['select_pais'] : 1;
?>
<div class="wrap">
    <h1><?php _e('Configuración de Paises', 'usaalo-cotizador'); ?></h1>
    <p><?php _e('Gestiona planes, países, marcas y modelos para el cotizador', 'usaalo-cotizador'); ?></p>
    <div style="display:flex;align-items:center;gap:15px;">
        <h2><?php _e('Países', 'usaalo-cotizador'); ?></h2>
        <!-- Toggle Marca -->
        <label class="switch">
            <input type="checkbox" class="usaalo-toggle" data-key="select_pais" <?php checked($select_pais, 1); ?>>
            <span class="slider round"></span>
        </label>
        <span id="pais-status" style="font-weight:bold;"></span>
    </div>
    <button class="button add-country"><?php _e('Agregar País','usaalo-cotizador');?></button>
    <table id="usaalo-countries-table" class="widefat striped">
        <thead><tr>
            <th></th>
            <th><?php _e('Code','usaalo-cotizador');?></th>
            <th><?php _e('Nombre','usaalo-cotizador');?></th>
            <th><?php _e('Region','usaalo-cotizador');?></th>
            <th><?php _e('Acciones','usaalo-cotizador');?></th>
        </tr></thead>
        <tbody>
            <tr>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        </tbody>
    </table>
    <button class="button button-danger usaalo-delete-selected" data-table="usaalo-countries-table">
        Eliminar seleccionados
    </button>
    
    <!-- Modal: Country editor -->
    <div id="usaalo-country-overlay" class="usaalo-overlay"></div>
    <div id="usaalo-country-modal" class="usaalo-modal">
        <h2><?php _e('Pais(es)','usaalo-cotizador');?></h2>
        <form id="usaalo-country-form">
            <input type="hidden" id="country-id" name="id" value="">
            <p><label><?php _e('Code','usaalo-cotizador');?></label>
                <input type="text" id="country-code" name="code" required>
            </p>
            <p><label><?php _e('Nombre','usaalo-cotizador');?></label>
                <input type="text" id="country-name" name="name" required>
            </p>
            <p><label><?php _e('Region','usaalo-cotizador');?></label>
                <input type="text" id="country-region" name="region" required>
            </p>
            <p>
                <button class="button button-primary" type="submit"><?php _e('Guardar','usaalo-cotizador');?></button>
                <button class="button cancel-country" type="button"><?php _e('Cancelar','usaalo-cotizador');?></button>
            </p>
        </form>
    </div>
</div>