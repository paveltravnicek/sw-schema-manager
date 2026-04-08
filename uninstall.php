<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('sw_schema_global');
delete_option('sw_schema_automatic');

global $wpdb;
$meta_keys = [
    '_sw_schema_mode',
    '_sw_schema_custom_json',
    '_sw_schema_presets',
    '_sw_schema_services',
    '_sw_schema_faqs',
];

foreach ($meta_keys as $meta_key) {
    $wpdb->delete($wpdb->postmeta, ['meta_key' => $meta_key], ['%s']);
}
