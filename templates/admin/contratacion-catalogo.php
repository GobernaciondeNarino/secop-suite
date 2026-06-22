<?php
/**
 * Template: Contratación — Catálogo de gráficas prediseñadas (admin).
 *
 * Lista cada preset de \SecopSuite\Tracking::presets() como una tarjeta ligera con:
 *  - Título y descripción.
 *  - Shortcodes listos para copiar.
 *
 * La vista previa de la gráfica y los 4 análisis NO se renderizan aquí (para que
 * el catálogo cargue al instante): aparecen solo al editar la card individual o
 * al insertar el shortcode en una página.
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
?>
<div class="wrap secop-suite-catalogo">
    <h1><?php esc_html_e('Contratación — Gráficas prediseñadas', 'secop-suite'); ?></h1>

    <p class="description">
        <?php esc_html_e(
            'Catálogo de gráficas listas para usar. Cada tarjeta muestra su descripción y los shortcodes que puede copiar y pegar en cualquier página o entrada. Las gráficas no se crean a mano: ya están definidas en el código y se actualizan con la vigencia en curso.',
            'secop-suite'
        ); ?>
    </p>

    <p class="description">
        <?php esc_html_e(
            'La galería presenta los mismos datos de contratación en un tipo de gráfica distinto por tarjeta (barras, treemap, donut, pie, burbujas, línea y área), cada una acompañada de su análisis automático.',
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
        ['preset',      sprintf(__('Clave de gráfica prediseñada (%s).', 'secop-suite'), implode(', ', array_keys($presets)))],
        ['card',        __('ID de una card a medida que sirve de base.', 'secop-suite')],
        ['dimension',   __('Dimensión de agrupación: dependencia, tipo_contrato, modalidad, tercero, mensual.', 'secop-suite')],
        ['tipo',        __('Tipo de gráfica (bar, stacked_bar, treemap, pie, donut, pack, line, area). Se valida contra la dimensión.', 'secop-suite')],
        ['metric',      __('Métrica: valor_contrato (valor del contrato), valordebito (valor ejecutado), saldoporejecutaresp (saldo por ejecutar), contratos (Nº de contratos), registros (Nº de registros).', 'secop-suite')],
        ['order',       __('Orden de las categorías: valor o etiqueta.', 'secop-suite')],
        ['orderdir',    __('Sentido del orden: ASC o DESC.', 'secop-suite')],
        ['limit',       __('Top-N: número máximo de categorías (0 = todas).', 'secop-suite')],
        ['colors',      __('Lista de colores hexadecimales separados por comas (#rrggbb,#rrggbb).', 'secop-suite')],
        ['dependencia', __('Filtra la gráfica por una dependencia concreta.', 'secop-suite')],
        ['legend',      __('Muestra u oculta la leyenda: on u off.', 'secop-suite')],
        ['height',      __('Altura de la gráfica en píxeles (por defecto 400).', 'secop-suite')],
        ['numberformat', __('Formato de números: colombiano (1.000.000), millones (1M), internacional (1,000,000) o sin_formato.', 'secop-suite')],
        ['xtitle',      __('Título del eje X.', 'secop-suite')],
        ['ytitle',      __('Título del eje Y.', 'secop-suite')],
        ['legendmode',  __('Modo de la leyenda: text (texto + icono) o icon (solo icono).', 'secop-suite')],
        ['legendpos',   __('Posición de la leyenda: bottom, top, left o right.', 'secop-suite')],
        ['toolbar',     __('Muestra u oculta la barra de herramientas: on u off.', 'secop-suite')],
        ['toolbaropts', __('Botones de la barra separados por comas: detail, share, data, image, download.', 'secop-suite')],
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

                <p class="ss-cat-note description">
                    <em><?php esc_html_e('Vista previa y análisis disponibles al editar la card o al insertar el shortcode en una página.', 'secop-suite'); ?></em>
                </p>

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

    <?php
    // v5.4.0: Red de contratación [secop_dep_red].
    $red_params = [
        ['dependencia', __('Filtra la red por una dependencia concreta (vacío = todas).', 'secop-suite')],
        ['limit',       __('Top-N de contratistas por valor (0 = TODOS, por defecto). Sin filtro de dependencia, la red muestra todos los contratistas; cap de seguridad 5000.', 'secop-suite')],
        ['height',      __('Altura mínima del lienzo en píxeles (por defecto 560).', 'secop-suite')],
        ['selector',    __('Muestra el selector de dependencia: on u off (por defecto on).', 'secop-suite')],
    ];
    $red_shortcodes = [
        '[secop_dep_red]',
        '[secop_dep_red limit="120" height="640"]',
        '[secop_dep_red dependencia="Secretaría General" selector="off"]',
    ];
    ?>
    <div class="ss-cat-card ss-cat-card-red" style="margin-top:20px;">
        <h2 class="ss-cat-title"><?php esc_html_e('Red de contratación', 'secop-suite'); ?></h2>
        <p class="ss-cat-desc">
            <?php esc_html_e('Red de contratación: dependencias como nodos centrales conectadas a contratistas, tipos y modalidades; tooltip con nº de contratos, valor y dependencia. Por defecto muestra TODOS los contratistas (magnitud completa) cuando no se filtra por dependencia.', 'secop-suite'); ?>
        </p>
        <details class="ss-cat-params">
            <summary><strong><?php esc_html_e('Parámetros del shortcode [secop_dep_red]', 'secop-suite'); ?></strong></summary>
            <table class="widefat striped" style="max-width:760px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Parámetro', 'secop-suite'); ?></th>
                        <th><?php esc_html_e('Descripción', 'secop-suite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($red_params as $param) : ?>
                        <tr>
                            <td><code><?php echo esc_html($param[0]); ?></code></td>
                            <td><?php echo esc_html($param[1]); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </details>
        <div class="ss-cat-shortcodes">
            <h3><?php esc_html_e('Shortcodes', 'secop-suite'); ?></h3>
            <?php foreach ($red_shortcodes as $sc) : ?>
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

    <?php
    // v5.4.1: Red ego (Rings) [secop_dep_rings].
    $rings_params = [
        ['dependencia', __('Dependencia central de la red ego (vacío = la de mayor valor).', 'secop-suite')],
        ['height',      __('Altura del lienzo en píxeles (por defecto 560).', 'secop-suite')],
        ['selector',    __('Muestra el selector de dependencia central: on u off (por defecto on).', 'secop-suite')],
    ];
    $rings_shortcodes = [
        '[secop_dep_rings]',
        '[secop_dep_rings height="640"]',
        '[secop_dep_rings dependencia="Secretaría General" selector="off"]',
    ];
    ?>
    <div class="ss-cat-card ss-cat-card-rings" style="margin-top:20px;">
        <h2 class="ss-cat-title"><?php esc_html_e('Red ego (Rings)', 'secop-suite'); ?></h2>
        <p class="ss-cat-desc">
            <?php esc_html_e('Red ego centrada en una dependencia (d3plus.Rings): la dependencia elegida ocupa el centro y sus contratistas, tipos y modalidades se disponen en anillos concéntricos. Si no se elige dependencia, se centra en la de mayor valor.', 'secop-suite'); ?>
        </p>
        <details class="ss-cat-params">
            <summary><strong><?php esc_html_e('Parámetros del shortcode [secop_dep_rings]', 'secop-suite'); ?></strong></summary>
            <table class="widefat striped" style="max-width:760px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Parámetro', 'secop-suite'); ?></th>
                        <th><?php esc_html_e('Descripción', 'secop-suite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rings_params as $param) : ?>
                        <tr>
                            <td><code><?php echo esc_html($param[0]); ?></code></td>
                            <td><?php echo esc_html($param[1]); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </details>
        <div class="ss-cat-shortcodes">
            <h3><?php esc_html_e('Shortcodes', 'secop-suite'); ?></h3>
            <?php foreach ($rings_shortcodes as $sc) : ?>
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

    <?php
    // v5.6.0: Explorador interactivo [secop_dep_explora].
    $explora_params = [
        ['campos', __('Campos de la fila 1 de cada contrato, separados por comas (orden de columnas). Disponibles: numero_del_contrato, valor_contrato, fecha_inicio_ejecucion, fecha_fin_ejecucion, modalidad_de_contratacion, tipo_de_contrato, nombretercero, documento_proveedor. El objeto del contrato siempre se muestra en una segunda fila a ancho completo.', 'secop-suite')],
        ['height', __('Altura mínima del treemap en píxeles (por defecto 460).', 'secop-suite')],
    ];
    $explora_shortcodes = [
        '[secop_dep_explora]',
        '[secop_dep_explora height="560"]',
        '[secop_dep_explora campos="numero_del_contrato,valor_contrato,nombretercero,tipo_de_contrato"]',
    ];
    ?>
    <div class="ss-cat-card ss-cat-card-explora" style="margin-top:20px;">
        <h2 class="ss-cat-title"><?php esc_html_e('Explorador interactivo', 'secop-suite'); ?></h2>
        <p class="ss-cat-desc">
            <?php esc_html_e('Explorador interactivo (treemap de dependencias): al hacer clic en una celda se despliega un panel inferior con dos secciones — la lista de modalidades (clicable) y un acordeón de contratistas cuyos elementos se expanden para mostrar los contratos del contratista (con campos de fila configurables y el objeto del contrato). Todo se actualiza dinámicamente por AJAX; al hacer clic en una modalidad se recarga la lista de contratistas. Incluye un botón para descargar TODA la vista (vigencia actual) en CSV.', 'secop-suite'); ?>
        </p>
        <details class="ss-cat-params">
            <summary><strong><?php esc_html_e('Parámetros del shortcode [secop_dep_explora]', 'secop-suite'); ?></strong></summary>
            <table class="widefat striped" style="max-width:760px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Parámetro', 'secop-suite'); ?></th>
                        <th><?php esc_html_e('Descripción', 'secop-suite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($explora_params as $param) : ?>
                        <tr>
                            <td><code><?php echo esc_html($param[0]); ?></code></td>
                            <td><?php echo esc_html($param[1]); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </details>
        <div class="ss-cat-shortcodes">
            <h3><?php esc_html_e('Shortcodes', 'secop-suite'); ?></h3>
            <?php foreach ($explora_shortcodes as $sc) : ?>
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

    <?php
    // v5.7.0: Predicción [secop_dep_prediccion].
    $pred_params = [
        ['dependencia', __('Filtra la serie por una dependencia concreta (vacío = todas).', 'secop-suite')],
        ['height',      __('Altura mínima del gráfico en píxeles (por defecto 420).', 'secop-suite')],
        ['selector',    __('Muestra el selector de dependencia: on u off (por defecto on).', 'secop-suite')],
    ];
    $pred_shortcodes = [
        '[secop_dep_prediccion]',
        '[secop_dep_prediccion height="520"]',
        '[secop_dep_prediccion dependencia="Secretaría General" selector="off"]',
    ];
    ?>
    <div class="ss-cat-card ss-cat-card-prediccion" style="margin-top:20px;">
        <h2 class="ss-cat-title"><?php esc_html_e('Predicción de contratación', 'secop-suite'); ?></h2>
        <p class="ss-cat-desc">
            <?php esc_html_e('Evolución mensual del valor contratado (según la fecha del contrato) con línea de proyección punteada a fin de vigencia. La serie mensual se construye a partir del mes del contrato (campo fecha, DD/MM/YYYY) y la proyección se calcula por regresión lineal sobre el valor contratado acumulado.', 'secop-suite'); ?>
        </p>
        <details class="ss-cat-params">
            <summary><strong><?php esc_html_e('Parámetros del shortcode [secop_dep_prediccion]', 'secop-suite'); ?></strong></summary>
            <table class="widefat striped" style="max-width:760px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Parámetro', 'secop-suite'); ?></th>
                        <th><?php esc_html_e('Descripción', 'secop-suite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pred_params as $param) : ?>
                        <tr>
                            <td><code><?php echo esc_html($param[0]); ?></code></td>
                            <td><?php echo esc_html($param[1]); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </details>
        <div class="ss-cat-shortcodes">
            <h3><?php esc_html_e('Shortcodes', 'secop-suite'); ?></h3>
            <?php foreach ($pred_shortcodes as $sc) : ?>
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
    .secop-suite-catalogo .ss-cat-note { margin: 0 0 12px; }
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
