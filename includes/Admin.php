<?php
if (!defined('ABSPATH')) exit;

class USAC_Admin {
    public static function menu(){
        add_menu_page('Usaalo Cotizador','Usaalo Cotizador','manage_options','usac',[__CLASS__,'dashboard'],'dashicons-admin-generic',58);
        add_submenu_page('usac','Países','Países','manage_options','usac-countries',[__CLASS__,'countries']);
        add_submenu_page('usac','Planes','Planes','manage_options','usac-plans',[__CLASS__,'plans']);
        add_submenu_page('usac','Reglas de Precio','Reglas de Precio','manage_options','usac-pricing',[__CLASS__,'pricing']);
        add_submenu_page('usac','Importar CSV','Importar CSV','manage_options','usac-import',[__CLASS__,'import']);
    }

    public static function enqueue_assets($hook){
        if (strpos($hook,'usac')===false) return;
        wp_enqueue_style('usac-admin', USAC_URL.'assets/css/wizard.css', [], USAC_VER);
        wp_enqueue_script('usac-admin', USAC_URL.'assets/js/admin.js', ['jquery','select2'], USAC_VER, true);
        wp_localize_script('usac-admin', 'USACAdmin', ['ajax'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('usac_admin')]);
    }

    public static function register_settings(){
        register_setting('usac_settings','usac_currency',['type'=>'string','default'=>'USD']);
    }

    public static function dashboard(){
        echo '<div class="wrap"><h1>Usaalo Cotizador</h1><p>Panel principal. Administra países, planes, relaciones y reglas desde el menú.</p></div>';
    }

    public static function countries(){
        global $wpdb;
        $tbl = $wpdb->prefix.'usac_countries';
        $rows = $wpdb->get_results("SELECT * FROM $tbl ORDER BY name");
        echo '<div class="wrap"><h1>Países</h1>';
        echo '<p>Lista de países cargados. Para añadir masivos usa Importar CSV.</p>';
        echo '<table class="widefat fixed striped"><thead><tr><th>Código</th><th>Nombre</th><th>Región</th><th>Estado</th><th>Voice/SMS</th></tr></thead><tbody>';
        foreach($rows as $r){
            echo '<tr><td>'.esc_html($r->code2).'</td><td>'.esc_html($r->name).'</td><td>'.esc_html($r->region).'</td><td>'.esc_html($r->status).'</td><td>'.($r->supports_voice_sms?'Sí':'No').'</td></tr>';
        }
        echo '</tbody></table>';
        echo '<p><a class="button" href="'.admin_url('admin.php?page=usac-import').'">Importar CSV</a></p></div>';
    }

    /* ===== Plans CRUD ===== */
    public static function plans(){
        global $wpdb;
        $tbl = $wpdb->prefix.'usac_plans';
        $tbl_pc = $wpdb->prefix.'usac_plan_country';
        if (!empty($_POST['usac_action']) && check_admin_referer('usac_plans_action','usac_plans_nonce')){
            $act = sanitize_text_field($_POST['usac_action']);
            if ($act==='add'){
                $wpdb->insert($tbl, [
                    'name'=>sanitize_text_field($_POST['name']),
                    'description'=>sanitize_textarea_field($_POST['description']),
                    'sim_types'=>sanitize_text_field($_POST['sim_types']),
                    'active'=>isset($_POST['active'])?1:0
                ]);
                echo '<div class="notice notice-success"><p>Plan creado.</p></div>';
            } elseif ($act==='update' && !empty($_POST['id'])){
                $id = intval($_POST['id']);
                $wpdb->update($tbl, [
                    'name'=>sanitize_text_field($_POST['name']),
                    'description'=>sanitize_textarea_field($_POST['description']),
                    'sim_types'=>sanitize_text_field($_POST['sim_types']),
                    'active'=>isset($_POST['active'])?1:0
                ], ['id'=>$id]);
                echo '<div class="notice notice-success"><p>Plan actualizado.</p></div>';
            } elseif ($act==='delete' && !empty($_POST['id'])){
                $wpdb->delete($tbl, ['id'=>intval($_POST['id'])]);
                echo '<div class="notice notice-success"><p>Plan eliminado.</p></div>';
            }
        }

        $plans = $wpdb->get_results("SELECT * FROM $tbl ORDER BY id DESC");
        $countries = $wpdb->get_results("SELECT code2,name FROM {$wpdb->prefix}usac_countries ORDER BY name", ARRAY_A);
        echo '<div class="wrap"><h1>Planes</h1>';
        echo '<h2>Crear nuevo plan</h2><form method="post">'; wp_nonce_field('usac_plans_action','usac_plans_nonce');
        echo '<input type="hidden" name="usac_action" value="add">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>Nombre</th><td><input name="name" required class="regular-text"/></td></tr>';
        echo '<tr><th>Descripción</th><td><textarea name="description" class="large-text"></textarea></td></tr>';
        echo '<tr><th>Tipo SIM</th><td><select name="sim_types"><option value="both">Ambos</option><option value="esim">eSIM</option><option value="physical">Física</option></select></td></tr>';
        echo '<tr><th>Activo</th><td><input type="checkbox" name="active" checked/></td></tr>';
        echo '</tbody></table>'; submit_button('Crear plan'); echo '</form>';

        echo '<h2>Planes existentes</h2><table class="widefat fixed striped"><thead><tr><th>ID</th><th>Nombre</th><th>Tipo SIM</th><th>Activo</th><th>Acciones</th></tr></thead><tbody>';
        foreach($plans as $p){
            echo '<tr><td>'.$p->id.'</td><td>'.esc_html($p->name).'</td><td>'.esc_html($p->sim_types).'</td><td>'.($p->active? 'Sí':'No').'</td><td>';
            echo '<a class="button" href="'.admin_url('admin.php?page=usac-plans&edit='.$p->id).'">Editar</a> ';
            echo '<a class="button" href="'.admin_url('admin.php?page=usac-pricing&plan_id='.$p->id).'">Reglas</a> ';
            echo '<a class="button" href="'.admin_url('admin.php?page=usac-plans&assoc='.$p->id).'">Paises</a> ';
            echo '<form style="display:inline" method="post">'.wp_nonce_field('usac_plans_action','usac_plans_nonce',true,false).'<input type="hidden" name="usac_action" value="delete"/><input type="hidden" name="id" value="'.$p->id.'"/><button class="button" onclick="return confirm(\'Eliminar plan?\')">Eliminar</button></form>';
            echo '</td></tr>';
        }
        echo '</tbody></table>';

        if (!empty($_GET['edit'])){
            $id = intval($_GET['edit']);
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tbl WHERE id=%d",$id));
            if ($row){
                echo '<h2>Editar plan #'.$id.'</h2><form method="post">'; wp_nonce_field('usac_plans_action','usac_plans_nonce');
                echo '<input type="hidden" name="usac_action" value="update"/><input type="hidden" name="id" value="'.$id.'">';
                echo '<table class="form-table"><tbody>';
                echo '<tr><th>Nombre</th><td><input name="name" required class="regular-text" value="'.esc_attr($row->name).'"/></td></tr>';
                echo '<tr><th>Descripción</th><td><textarea name="description" class="large-text">'.esc_textarea($row->description).'</textarea></td></tr>';
                echo '<tr><th>Tipo SIM</th><td><select name="sim_types"><option value="both" '.($row->sim_types=='both'?'selected':'').'>Ambos</option><option value="esim" '.($row->sim_types=='esim'?'selected':'').'>eSIM</option><option value="physical" '.($row->sim_types=='physical'?'selected':'').'>Física</option></select></td></tr>';
                echo '<tr><th>Activo</th><td><input type="checkbox" name="active" '.($row->active?'checked':'').'/></td></tr>';
                echo '</tbody></table>'; submit_button('Guardar'); echo '</form>';
            }
        }

        if (!empty($_GET['assoc'])){
            $plan_id = intval($_GET['assoc']);
            echo '<h2>Asociar países al plan #'.$plan_id.'</h2>';
            if (!empty($_POST['save_assoc']) && check_admin_referer('usac_assoc_action','usac_assoc_nonce')){
                $wpdb->delete($tbl_pc, ['plan_id'=>$plan_id]);
                $selected = $_POST['countries'] ?? [];
                foreach($selected as $cc){
                    $wpdb->insert($tbl_pc, ['plan_id'=>$plan_id,'country_code'=>sanitize_text_field($cc)]);
                }
                echo '<div class="notice notice-success"><p>Asociaciones guardadas.</p></div>';
            }
            $current = $wpdb->get_col($wpdb->prepare("SELECT country_code FROM {$wpdb->prefix}usac_plan_country WHERE plan_id=%d", $plan_id));
            echo '<form method="post">'; wp_nonce_field('usac_assoc_action','usac_assoc_nonce');
            echo '<input type="hidden" name="save_assoc" value="1"/>';
            echo '<table class="form-table"><tbody><tr><th>Países</th><td>';
            foreach($countries as $c){
                $checked = in_array($c['code2'],$current)?'checked':'';
                echo '<label style="display:inline-block;width:200px;"><input type="checkbox" name="countries[]" value="'.esc_attr($c['code2']).'" '.$checked.'/> '.esc_html($c['name']).' ('.esc_html($c['code2']).')</label>';
            }
            echo '</td></tr></tbody></table>'; submit_button('Guardar asociaciones'); echo '</form>';
        }

        echo '</div>';
    }

    /* ===== Pricing Rules CRUD ===== */
    public static function pricing(){
        global $wpdb;
        $tbl = $wpdb->prefix.'usac_pricing_rules';
        $tbl_plans = $wpdb->prefix.'usac_plans';

        if (!empty($_POST['usac_pr_action']) && check_admin_referer('usac_pricing_action','usac_pricing_nonce')){
            $act = sanitize_text_field($_POST['usac_pr_action']);
            if ($act==='add'){
                $wpdb->insert($tbl, [
                    'plan_id'=>intval($_POST['plan_id']),
                    'sim_type'=>sanitize_text_field($_POST['sim_type']),
                    'min_days'=>intval($_POST['min_days']),
                    'max_days'=>intval($_POST['max_days']),
                    'base_price'=>floatval($_POST['base_price']),
                    'voice_addon'=>floatval($_POST['voice_addon']),
                    'sms_addon'=>floatval($_POST['sms_addon']),
                    'region_surcharge'=>floatval($_POST['region_surcharge']),
                    'active'=>isset($_POST['active'])?1:0
                ]);
                echo '<div class="notice notice-success"><p>Regla añadida.</p></div>';
            } elseif ($act==='update' && !empty($_POST['id'])){
                $id = intval($_POST['id']);
                $wpdb->update($tbl, [
                    'plan_id'=>intval($_POST['plan_id']),
                    'sim_type'=>sanitize_text_field($_POST['sim_type']),
                    'min_days'=>intval($_POST['min_days']),
                    'max_days'=>intval($_POST['max_days']),
                    'base_price'=>floatval($_POST['base_price']),
                    'voice_addon'=>floatval($_POST['voice_addon']),
                    'sms_addon'=>floatval($_POST['sms_addon']),
                    'region_surcharge'=>floatval($_POST['region_surcharge']),
                    'active'=>isset($_POST['active'])?1:0
                ], ['id'=>$id]);
                echo '<div class="notice notice-success"><p>Regla actualizada.</p></div>';
            } elseif ($act==='delete' && !empty($_POST['id'])){
                $wpdb->delete($tbl, ['id'=>intval($_POST['id'])]);
                echo '<div class="notice notice-success"><p>Regla eliminada.</p></div>';
            }
        }

        $plans = $wpdb->get_results("SELECT * FROM $tbl_plans ORDER BY name");
        $rules = $wpdb->get_results("SELECT r.*, p.name as plan_name FROM $tbl r JOIN $tbl_plans p ON p.id=r.plan_id ORDER BY r.plan_id, r.min_days");

        echo '<div class="wrap"><h1>Reglas de Precio</h1>';
        echo '<h2>Añadir regla</h2><form method="post">'; wp_nonce_field('usac_pricing_action','usac_pricing_nonce');
        echo '<input type="hidden" name="usac_pr_action" value="add"/>';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>Plan</th><td><select name="plan_id">'; foreach($plans as $pl) echo '<option value="'.$pl->id.'">'.esc_html($pl->name).'</option>'; echo '</select></td></tr>';
        echo '<tr><th>Tipo SIM</th><td><select name="sim_type"><option value="esim">eSIM</option><option value="physical">Física</option></select></td></tr>';
        echo '<tr><th>Días min</th><td><input type="number" name="min_days" value="1"/></td></tr>';
        echo '<tr><th>Días max</th><td><input type="number" name="max_days" value="30"/></td></tr>';
        echo '<tr><th>Precio base</th><td><input type="text" name="base_price" value="0.00"/></td></tr>';
        echo '<tr><th>Voice addon</th><td><input type="text" name="voice_addon" value="0.00"/></td></tr>';
        echo '<tr><th>SMS addon</th><td><input type="text" name="sms_addon" value="0.00"/></td></tr>';
        echo '<tr><th>Region surcharge</th><td><input type="text" name="region_surcharge" value="0.00"/></td></tr>';
        echo '<tr><th>Activo</th><td><input type="checkbox" name="active" checked/></td></tr>';
        echo '</tbody></table>'; submit_button('Agregar regla'); echo '</form>';

        echo '<h2>Reglas existentes</h2><table class="widefat fixed striped"><thead><tr><th>ID</th><th>Plan</th><th>Tipo</th><th>Días</th><th>Base</th><th>Voice</th><th>SMS</th><th>Acciones</th></tr></thead><tbody>';
        if ($rules){
            foreach($rules as $r){
                echo '<tr><td>'.$r->id.'</td><td>'.esc_html($r->plan_name).'</td><td>'.esc_html($r->sim_type).'</td><td>'.$r->min_days.'-'.$r->max_days.'</td><td>'.$r->base_price.'</td><td>'.$r->voice_addon.'</td><td>'.$r->sms_addon.'</td><td>'.
                '<form method="post" style="display:inline">'.wp_nonce_field('usac_pricing_action','usac_pricing_nonce',true,false).'<input type="hidden" name="usac_pr_action" value="delete"/><input type="hidden" name="id" value="'.$r->id.'"/><button class="button button-secondary" onclick="return confirm(\'Eliminar?\')">Eliminar</button></form>'.
                '</td></tr>';
            }
        } else {
            echo '<tr><td colspan="8">No hay reglas.</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function import(){
        include_once USAC_PATH.'includes/Importer.php';
        USAC_Admin::enqueue_assets('usac-import');
        echo '<div class="wrap"><h1>Importar CSV</h1>';
        echo '<p>Sube CSV para poblar Países o Dispositivos.</p>';
        echo '<p><a class="button" href="'.plugins_url('samples/countries.sample.csv', __FILE__).'">Descargar ejemplo países</a> ';
        echo '<a class="button" href="'.plugins_url('samples/devices.sample.csv', __FILE__).'">Ejemplo dispositivos</a></p>';
        // reuse Importer UI
        if (method_exists('USAC_Importer','import_ui')){
            USAC_Importer::import_ui();
        } else {
            echo '<p>Importador no disponible.</p>';
        }
        echo '</div>';
    }
}

// small admin ajax helper
add_action('wp_ajax_usac_admin_get_pricing', function(){
    if (!current_user_can('manage_options')) wp_send_json_error('no');
    check_ajax_referer('usac_admin','nonce');
    global $wpdb;
    $id = intval($_POST['id'] ?? 0);
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}usac_pricing_rules WHERE id=%d",$id), ARRAY_A);
    wp_send_json_success($row);
});
