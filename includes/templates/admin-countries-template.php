<div class="wrap">
    <h1><?php _e('Configuración de Paises', 'usaalo-cotizador'); ?></h1>
    <p><?php _e('Gestiona planes, países, marcas y modelos para el cotizador', 'usaalo-cotizador'); ?></p>

    <h2><?php _e('Países', 'usaalo-cotizador'); ?></h2>
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