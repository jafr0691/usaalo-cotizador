<?php
    $config = get_option('usaalo_cotizador_config', []); // Obtiene todo el array
    $show_sim = isset($config['show_sim']) ? $config['show_sim'] : 1;
    $show_esim = isset($config['show_esim']) ? $config['show_esim'] : 1;
    $show_data = isset($config['show_data']) ? $config['show_data'] : 1;
    $show_voice = isset($config['show_voice']) ? $config['show_voice'] : 1;
    $show_sms = isset($config['show_sms']) ? $config['show_sms'] : 1;
?>
<div class="wrap">
    <h1><?php _e('Configuración de SIM y Servicios por Modelo y País', 'usaalo-cotizador'); ?></h1>

    <!-- ⚡ Configuración rápida -->
    <div class="usaalo-toggles" style="display:flex; flex-wrap:wrap; gap:20px; align-items:center; margin:15px 0;">
        
        <div class="toggle-item">
            <label><?php _e('SIM','usaalo-cotizador');?></label><br>
            <label class="switch">
                <input type="checkbox" class="usaalo-toggle" data-key="show_sim" <?php checked($show_sim, 1); ?>>
                <span class="slider round"></span>
            </label><br>
            <span id="sim-status" class="status-label"></span>
        </div>

        <div class="toggle-item">
            <label><?php _e('eSIM','usaalo-cotizador');?></label><br>
            <label class="switch">
                <input type="checkbox" class="usaalo-toggle" data-key="show_esim" <?php checked($show_esim, 1); ?>>
                <span class="slider round"></span>
            </label><br>
            <span id="esim-status" class="status-label"></span>
        </div>

        <div class="toggle-item">
            <label><?php _e('Datos','usaalo-cotizador');?></label><br>
            <label class="switch">
                <input type="checkbox" class="usaalo-toggle" data-key="show_data" <?php checked($show_data, 1); ?>>
                <span class="slider round"></span>
            </label><br>
            <span id="data-status" class="status-label"></span>
        </div>

        <div class="toggle-item">
            <label><?php _e('Voz','usaalo-cotizador');?></label><br>
            <label class="switch">
                <input type="checkbox" class="usaalo-toggle" data-key="show_voice" <?php checked($show_voice, 1); ?>>
                <span class="slider round"></span>
            </label><br>
            <span id="voice-status" class="status-label"></span>
        </div>

        <div class="toggle-item">
            <label><?php _e('SMS','usaalo-cotizador');?></label><br>
            <label class="switch">
                <input type="checkbox" class="usaalo-toggle" data-key="show_sms" <?php checked($show_sms, 1); ?>>
                <span class="slider round"></span>
            </label><br>
            <span id="sms-status" class="status-label"></span>
        </div>

    </div>

    <!-- 🔍 Filtros -->
    <div class="filters mb-3">
        <label><?php _e('País:', 'usaalo-cotizador'); ?></label>
        <input type="text" id="filter-country" placeholder="<?php _e('Buscar país','usaalo-cotizador');?>">

        <label><?php _e('Marca:', 'usaalo-cotizador'); ?></label>
        <input type="text" id="filter-brand" placeholder="<?php _e('Buscar marca','usaalo-cotizador');?>">

        <label><?php _e('Modelo:', 'usaalo-cotizador'); ?></label>
        <input type="text" id="filter-model" placeholder="<?php _e('Buscar modelo','usaalo-cotizador');?>">
    </div>

    <!-- 📋 Tabla -->
    <table id="sim_servicio_table" class="widefat striped">
        <thead>
            <tr>
                <th><?php _e('País', 'usaalo-cotizador'); ?></th>
                <th><?php _e('Marca', 'usaalo-cotizador'); ?></th>
                <th><?php _e('Modelo', 'usaalo-cotizador'); ?></th>
                <th><?php _e('SIM', 'usaalo-cotizador'); ?></th>
                <th><?php _e('eSIM', 'usaalo-cotizador'); ?></th>
                <th><?php _e('Datos', 'usaalo-cotizador'); ?></th>
                <th><?php _e('Voz', 'usaalo-cotizador'); ?></th>
                <th><?php _e('SMS', 'usaalo-cotizador'); ?></th>
            </tr>
        </thead>
    </table>
</div>
