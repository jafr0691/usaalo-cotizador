<div class="wrap">
    <h1><?php _e('Configuración de Planes para los productos SIM', 'usaalo-cotizador'); ?></h1>
    <p><?php _e('Gestiona planes, países, marcas y modelos para el cotizador', 'usaalo-cotizador'); ?></p>

    <h2><?php _e('Planes', 'usaalo-cotizador'); ?></h2>
    <button class="button add-plan"><?php _e('Agregar Plan','usaalo-cotizador');?></button>
    <table id="usaalo-plans-table" class="widefat striped">
        <thead>
            <tr>
                <th><?php _e('Nombre','usaalo-cotizador');?></th>
                <th><?php _e('Producto WC','usaalo-cotizador');?></th>
                <th><?php _e('Rangos de días','usaalo-cotizador');?></th>
                <th><?php _e('Precios','usaalo-cotizador');?></th>
                <th><?php _e('Activo','usaalo-cotizador');?></th>
                <th><?php _e('Acciones','usaalo-cotizador');?></th>
            </tr>
        </thead>
        <tbody>
        
        <?php if (empty($plans)): ?>
            <tr>
                <td colspan="6"><?php _e('No existen planes o productos.','usaalo-cotizador'); ?></td>
            </tr>
        <?php else: ?>
            <?php foreach($plans as $p): ?>
                <tr>
                    <td><?php echo esc_html($p['name']); ?></td>
                    <td><?php echo $p['wc_product_id'] ? esc_html(get_the_title($p['wc_product_id'])) : '-'; ?></td>
                    <td>
                        <?php
                        if (!empty($p['ranges'])) {
                            foreach ($p['ranges'] as $range) {
                                echo esc_html($range['min_days'].'-'.$range['max_days']).'<br>';
                            }
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        if (!empty($p['ranges'])) {
                            foreach ($p['ranges'] as $range) {
                                echo esc_html($range['price']).'<br>';
                            }
                        }
                        ?>
                    </td>
                    <td><?php echo $p['active'] ? __('Sí','usaalo-cotizador') : __('No','usaalo-cotizador'); ?></td>
                    <td>
                        <button class="button edit-plan" data-id="<?php echo intval($p['id']); ?>"><?php _e('Editar','usaalo-cotizador');?></button>
                        <button class="button delete-plan" data-id="<?php echo intval($p['id']); ?>"><?php _e('Eliminar','usaalo-cotizador');?></button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    <button class="button button-danger usaalo-delete-selected" data-table="usaalo_plan_country">
        Eliminar seleccionados
    </button>

<!-- Modal overlay -->
<div id="usaalo-plan-overlay" class="usaalo-overlay"></div>

<!-- Modal -->
<div id="usaalo-plan-modal" class="usaalo-modal" style="display:none;">
  <div class="usaalo-modal-content">
    
    <!-- Encabezado -->
    <div class="usaalo-header">
      <h2><?php _e('Editor de producto','usaalo-cotizador');?></h2>
      <button type="button" class="usaalo-close">&times;</button>
    </div>

    <!-- Contenido con scroll -->
    <form id="usaalo-plan-form" class="usaalo-form" enctype="multipart/form-data">
      <input type="hidden" id="product_id" name="product_id" value="">
      <input type="hidden" id="product_image_id" name="product_image_id" value="">

      <!-- Nombre y descripción -->
      <div class="usaalo-grid">
        <div>
          <label><?php _e('Nombre','usaalo-cotizador');?></label>
          <input type="text" id="nameWC" name="nameWC" required>
        </div>
        <div>
          <label><?php _e('Activo','usaalo-cotizador');?></label>
          <label class="switch">
            <input type="checkbox" name="active" value="1">
            <span class="slider"></span>
          </label>
        </div>
      </div>

      <p>
        <label><?php _e('Descripción','usaalo-cotizador');?></label>
        <textarea name="descriptionWC" id="descriptionWC"></textarea>
      </p>

      <!-- Imagen destacada -->
      <p>
        <label><?php _e('Imagen destacada','usaalo-cotizador');?></label>
        <button type="button" class="button" id="upload_image_button"><?php _e('Subir/Seleccionar imagen','usaalo-cotizador');?></button>
        <div id="image_preview" style="margin-top:10px;">
          <img src="" style="max-width:150px; display:none;" />
        </div>
      </p>

      <!-- Países -->
      <p>
        <label>
          <?php _e('Países ','usaalo-cotizador');?>
          <label class="switch">
            <input type="checkbox" name="Todos" id="todosPais" value="1">
            <span class="slider"></span>
          </label>
          <span><?php _e(' Todos','usaalo-cotizador');?></span>
          <img id="todos-check-icon" src="<?php echo plugin_dir_url(__FILE__); ?>../../assets/img/icon-check.svg" style="display:none; width:18px; height:18px; margin-left:5px;" alt="Check">
        </label>
        <select id="countries-select" name="countries[]" multiple>
          <?php foreach($countries as $b): ?>
              <option value="<?php echo intval($b['id']); ?>">
                  <?php echo esc_html($b['name']); ?>
              </option>
          <?php endforeach; ?>
        </select>
      </p>

      <!-- Tipo de producto -->
      <fieldset class="usaalo-type">
        <legend><?php _e('Tipo de producto','usaalo-cotizador');?></legend>
        <label class="radio-card">
          <input type="radio" name="product_type" value="simple" checked>
          <div class="radio-content">
            <strong><?php _e('Simple','usaalo-cotizador');?></strong>
            <p><?php _e('Un solo precio fijo','usaalo-cotizador');?></p>
          </div>
        </label>
        <label class="radio-card">
          <input type="radio" name="product_type" value="variable">
          <div class="radio-content">
            <strong><?php _e('Por rangos','usaalo-cotizador');?></strong>
            <p><?php _e('Precio por rangos de días','usaalo-cotizador');?></p>
          </div>
        </label>
      </fieldset>

      <!-- Config simple -->
      <div id="simple-config" class="usaalo-config">
        <label><?php _e('Precio','usaalo-cotizador');?></label>
        <input type="number" id="simple_price" name="simple_price" step="0.01" required>
      </div>

      <!-- Config variable -->
      <div id="variable-config" class="usaalo-config" style="display:none;">
        <label><?php _e('Rangos de días y precios','usaalo-cotizador');?></label>
        <div id="plan-ranges">
          <div class="plan-range-group">
            <input type="number" name="ranges[0][min_days]" placeholder="<?php _e('Desde','usaalo-cotizador');?>" min="1" step="1" inputmode="numeric" pattern="[0-9]*" value="1" readonly>
            <input type="number" name="ranges[0][max_days]" placeholder="<?php _e('Hasta','usaalo-cotizador');?>" min="1" step="1" inputmode="numeric" pattern="[0-9]*" required>
            <input type="number" name="ranges[0][price]" placeholder="<?php _e('Precio/día','usaalo-cotizador');?>" step="0.01" min="0" required>
          </div>
        </div>
        <button type="button" class="button add-range"><?php _e('Agregar rango','usaalo-cotizador');?></button>
        <p>
          <label><?php _e('Precio a partir del último rango','usaalo-cotizador');?></label>
          <input type="number" name="max_price" id="max_price" step="0.01" required>
        </p>
      </div>
    </form>

    <!-- Footer fijo -->
    <div class="usaalo-footer">
      <button class="button button-primary" type="submit" form="usaalo-plan-form"><?php _e('Guardar','usaalo-cotizador');?></button>
      <button class="button cancel-plan" type="button"><?php _e('Cancelar','usaalo-cotizador');?></button>
    </div>

  </div>
</div>


</div>