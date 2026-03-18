<?php
/**
 * Template: Página de registros
 * 
 * @package SecopSuite
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap ss-admin-wrap">
    <h1>
        <span class="dashicons dashicons-list-view"></span>
        <?php esc_html_e('Registros de Contratos', 'secop-suite'); ?>
    </h1>

    <!-- Filtros -->
    <div class="ss-filters-panel">
        <form method="get" class="ss-filters-form">
            <input type="hidden" name="page" value="ss-records" />
            
            <div class="ss-filter-group">
                <label for="search"><?php esc_html_e('Buscar', 'secop-suite'); ?></label>
                <input type="text" 
                       id="search" 
                       name="search" 
                       value="<?php echo esc_attr($_GET['search'] ?? ''); ?>" 
                       placeholder="<?php esc_attr_e('Proveedor, descripción, referencia...', 'secop-suite'); ?>" />
            </div>

            <div class="ss-filter-group">
                <label for="anno"><?php esc_html_e('Año', 'secop-suite'); ?></label>
                <select id="anno" name="anno">
                    <option value=""><?php esc_html_e('Todos los años', 'secop-suite'); ?></option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?php echo esc_attr($year); ?>" <?php selected($_GET['anno'] ?? '', $year); ?>>
                            <?php echo esc_html($year); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ss-filter-group">
                <label for="estado"><?php esc_html_e('Estado', 'secop-suite'); ?></label>
                <select id="estado" name="estado">
                    <option value=""><?php esc_html_e('Todos los estados', 'secop-suite'); ?></option>
                    <?php foreach ($estados as $estado): ?>
                        <option value="<?php echo esc_attr($estado); ?>" <?php selected($_GET['estado'] ?? '', $estado); ?>>
                            <?php echo esc_html($estado); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ss-filter-actions">
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-search"></span>
                    <?php esc_html_e('Filtrar', 'secop-suite'); ?>
                </button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ss-records')); ?>" class="button">
                    <?php esc_html_e('Limpiar', 'secop-suite'); ?>
                </a>
            </div>
        </form>
    </div>

    <!-- Resumen -->
    <div class="ss-records-summary">
        <p>
            <?php
            printf(
                esc_html__('Mostrando %1$d de %2$d registros', 'secop-suite'),
                count($records),
                $total_records
            );
            ?>
        </p>
    </div>

    <!-- Tabla de registros -->
    <?php if (!empty($records)): ?>
        <div class="ss-table-responsive">
            <table class="wp-list-table widefat fixed striped ss-records-table">
                <thead>
                    <tr>
                        <th class="column-referencia"><?php esc_html_e('Referencia', 'secop-suite'); ?></th>
                        <th class="column-proveedor"><?php esc_html_e('Proveedor', 'secop-suite'); ?></th>
                        <th class="column-tipo"><?php esc_html_e('Tipo', 'secop-suite'); ?></th>
                        <th class="column-valor"><?php esc_html_e('Valor', 'secop-suite'); ?></th>
                        <th class="column-fecha"><?php esc_html_e('Fecha Firma', 'secop-suite'); ?></th>
                        <th class="column-estado"><?php esc_html_e('Estado', 'secop-suite'); ?></th>
                        <th class="column-acciones"><?php esc_html_e('Acciones', 'secop-suite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td class="column-referencia">
                                <strong><?php echo esc_html($record->referencia_del_contrato); ?></strong>
                                <div class="row-actions">
                                    <span class="id"><?php echo esc_html($record->id_contrato); ?></span>
                                </div>
                            </td>
                            <td class="column-proveedor">
                                <span class="ss-proveedor-name"><?php echo esc_html($record->proveedor_adjudicado); ?></span>
                                <div class="row-actions">
                                    <span class="doc"><?php echo esc_html($record->tipodocproveedor); ?>: <?php echo esc_html($record->documento_proveedor); ?></span>
                                </div>
                            </td>
                            <td class="column-tipo">
                                <?php echo esc_html($record->tipo_de_contrato); ?>
                                <div class="row-actions">
                                    <span class="modalidad"><?php echo esc_html($record->modalidad_de_contratacion); ?></span>
                                </div>
                            </td>
                            <td class="column-valor">
                                <strong>$<?php echo esc_html(number_format($record->valor_del_contrato, 0, ',', '.')); ?></strong>
                            </td>
                            <td class="column-fecha">
                                <?php echo $record->fecha_de_firma ? esc_html(date_i18n('d/m/Y', strtotime($record->fecha_de_firma))) : '-'; ?>
                            </td>
                            <td class="column-estado">
                                <?php
                                $estado_class = 'ss-estado-default';
                                if (stripos($record->estado_contrato, 'aprobado') !== false) {
                                    $estado_class = 'ss-estado-aprobado';
                                } elseif (stripos($record->estado_contrato, 'liquidado') !== false) {
                                    $estado_class = 'ss-estado-liquidado';
                                } elseif (stripos($record->estado_contrato, 'terminado') !== false) {
                                    $estado_class = 'ss-estado-terminado';
                                }
                                ?>
                                <span class="ss-estado <?php echo esc_attr($estado_class); ?>">
                                    <?php echo esc_html($record->estado_contrato); ?>
                                </span>
                            </td>
                            <td class="column-acciones">
                                <button type="button" 
                                        class="button button-small ss-view-details" 
                                        data-id="<?php echo esc_attr($record->id); ?>"
                                        title="<?php esc_attr_e('Ver detalles', 'secop-suite'); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>
                                <?php if (!empty($record->urlproceso)): ?>
                                    <a href="<?php echo esc_url($record->urlproceso); ?>" 
                                       target="_blank" 
                                       class="button button-small"
                                       title="<?php esc_attr_e('Ver en SECOP', 'secop-suite'); ?>">
                                        <span class="dashicons dashicons-external"></span>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($total_pages > 1): ?>
            <div class="ss-pagination">
                <?php
                $base_url = admin_url('admin.php?page=ss-records');
                if (!empty($_GET['search'])) {
                    $base_url = add_query_arg('search', sanitize_text_field($_GET['search']), $base_url);
                }
                if (!empty($_GET['anno'])) {
                    $base_url = add_query_arg('anno', sanitize_text_field($_GET['anno']), $base_url);
                }
                if (!empty($_GET['estado'])) {
                    $base_url = add_query_arg('estado', sanitize_text_field($_GET['estado']), $base_url);
                }

                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%', $base_url),
                    'format' => '',
                    'prev_text' => '&laquo; ' . __('Anterior', 'secop-suite'),
                    'next_text' => __('Siguiente', 'secop-suite') . ' &raquo;',
                    'total' => $total_pages,
                    'current' => $current_page,
                ]);
                ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="ss-no-records">
            <span class="dashicons dashicons-info"></span>
            <p><?php esc_html_e('No se encontraron registros con los filtros seleccionados.', 'secop-suite'); ?></p>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de detalles -->
<div id="ss-detail-modal" class="ss-modal" style="display: none;">
    <div class="ss-modal-content">
        <div class="ss-modal-header">
            <h2><?php esc_html_e('Detalles del Contrato', 'secop-suite'); ?></h2>
            <button type="button" class="ss-modal-close">&times;</button>
        </div>
        <div class="ss-modal-body" id="ss-detail-content">
            <!-- Contenido cargado por AJAX -->
        </div>
    </div>
</div>
