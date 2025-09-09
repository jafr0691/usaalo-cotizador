 <?php
if (!defined('ABSPATH')) exit;

class USAALO_Installer {

    public static function activate() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // 1️⃣ Países
        $wpdb->query("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}usaalo_countries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code CHAR(2) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            region VARCHAR(60) DEFAULT '',
            KEY idx_code (code)
        ) $charset");

        // 2️⃣ Marcas
        $wpdb->query("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}usaalo_brands (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(140) NOT NULL UNIQUE
        ) $charset");

        // 3️⃣ Modelos
        $wpdb->query("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}usaalo_models (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            brand_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(160) NOT NULL,
            slug VARCHAR(180) NOT NULL,
            FOREIGN KEY (brand_id) REFERENCES {$wpdb->prefix}usaalo_brands(id) ON DELETE CASCADE,
            UNIQUE KEY brand_model (brand_id, slug),
            KEY idx_brand (brand_id)
        ) $charset");

        // 4️⃣ Configuración por modelo (base global, sin país)
        $wpdb->query("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}usaalo_device_config (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            model_id BIGINT UNSIGNED NOT NULL,
            sim_supported TINYINT(1) DEFAULT 1,
            esim_supported TINYINT(1) DEFAULT 1,
            voice_supported TINYINT(1) DEFAULT 0,
            sms_supported TINYINT(1) DEFAULT 0,
            data_supported TINYINT(1) DEFAULT 1,
            FOREIGN KEY (model_id) REFERENCES {$wpdb->prefix}usaalo_models(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_model (model_id)
        ) $charset");

        // 5️⃣ Configuración específica modelo-país (solo cuando difiere del global)
        $wpdb->query("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}usaalo_device_country (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            model_id BIGINT UNSIGNED NOT NULL,
            country_id BIGINT UNSIGNED NOT NULL,
            sim_supported TINYINT(1) NULL,
            esim_supported TINYINT(1) NULL,
            voice_supported TINYINT(1) NULL,
            sms_supported TINYINT(1) NULL,
            data_supported TINYINT(1) NULL,
            FOREIGN KEY (model_id) REFERENCES {$wpdb->prefix}usaalo_models(id) ON DELETE CASCADE,
            FOREIGN KEY (country_id) REFERENCES {$wpdb->prefix}usaalo_countries(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_model_country (model_id, country_id),
            KEY idx_model (model_id),
            KEY idx_country (country_id)
        ) $charset");

        // 6️⃣ Relación producto ↔ país (WooCommerce products)
        $wpdb->query("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}usaalo_product_country (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NOT NULL,
            country_id BIGINT UNSIGNED NOT NULL,
            FOREIGN KEY (country_id) REFERENCES {$wpdb->prefix}usaalo_countries(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_product_country (product_id, country_id),
            KEY idx_product (product_id),
            KEY idx_country (country_id)
        ) $charset");

        self::seed_initial();
    }

    public static function deactivate() {
        // noop
    }

    private static function seed_initial() {
        global $wpdb;

        // Países con códigos ISO (para banderas)
        $countries = [
            ['AL','Albania','Europa'],
            ['DZ','Argelia','África'],
            ['AI','Anguila','Caribe'],
            ['AG','Antigua y Barbuda','Caribe'],
            ['AR','Argentina','Sudamérica'],
            ['AM','Armenia','Asia'],
            ['AW','Aruba','Caribe'],
            ['AU','Australia','Oceanía'],
            ['AT','Austria','Europa'],
            ['AZ','Azerbaiyán','Asia'],
            ['BD','Bangladés','Asia'],
            ['BB','Barbados','Caribe'],
            ['BY','Bielorrusia','Europa'],
            ['BE','Bélgica','Europa'],
            ['BM','Bermudas','Caribe'],
            ['BA','Bosnia y Herzegovina','Europa'],
            ['BR','Brasil','Sudamérica'],
            ['VG','Islas Vírgenes Británicas','Caribe'],
            ['BN','Brunéi','Asia'],
            ['BG','Bulgaria','Europa'],
            ['KH','Camboya','Asia'],
            ['CM','Camerún','África'],
            ['CA','Canadá','Norteamérica'],
            ['KY','Islas Caimán','Caribe'],
            ['CF','República Centroafricana','África'],
            ['CL','Chile','Sudamérica'],
            ['CN','China','Asia'],
            ['CO','Colombia','Sudamérica'],
            ['CI',"Costa de Marfil",'África'],
            ['HR','Croacia','Europa'],
            ['CW','Curazao y Bonaire','Caribe'],
            ['CY','Chipre','Europa'],
            ['CZ','República Checa','Europa'],
            ['CD','República Democrática del Congo','África'],
            ['DK','Dinamarca','Europa'],
            ['DM','Dominica','Caribe'],
            ['DO','República Dominicana','Caribe'],
            ['EC','Ecuador','Sudamérica'],
            ['EG','Egipto','África'],
            ['SV','El Salvador','Centroamérica'],
            ['EE','Estonia','Europa'],
            ['FO','Islas Feroe','Europa'],
            ['FJ','Fiyi','Oceanía'],
            ['FI','Finlandia','Europa'],
            ['FR','Francia','Europa'],
            ['GF','Guayana Francesa','Sudamérica'],
            ['GE','Georgia','Asia'],
            ['DE','Alemania','Europa'],
            ['GH','Ghana','África'],
            ['GI','Gibraltar','Europa'],
            ['GR','Grecia','Europa'],
            ['GD','Granada','Caribe'],
            ['GP','Guadalupe','Caribe'],
            ['GU','Guam','Oceanía'],
            ['GT','Guatemala','Centroamérica'],
            ['GY','Guyana','Sudamérica'],
            ['HT','Haití','Caribe'],
            ['HN','Honduras','Centroamérica'],
            ['HK','Hong Kong','Asia'],
            ['HU','Hungría','Europa'],
            ['IS','Islandia','Europa'],
            ['IN','India','Asia'],
            ['ID','Indonesia','Asia'],
            ['IR','Irán','Asia'],
            ['IE','Irlanda','Europa'],
            ['IL','Israel','Asia'],
            ['IT','Italia','Europa'],
            ['JM','Jamaica','Caribe'],
            ['JP','Japón','Asia'],
            ['JO','Jordania','Asia'],
            ['KZ','Kazajistán','Asia'],
            ['KE','Kenia','África'],
            ['KW','Kuwait','Asia'],
            ['KG','Kirguistán','Asia'],
            ['LA','Laos','Asia'],
            ['LV','Letonia','Europa'],
            ['LR','Liberia','África'],
            ['LT','Lituania','Europa'],
            ['LU','Luxemburgo','Europa'],
            ['MO','Macao','Asia'],
            ['MG','Madagascar','África'],
            ['MW','Malaui','África'],
            ['MY','Malasia','Asia'],
            ['MT','Malta','Europa'],
            ['MQ','Martinica','Caribe'],
            ['MU','Mauricio','África'],
            ['MX','México','Norteamérica'],
            ['MD','Moldavia','Europa'],
            ['MN','Mongolia','Asia'],
            ['ME','Montenegro','Europa'],
            ['MA','Marruecos','África'],
            ['MZ','Mozambique','África'],
            ['MM','Birmania','Asia'],
            ['NP','Nepal','Asia'],
            ['NL','Países Bajos','Europa'],
            ['NZ','Nueva Zelanda','Oceanía'],
            ['NI','Nicaragua','Centroamérica'],
            ['NO','Noruega','Europa'],
            ['OM','Omán','Asia'],
            ['PK','Pakistán','Asia'],
            ['PA','Panamá','Centroamérica'],
            ['PG','Papúa Nueva Guinea','Oceanía'],
            ['PY','Paraguay','Sudamérica'],
            ['PE','Perú','Sudamérica'],
            ['PH','Filipinas','Asia'],
            ['PL','Polonia','Europa'],
            ['PT','Portugal','Europa'],
            ['QA','Catar','Asia'],
            ['RO','Rumania','Europa'],
            ['RU','Rusia','Europa'],
            ['RW','Ruanda','África'],
            ['SA','Arabia Saudita','Asia'],
            ['RS','Serbia','Europa'],
            ['SC','Seychelles','África'],
            ['SL','Sierra Leona','África'],
            ['SG','Singapur','Asia'],
            ['SK','Eslovaquia','Europa'],
            ['SI','Eslovenia','Europa'],
            ['ZA','Sudáfrica','África'],
            ['KR','Corea del Sur','Asia'],
            ['ES','España','Europa'],
            ['LK','Sri Lanka','Asia'],
            ['KN','San Cristóbal y Nieves','Caribe'],
            ['LC','Santa Lucía','Caribe'],
            ['VC','San Vicente','Caribe'],
            ['SD','Sudán','África'],
            ['SZ','Suazilandia','África'],
            ['SE','Suecia','Europa'],
            ['CH','Suiza','Europa'],
            ['TW','Taiwán','Asia'],
            ['TJ','Tayikistán','Asia'],
            ['TZ','Tanzania','África'],
            ['TH','Tailandia','Asia'],
            ['TO','Tonga','Oceanía'],
            ['TT','Trinidad y Tobago','Caribe'],
            ['TN','Túnez','África'],
            ['TR','Turquía','Europa'],
            ['TC','Islas Turcas y Caicos','Caribe'],
            ['AE','Emiratos Árabes Unidos','Asia'],
            ['UG','Uganda','África'],
            ['UA','Ucrania','Europa'],
            ['GB','Reino Unido','Europa'],
            ['UY','Uruguay','Sudamérica'],
            ['US','Estados Unidos','Norteamérica'],
            ['UZ','Uzbekistán','Asia'],
            ['VU','Vanuatu','Oceanía'],
            ['VN','Vietnam','Asia'],
            ['YE','Yemen','Asia'],
            ['ZM','Zambia','África'],
        ];

        foreach($countries as $c){
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->prefix}usaalo_countries (code,name,region) VALUES (%s,%s,%s)", $c
            ));
        }

        
        // 1️⃣ Marcas (brand)
        $brands = [
            ['Alcatel','alcatel'],
            ['Apple','apple'],
            ['ASUS','asus'],
            ['BLU','blu'],
            ['CAT','cat'],
            ['Google','google'],
            ['HMD / Nokia','nokia'],
            ['Hot Pepper','hot-pepper'],
            ['HTC','htc'],
            ['Huawei','huawei'],
            ['Kyocera','kyocera'],
            ['ZTE','zte'],
            ['LG','lg'],
            ['Lenovo','lenovo'],
            ['Maxwest','maxwest'],
            ['Microsoft','microsoft'],
            ['Motorola','motorola'],
            ['Nothing','nothing'],
            ['Nuu','nuu'],
            ['OnePlus','oneplus'],
            ['OSOM','osom'],
            ['RAZ Mobility','raz-mobility'],
            ['RED','red'],
            ['Samsung','samsung'],
            ['Schok','schok'],
            ['Siyata','siyata'],
            ['Sky','sky'],
            ['Sonim','sonim'],
            ['Sony','sony'],
            ['Topwell','topwell'],
            ['Unihertz','unihertz'],
            ['Xplora','xplora'],
            ['Teracube','teracube'],
            ['Punkt','punkt'],
        ];

        foreach($brands as $b){
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->prefix}usaalo_brands (name,slug) VALUES (%s,%s)", $b
            ));
        }

        // 2️⃣ Modelos representativos (model)
        //  brand_slug => [ modelos ]
        $models = [
            'alcatel' => ['1S','1B','1L','1V','3X','3L','3V','3C','3T','3X 2021','3L 2021','3X 2022','5','5V','5X','5L','7','7X','7C','7L','A3 XL','A5 LED','A7','A7 XL','Alcatel Go Flip 3','Alcatel Go Flip 4','Alcatel 1SE','Alcatel 1S 2021','Alcatel 1S 2022','Alcatel 1B 2021','Alcatel 1V 2020'],
            'apple' => ['iPhone 15 Pro Max','iPhone 15 Pro','iPhone 15 Plus','iPhone 15','iPhone 14 Pro Max','iPhone 14 Pro','iPhone 14 Plus','iPhone 14','iPhone 13 Pro Max','iPhone 13 Pro','iPhone 13','iPhone 13 Mini','iPhone 12 Pro Max','iPhone 12 Pro','iPhone 12','iPhone 12 Mini','iPhone SE (3rd generation)','iPhone SE (2nd generation)','iPhone 11 Pro Max','iPhone 11 Pro','iPhone 11','iPhone XR','iPhone XS Max','iPhone XS','iPhone X','iPhone 8 Plus','iPhone 8','iPhone 7 Plus','iPhone 7','iPhone 6s Plus','iPhone 6s','iPhone 6 Plus','iPhone 6','iPhone SE (1st generation)'],
            'asus' => ['ROG Phone 6','ROG Phone Pro','ROG Phone 5','ROG Phone 5s','ROG Phone 5 Ultimate','ROG Phone 5s Pro','Zenfone 9','Zenfone 8','Zenfone 8 Flip','Zenfone 7','Zenfone 7 Pro','Zenfone 6','Zenfone Max Pro M2','Zenfone Max M2','Zenfone Max Pro M1','Zenfone Max M1','Zenfone Live L1','Zenfone Live L2'],
            'blu' => ['G90 Pro','G91','G80','G60','G50','G40','G30','Studio Mega','Studio X10','Studio X8','Studio X6','Studio C6','Advance A6','Advance A5','Vivo One Plus','Vivo X','Vivo XL','Vivo XL2','Vivo X5','Vivo X3'],
            'cat' => ['S62 Pro','S52','S42','S41','S60','S61','B35','B30','B25','B100','CAT K50','CAT K30','CAT S31','CAT S30','CAT S20'],
            'google' => ['Pixel 4a','Pixel 5','Pixel 6','Pixel 6a','Pixel 7','Pixel 7a','Pixel 8'],
            'nokia' => ['X20','G50','5.4','3.4','2.4','C20','C30','XR20','X10','8.3 5G','7.2','6.2','4.2','1.4','1.3','105','106','110 4G','3310 3G','6300 4G','8110 4G','2720 Flip','2660 Flip'],
            'hot-pepper' => ['HP9','HP10','HP12','HP15','HP18','HP20','HP22','HP24','HP26','HP30','HP35','HP40'],
            'htc' => ['U12+','U11','U11 Life','U Ultra','Desire 21 Pro 5G','Desire 20 Pro','Desire 12','Desire 12+','One M9','One A9','10','Wildfire E2','Wildfire X','Exodus 1','Sensation XE','EVO 4G','Desire 10 Pro'],
            'huawei' => ['P60 Pro','P60','P50 Pro','P50','P40 Pro','P40','Mate 50 Pro','Mate 50','Mate 40 Pro','Mate 40','Mate 30 Pro','Mate 30','Nova 10','Nova 9','Nova 8','Nova 7','Y9a','Y9s','Y8p','Y7a','Y6p'],
            'kyocera' => ['DuraForce Pro 2','DuraForce Pro 3','DuraXV Extreme','DuraXV LTE','Hydro WAVE','Hydro EDGE','Hydro VIBE','Torque','Brigadier','Hydro LIFE','Cadence LTE','Rise','Kona','Hydro ICON','Hydro ELITE'],
            'zte' => ['Axon 30 Ultra','Axon 40 Ultra','Axon 20 5G','Blade V30','Blade V31','Blade V2020','Blade A72','Blade A71','Nubia Red Magic 7','Nubia Red Magic 6','Nubia Red Magic 6R','Grand X Max+','Max Duo 2','Open C','Warp Elite'],
            'lg' => ['LG Velvet','LG Wing','LG V60 ThinQ','LG G8 ThinQ','LG G7 ThinQ','LG K92 5G','LG Q92 5G','LG Stylo 6','LG V50 ThinQ','LG V40 ThinQ','LG G6','LG K62','LG K52','LG K42'],
            'lenovo' => ['Lenovo K13','Lenovo Z6 Pro','Lenovo Legion Phone Duel 2','Lenovo A6 Note','Lenovo Tab P11','Lenovo Tab M10','Lenovo K12 Note','Lenovo S5 Pro','Lenovo K10 Plus','Lenovo Z5 Pro GT','Lenovo K6 Power','Lenovo A7000','Lenovo Vibe K5','Lenovo Vibe K6','Lenovo P2'],
            'maxwest' => ['Nitro 55M','Nitro 63'],
            'microsoft' => ['Maxwest Nitro 5','Maxwest Nitro 5 Pro','Maxwest Orbit 2','Maxwest Orbit 3','Maxwest Astro 3','Maxwest Astro 4','Maxwest Lynx','Maxwest Odyssey','Maxwest Fire 1','Maxwest Fire 2'],
            'motorola' => ['Moto G Power','Moto G Stylus','Moto G Play','Moto G Fast','Moto G 5G','Moto Edge 30','Moto Edge 40','Moto Edge 30 Pro','Razr 5G','Razr 2022'],
            'nothing' => ['Phone (1)','Phone (2)'],
            'nuu' => ['X5','X6','X7','Q1','Q2','G3','G4','Z8','Z9','A5','A6'],
            'oneplus' => ['11','10 Pro','10T','9 Pro','9','9R','8 Pro','8','Nord 2','Nord CE 2','Nord CE 2 Lite','Nord 1','7T Pro','7T','7 Pro','7','6T','6','5T','5'],
            'osom' => ['OV1'],
            'raz-mobility' => ['MiniVision2','Memory Plus'],
            'red' => ['Hydrogen One','Komodo Phone','Mini Phone'],
            'samsung' => ['S23 Ultra','S23+','S23','S22 Ultra','S22+','S22','S21 Ultra','S21+','S21','S20 Ultra','S20+','S20','S10+','S10','S10e','Note 20 Ultra','Note 20','Note 10+','Note 10','Z Fold 5','Z Fold 4','Z Flip 5','Z Flip 4','A73','A72','A71','A53','A52','A52s','A33','A32','A13','A12','M53','M52','M33','M32','M12','M11'],
            'schok' => ['X1','X2','X3','X4','S1','S2','S3'],
            'siyata' => ['Mobile SD970','Mobile SD985','Mobile SD972','Mobile SD960','Mobile SD962'],
            'sky' => ['Vista','Platinum 5.5','Rainbow 3G','IM-100','Platinum 6.0','Elite','Chief'],
            'sonim' => ['XP8','XP5s','XP3','XP7','XP1','XP50','XP5800 Force','XP3300 Force'],
            'sony' => ['Xperia 1 IV','Xperia 5 III','Xperia 10 IV','Xperia 1 III','Xperia 5 II','Xperia 10 III','Xperia L4','Xperia 1 II','Xperia 5','Xperia 10 II','Xperia L3'],
            'topwell' => ['T1','T2','T3','T4','T5'],
            'unihertz' => ['Jelly 2','Titan','Atom XL','Atom L','Atom','TickTock'],
            'xplora' => ['X5 Play','X5 Play 2','X5 Play 3','XGO 2','XGO 3','X4','X6','X7'],
            'teracube' => ['One','2e','2e (2023)','3e'],
            'punkt' => ['MP01','MP02','MP03'],
        ];

        foreach($models as $brand_slug => $model_list){
            // buscar id del brand
            $brand_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}usaalo_brands WHERE slug=%s LIMIT 1", $brand_slug
            ));

            if ($brand_id) {
                foreach($model_list as $m){
                    // Insertar modelo si no existe
                    $wpdb->query($wpdb->prepare(
                        "INSERT IGNORE INTO {$wpdb->prefix}usaalo_models (brand_id,name,slug) VALUES (%d,%s,%s)",
                        $brand_id, $m, sanitize_title($m)
                    ));

                    // Obtener el ID del modelo recién insertado
                    $model_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}usaalo_models WHERE brand_id=%d AND slug=%s LIMIT 1",
                        $brand_id, sanitize_title($m)
                    ));

                    // Insertar configuración inicial solo si no existe
                    if ($model_id) {
                        $wpdb->query($wpdb->prepare(
                            "INSERT IGNORE INTO {$wpdb->prefix}usaalo_device_config 
                                (model_id, sim_supported, esim_supported, voice_supported, sms_supported, data_supported)
                            VALUES (%d, 1, 1, 0, 0, 1)",
                            $model_id
                        ));
                    }
                }
            }
        }

    }
}
