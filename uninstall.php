<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;
$tables = [
    'usaalo_plan_country',
    'usaalo_device_country',
    'usaalo_models',
    'usaalo_brands',
    'usaalo_countries'
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}$table");
}
