<?php
/**
 * SECOP Suite — Desinstalación limpia.
 *
 * @package SecopSuite
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Eliminar tabla de contratos
$table = $wpdb->prefix . 'secop_contracts';
$wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore

// Eliminar opciones del plugin
$options = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
        'secop_suite_%'
    )
);

foreach ($options as $option) {
    delete_option($option);
}

// Eliminar transients
delete_transient('secop_suite_import_progress');
delete_transient('secop_suite_import_running');

// Eliminar CPT de gráficas y sus meta
$charts = get_posts([
    'post_type'   => 'secop_chart',
    'numberposts' => -1,
    'post_status' => 'any',
    'fields'      => 'ids',
]);

foreach ($charts as $chart_id) {
    wp_delete_post($chart_id, true);
}

// Eliminar cron
wp_clear_scheduled_hook('secop_suite_scheduled_import');
