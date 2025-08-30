<?php
if (!defined('ABSPATH')) exit;

class USAC_Importer {
    public static function import_countries(){
        check_admin_referer('usac_import_countries');
        if (empty($_FILES['csv']['tmp_name'])) wp_die('CSV requerido');
        $fh = fopen($_FILES['csv']['tmp_name'], 'r');
        global $wpdb;
        while(($row = fgetcsv($fh)) !== false){
            if (count($row) < 5 || $row[0]==='code2') continue;
            [$code2,$name,$region,$status,$svs] = $row;
            $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}usac_countries
                (code2,name,region,status,supports_voice_sms)
                VALUES(%s,%s,%s,%s,%d)
                ON DUPLICATE KEY UPDATE name=VALUES(name),region=VALUES(region),status=VALUES(status),supports_voice_sms=VALUES(supports_voice_sms)",
                $code2,$name,$region,$status,(int)$svs
            ));
        }
        fclose($fh);
        wp_redirect(admin_url('admin.php?page=usac-import&done=1')); exit;
    }

    public static function import_devices(){
        check_admin_referer('usac_import_devices');
        if (empty($_FILES['csv']['tmp_name'])) wp_die('CSV requerido');
        $fh = fopen($_FILES['csv']['tmp_name'], 'r');
        global $wpdb;
        while(($row = fgetcsv($fh)) !== false){
            if (count($row) < 7 || $row[0]==='brand') continue;
            [$brand,$model,$cc,$esim,$voice,$sms,$data] = $row;

            $bslug = sanitize_title($brand);
            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}usac_brands (name,slug) VALUES(%s,%s)", $brand,$bslug));
            $brand_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}usac_brands WHERE slug=%s",$bslug));

            $mslug = sanitize_title($model);
            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}usac_models (brand_id,name,slug) VALUES(%d,%s,%s)", $brand_id,$model,$mslug));
            $model_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}usac_models WHERE brand_id=%d AND slug=%s",$brand_id,$mslug));

            $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}usac_device_country
                (model_id,country_code,esim_supported,voice_supported,sms_supported,data_supported)
                VALUES (%d,%s,%d,%d,%d,%d)
                ON DUPLICATE KEY UPDATE esim_supported=VALUES(esim_supported),voice_supported=VALUES(voice_supported),
                sms_supported=VALUES(sms_supported),data_supported=VALUES(data_supported)",
                $model_id,$cc,(int)$esim,(int)$voice,(int)$sms,(int)$data
            ));
        }
        fclose($fh);
        wp_redirect(admin_url('admin.php?page=usac-import&done=1')); exit;
    }
}
