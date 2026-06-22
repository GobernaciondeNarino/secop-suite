<?php
/**
 * Template: Contratación — Catálogo de gráficas prediseñadas (admin).
 *
 * Lista cada preset de \SecopSuite\Tracking::presets() como una tarjeta con:
 *  - Vista previa de la gráfica (shortcode [secop_dep_chart preset="…"]).
 *  - Los 4 análisis (descripción, cualitativo, cuantitativo, predicción).
 *  - Shortcodes listos para copiar.
 *
 * Variables disponibles:
 * - $tracking: instancia de \SecopSuite\Tracking.
 *
 * @package SecopSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

/** @var \SecopSuite\Tracking $tracking */
$presets = $tracking->presets();
$tipos   = [
    'descripcion'  => __('Descripción', 'secop-suite'),
    'cualitativo'  => __('Análisis cualitativo', 'secop-suite'),
    'cuantitativo' => __('Análisis cuantitativo', 'secop-suite'),
    'prediccion'   => __('Predicción', 'secop-suite'),
];
?>
<div class="wrap secop-suite-catalogo">
    <h1><?php esc_html_e('Contratación — Gráficas prediseñadas', 'secop-suite'); ?></h1>

    <p class="description">
        <?php esc_html_e(
            'Catálogo de gráficas listas para usar. Cada tarjeta muestra una vista previa, sus cuatro textos de análisis generados automáticamente y los shortcodes que puede copiar y pegar en cualquier página o entrada. Las gráficas no se crean a mano: ya están definidas en el código y se actualizan con la vigencia en curso.',
            'secop-suite'
        ); ?>
    </p>

    <p>
        <a href="<?php echo esc_url(admin_url('edit.php?post_type=secop_dep_card')); ?>" class="button">
            <span class="dashicons dashicons-plus-alt2" style="vertical-align:middle;"></span>
            <?php esc_html_e('Gestionar gráficas a medida (avanzado)', 'secop-suite'); ?>
        </a>
    </p>

    <?php
    // Parámetros de personalización del shortcode [secop_dep_chart] (v5.1.8).
    $sc_params = [
        ['preset',      __('Clave de gráfica prediseñada (por_dependencia, top_contratistas, evolucion_mensual).', 'secop-suite')],
        ['card',        __('ID de una card a medida que sirve de base.', 'secop-suite')],
        ['dimension',   __('Dimensión de agrupación: dependencia, tipo_contrato, modalidad, estado, tipo_documento, programa, rubro, tercero, mensual.', 'secop-suite')],
        ['tipo',        __('Tipo de gráfica (bar, stacked_bar, treemap, pie, donut, line, area). Se valida contra la dimensión.', 'secop-suite')],
        ['metric',      __('Métrica: valordebito (valor ejecutado), saldoporejecutaresp (saldo por ejecutar), contratos (Nº de contratos), registros (Nº de registros).', 'secop-suite')],
        ['order',       __('Orden de las categorías: valor o etiqueta.', 'secop-suite')],
        ['orderdir',    __('Sentido del orden: ASC o DESC.', 'secop-suite')],
        ['limit',       __('Top-N: número máximo de categorías (0 = todas).', 'secop-suite')],
        ['colors',      __('Lista de colores hexadecimales separados por comas (#rrggbb,#rrggbb).', 'secop-suite')],
        ['dependencia', __('Filtra la gráfica por una dependencia concreta.', 'secop-suite')],
        ['legend',      __('Muestra u oculta la leyenda: on u off.', 'secop-suite')],
        ['height',      __('Altura de la gráfica en píxeles (por defecto 400).', 'secop-suite')],
    ];
    ?>
    <details class="ss-cat-params">
        <summary><strong><?php esc_html_e('Parámetros del shortcode [secop_dep_chart]', 'secop-suite'); ?></strong></summary>
        <p class="description">
            <?php esc_html_e('Todos son opcionales. Puede configurar una gráfica completa desde el propio shortcode, sin crear una card. Ejemplo:', 'secop-suite'); ?>
            <code>[secop_dep_chart dimension="modalidad" tipo="donut" metric="contratos" order="valor" orderdir="DESC" limit="5" colors="#844e80,#ff7300"]</code>
        </p>
        <table class="widefat striped" style="max-width:760px;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Parámetro', 'secop-suite'); ?></th>
                    <th><?php esc_html_e('Descripción', 'secop-suite'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sc_params as $param) : ?>
                    <tr>
                        <td><code><?php echo esc_html($param[0]); ?></code></td>
                        <td><?php echo esc_html($param[1]); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </details>

    <?php if (empty($presets)) : ?>
        <div class="notice notice-warning inline"><p>
            <?php esc_html_e('No hay gráficas prediseñadas disponibles.', 'secop-suite'); ?>
        </p></div>
    <?php endif; ?>

    <div class="ss-cat-grid">
        <?php foreach ($presets as $key => $p) :
            $key = (string) $key;
            // Dataset (una sola vez por preset) para los 4 análisis server-side.
            $ds = $tracking->build_dataset($p['dimension']);

            // Shortcodes copiables para este preset.
            $shortcodes = [
                '[secop_dep_chart preset="' . $key . '"]',
                '[secop_dep_analisis preset="' . $key . '" tipo="descripcion"]',
                '[secop_dep_analisis preset="' . $key . '" tipo="cualitativo"]',
                '[secop_dep_analisis preset="' . $key . '" tipo="cuantitativo"]',
                '[secop_dep_analisis preset="' . $key . '" tipo="prediccion"]',
            ];
            ?>
            <div class="ss-cat-card">
                <h2 class="ss-cat-title"><?php echo esc_html($p['titulo']); ?></h2>
                <p class="ss-cat-desc"><?php echo esc_html($p['descripcion']); ?></p>

                <div class="ss-cat-chart">
                    <?php
                    // Vista previa renderizada por el motor del Visualizer.
                    // El shortcode es una cadena estática del preset (segura).
                    echo do_shortcode('[secop_dep_chart preset="' . esc_attr($key) . '"]');
                    ?>
                </div>
                <p class="ss-cat-note description">
                    <em><?php esc_html_e('La gráfica se actualiza con la vigencia actual.', 'secop-suite'); ?></em>
                </p>

                <div class="ss-cat-analisis">
                    <h3><?php esc_html_e('Análisis', 'secop-suite'); ?></h3>
                    <?php foreach ($tipos as $tipo => $label) :
                        $metodo = 'analisis_' . $tipo;
                        ?>
                        <div class="ss-cat-analisis-item">
                            <h4><?php echo esc_html($label); ?></h4>
                            <p><?php echo esc_html(\SecopSuite\Stats::$metodo($ds)); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="ss-cat-shortcodes">
                    <h3><?php esc_html_e('Shortcodes', 'secop-suite'); ?></h3>
                    <?php foreach ($shortcodes as $sc) : ?>
                        <div class="ss-cat-sc-row">
                            <input type="text" class="ss-cat-sc-input" readonly
                                   value="<?php echo esc_attr($sc); ?>"
                                   onclick="this.select();" />
                            <button type="button" class="button ss-cat-copy"
                                    data-clipboard="<?php echo esc_attr($sc); ?>">
                                <?php esc_html_e('Copiar', 'secop-suite'); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
    .secop-suite-catalogo .ss-cat-params { margin: 16px 0; }
    .secop-suite-catalogo .ss-cat-params summary { cursor: pointer; }
    .secop-suite-catalogo .ss-cat-params table { margin-top: 10px; }
    .secop-suite-catalogo .ss-cat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    .secop-suite-catalogo .ss-cat-card {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 6px;
        padding: 16px 20px;
        box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
    }
    .secop-suite-catalogo .ss-cat-title { margin-top: 0; }
    .secop-suite-catalogo .ss-cat-chart { margin: 12px 0 4px; min-height: 200px; }
    .secop-suite-catalogo .ss-cat-note { margin: 0 0 12px; }
    .secop-suite-catalogo .ss-cat-analisis-item { margin-bottom: 10px; }
    .secop-suite-catalogo .ss-cat-analisis-item h4 { margin: 0 0 2px; }
    .secop-suite-catalogo .ss-cat-analisis-item p { margin: 0; color: #50575e; }
    .secop-suite-catalogo .ss-cat-sc-row {
        display: flex;
        gap: 6px;
        margin-bottom: 6px;
    }
    .secop-suite-catalogo .ss-cat-sc-input {
        flex: 1 1 auto;
        font-family: Consolas, Monaco, monospace;
        font-size: 12px;
    }
</style>
<script>
    (function () {
        document.querySelectorAll('.ss-cat-copy').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var text = btn.getAttribute('data-clipboard') || '';
                var done = function () {
                    var original = btn.textContent;
                    btn.textContent = '<?php echo esc_js(__('¡Copiado!', 'secop-suite')); ?>';
                    setTimeout(function () { btn.textContent = original; }, 1500);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(done).catch(function () {
                        window.prompt('<?php echo esc_js(__('Copie el shortcode:', 'secop-suite')); ?>', text);
                    });
                } else {
                    window.prompt('<?php echo esc_js(__('Copie el shortcode:', 'secop-suite')); ?>', text);
                }
            });
        });
    })();
</script>
