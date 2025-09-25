<?php
    $config = get_option('usaalo_cotizador_config', []); // Obtiene todo el array
    $show_brand = isset($config['show_brand']) ? $config['show_brand'] : 1;
    $show_model = isset($config['show_model']) ? $config['show_model'] : 1;
?>
<div class="wrap">
    <h1><?php _e('Configuración de Marcas y Modelos de los dispositivos', 'usaalo-cotizador'); ?></h1>
    <p><?php _e('Gestiona planes, países, marcas y modelos para el cotizador', 'usaalo-cotizador'); ?></p>
    <div style="display:flex;align-items:center;gap:15px;">
        <h2><?php _e('Marcas', 'usaalo-cotizador'); ?></h2>
        <!-- Toggle Marca -->
        <label class="switch">
            <input type="checkbox" class="usaalo-toggle" data-key="show_brand" <?php checked($show_brand, 1); ?>>
            <span class="slider round"></span>
        </label>
        <span id="brand-status" style="font-weight:bold;"></span>
    </div>
        <button class="button add-brand"><?php _e('Agregar Marca','usaalo-cotizador');?></button>
        
        
    <table id="usaalo-brands-table" class="widefat striped">
        <thead><tr>
            <th></th>
            <th><?php _e('Nombre','usaalo-cotizador');?></th>
            <th><?php _e('Slug','usaalo-cotizador');?></th>
            <th><?php _e('Acciones','usaalo-cotizador');?></th>
        </tr></thead>
        <tbody>
            <tr>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        </tbody>
    </table>
    <button class="button button-danger usaalo-delete-selected" data-table="usaalo-brands-table">
        Eliminar seleccionados
    </button>
    <div style="display:flex;align-items:center;gap:15px;">
        <h2><?php _e('Modelos', 'usaalo-cotizador'); ?></h2>
        <!-- Toggle Modelo -->
        <label class="switch">
            <input type="checkbox" class="usaalo-toggle" data-key="show_model" <?php checked($show_model, 1); ?>>
            <span class="slider round"></span>
        </label>
        <span id="model-status" style="font-weight:bold;"></span>
    </div>
        <button class="button add-model"><?php _e('Agregar Modelo','usaalo-cotizador');?></button>
        
        
    <table id="usaalo-models-table" class="widefat striped">
        <thead><tr>
            <th></th>
            <th><?php _e('Marca','usaalo-cotizador');?></th>
            <th><?php _e('Nombre','usaalo-cotizador');?></th>
            <th><?php _e('slug','usaalo-cotizador');?></th>
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
    <button class="button button-danger usaalo-delete-selected" data-table="usaalo-model-tables">
        Eliminar seleccionados
    </button>
    
    <!-- Modal + overlay: Brand editor -->
    <div id="usaalo-brand-overlay" class="usaalo-overlay"></div>
    <div id="usaalo-brand-modal" class="usaalo-modal">
        <h2><?php _e('Marca(s)','usaalo-cotizador');?></h2>
        <form id="usaalo-brand-form">
            <input type="hidden" id="brand-id" name="id" value="">
            
            <p>
                <label><?php _e('Nombre','usaalo-cotizador');?></label>
                <input type="text" id="brand-name" name="name" placeholder="Marca o marcas separadas por coma" required>
            </p>

            <p>
                <button class="button button-primary" type="submit"><?php _e('Guardar','usaalo-cotizador');?></button>
                <button class="button cancel-brand" type="button"><?php _e('Cancelar','usaalo-cotizador');?></button>
            </p>
        </form>
    </div>

    <!-- Modal + overlay: Model editor -->
    <div id="usaalo-model-overlay" class="usaalo-overlay"></div>
    <div id="usaalo-model-modal" class="usaalo-modal">
        <h2><?php _e('Modelo(s) de la Marca','usaalo-cotizador');?></h2>
        <form id="usaalo-model-form">
            <input type="hidden" id="model-id" name="id" value="">
            <p>
                <label><?php _e('Marca','usaalo-cotizador');?></label>
                <select id="model-brand" name="brand_id" required>
                    <?php foreach($brands as $b): ?>
                        <option value="<?php echo intval($b['id']); ?>">
                            <?php echo esc_html($b['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label><?php _e('Nombre de modelo(s)','usaalo-cotizador');?></label>
                <input type="text" id="model-name" name="name" placeholder="Modelo o modelos separados por coma" required>
            </p>

            <p>
                <button class="button button-primary" type="submit"><?php _e('Guardar','usaalo-cotizador');?></button>
                <button class="button cancel-model" type="button"><?php _e('Cancelar','usaalo-cotizador');?></button>
            </p>
        </form>
    </div>
    
</div>

