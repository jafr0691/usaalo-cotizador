<?php
if (!defined('ABSPATH')) exit;

class USAALO_Installer {

    public static function activate() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // 1️⃣ Países
        dbDelta("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}usaalo_countries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code CHAR(2) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            region VARCHAR(60) DEFAULT '',
            KEY idx_code (code)
        ) $charset");

        // 2️⃣ Marcas
        dbDelta("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}usaalo_brands (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(140) NOT NULL UNIQUE
        ) $charset");

        // 3️⃣ Modelos
        dbDelta("
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
        dbDelta("
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
        dbDelta("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}usaalo_product_country (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NOT NULL,
            country_id BIGINT UNSIGNED NOT NULL,
            FOREIGN KEY (country_id) REFERENCES {$wpdb->prefix}usaalo_countries(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_product_country (product_id, country_id),
            KEY idx_product (product_id),
            KEY idx_country (country_id)
        ) $charset");

        $default_config = [
            'show_brand' => 1,
            'show_model' => 1,
            'show_sim'   => 1,
            'show_esim'  => 1,
            'show_data'  => 1,
            'show_voice' => 1,
            'show_sms'   => 1,
            'select_pais'   => 1,
            'form_horizont'   => 1
        ];

        if (false === get_option('usaalo_cotizador_config')) {
            update_option('usaalo_cotizador_config', $default_config);
        }

        self::seed_initial();
    }

    public static function deactivate() {
        delete_option('usaalo_cotizador_config');
        // noop
    }
    public static function uninstall() {
        global $wpdb;

        // 1️⃣ Eliminar opción de configuración
        delete_option('usaalo_cotizador_config');

        // 2️⃣ Listado de tablas a eliminar
        $tables = [
            $wpdb->prefix . 'usaalo_product_country',
            $wpdb->prefix . 'usaalo_device_country',
            $wpdb->prefix . 'usaalo_device_config',
            $wpdb->prefix . 'usaalo_models',
            $wpdb->prefix . 'usaalo_brands',
            $wpdb->prefix . 'usaalo_countries'
        ];

        // 3️⃣ Ejecutar DROP TABLE para cada tabla
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }


    public static function seed_initial() {
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
            ['Samsung','samsung'],
            ['Sony','sony'],
            ['ZTE','zte'],
            ['Alcatel','alcatel'],
            ['Apple','apple'],
            ['AsusTek','asustek'],
            ['AT&T','at&t'],
            ['Basic','basic'],
            ['BLU','blu'],
            ['CAT','cat'],
            ['Cricket','cricket'],
            ['Coosea','coosea'],
            ['Dialn','dialn'],
            ['HMD','hmd'],
            ['Essential','essential'],
            ['Hot Pepper','hot-pepper'],
            ['Foxx','foxx'],
            ['HTC','htc'],
            ['Google','google'],
            ['Huawei','huawei'],
            ['Juniper','juniper'],
            ['Kyocera','kyocera'],
            ['LG','lg'],
            ['Motorola','motorola'],
            ['Nokia - HMD','nokia-hmd'],
            ['Nothing','nothing'],
            ['Nuu Inc','nuu-inc'],
            ['OnePlus','oneplus'],
            ['OSOM','osom'],
            ['Pepperl+Fuchs','pepperl-fuchs'],
            ['Punkt','punkt'],
            ['RAZ Mobility','raz-mobility'],
            ['RED','red'],
            ['Schok','schok'],
            ['Siyata','siyata'],
            ['Sky','sky'],
            ['Sonim','sonim'],
            ['SSB Trading','ssb-trading'],
            ['Topwell','topwell'],
            ['Unihertz','unihertz'],
            ['Nokia','nokia'],
            ['ASUS','asus'],
            ['Lenovo','lenovo'],
            ['Moolah','moolah'],
            ['Lunar','lunar'],
            ['Microsoft','microsoft'],
            ['Maxwest','maxwest'],
            ['Spacetalk','spacetalk']
        ];


        foreach($brands as $b){
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->prefix}usaalo_brands (name,slug) VALUES (%s,%s)", $b
            ));
        }

        // 2️⃣ Modelos representativos (model)
        //  brand_slug => [ modelos ]
        $models = [
            'Samsung' => ['Galaxy A71 5G','Galaxy SM-A716U**','Galaxy SM-A716U1','Galaxy A54','Galaxy SM-A546U','Galaxy SM-A546U1/DS','Galaxy A53 5G','Galaxy A536U1/DS','Galaxy SM-A536U','Galaxy SM-A536V*','Galaxy A52 5G','Galaxy SM-A526U','Galaxy SM-A526U1','Galaxy A51 5G','Galaxy SM-A516U','Galaxy SM-A516U1','Galaxy SM-A516UP','Galaxy A51','Galaxy SM-A515U','Galaxy SM-A515U1','Galaxy A50','Galaxy SM-A505U1','Galaxy SM-A505A','Galaxy SM-S506DL','Galaxy A42 5G','Galaxy SM-A426U1','Galaxy A36 5G','Galaxy SM-A366U1','Galaxy A35 5G','Galaxy SM-A356U','Galaxy SM-A356U1','Galaxy A32 5G SE','Galaxy SM-A326U**','Galaxy SM-A326U1/DS','Galaxy A26 5G','Galaxy SM-A266U1','Galaxy A25','Galaxy SM-A256U1','Galaxy SM-A256U','Galaxy A23 5G','Galaxy SM-A236U','Galaxy SM-A236U1/DS','Galaxy A21','Galaxy SM-A215U1','Galaxy A16 5G','Galaxy SM-A166U1','Galaxy A15','Galaxy SM-A156U1','Galaxy SM-A156U','Galaxy A14 5G SE','Galaxy SM-A146U1/DS','Galaxy SM-A146U','Galaxy A13 5G','Galaxy SM-A136U','Galaxy A13','Galaxy SM-A135U1','Galaxy SM-A135U','Galaxy A12','Galaxy SM-A125U','Galaxy SM-A125U1/DS','Galaxy A11','Galaxy SM-A115A','Galaxy SM-A115AZ','Galaxy SM-A115AP','Galaxy SM-A115U1','Galaxy A10e','Galaxy SM-A102U','Galaxy SM-A102UC','Galaxy SM-A102U1','Galaxy A6','Galaxy SM-A600A','Galaxy SM-A600AZ','Galaxy SM-A600U*','Galaxy A03s','Galaxy SM-A037U','Galaxy A02','Galaxy SM-A025A','Galaxy SM-A025U1/DS','Galaxy A01','Galaxy SM-A015A','Galaxy SM-A015U1','Galaxy Express Prime 3','Galaxy SM-J337A','Galaxy Express Prime 2/J3','Galaxy SM-J327A','Galaxy Express Prime/J3','Galaxy SM-J320A','Galaxy Express 3','Galaxy SM-J120A','Galaxy J7','Galaxy SM-J727A','Galaxy SM-J737A','Galaxy J7 Top (SM-J737U)*','Galaxy J3 Top (SM-J337U)*','Galaxy J2 Dash','Galaxy SM-J260A','Galaxy J2 Pure','Galaxy SM-J260AZ',
                'Galaxy Note 20','Galaxy SM-N980F','Galaxy SM-N980F/DS','Galaxy Note 20 Ultra','Galaxy SM-N985F','Galaxy SM-N985F/DS','Galaxy Note20 5G','Galaxy SM-N981U*','Galaxy SM-N981U1','Galaxy SM-N981W','Galaxy SM-N981B','Galaxy SM-N981B/DS','Galaxy Note20 Ultra 5G','Galaxy SM-N986U*','Galaxy SM-N986U1','Galaxy SM-N986B','Galaxy SM-N986B/DS','Galaxy Note10','Galaxy SM-N970U','Galaxy SM-N970U1*','Galaxy SM-N970W','Galaxy SM-N976B','Galaxy SM-N970F','Galaxy SM-N970F/DS','Galaxy Note10+','Galaxy SM-N975U','Galaxy SM-N975U1*','Galaxy SM-N975F','Galaxy SM-N975F/DS','Galaxy Note10+ 5G','Galaxy N976U','Galaxy Note9','Galaxy SM-N960U*','Galaxy SM-N960U1*','Galaxy Note8','Galaxy SM-N950U*','Galaxy SM-N950U1*','Galaxy Note5','Galaxy SM-N920A','Galaxy Note4','Galaxy SM-N910A*','Galaxy Note Edge','Galaxy SM-N915A*','Galaxy S25','Galaxy SM-S937U1','Galaxy S25 Ultra','Galaxy SM-S938U1','Galaxy S25+','Galaxy SM-S931U1','Galaxy S25','Galaxy SM-S936U1','Galaxy S24 FE SE','Galaxy SM-S721U1','Galaxy S24 Ultra','Galaxy SM-S928U1','Galaxy SM-S928U','Galaxy SM-S928B','Galaxy SM-S928N','Galaxy SM-S928W','Galaxy S24+','Galaxy SM-S926U1','Galaxy SM-S926U','Galaxy SM-S926B','Galaxy SM-S926N','Galaxy SM-S926W','Galaxy S24','Galaxy SM-S921U1','Galaxy SM-S921U','Galaxy SM-S921B','Galaxy SM-S921N','Galaxy SM-S921W','Galaxy S23','Galaxy SM-S911U','Galaxy SM-S911U1','Galaxy GT-S9110','Galaxy SM-S911B','Galaxy SM-S911N','Galaxy SM-S911W','Galaxy S23+','Galaxy SM-S916U','Galaxy SM-S916U1','Galaxy SM-S9160','Galaxy SM-S916B','Galaxy SM-S916N','Galaxy SM-S916W','Galaxy S23 Ultra','Galaxy SM-S918U','Galaxy SM-S918U1','Galaxy SM-S9180','Galaxy SM-S918B','Galaxy SM-S918N','Galaxy SM-S918W','Galaxy S23 FE','Galaxy SM-S711U','Galaxy SM-S711U1','Galaxy SM-S711W','Galaxy S22','Galaxy SM-S901U','Galaxy SM-S901U1',
                'Galaxy S22+','Galaxy SM-S906U','Galaxy SM-S906U1','Galaxy S22 Ultra','Galaxy SM-S908U','Galaxy SM-S908U1',
                'Galaxy S21 FE','Galaxy SM-G990U','Galaxy SM-G990U1','Galaxy SM-G990U3/DS','Galaxy SM-G990B','Galaxy SM-G990B/DS','Galaxy SM-G990F','Galaxy SM-G990F/DS','Galaxy S21 5G','Galaxy SM-G991U','Galaxy SM-G991U1','Galaxy SM-G991W','Galaxy SM-G991B','Galaxy SM-G991B/DS','Galaxy S21+ 5G','Galaxy SM-G996U','Galaxy G996U1','Galaxy SM-SM-G996W','Galaxy SM-G996B','Galaxy SM-G996B/DS','Galaxy S21 Ultra 5G','Galaxy SM-G998U','Galaxy SM-G998U1','Galaxy SM-G998W','Galaxy SM-G998B','Galaxy SM-G998B/SM-','Galaxy S20','Galaxy SM-G908F','Galaxy G980F/DS','Galaxy S20+','Galaxy SM-GG985F','Galaxy SM-G985F/DS','Galaxy S20 5G','Galaxy SM-G981U*','Galaxy SM-G981U1','Galaxy SM-G981W','Galaxy SM-G981B','Galaxy SM-G981B/DS','Galaxy S20+ 5G','Galaxy SM-G986U','Galaxy SM-G986U1','Galaxy SM-G986B','Galaxy SM-G986B/DS','Galaxy S20 Ultra 5G','Galaxy SM-G988U','Galaxy SM-G988U1','Galaxy SM-G988W','Galaxy SM-G988B','Galaxy SM-G988B/DS','Galaxy S20 FE','Galaxy SM-G781U','Galaxy SM-G781U1','Galaxy SM-G781UC','Galaxy SM-G781W','Galaxy SM-G781B','Galaxy SM-G781B/DS','Galaxy SM-G780F','Galaxy SM-G780F/DS','Galaxy S10','Galaxy SM-G973U*','Galaxy G973UC','Galaxy SM-G973U1*','Galaxy SM-G973W','Galaxy SM-G973F','Galaxy SM-G973F/DS','Galaxy S10+','Galaxy SM-G975U*','Galaxy SM-SM-G975U1*','Galaxy SM-G975W','Galaxy SM-G975F','Galaxy SM-G975F/DS','Galaxy S10 5G','Galaxy G977U','Galaxy G977B','Galaxy G977F','Galaxy S10e','Galaxy SM-G970U*','Galaxy SM-G970U1*','Galaxy G970W','Galaxy G970F','Galaxy G970F/DS','Galaxy S10 Lite','Galaxy SM-G770U1','Galaxy SM-G770F','Galaxy SM-G770F/DS','Galaxy S9','Galaxy SM-G960U*','Galaxy SM-G960U1*','Galaxy S9+','Galaxy SM-G965U*','Galaxy SM-G965U1*','Galaxy S8','Galaxy SM-G950U*','Galaxy SM-G950U1*','Galaxy S8 Active','Galaxy SM-G892A','Galaxy S8+','Galaxy SM-G955U*','Galaxy SM-G955U1*',
                'Galaxy S7 Edge','Galaxy SM-G935A','Galaxy S7+','Galaxy SM-G935U*','Galaxy S7','Galaxy SM-G930A','Galaxy SM-G930U*','Galaxy S7 Active','Galaxy SM-G891A','Galaxy S6 Edge Plus',
                'Galaxy SM-G928A','Galaxy S6 Edge','Galaxy SM-G925A','Galaxy S6','Galaxy SM-G920A','Galaxy S6 Active','Galaxy SM-G890A','Galaxy S5','Galaxy SM-G900A*','Galaxy S5 Active','Galaxy G870A','Galaxy S5 Mini','Galaxy G800A','Galaxy S4 Mini','Galaxy i257','Galaxy Xcover Pro 7','Galaxy SM-G766U1','Galaxy XCover Pro 6','Galaxy SM-G736U1','Galaxy SM-G736U','Galaxy SM-G736B/DS','Galaxy XCover FieldPro','Galaxy G889A','Galaxy XCover Pro','Galaxy G715U1','Galaxy G715A','Galaxy Z Flip FE','Galaxy SM-F761B','Galaxy SM-F761N','Galaxy SM-F761W','Galaxy Z Flip7','Galaxy SM-F761W','Galaxy SC-55F','Galaxy SCG35','Galaxy SM-F7660','Galaxy SM-F766B','Galaxy SM-F766N','Galaxy SM-F766Q','Galaxy SM-F766Z','Galaxy Z Fold7','Galaxy SC-56F','Galaxy SCG34','Galaxy SM-F9660','Galaxy SM-F966B','Galaxy SM-F966B/DS','Galaxy SM-F966N','Galaxy SM-F966Q','Galaxy SM-F966W','Galaxy SM-F966Z','Galaxy Z Flip6','Galaxy SC-54E','Galaxy SCG29','Galaxy SM-F7410','Galaxy SM-F741B','Galaxy SM-F741N','Galaxy SM-F741Q','Galaxy SM-F741U','Galaxy SM-F741W','Galaxy Z Fold6','Galaxy SM-F9560','Galaxy SM-F956B','Galaxy SM-F956B/DS','Galaxy SM-F956N','Galaxy SM-F956Q','Galaxy SM-F956U','Galaxy SM-F956W','Galaxy SC-55E','Galaxy SCG28','Galaxy Z Flip5','Galaxy SM-F731U1','Galaxy SM-F731U','Galaxy SM-F731B','Galaxy SM-F731N','Galaxy SM-F731D','Galaxy Z Fold5','Galaxy SM-F946U1','Galaxy SM-F946U','Galaxy SM-F946B','Galaxy SM-F946N','Galaxy SM-F946W','Galaxy Z Flip4','Galaxy SM-F721U','Galaxy SM-F721U1','Galaxy SM-F721W','Galaxy Z Flip3 5G','Galaxy SM-F711U','Galaxy SM-F711U1','Galaxy F711B','Galaxy Z Flip 5G','Galaxy SM-F707U','Galaxy SM-F707U1','Galaxy SM-F707W','Galaxy SM-F707B','Galaxy Z Flip','Galaxy SM-F700U/DS','Galaxy SM-F700U1','Galaxy SM-F700F','Galaxy SM-F700W/DS','Galaxy Z Fold4','Galaxy SM-F936U','Galaxy SM-F936U1',
                'Galaxy SM-F936W','Galaxy Z Fold3 5G','Galaxy SM-F926U',
                'Galaxy SM-F926U1','Galaxy SM-F926B','Galaxy SM-F926B/DS','Galaxy Z Fold2 5G','Galaxy SM-F916U','Galaxy SM-F916U1','Galaxy SM-F916W','Galaxy SM-F916B','Galaxy Z Fold','Galaxy SM-F900U','Galaxy SM-F900U1','Galaxy SM-F900F','Galaxy SM-F907B','Galaxy Book2','Galaxy SM-W737A','Galaxy Tab A','Galaxy SM-T387AA','Galaxy Tab A 8.4','Galaxy SM-T307U','Galaxy Tab E','Galaxy SM-T377A','Galaxy Tab 3 7.0','Galaxy SM-T217A','Galaxy Tab S9 FE 5G','Galaxy SM-X518U','Galaxy Tab S9+','Galaxy SM-X818U','Galaxy SM-X816N','Galaxy SM-X816B','Galaxy Tab 9 FE','Galaxy Tab S 8.4','Galaxy SM-T707A','Galaxy Tab S2','Galaxy SM-T817A','Galaxy Tab S2_R','Galaxy SM-T818A','Galaxy Tab S4','Galaxy SM-T837A','Galaxy Tab S5e','Galaxy SM-T727A','Galaxy Tab S7 5G','Galaxy SM-T878U','Galaxy Tab S7 5G FE','Galaxy SM-T737U','Galaxy Tab S8+ 5G','Galaxy SM-X808U','Galaxy View','Galaxy SM-T677A','Galaxy View2','Galaxy SM-T927A','Galaxy A53 5G','Galaxy SM-A536V','Galaxy A52 5G','Galaxy SM-A526U','Galaxy A42 5G','Galaxy SM-A42U','Galaxy A6','Galaxy A600U','Galaxy Z Flip3 5G','Galaxy SM-F711U','Galaxy Z Fold3 5G','Galaxy SM-F926U','Galaxy S21 Ultra 5G','Galaxy SM-G998U','Galaxy S21+ 5G','Galaxy SM-G996U','Galaxy S21 5G','Galaxy SM-G991U','Galaxy S21 FE','Galaxy SM-G990U','Galaxy S20 Ultra 5G','Galaxy SM-G988U','Galaxy S20+ 5G','Galaxy SM-G986U','Galaxy S20 5G','Galaxy SM-G981U','Galaxy SM-G981V','Galaxy Watch Active 2','Galaxy SM-R825U','Galaxy Watch Active 2','Galaxy SM-R835U','Gear S4','Galaxy SM-R805U','Gear S4','Galaxy SM-R815U','Galaxy Note20 Ultra 5G','Galaxy SM-N986U','Galaxy Note20 5G','Galaxy SM-N981U','Galaxy Note 10','Galaxy SM-N970U1','Galaxy Note 10+','Galaxy SM-N975U1','Galaxy Note 10+ 5G','Galaxy SM-SM-N976U','Galaxy SM-N976V','Galaxy S10','Galaxy SM-G973U','Galaxy (SM-G973U1','Galaxy S10 5G','Galaxy SM-G977P',
                'Galaxy S10+','Galaxy SM-G975U','Galaxy S10+','Galaxy SM-G975U1','Galaxy S10e','Galaxy SM-G970U1','Galaxy S10e','Galaxy SM-G970U','Galaxy Note 9','Galaxy SM-N960U1','Galaxy Note9','Galaxy SM-N960U','Galaxy S9','Galaxy SM-G960U','Galaxy S9','Galaxy SM-G960U1','Galaxy S9+','Galaxy SM-G965U','Galaxy S9+','Galaxy SM-G965U1','Galaxy S8','Galaxy SM-G950U','Galaxy S8','Galaxy SM-G950U1','Galaxy S8+','Galaxy SM-G955U1','Galaxy S8+','Galaxy SM-G955U','Galaxy Note8','Galaxy SM-N950U','Galaxy SM-N950U1','Galaxy S7','Galaxy SM-G930U','Galaxy S7+','Galaxy SM-G935U','Galaxy Note Edge','Galaxy SM-N915A','Galaxy S 5','Galaxy SM-G900A','Galaxy Note 4','Galaxy SM-N910A','Galaxy J7 Top',
                'Galaxy SM-J737U','Galaxy J3 Top','Galaxy SM-J337U','Galaxy Watch Active 2','Galaxy SM-R825U*','Galaxy SM-R835U*','Galaxy Watch Active 4','Galaxy SM-R865U','Galaxy SM-R875U','Gear S4','Galaxy SM-R805U*','Galaxy SM-R815U*','Galaxy Watch 3','Galaxy SM-R845U','Galaxy SM-R855U','Galaxy Watch 4','Galaxy SM-R885U','Galaxy SM-R895U','Galaxy Watch Pro','Galaxy SM-R925U','Galaxy Watch 5','Galaxy SM-R905U','Galaxy SM-R915U','Galaxy Watch6','Galaxy SM-R935U','Galaxy SM-R935F','Galaxy SM-R935N','Galaxy SM-R945U','Galaxy SM-R945F','Galaxy SM-R945N','Galaxy SM-R955U','Galaxy SM-R955F','Galaxy SM-R955N','Galaxy SM-R965U','Galaxy SM-R965F','Galaxy SM-R965N'],
            'Sony' => [	'Xperia 1* (J817)','Xperia 1 II (XQ-AT51)','Xperia 1 III (XQ-BC62)','Xperia 1 IV (XQ-CT62)','Xperia 1 V (XQ-DQ62)','Xperia 5* (J8270)','Xperia 5 II (XQ-AS62)','Xperia 5 III (XQ-BQ62)','Xperia 5 IV (XQ-CQ62)','Xperia 10* (I3123)','Xperia 10 Plus* (I3223)','Xperia PRO (XQ-AQ62)','Xperia PRO–I (XQ-BE62)','Xperia 1 (J8170)','Xperia 5 (J8270)','Xperia 10 (I3123)','Xperia 10 Plus(I3223)'],
            'ZTE' => [	'Avid 579 (Z5156CC)','Avid 589 (Z5158)','Axon Ultra 40 (A2023PG)','Axon Ultra 30 5G (A2022PG)','Axon Ultra 20 5G (A2022PG)','Axon 30 5G (A2322G)','Axon 20 5G (A2322G)','Axon 40 Pro (A2023G)','Blade Spark (Z971)','Link II (Z2335CC)','Maven 2 (Z831)','Maven 3 (Z835)','Zmax 10 (Z6250CC)','Zmax 11 (Z6251)','AT&T TREK 2 HD (K88)','AT&T Primetime (K92)'],
            'Alcatel' => ['IDEAL (4060A)','OneTouch Allura (5056O)','Essential','PH-1','TCL Tab 8 LTE/Gen 2 (8188S)','A3X (A600DL)','A3 (A509DL)','A1X (A508DL)','A1 (A507DL)','AXEL (5004R)','My FLIP2 (A406DL)','Flip 2 (T408DL)','idealXTRA (5059R)','idealXCITE/ CameoX (5044R)','IDEAL* (4060A)','OneTouch Allura* (5056O)','ONYX (5008R)','QUICKFLIP (4044C)','SMARTFLIP (4052R, 4052C)','TCL 4X 5G (T601DL)','TCL 20 Pro 5G (T810S)','TCL 30 T (T602DL)','TCL 30 Z (4188R)','TCL 40 X 5G (T609M)','TCL 40XL (T608M)','TCL 60 XE NXTPAPER 5G (T705M)','TCL Classic (4058R)','TCL Flip 2 (4058G)','TCL ION V (T430M)','TCL ION Z (T501C)'],
            'Apple' => ['iPhone6s','iPhone 6s Plus','iPhone SE (1.ª gen)','iPhone 7','iPhone 7 Plus','iPhone 8','iPhone 8 Plus','iPhone X','iPhone XS','iPhone XS Max','iPhone XR','iPhone 11','iPhone 11 Pro','iPhone 11 Pro Max','iPhone 12','iPhone 12 mini','iPhone 12 Pro','iPhone 12 Pro Ma','iPhone 13','iPhone 13 mini','iPhone 13 Pro','iPhone 13 Pro Max','iPhone 14','iPhone 14 Plus','iPhone 14 Pro','iPhone 14 Pro Max','iPhone 15','iPhone 15 Plus','iPhone 15 Pro','iPhone 15 Pro Max','iPhone 16','iPhone 16 Plus','iPhone 16 Pr','iPhone 16 Pro Max','iPhone 16e','iPhone 17','iPhone 17 Pro','iPhone 17 Pro Max','iPhone Air','iPad (4.ª gen)','iPad (5.ª gen)','iPad (6.ª gen)','iPad (7.ª gen)','iPad (8.ª gen)','iPad (9.ª gen)','iPad (10.ª gen)','iPad (11.ª gen)','iPad Air (1.ª gen)','iPad Air 2','iPad Air (3.ª gen)','iPad Air (4.ª gen)','iPad Air (5.ª gen)','iPad Air (6.ª gen)','iPad Pro (1.ª gen 12.9")','iPad Pro (1.ª gen 9.7")','iPad Pro (2.ª gen 10.5")','iPad Pro (2.ª gen 12.9")','iPad Pro (3.ª gen 11")','iPad Pro (3.ª gen 12.9")','iPad Pro (4.ª gen 11")','iPad Pro (4.ª gen 12.9")','iPad Pro (5.ª gen 11")','iPad Pro (5.ª gen 12.9")','iPad Pro (6.ª gen 11")','iPad Pro (6.ª gen 12.9")','iPad Pro (7.ª gen OLED 11")','iPad Pro (7.ª gen OLED 13")','iPad mini (1.ª gen)','iPad mini 2','iPad mini 3','iPad mini 4','iPad mini (5.ª gen)','iPad mini (6.ª gen)','iPad mini (7.ª gen)','Watch Series 4','Watch Series 5','Watch Series 6','Watch Series 7','Watch Series 8','Watch Series 9','Watch Series 10','Watch Series 11','Watch SE (1.ª gen)','Watch SE (2.ª gen)','Watch SE (3.ª gen)','Watch Ultra','Watch Ultra 2','Watch Ultra 3	'],
            'AsusTek' => ['ROG 3 (ASUS_I003D)','ROG 5 (ASUS_I005D)','ROG 5 (ASUS_I005DA)','ROG 5 (ASUS_I005DC)','ROG Phone 6 (ASUS_AI2201_F)','ROG Phone 6 Pro (ASUS_AI2201_D)','ROG Phone 7 (ASUS_AI2205_F)','ROG Phone 7 Ultimate (ASUS_AI2205_E)','ROG Phone 8 (ASUS_AI2401)','ROG Phone 8 Pro (ASUS_AI2401)','ROG Phone 9 (ASUS_AI2501D)','Zenfone 8 (ASUS_I006D)','Zenfone 9 (ASUS_AI2202)','Zenfone 10 (ASUS_AI2302)','Zenfone 11 Ultra (ASUS_AI2401_H)']	,
            'AT&T' => ['AXIA (QS5509A)', 'Calypso (318A)','Calypso 2 (U319AA)','Calypso 3 (U328AA)','Calypso 4 (U380AA)','Cingular Flex® 2 (U1030A)','Cingular Flex (EA211101)','Cingular Flip IV (U102AA)','Cingular Flip 2 (4044O)','Cingular Flip (Q28A)','Fusion 5G (EA211005)','Fusion Z (V340U)','Maestro3 (U626AA)','Maestro Plus (V350U)','Maestro (U202AA)','Maestro (V340U)','Maestro (EA1002)','Motivate 4 (SL112A)','Motivate 3 (EABF22206A)','Motivate (V341U)','Motivate Max (U668AA)','Radiant Core (U304AA)','Radiant Max (U705AA)','Radiant Max 5G (EC211001)','Vista (WTATTRW2)','AT&T Trek HD (9020A)'],
            'Basic' => ['Sunbeam F1'],
            'BLU' => ['C5LMax','G33','G34','G40','G53','G54','G64','G84','G91','G91s','G91 Pro','Tank Flip','S91','S91 Pro','V91'],	
            'CAT' => ['S62 Pro','S42G/S42H+'],	
            'Cricket' => ['Debut S2 (U380AC)','Debut Smart (SL101AE)','Icon (U304AAC)','Icon 3 (EC21100)','Icon 4 (WTCKT01)','Icon 5 (SL112C)','Influence (V350C)','Innovate E 5G (SN304AE)','Maestro3 (U626AA)','Bounce (SL201D)','Summit Flip (SL006D)','Magic 5G (U6080A)','Outlast (U680AC)','Ovation 2 (EC1002)','Ovation 3 (U668AC)','Vision (QS5509AC)','Vision 3 (U318AA)','Vision Plus (SL100EA)','Wave (FTU18A00QW)'],
            'Coosea' => ['Pronger (SL104D)','Elbert/Celero 5G SC (SN339D)'],
            'Dialn' => ['BlackviewA55','X65','X62','Nova','Neo'],
            'HMD' => ['Fusion (TA-165)','XR21 (TA-1592)','Vibe (TA-1590)'],
            'Essential' => ['PH 1*'],
            'Hot Pepper' => ['Chilaca Plus (HPPL60A)','Cascabel (HPPH88C)','Tepin (HPPL63A)'],	
            'Foxx' => ['A65L','A67'],
            'HTC' => ['Desire 626 (0PM9120)','Desire EYE (0PFH100)','One A9 (2PQ9120)','One M8* (0P6B120)','One M9 (0PJA110)'],
            'Google' => ['Pixel Watch (GD2WG)','Pixel Watch LTE (GWT9R)','Pixel Watch 2 (GD2WG)','Pixel 4a 5G (G025E)','Pixel 4a (G025J)','Pixel 4XL* (G020J)','Pixel 4XL* (G020P)','Pixel 4XL* (G020Q)','Pixel 4* (G020I)','Pixel 4* (G020M)','Pixel 4* (G020N)','Pixel 3a (G020E)','Pixel 3a (G020F)','Pixel 3a (G020G)','Pixel 3a XL (G020AC)','Pixel 3a XL (G020B)','Pixel 3a XL (G020C)','Pixel 3 XL (G013C)','Pixel 3 XL (G013D)','Pixel 3 (G013A)','Pixel 2XL','Pixel 2 (G011A)','Pixel 2 (G011C)','Pixel Fold (G9FPL)','Pixel 4 Unlocked','Pixel 4XL Unlocked'],
            'Huawei' => ['Ascend XT2 (H1711)','Ascend XT (H1711)'],
            'Juniper' => ['Archer (AR4)'],	
            'Kyocera' => ['DuraForce Pro 2 (E6920','DuraForce Pro (E6820)','DuraForce* (E6560)','DuraForce XD (E6790)','Dura XE* (E4710)','Dura XE Epic (E4830)','DuraXA Equip (E4831)','DuraForce (E6560)','Dura XE (E4710)'],	
            'LG' => ['Arena 2/Neon Plus (LM-X320APM/AM8)','Escape Plus (X320CM)','Escape2 (H443)','Classic Flip (L125DL)','Fortune 3 (LMK300AMC)','G8X (LM-G850QM)','G8X ThinQ (LM-G850UM)','G8 ThinQ (LM-G820UM**)','G8 ThinQ (LM-G820QM*)','G7 (G710ULM)','G7 ThinQ (LM-G710ULM)','G6 (H871S)','G6 ( H871)','G5 (H820)','G4 (H810)','G3* (D850)','G3 Vigor (D725)','G Flex 2 (H950)','G Vista 2 (H740)','G Vista* (D631)','Harmony 4 (LM-K400AM)','Harmony 3 (X420CM)','Journey (L322DL)','K92 5G (LM-K920AM)','K40 (LM-X420AS)','K40 (LM-X420QN*)','K30 (LM-X320QMG)','K20 (M255)','K10 (K425)','K51 (LM-K500QM)','Phoenix 5 (LM-K300AM)','Phoenix Plus (LM-X410AS)','Phoenix 4 (LM-X210APM)','Phoenix 3 (M150)','Phoenix 2 (K371)','Premier Pro LTE (LML414DL)','Premier Pro LTE (LML413DL)','Premier Pro Plus (L455D)','Prime 2 (LM-X320AA)','Q70 (LM-Q620QM)','Rebel 4 (LML211BL)','Reflect (L555DL)','Risio 4 (LM-K300CMR)','Solo LTE (L423DL)','Stylo 6 (LMQ730AM)','Stylo 5 (LM-Q720CS)','Stylo 5 (L722D)','Stylo 5 (LM-Q720QM*)','Stylo 5+ (LM-Q720AM)','Stylo 4 (LML713DL)','Stylo 4 (LM-Q710ULM*)','Stylo 4+ (LM-Q710WA)','Velvet (LM-G900UM)','V60 ThinQ (LM-V600AM)','V40 ThinQ (LM-V405UA**)','V35 (LM-V350ULM)','V35 ThinQ (LM-V350AWM**)','V30 (H931)','V20 (H910)','V10 (H900)','Wing (F100VM)','Xpression Plus 3 (LM-K400AKR)','Xpression Plus 2 (LM-X420AS8)','X Venture (H700)','G Pad F 8.0 (V495)','G Pad X 10.1 (V930)','G Pad X 8.0 (V520)','G8 ThinQ (LM-G820QM)','Stylo 5 (LM-Q720QM)','Stylo 4 (LM-Q710ULM)','K40 (LM-X420QN)','G Vista (D631)','G3 (D850)'],	
            'Motorola' => ['g Stylus 5G 2023 (XT2315-1)','g Stylus 5G 2023 (XT2315-4)','g Stylus 5G 2023 (XT2315-5)','g Stylus 5G 2022 (XT2215-1)','g Stylus 5G 2022 (XT2215-2)','g Stylus 5G 2022 (XT2215-3)','g Stylus 5G 2022 XT2215-4)','g Stylus 5G 2022 (XT2215DL)','g Stylus 5G (XT2131-1)','g Stylus 5G (XT2131-3)','g Stylus 5G (XT2131-4)','g Stylus 5G (XT2131DL)','g Stylus (XT2043-4)','g Stylus (XT2115-1)','g Pro (XT2043-8)','g100 (XT2125-4)','g32 (XT2235-1)','g30 (XT2129-1)','g9 Play (XT2083-1)','g9 Power (XT2091-4)','g8 Power (XT2041-1)','g8 Power Lite (XT2055-2)','g8 Play (XT2015-2)','g8 Plus (XT2019-2)','g7 (XT1962-1)','g7 Play (XT1952-4)','g7 Play (XT1952-5)','g7 Plus (XT1965-T)','g7 Power (XT1955-5)','g6 Forge (XT1922)','g6 Play (XT1922-9)','g Play 2021 (XT2093DL)','g Power 2021 (XT2117-2)','g Pure (XT2163-2)','g Pure (XT2163-4)','g Pure (XT2163-6)','g Pure (XT2163-2PP)','g Pure (XT2163DL)','One 5G Ace (XT2113-2)','One 5G Ace (XT2113-5)','One 5G (XT2075-2)','One 5G (XT2075-5)','Razr ultra 2025 (XT2551-1)','Razr 2025 (XT2553-3)','Razr 2024 (XT2453-3)','Razr+ 2024 (XT2451-1)','Razr 2023 (XT2321-3)','Razr 2023 (XT2321-5)','Razr 2023 (XT2323-2)','Razr 2023 (XT2323-5)','Razr 2023 (XT2323-6)','Razr 5G (XT2071-3)','Razr (XT2071-2)','Thinkphone (XT2309-3)','XT2235-3','Z4 (XT1980-3)','Z2 Force Edition (XT1789-04)','moto 5g 2024 (XT2417-1)','g Stylus 2023 (XT2317-2)','g Stylus 2023 (XT2317-3)','g Stylus 2022 (XT2211-1)','g Stylus 2022 (XT2211-2)','g Stylus 2022 (XT2211DL)'],	
            'Nokia - HMD' => ['1.4 (TA1323)*','3.1.A (TA1140)','3.1.C (TA1141)','3.1 Plus (TA1124)','3.4 (TA1285)*','5.4 (TA1333)*','8.3 (TA1243)*','6300 4G (TA-1324)','Lumia 830','C2 Tennen (TA1226)','C2 Tava (TA1218)','C210 (TA-1584)','C300 (TA-1515)','C5 Endi (TA1222)','G10 (TA1338)*','G20 (TA1343)','G50 (TA1390)','G100 (TA-1430)','G300 (N1374DL)','G400 5G (TA-1476)','TA-1600','XR20 (TA-1371)','XR21 (TA-1486)'],
            'Nothing' => ['Nothing Phone 2'],	
            'Nuu Inc' => ['A10L (N5502L)','B20 (N6501L)','NUU N13 (S6514L)','X6 Plus (S6003L)','X7 Plus (S6601L)'],
            'OnePlus' => ['Nord N30 5G (CPH2513)','Nord N200 (DE2117)','Nord N10 (BE2026)','Nord N100 (BE2011)','Nord N100 (BE2013)','Nord N100 (BE2029)','Open (CPH2551)','13R (CPH2647)','13 (CPH2655)','12R (CPH2611)','12 (CPH2583)','11 5G (CPH2451)','10T 5G (CPH2417)','10 Pro 5G (NE2215)','9 5G (LE2115)','9 Pro 5G (LE2125)','8 5G (IN2010)','8 5G (IN2015)','8 5G UW (IN2019)','8 (IN2011)','8 (IN2013)','8 (IN2017)','8T (KB2000)','8T (KB2001)','8T (KB2005)','8 Pro (IN2021)','8 Pro (IN2023)','8 Pro (IN2025)','7T (HD1905)','7 Pro (GM1917)','6T (A6013)'],
            'OSOM' => [	'Saga (200731A)'],
            'Pepperl+Fuchs' => ['Smart Ex-02 ROW','Smart Ex-03'],	
            'Punkt' => ['MP 02'],
            'RAZ Mobility' => [	'MiniVision2+ (KAP04300)'],	
            'RED' => [	'Hydrogen One (H1A1000/H1T1000)'],	
            'Schok' => ['Freedom Turbo XL (SFT656128)','Schok Classic (SC3218)','Schok Volt (SV67Q)'],	
            'Siyata' => ['SD7'],	
            'Sky' => ['Elite G63'],
            'Sonim' => ['RS60 (S6001)','XP10 (XP9900)','XP8 (XP8800)','XP7 (XP7700)','XP5s (XP5800)','XP5Plus (XP5900)','XP3 (XP3800)','XP3Plus (XP3900)'],	
            'SSB Trading' => ['M55 Maze Speed / S55 Soho Style','G799'],	
            'Topwell' => ['S988'],	
            'Unihertz' => ['Jelly 2'],	
            'Nokia' => ['Nokia 1.4 (TA1323)','Nokia 3.4 (TA1285)','Nokia G10 (TA1338)','Nokia 5.4 (TA1333)','Nokia 8.3 (TA1243)'],
            'ASUS' => ['Memo Pad 7 LTE (ME375CL)'],
            'Lenovo' => ['Moto Tab (TB-X704A)'],
            'Moolah' => ['M1 Tablet'],	
            'Lunar' => ['Eclipse L1','Astro A65 (MX-A65)','Astro A66'],	
            'Microsoft' => ['Lumia 950','Lumia 640 XL','Surface Duo 2 (1995)','Surface Duo (1930)','Surface 3 (1657)'],
            'Maxwest' => ['Nitro N62 (MX-NN62)','Nitro A65'],
            'Spacetalk' => ['Adventurer (ST2-4G-2)','Xplora','XGO3','Kidzi','Tick Talk Tech','Tick Talk 4']
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
