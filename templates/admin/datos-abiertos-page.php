<?php
/**
 * Template: Datos Abiertos — Submenú admin.
 *
 * Documenta los shortcodes y endpoints REST de datos abiertos.
 *
 * @package SecopSuite
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Datos Abiertos — SECOP Suite', 'secop-suite'); ?></h1>

    <p><?php esc_html_e(
        'Estos shortcodes publican datos contractuales como datos abiertos, exponiendo endpoints REST accesibles públicamente en formatos JSON, CSV y TXT.',
        'secop-suite'
    ); ?></p>

    <h2><?php esc_html_e('Shortcodes disponibles', 'secop-suite'); ?></h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Shortcode', 'secop-suite'); ?></th>
                <th><?php esc_html_e('Descripción', 'secop-suite'); ?></th>
                <th><?php esc_html_e('Parámetros', 'secop-suite'); ?></th>
                <th><?php esc_html_e('Formatos', 'secop-suite'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>[secop_export]</code></td>
                <td><?php esc_html_e(
                    'Todos los contratos importados (tabla completa SECOP). Muestra botones de descarga CSV, TXT y enlace JSON API REST hacia los endpoints /export y /contracts.',
                    'secop-suite'
                ); ?></td>
                <td><code>title</code>, <code>class</code></td>
                <td>CSV, TXT, JSON</td>
            </tr>
            <tr>
                <td><code>[secop_consulta formato="tabla"]</code></td>
                <td><?php esc_html_e(
                    'Seguimiento de ejecución presupuestal por dependencia para la vigencia actual (desde el VIEW de Sysman). Muestra tabla resumen y botones de descarga/API hacia los endpoints /consulta.',
                    'secop-suite'
                ); ?></td>
                <td><code>formato</code> — tabla | csv | txt | json</td>
                <td>tabla, CSV, TXT, JSON</td>
            </tr>
        </tbody>
    </table>

    <h2><?php esc_html_e('Endpoints REST', 'secop-suite'); ?></h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Endpoint', 'secop-suite'); ?></th>
                <th><?php esc_html_e('Formato', 'secop-suite'); ?></th>
                <th><?php esc_html_e('Descripción', 'secop-suite'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code><?php echo esc_html(rest_url('secop-suite/v1/consulta')); ?></code></td>
                <td>JSON</td>
                <td><?php esc_html_e(
                    'Ejecución presupuestal de la vigencia actual, paginada. Parámetros: page (default 1), per_page (default 100, máx 1000). Acceso público.',
                    'secop-suite'
                ); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html(rest_url('secop-suite/v1/consulta/csv')); ?></code></td>
                <td>CSV</td>
                <td><?php esc_html_e(
                    'Descarga CSV completa de la vigencia actual, ordenada por valor de débito descendente. Acceso público.',
                    'secop-suite'
                ); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html(rest_url('secop-suite/v1/consulta/txt')); ?></code></td>
                <td>TXT</td>
                <td><?php esc_html_e(
                    'Descarga TXT ancho fijo de la vigencia actual, ordenada por valor de débito descendente. Acceso público.',
                    'secop-suite'
                ); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html(rest_url('secop-suite/v1/export/csv')); ?></code></td>
                <td>CSV</td>
                <td><?php esc_html_e(
                    'Exportación CSV completa de todos los contratos SECOP (tabla principal). Acceso público.',
                    'secop-suite'
                ); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html(rest_url('secop-suite/v1/export/txt')); ?></code></td>
                <td>TXT</td>
                <td><?php esc_html_e(
                    'Exportación TXT ancho fijo de todos los contratos SECOP (tabla principal). Acceso público.',
                    'secop-suite'
                ); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html(rest_url('secop-suite/v1/contracts')); ?></code></td>
                <td>JSON</td>
                <td><?php esc_html_e(
                    'API REST de contratos SECOP con filtros: per_page, page, anno, estado, search, fecha_desde, fecha_hasta. Acceso público.',
                    'secop-suite'
                ); ?></td>
            </tr>
        </tbody>
    </table>
</div>
