<?php
if (!defined('ABSPATH')) exit;

class USAALO_Installer {

    /**
     * Activación del plugin: crear tablas y cargar datos iniciales.
     */
    public static function activate() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // 1️⃣ Tabla de países
        $wpdb->query("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}usaalo_countries (
            code CHAR(2) NOT NULL PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            region VARCHAR(60) DEFAULT '',
            status ENUM('enabled','disabled','coming_soon') DEFAULT 'enabled',
            supports_voice_sms TINYINT(1) DEFAULT 0
        ) $charset");

        // 2️⃣ Tabla de marcas
        $wpdb->query("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}usaalo_brands (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(140) NOT NULL UNIQUE
        ) $charset");

        // 3️⃣ Tabla de modelos
        $wpdb->query("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}usaalo_models (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            brand_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(160) NOT NULL,
            slug VARCHAR(180) NOT NULL,
            FOREIGN KEY (brand_id) REFERENCES {$wpdb->prefix}usaalo_brands(id) ON DELETE CASCADE,
            UNIQUE KEY brand_model (brand_id, slug)
        ) $charset");

        // 4️⃣ Compatibilidad dispositivo por país
        $wpdb->query("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}usaalo_device_country (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            model_id BIGINT UNSIGNED NOT NULL,
            country_code CHAR(2) NOT NULL,
            esim_supported TINYINT(1) DEFAULT 0,
            voice_supported TINYINT(1) DEFAULT 1,
            sms_supported TINYINT(1) DEFAULT 1,
            data_supported TINYINT(1) DEFAULT 1,
            FOREIGN KEY (model_id) REFERENCES {$wpdb->prefix}usaalo_models(id) ON DELETE CASCADE,
            UNIQUE KEY uniq (model_id, country_code)
        ) $charset");

        // 5️⃣ Tabla de planes
        $wpdb->query("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}usaalo_plans (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            description TEXT,
            sim_types ENUM('both','esim','physical') DEFAULT 'both',
            wc_product_id BIGINT UNSIGNED DEFAULT NULL,
            active TINYINT(1) DEFAULT 1
        ) $charset");

        // 6️⃣ Relación plan ↔ país (N:M)
        $wpdb->query("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}usaalo_plan_country (
            plan_id BIGINT UNSIGNED NOT NULL,
            country_code CHAR(2) NOT NULL,
            PRIMARY KEY (plan_id, country_code),
            FOREIGN KEY (plan_id) REFERENCES {$wpdb->prefix}usaalo_plans(id) ON DELETE CASCADE,
            FOREIGN KEY (country_code) REFERENCES {$wpdb->prefix}usaalo_countries(code) ON DELETE CASCADE
        ) $charset");

        // 7️⃣ Reglas de precios por plan, tipo SIM y rango de días
        $wpdb->query("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}usaalo_pricing_rules (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            plan_id BIGINT UNSIGNED NOT NULL,
            sim_type ENUM('esim','physical') NOT NULL,
            min_days INT NOT NULL,
            max_days INT NOT NULL,
            base_price DECIMAL(10,2) NOT NULL,
            voice_addon DECIMAL(10,2) DEFAULT 0,
            sms_addon DECIMAL(10,2) DEFAULT 0,
            region_surcharge DECIMAL(10,2) DEFAULT 0,
            active TINYINT(1) DEFAULT 1,
            FOREIGN KEY (plan_id) REFERENCES {$wpdb->prefix}usaalo_plans(id) ON DELETE CASCADE,
            UNIQUE KEY uniq (plan_id, sim_type, min_days, max_days)
        ) $charset");

        // Cargar datos iniciales
        self::seed_initial();
    }

    /**
     * Desactivación del plugin (puede usarse para limpiar hooks o caches)
     */
    public static function deactivate() {
        // Actualmente noop
    }

    /**
     * Semillas iniciales para países, marcas, modelos y planes demo
     */
    private static function seed_initial() {
        global $wpdb;

        // Países iniciales
        $countries = [
            ['US','Estados Unidos','Norteamérica','enabled',1],
            ['MX','México','Norteamérica','enabled',1],
            ['CA','Canadá','Norteamérica','enabled',1],
            ['ES','España','Europa','enabled',0],
            ['BR','Brasil','Sudamérica','enabled',0],
            ['AR','Argentina','Sudamérica','coming_soon',0],
        ];

        foreach($countries as $c){
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->prefix}usaalo_countries (code,name,region,status,supports_voice_sms) VALUES (%s,%s,%s,%s,%d)", $c
            ));
        }

        // Marcas demo
        $wpdb->query("INSERT IGNORE INTO {$wpdb->prefix}usaalo_brands (name,slug) VALUES ('Apple','apple'),('Samsung','samsung')");

        $apple = (int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}usaalo_brands WHERE slug='apple'");
        $samsung = (int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}usaalo_brands WHERE slug='samsung'");

        // Modelos demo
        $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}usaalo_models (brand_id,name,slug) VALUES
            (%d,'iPhone 13','iphone-13'),
            (%d,'iPhone 14','iphone-14'),
            (%d,'Galaxy S22','galaxy-s22'),
            (%d,'Galaxy S23','galaxy-s23')", $apple,$apple,$samsung,$samsung
        ));

        // Compatibilidad demo
        $model13 = (int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}usaalo_models WHERE slug='iphone-13'");
        $modelS22 = (int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}usaalo_models WHERE slug='galaxy-s22'");

        $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}usaalo_device_country
            (model_id,country_code,esim_supported,voice_supported,sms_supported,data_supported)
            VALUES
            (%d,'US',1,1,1,1),
            (%d,'MX',1,1,1,1),
            (%d,'CA',1,1,1,1),
            (%d,'ES',1,0,0,1)", $model13,$model13,$model13,$modelS22
        ));

        // Plan demo
        $wpdb->query("INSERT IGNORE INTO {$wpdb->prefix}usaalo_plans (name,description,sim_types,active)
            VALUES ('Plan Norteamérica','Cobertura USA/MX/CA','both',1)");

        $plan = (int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}usaalo_plans WHERE name='Plan Norteamérica'");

        // Relación plan ↔ país
        foreach(['US','MX','CA'] as $cc){
            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}usaalo_plan_country (plan_id,country_code) VALUES (%d,%s)", $plan, $cc));
        }

        // Reglas de precio demo
        $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}usaalo_pricing_rules
            (plan_id,sim_type,min_days,max_days,base_price,voice_addon,sms_addon,region_surcharge,active)
            VALUES
            (%d,'esim',1,7,15,3,1,0,1),
            (%d,'esim',8,30,10,5,2,0,1),
            (%d,'physical',1,7,18,3,1,0,1),
            (%d,'physical',8,30,12,5,2,0,1)", $plan,$plan,$plan,$plan
        ));
    }
}
