<?php
/**
 * Tracking — Módulo de Seguimiento de Dependencias y Datos Abiertos.
 *
 * @package SecopSuite
 */
declare(strict_types=1);

namespace SecopSuite;

if (!defined('ABSPATH')) {
    exit;
}

final class Tracking
{
    private Database $db;
    private const POST_TYPE = 'secop_dep_card';

    /**
     * Dimensiones del módulo → tipos de gráfica compatibles.
     * Limitadas a columnas que existen en la vista vista_secop_sysman.
     */
    private const COMPAT = [
        'dependencia'   => ['bar', 'stacked_bar', 'treemap', 'pie', 'donut', 'pack'],
        'tipo_contrato' => ['bar', 'stacked_bar', 'treemap', 'pie', 'donut', 'pack'],
        'modalidad'     => ['bar', 'stacked_bar', 'treemap', 'pie', 'donut', 'pack'],
        'tercero'       => ['bar', 'stacked_bar', 'treemap', 'pie', 'donut', 'pack'],
        'mensual'       => ['line', 'area'],
    ];

    /** Columna del VIEW que agrupa cada dimensión. */
    private const DIM_COLUMN = [
        'dependencia'   => 'nombredependencia',
        'tipo_contrato' => 'tipo_de_contrato',
        'modalidad'     => 'modalidad_de_contratacion',
        'tercero'       => 'nombretercero',
        'mensual'       => 'mes',
    ];

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->register_hooks();
    }

    // ── Compatibilidad dimensión ↔ tipo (puro, testeable) ──────
    public static function dimensions(): array { return array_keys(self::COMPAT); }

    /**
     * Métricas configurables del módulo de Contratación.
     * Cada entrada define la etiqueta visible, la función de agregación y la
     * columna del VIEW a agregar. COUNT_DISTINCT lo traduce el Visualizer a
     * COUNT(DISTINCT col). valor_contrato es la métrica de valor PRINCIPAL.
     * NOTA: un contrato puede aparecer en varias filas presupuestales, por lo
     * que SUM(valor_contrato) sobrecuenta ligeramente los contratos con varios
     * comprobantes — compromiso aceptado y documentado al usar valor_contrato.
     */
    public function metrics(): array
    {
        return [
            'valor_contrato'      => ['label' => __('Valor del contrato', 'secop-suite'),  'agg' => 'SUM',            'col' => 'valor_contrato'],
            'valordebito'         => ['label' => __('Valor ejecutado', 'secop-suite'),     'agg' => 'SUM',            'col' => 'valordebito'],
            'saldoporejecutaresp' => ['label' => __('Saldo por ejecutar', 'secop-suite'),  'agg' => 'SUM',            'col' => 'saldoporejecutaresp'],
            'contratos'           => ['label' => __('Nº de contratos', 'secop-suite'),      'agg' => 'COUNT_DISTINCT', 'col' => 'numero_del_contrato'],
            'registros'           => ['label' => __('Nº de registros', 'secop-suite'),      'agg' => 'COUNT',          'col' => 'numero_de_proceso'],
        ];
    }

    /**
     * Whitelist de columnas filtrables del módulo de Contratación.
     * Sólo columnas que existen en la vista vista_secop_sysman. El valor es la
     * etiqueta amigable mostrada en el editor. build_chart_query revalida la
     * columna contra las columnas reales y prepara el valor con $wpdb->prepare,
     * por lo que esta whitelist es la primera (no la única) línea de defensa.
     *
     * @return array<string,string> [columna => etiqueta].
     */
    public function filter_columns(): array
    {
        return [
            'nombredependencia'         => __('Dependencia', 'secop-suite'),
            'tipo_de_contrato'          => __('Tipo de contrato', 'secop-suite'),
            'modalidad_de_contratacion' => __('Modalidad', 'secop-suite'),
            'nombretercero'             => __('Contratista', 'secop-suite'),
            'documento_proveedor'       => __('Documento proveedor', 'secop-suite'),
            'mes'                       => __('Mes', 'secop-suite'),
            'valor_contrato'            => __('Valor del contrato', 'secop-suite'),
            'valordebito'               => __('Valor ejecutado', 'secop-suite'),
            'saldoporejecutaresp'       => __('Saldo por ejecutar', 'secop-suite'),
            'numero_del_contrato'       => __('Nº de contrato', 'secop-suite'),
        ];
    }

    /**
     * v5.6.0: Whitelist de campos seleccionables para la fila 1 de cada contrato
     * en el explorador. Sólo columnas reales de vista_secop_sysman. El valor es la
     * etiqueta amigable mostrada en la tabla del acordeón. objeto_a_contratar NO
     * está aquí: siempre es la segunda fila (ancho completo) de cada contrato.
     *
     * @return array<string,string> [columna => etiqueta].
     */
    public function explora_fields(): array
    {
        return [
            'numero_del_contrato'       => __('Nº contrato', 'secop-suite'),
            'valor_contrato'            => __('Valor', 'secop-suite'),
            'fecha_inicio_ejecucion'    => __('Inicio', 'secop-suite'),
            'fecha_fin_ejecucion'       => __('Fin', 'secop-suite'),
            'modalidad_de_contratacion' => __('Modalidad', 'secop-suite'),
            'tipo_de_contrato'          => __('Tipo', 'secop-suite'),
            'nombretercero'             => __('Contratista', 'secop-suite'),
            'documento_proveedor'       => __('Documento', 'secop-suite'),
        ];
    }

    public static function is_compatible(string $dimension, string $type): bool
    {
        return in_array($type, self::COMPAT[$dimension] ?? [], true);
    }

    public static function default_type(string $dimension): string
    {
        return self::COMPAT[$dimension][0] ?? 'bar';
    }

    public static function compat_help(string $dimension): string
    {
        $types = self::COMPAT[$dimension] ?? [];
        return sprintf(
            'Tipos compatibles para «%s»: %s.',
            $dimension,
            implode(', ', $types)
        );
    }

    private function register_hooks(): void
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_card_meta'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_shortcode('secop_dep_chart',    [$this, 'sc_chart']);
        add_shortcode('secop_dep_analisis', [$this, 'sc_analisis']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_shortcode('secop_seguimiento',               [$this, 'sc_seguimiento']);
        add_shortcode('secop_dep_contratos',             [$this, 'sc_contratos']);
        add_action('wp_ajax_secop_dep_contratos',        [$this, 'ajax_contratos']);
        add_action('wp_ajax_nopriv_secop_dep_contratos', [$this, 'ajax_contratos']);
        // Drill-down: contratos asociados al valor de una dimensión (click en gráfica).
        add_action('wp_ajax_secop_dep_drill',            [$this, 'ajax_drill']);
        add_action('wp_ajax_nopriv_secop_dep_drill',     [$this, 'ajax_drill']);
        // v5.4.0: red de contratación (grafo de fuerza dependencias↔contratistas/tipos/modalidades).
        add_shortcode('secop_dep_red',                   [$this, 'sc_red']);
        add_action('wp_ajax_secop_dep_network',          [$this, 'ajax_network']);
        add_action('wp_ajax_nopriv_secop_dep_network',   [$this, 'ajax_network']);
        // v5.4.1: red ego (Rings) centrada en una dependencia (d3plus.Rings).
        add_shortcode('secop_dep_rings',                 [$this, 'sc_rings']);
        // v5.7.0: predicción — serie mensual (mes del contrato) + proyección punteada.
        add_shortcode('secop_dep_prediccion',            [$this, 'sc_prediccion']);
        add_action('wp_ajax_secop_dep_prediccion',        [$this, 'ajax_prediccion']);
        add_action('wp_ajax_nopriv_secop_dep_prediccion', [$this, 'ajax_prediccion']);
        // v5.6.0: explorador interactivo (treemap de dependencias + panel modalidades/contratistas).
        add_shortcode('secop_dep_explora',                       [$this, 'sc_explora']);
        add_action('wp_ajax_secop_dep_explora_tree',             [$this, 'ajax_explora_tree']);
        add_action('wp_ajax_nopriv_secop_dep_explora_tree',      [$this, 'ajax_explora_tree']);
        add_action('wp_ajax_secop_dep_explora_modalidades',        [$this, 'ajax_explora_modalidades']);
        add_action('wp_ajax_nopriv_secop_dep_explora_modalidades', [$this, 'ajax_explora_modalidades']);
        add_action('wp_ajax_secop_dep_explora_contratistas',        [$this, 'ajax_explora_contratistas']);
        add_action('wp_ajax_nopriv_secop_dep_explora_contratistas', [$this, 'ajax_explora_contratistas']);
        // Vista previa en vivo del editor de cards (solo admin, sin nopriv).
        add_action('wp_ajax_secop_dep_preview',          [$this, 'ajax_dep_preview']);
        add_shortcode('secop_consulta',                  [$this, 'sc_consulta']);
    }

    public function register_post_type(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'          => __('Contratación · a medida', 'secop-suite'),
                'singular_name' => __('Card', 'secop-suite'),
                'menu_name'     => __('Contratación · a medida', 'secop-suite'),
                'add_new_item'  => __('Nueva Card', 'secop-suite'),
                'edit_item'     => __('Editar Card', 'secop-suite'),
            ],
            'public'             => false,
            'show_ui'            => true,
            // No se muestra como submenú propio para evitar duplicar "Contratación":
            // la página catálogo es el acceso principal; las cards a medida se gestionan
            // desde el enlace del catálogo (edit.php?post_type=secop_dep_card).
            'show_in_menu'       => false,
            'capability_type'    => 'post',
            'supports'           => ['title'],
            'menu_icon'          => 'dashicons-analytics',
        ]);
    }

    // ── Queries del VIEW (vigencia actual) ────────────────────────

    /** Vigencia activa (año actual), server-side. */
    public function current_vigencia(): int
    {
        return (int) current_time('Y');
    }

    /** Agrupación por dimensión para la vigencia actual. */
    public function group_by_dimension(string $dimension, ?string $dependencia = null): array
    {
        global $wpdb;
        if (!isset(self::DIM_COLUMN[$dimension])) return [];

        $cache_key = 'secop_trk_' . md5('group_by_dimension|' . $dimension . '|' . ($dependencia ?? '') . '|' . $this->current_vigencia());
        $cached = get_transient($cache_key);
        if (is_array($cached)) return $cached;

        $view   = $this->db->get_view_name();
        $col    = self::DIM_COLUMN[$dimension];
        $cols   = $this->db->get_table_columns($view);
        if (!isset($cols[$col])) return [];

        $where  = ['anio = %d'];
        $params = [$this->current_vigencia()];
        if ($dependencia !== null && $dependencia !== '') {
            $where[]  = 'nombredependencia = %s';
            $params[] = $dependencia;
        }
        $where_sql = implode(' AND ', $where);

        // Métrica de valor: valor_contrato (valor principal) y conteo de contratos distintos.
        // NOTA: un contrato puede repetirse en varias filas presupuestales, por lo que
        // SUM(valor_contrato) sobrecuenta ligeramente los contratos multi-comprobante.
        $sql = "SELECT `{$col}` AS label,
                       SUM(valor_contrato) AS valor,
                       COUNT(DISTINCT numero_del_contrato) AS conteo
                FROM `{$view}` WHERE {$where_sql}
                GROUP BY `{$col}` ORDER BY valor DESC";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $result = array_map(fn($r) => [
            'label'  => $r['label'] ?? 'N/D',
            'valor'  => (float) $r['valor'],
            'conteo' => (int) $r['conteo'],
        ], $rows ?: []);

        set_transient($cache_key, $result, 30 * MINUTE_IN_SECONDS);
        return $result;
    }

    /** Serie mensual acumulada de ejecución (para predicción). */
    public function monthly_series(?string $dependencia = null): array
    {
        global $wpdb;

        $cache_key = 'secop_trk_' . md5('monthly_series|' . ($dependencia ?? '') . '|' . $this->current_vigencia());
        $cached = get_transient($cache_key);
        if (is_array($cached)) return $cached;

        $view = $this->db->get_view_name();
        $where  = ['anio = %d'];
        $params = [$this->current_vigencia()];
        if ($dependencia !== null && $dependencia !== '') {
            $where[]  = 'nombredependencia = %s';
            $params[] = $dependencia;
        }
        $where_sql = implode(' AND ', $where);

        // v5.7.0: el mes proviene del MES DEL CONTRATO (columna varchar `fecha`,
        // formato DD/MM/YYYY), NO de `mes` (mes de actualización de Sysman, que es
        // ~constante → serie degenerada). El literal de formato '%d/%m/%Y' se escribe
        // con porcentajes DOBLES ('%%d/%%m/%%Y') para que $wpdb->prepare lo emita como
        // '%d/%m/%Y' (un solo %) en lugar de tratarlo como placeholders: sólo
        // anio (%d) y la dependencia (%s) son placeholders preparados. Las filas cuya
        // `fecha` no parsea devuelven NULL y se excluyen del agregado.
        $expr = "MONTH(STR_TO_DATE(`fecha`, '%%d/%%m/%%Y'))";
        $sql = "SELECT {$expr} AS mes, SUM(valor_contrato) AS valor
                FROM `{$view}`
                WHERE {$where_sql} AND STR_TO_DATE(`fecha`, '%%d/%%m/%%Y') IS NOT NULL
                GROUP BY {$expr} ORDER BY mes ASC";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $acc = 0.0; $serie = [];
        foreach ($rows ?: [] as $r) {
            if ($r['mes'] === null) continue; // defensa extra: meses no parseables.
            $acc += (float) $r['valor'];
            $serie[] = [(int) $r['mes'], $acc];
        }

        set_transient($cache_key, $serie, 30 * MINUTE_IN_SECONDS);
        return $serie;
    }

    /**
     * v5.7.0: Dataset del gráfico de predicción. Serie observada (valor contratado
     * acumulado por mes del contrato) + proyección de cierre de vigencia mediante
     * regresión lineal. El primer punto «Proyectado» es el punto de UNIÓN (último
     * mes observado con su valor observado) para que la línea punteada conecte con
     * la sólida sin salto.
     *
     * @param string|null $dependencia Filtra por una dependencia (null = todas).
     * @return array{points:array<int,array{mes:int,valor:float,serie:string}>,meta:array<string,mixed>}
     */
    public function prediccion_data(?string $dependencia = null): array
    {
        $cache_key = 'secop_trk_' . md5('prediccion_data|' . ($dependencia ?? '') . '|' . $this->current_vigencia());
        $cached = get_transient($cache_key);
        if (is_array($cached)) return $cached;

        $serie = $this->monthly_series($dependencia);
        $reg   = \SecopSuite\Stats::linear_regression($serie);

        $points = [];
        foreach ($serie as [$m, $acc]) {
            $points[] = ['mes' => (int) $m, 'valor' => (float) $acc, 'serie' => 'Observado'];
        }

        if (!$reg['insufficient'] && !empty($serie)) {
            $last = end($serie);
            $L = (int) $last[0];
            // Punto de unión: arranca en el último mes observado con su valor real.
            $points[] = ['mes' => $L, 'valor' => (float) $last[1], 'serie' => 'Proyectado'];
            for ($m = $L + 1; $m <= 12; $m++) {
                $points[] = [
                    'mes'   => $m,
                    'valor' => max(0.0, (float) \SecopSuite\Stats::project($reg, (float) $m)),
                    'serie' => 'Proyectado',
                ];
            }
        }

        $result = [
            'points' => $points,
            'meta'   => [
                'slope'        => $reg['slope'],
                'r2'           => $reg['r2'],
                'n'            => $reg['n'],
                'cierre'       => $reg['insufficient'] ? null : \SecopSuite\Stats::project($reg, 12.0),
                'insufficient' => (bool) $reg['insufficient'],
                'vigencia'     => $this->current_vigencia(),
            ],
        ];

        set_transient($cache_key, $result, 30 * MINUTE_IN_SECONDS);
        return $result;
    }

    /** Construir el dataset de análisis completo para una card. */
    public function build_dataset(string $dimension, ?string $dependencia = null): array
    {
        global $wpdb;

        $cache_key = 'secop_trk_' . md5('build_dataset|' . $dimension . '|' . ($dependencia ?? '') . '|' . $this->current_vigencia());
        $cached = get_transient($cache_key);
        if (is_array($cached)) return $cached;

        $cats  = $this->group_by_dimension($dimension, $dependencia);
        $serie = $this->monthly_series($dependencia);
        $view  = $this->db->get_view_name();

        $where  = ['anio = %d'];
        $params = [$this->current_vigencia()];
        if ($dependencia) { $where[] = 'nombredependencia = %s'; $params[] = $dependencia; }
        $where_sql = implode(' AND ', $where);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $tot = $wpdb->get_row($wpdb->prepare(
            "SELECT SUM(valordebito) AS ejec, SUM(saldoporejecutaresp) AS saldo,
                    COUNT(DISTINCT numero_del_contrato) AS conteo,
                    SUM(valor_contrato) AS valc
             FROM `{$view}` WHERE {$where_sql}", ...$params), ARRAY_A);

        $result = [
            'dimension'     => $dimension,
            'vigencia'      => $this->current_vigencia(),
            'meses'         => count($serie),
            'categorias'    => $cats,
            'serie_mensual' => $serie,
            'total_valor'   => (float) ($tot['valc'] ?? 0),
            'total_conteo'  => (int) ($tot['conteo'] ?? 0),
            'ejecutado'     => (float) ($tot['ejec'] ?? 0),
            'saldo'         => (float) ($tot['saldo'] ?? 0),
        ];

        set_transient($cache_key, $result, 30 * MINUTE_IN_SECONDS);
        return $result;
    }

    // ── CPT metaboxes y guardado ──────────────────────────────────

    public function add_meta_boxes(): void
    {
        add_meta_box('secop_dep_card_config', __('Configuración de la Card', 'secop-suite'),
            [$this, 'render_config_metabox'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('secop_dep_card_shortcodes', __('Shortcodes', 'secop-suite'),
            [$this, 'render_shortcodes_metabox'], self::POST_TYPE, 'side', 'high');
        add_meta_box('secop_dep_card_preview', __('Análisis y Vista Previa', 'secop-suite'),
            [$this, 'render_preview_metabox'], self::POST_TYPE, 'normal', 'default');
    }

    public function render_config_metabox(\WP_Post $post): void
    {
        wp_nonce_field('secop_dep_card_config', 'secop_dep_card_nonce');
        $config = get_post_meta($post->ID, '_secop_dep_card_config', true) ?: [];
        $dimensions = self::COMPAT;
        $metrics    = $this->metrics();
        // Etiquetas amigables para el desplegable de dimensión.
        // Limitadas a las columnas que existen en la vista vista_secop_sysman.
        $dim_labels = [
            'dependencia'    => __('Dependencia', 'secop-suite'),
            'tipo_contrato'  => __('Tipo de contrato', 'secop-suite'),
            'modalidad'      => __('Modalidad de contratación', 'secop-suite'),
            'tercero'        => __('Contratista', 'secop-suite'),
            'mensual'        => __('Mensual (evolución)', 'secop-suite'),
        ];
        $dependencias = $this->list_dependencies();
        $filter_columns = $this->filter_columns();
        include SECOP_SUITE_DIR . 'templates/admin/dep-card-config.php';
    }

    public function render_shortcodes_metabox(\WP_Post $post): void
    {
        $id = (int) $post->ID;
        echo '<p>' . esc_html__('Gráfica:', 'secop-suite') . '</p>';
        echo '<code>[secop_dep_chart card="' . $id . '"]</code>';
        foreach (['descripcion', 'cualitativo', 'cuantitativo', 'prediccion'] as $tipo) {
            echo '<p><code>[secop_dep_analisis card="' . $id . '" tipo="' . $tipo . '"]</code></p>';
        }
    }

    public function render_preview_metabox(\WP_Post $post): void
    {
        ?>
        <div class="secop-dep-card-preview">
            <p>
                <button type="button" class="button button-primary" id="ss-dep-refresh-preview">
                    <span class="dashicons dashicons-update" style="vertical-align:text-bottom"></span>
                    <?php esc_html_e('Actualizar vista previa', 'secop-suite'); ?>
                </button>
                <span class="description"><?php esc_html_e('La vista previa se actualiza automáticamente al cambiar la configuración.', 'secop-suite'); ?></span>
            </p>
            <div id="ss-dep-preview-render" class="ss-chart-render" style="min-height:380px"></div>
            <h4><?php esc_html_e('Datos', 'secop-suite'); ?></h4>
            <div id="ss-dep-preview-data"></div>
            <h4><?php esc_html_e('Consulta SQL generada', 'secop-suite'); ?></h4>
            <pre id="ss-dep-preview-sql" style="white-space:pre-wrap;overflow:auto;background:#f6f7f7;padding:10px;border:1px solid #dcdcde;"></pre>
            <h4><?php esc_html_e('Análisis', 'secop-suite'); ?></h4>
            <div id="ss-dep-preview-analisis"></div>
        </div>
        <?php
    }

    /**
     * AJAX (solo admin) — genera la vista previa en vivo del editor de cards.
     * Reconstruye la config a partir de los valores actuales del formulario,
     * la traduce a una config segura del Visualizer y ejecuta la query SIN caché
     * para devolver los datos y el SQL realmente ejecutado.
     */
    public function ajax_dep_preview(): void
    {
        check_ajax_referer('secop_dep_preview', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']);
        }

        $cfg = [
            'dimension'   => sanitize_text_field(wp_unslash($_POST['dimension'] ?? 'dependencia')),
            'chart_type'  => sanitize_text_field(wp_unslash($_POST['chart_type'] ?? '')),
            'metric'      => sanitize_text_field(wp_unslash($_POST['metric'] ?? 'valor_contrato')),
            'dependencia' => sanitize_text_field(wp_unslash($_POST['dependencia'] ?? '')),
            'order'       => sanitize_text_field(wp_unslash($_POST['order'] ?? 'valor')),
            'order_dir'   => sanitize_text_field(wp_unslash($_POST['order_dir'] ?? 'DESC')),
            'limit'       => (int) ($_POST['limit'] ?? 0),
            'colors'      => array_values(array_filter(
                array_map('trim', explode(',', sanitize_text_field(wp_unslash($_POST['colors'] ?? '')))),
                static fn($c) => (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $c)
            )),
            // v5.3.0: personalización del gráfico. show_legend/show_toolbar llegan como
            // '1'/'0' o 'true'/'false' desde JS — se interpretan como truthy.
            'number_format'   => in_array($_POST['number_format'] ?? '', ['colombiano', 'millones', 'internacional', 'sin_formato'], true) ? sanitize_text_field(wp_unslash($_POST['number_format'])) : 'colombiano',
            'chart_height'    => max(150, (int) ($_POST['chart_height'] ?? 400)),
            'x_axis_title'    => sanitize_text_field(wp_unslash($_POST['x_axis_title'] ?? '')),
            'y_axis_title'    => sanitize_text_field(wp_unslash($_POST['y_axis_title'] ?? '')),
            'show_legend'     => in_array((string) ($_POST['show_legend'] ?? '1'), ['1', 'true', 'on'], true),
            'legend_mode'     => in_array($_POST['legend_mode'] ?? '', ['text', 'icon'], true) ? sanitize_text_field(wp_unslash($_POST['legend_mode'])) : 'text',
            'legend_position' => in_array($_POST['legend_position'] ?? '', ['bottom', 'top', 'left', 'right'], true) ? sanitize_text_field(wp_unslash($_POST['legend_position'])) : 'bottom',
            'show_toolbar'    => in_array((string) ($_POST['show_toolbar'] ?? '1'), ['1', 'true', 'on'], true),
            // toolbar_options llega como lista separada por comas (JS) o como array.
            'toolbar_options' => array_values(array_intersect(
                array_map('trim', is_array($_POST['toolbar_options'] ?? '')
                    ? array_map('sanitize_text_field', wp_unslash($_POST['toolbar_options']))
                    : explode(',', sanitize_text_field(wp_unslash($_POST['toolbar_options'] ?? '')))),
                ['detail', 'share', 'data', 'image', 'download']
            )),
            // v5.3.2: campos del tooltip (array o cadena separada por comas).
            'tooltip_fields'  => $this->sanitize_tooltip_fields(wp_unslash($_POST['tooltip_fields'] ?? ['categoria', 'valor'])),
            // v5.3.1: filtros configurables enviados por la vista previa.
            'filters'         => $this->sanitize_filter_rows($_POST['filters'] ?? []),
        ];

        $chart_config = $this->card_to_chart_config($cfg);
        $res = \SecopSuite\Plugin::get_instance()->visualizer()->get_chart_data_with_sql($chart_config);

        // Análisis en vivo para la dimensión/dependencia de la card.
        $ds = $this->build_dataset($cfg['dimension'], $cfg['dependencia'] !== '' ? $cfg['dependencia'] : null);
        $analisis = [
            'descripcion'  => \SecopSuite\Stats::analisis_descripcion($ds),
            'cualitativo'  => \SecopSuite\Stats::analisis_cualitativo($ds),
            'cuantitativo' => \SecopSuite\Stats::analisis_cuantitativo($ds),
            'prediccion'   => \SecopSuite\Stats::analisis_prediccion($ds),
        ];

        wp_send_json_success([
            'config'   => $chart_config,
            'data'     => $res['data'],
            'sql'      => $res['sql'],
            'analisis' => $analisis,
        ]);
    }

    public function save_card_meta(int $post_id, \WP_Post $post): void
    {
        // FIX 7: sanitize + unslash antes de verificar el nonce
        if (!isset($_POST['secop_dep_card_nonce'])) return;
        $nonce = sanitize_text_field(wp_unslash($_POST['secop_dep_card_nonce']));
        if (!wp_verify_nonce($nonce, 'secop_dep_card_config')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $dimension = sanitize_text_field($_POST['dep_dimension'] ?? 'dependencia');
        if (!isset(self::COMPAT[$dimension])) $dimension = 'dependencia';
        $type = sanitize_text_field($_POST['dep_chart_type'] ?? '');
        if (!self::is_compatible($dimension, $type)) $type = self::default_type($dimension);

        $colors_raw = sanitize_text_field(wp_unslash($_POST['dep_colors'] ?? ''));
        $colors = array_values(array_filter(array_map('trim', explode(',', $colors_raw)), static fn($c) => (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $c)));

        $metric = sanitize_text_field($_POST['dep_metric'] ?? 'valor_contrato');
        if (!in_array($metric, array_keys($this->metrics()), true)) $metric = 'valor_contrato';
        $order = sanitize_text_field($_POST['dep_order'] ?? 'valor');
        if (!in_array($order, ['valor', 'etiqueta'], true)) $order = 'valor';
        $order_dir = sanitize_text_field($_POST['dep_order_dir'] ?? 'DESC');
        if (!in_array($order_dir, ['ASC', 'DESC'], true)) $order_dir = 'DESC';

        $config = [
            'dimension'   => $dimension,
            'chart_type'  => $type,
            'dependencia' => sanitize_text_field($_POST['dep_dependencia'] ?? ''),
            'metric'      => $metric,
            'order'       => $order,
            'order_dir'   => $order_dir,
            'limit'       => (int) ($_POST['dep_limit'] ?? 0),
            'colors'      => $colors,
            // v5.3.0: personalización del gráfico (mirror del Visualizer).
            'number_format'   => in_array($_POST['dep_number_format'] ?? '', ['colombiano', 'millones', 'internacional', 'sin_formato'], true) ? $_POST['dep_number_format'] : 'colombiano',
            'chart_height'    => max(150, (int) ($_POST['dep_chart_height'] ?? 400)),
            'x_axis_title'    => sanitize_text_field(wp_unslash($_POST['dep_x_title'] ?? '')),
            'y_axis_title'    => sanitize_text_field(wp_unslash($_POST['dep_y_title'] ?? '')),
            'show_legend'     => isset($_POST['dep_show_legend']),
            'legend_mode'     => in_array($_POST['dep_legend_mode'] ?? '', ['text', 'icon'], true) ? $_POST['dep_legend_mode'] : 'text',
            'legend_position' => in_array($_POST['dep_legend_position'] ?? '', ['bottom', 'top', 'left', 'right'], true) ? $_POST['dep_legend_position'] : 'bottom',
            'show_toolbar'    => isset($_POST['dep_show_toolbar']),
            'toolbar_options' => array_values(array_intersect((array) ($_POST['dep_toolbar_options'] ?? []), ['detail', 'share', 'data', 'image', 'download'])),
            // v5.3.2: campos del tooltip (categoría / valor / nº de contratos).
            'tooltip_fields'  => $this->sanitize_tooltip_fields($_POST['dep_tooltip_fields'] ?? ['categoria', 'valor']),
            // v5.3.1: filtros configurables (columna/operador/valor).
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verificado arriba.
            'filters'         => $this->sanitize_filter_rows($_POST['dep_filters'] ?? []),
        ];
        update_post_meta($post_id, '_secop_dep_card_config', $config);
        update_post_meta($post_id, '_secop_chart_config', $this->card_to_chart_config($config));
    }

    /**
     * Sanea las filas de filtros personalizados provenientes del formulario o de
     * la petición AJAX de vista previa. Cada fila es ['field','operator','value'].
     * Descarta filas con columna no permitida, operador inválido (→ '=') o valor
     * vacío. El valor se desliza+sanea; build_chart_query lo prepara después.
     *
     * @param mixed $raw Array de filas (potencialmente sucio) o no-array.
     * @return array<int,array{field:string,operator:string,value:string}>
     */
    private function sanitize_filter_rows($raw): array
    {
        if (!is_array($raw)) return [];
        $allowed_ops = ['=', '!=', '>', '<', '>=', '<=', 'LIKE'];
        $cols = $this->filter_columns();
        $clean = [];
        foreach ($raw as $row) {
            if (!is_array($row)) continue;
            $field = sanitize_text_field(wp_unslash($row['field'] ?? ''));
            if (!isset($cols[$field])) continue;
            $op  = (string) ($row['operator'] ?? '=');
            if (!in_array($op, $allowed_ops, true)) $op = '=';
            $val = sanitize_text_field(wp_unslash($row['value'] ?? ''));
            if ($val === '') continue;
            $clean[] = ['field' => $field, 'operator' => $op, 'value' => $val];
        }
        return array_values($clean);
    }

    /**
     * v5.3.2: Sanea los campos del tooltip provenientes del formulario, de la
     * vista previa AJAX o del atributo del shortcode. Acepta un array o una
     * cadena separada por comas. Devuelve un subconjunto validado de
     * ['categoria','valor','conteo']; por defecto ['categoria','valor'].
     *
     * @param mixed $raw Array o string (lista separada por comas).
     * @return array<int,string>
     */
    private function sanitize_tooltip_fields($raw): array
    {
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        if (!is_array($raw)) {
            $raw = [];
        }
        $clean = array_values(array_intersect(
            array_map(static fn($v) => sanitize_text_field((string) $v), $raw),
            ['categoria', 'valor', 'conteo']
        ));
        return empty($clean) ? ['categoria', 'valor'] : $clean;
    }

    public function enqueue_admin_assets(string $hook): void
    {
        // Detección robusta de la pantalla de edición de la card (el global
        // $post_type a veces no está disponible en admin_enqueue_scripts).
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $is_card_screen = ($screen && $screen->post_type === self::POST_TYPE)
            || (($GLOBALS['post_type'] ?? '') === self::POST_TYPE)
            || (in_array($hook, ['post.php', 'post-new.php'], true)
                && ($_GET['post_type'] ?? '') === self::POST_TYPE);
        if (!$is_card_screen) return;
        // Reutiliza las librerías de gráfica del Visualizer vía el handle compartido.
        wp_enqueue_style('secop-suite-admin', SECOP_SUITE_URL . 'assets/css/admin.css', [], SECOP_SUITE_VERSION);
        // Carga el stack de gráficas del frontend para que ChartManager pueda renderizar
        // la vista previa en la pantalla de edición de la tarjeta.
        \SecopSuite\Plugin::get_instance()->visualizer()->enqueue_frontend_chart_stack();

        // Script de vista previa en vivo: depende de secop-suite-frontend para
        // disponer de window.SSChartRender (motor de gráficas reutilizable).
        wp_enqueue_script('secop-dep-card-preview', SECOP_SUITE_URL . 'assets/js/dep-card-preview.js',
            ['jquery', 'secop-suite-frontend'], SECOP_SUITE_VERSION, true);
        wp_localize_script('secop-dep-card-preview', 'secopDepPreview', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('secop_dep_preview'),
            'strings' => [
                'loading' => __('Generando vista previa…', 'secop-suite'),
                'error'   => __('Error al generar la vista previa', 'secop-suite'),
                'noData'  => __('La consulta no devolvió datos.', 'secop-suite'),
            ],
        ]);
    }

    // ── Shortcodes frontend ───────────────────────────────────────

    private function resolve_config(array $atts): array
    {
        if (!empty($atts['card'])) {
            $cfg = get_post_meta((int) $atts['card'], '_secop_dep_card_config', true) ?: [];
        } else {
            $cfg = [];
        }
        $dimension = sanitize_text_field($atts['dimension'] ?? ($cfg['dimension'] ?? 'dependencia'));
        if (!isset(self::COMPAT[$dimension])) $dimension = 'dependencia';
        $type = sanitize_text_field($atts['tipo'] ?? ($cfg['chart_type'] ?? ''));
        if (!self::is_compatible($dimension, $type)) $type = self::default_type($dimension);
        $dep = sanitize_text_field($atts['dependencia'] ?? ($cfg['dependencia'] ?? ''));
        return ['dimension' => $dimension, 'chart_type' => $type, 'dependencia' => $dep];
    }

    /**
     * Maps a dep card config array to a Visualizer-compatible _secop_chart_config.
     * The returned config can be stored on the dep card post so the Visualizer AJAX
     * (secop_suite_get_chart_data) serves data using the dep card's post ID.
     *
     * @param array       $cfg        The _secop_dep_card_config array.
     * @param string|null $dependencia Override dependencia filter (null = use $cfg value).
     */
    public function card_to_chart_config(array $cfg, ?string $dependencia = null): array
    {
        $view      = $this->db->get_view_name();
        $dimension = $cfg['dimension'] ?? 'dependencia';
        $type      = self::is_compatible($dimension, $cfg['chart_type'] ?? '')
            ? $cfg['chart_type']
            : self::default_type($dimension);
        $metrics    = $this->metrics();
        $raw_metric = $cfg['metric'] ?? 'valor_contrato';
        $metric_key = isset($metrics[$raw_metric]) ? $raw_metric : 'valor_contrato';
        $m          = $metrics[$metric_key];
        $x_field    = self::DIM_COLUMN[$dimension] ?? 'nombredependencia';

        // Ordenamiento de barras: por valor agregado (sentinela __value__) o por etiqueta.
        $order     = in_array($cfg['order'] ?? '', ['valor', 'etiqueta'], true) ? $cfg['order'] : 'valor';
        $order_dir = in_array($cfg['order_dir'] ?? '', ['ASC', 'DESC'], true) ? $cfg['order_dir'] : 'DESC';
        if ($dimension === 'mensual') {
            // La evolución mensual siempre es cronológica.
            $order_by  = $x_field;
            $order_dir = 'ASC';
        } elseif ($order === 'etiqueta') {
            $order_by = $x_field;
        } else {
            $order_by = '__value__';
        }

        $default_palette = ['#844e80', '#ff7300', '#ffc53b', '#3eba6a', '#0080c3', '#e74c3c', '#9b59b6', '#1abc9c'];
        $colors = (!empty($cfg['colors']) && is_array($cfg['colors'])) ? $cfg['colors'] : $default_palette;

        // v5.3.2: campos del tooltip configurables (categoría / valor / nº de contratos).
        // Subconjunto validado; por defecto categoría + valor (comportamiento previo).
        $tf = array_values(array_intersect((array) ($cfg['tooltip_fields'] ?? ['categoria', 'valor']), ['categoria', 'valor', 'conteo']));
        if (empty($tf)) $tf = ['categoria', 'valor'];

        $filters = [['field' => 'anio', 'operator' => '=', 'value' => (string) $this->current_vigencia()]];
        $dep = ($dependencia !== null && $dependencia !== '') ? $dependencia : ($cfg['dependencia'] ?? '');
        if ($dep !== '') {
            $filters[] = ['field' => 'nombredependencia', 'operator' => '=', 'value' => $dep];
        }

        // Filtros personalizados de la card (columna/operador/valor), validados
        // contra la whitelist. build_chart_query revalida y prepara el valor.
        foreach (($cfg['filters'] ?? []) as $f) {
            $field = $f['field'] ?? '';
            if (!isset($this->filter_columns()[$field])) continue;
            $op = in_array($f['operator'] ?? '=', ['=', '!=', '>', '<', '>=', '<=', 'LIKE'], true) ? $f['operator'] : '=';
            $val = (string) ($f['value'] ?? '');
            if ($val === '') continue;
            $filters[] = ['field' => $field, 'operator' => $op, 'value' => $val];
        }

        return [
            'chart_type'      => $type,
            'table_name'      => $view,
            'x_field'         => $x_field,
            'x_date_grouping' => '',
            'y_field'         => $m['col'],
            'y_fields'        => [],
            'group_by'        => '',
            'aggregate'       => $m['agg'],
            'color_field'     => '',
            'filters'         => $filters,
            'date_field'      => '',
            'date_from'       => '',
            'date_to'         => '',
            'limit'           => (!empty($cfg['limit']) && (int)$cfg['limit'] > 0) ? (int)$cfg['limit'] : ($dimension === 'mensual' ? 0 : 50),
            'order_by'        => $order_by,
            'order_dir'       => $order_dir,
            'colors'          => $colors,
            'show_legend'     => array_key_exists('show_legend', $cfg) ? (bool) $cfg['show_legend'] : true,
            'legend_mode'     => in_array($cfg['legend_mode'] ?? '', ['text', 'icon'], true) ? $cfg['legend_mode'] : 'text',
            'legend_position' => in_array($cfg['legend_position'] ?? '', ['bottom', 'top', 'left', 'right'], true) ? $cfg['legend_position'] : 'bottom',
            'show_timeline'   => false,
            'show_toolbar'    => array_key_exists('show_toolbar', $cfg) ? (bool) $cfg['show_toolbar'] : true,
            'toolbar_options' => (!empty($cfg['toolbar_options']) && is_array($cfg['toolbar_options'])) ? array_values(array_intersect($cfg['toolbar_options'], ['detail', 'share', 'data', 'image', 'download'])) : ['share', 'data', 'image', 'download'],
            'chart_height'    => (!empty($cfg['chart_height']) && (int) $cfg['chart_height'] > 0) ? (int) $cfg['chart_height'] : 400,
            'y_axis_title'    => isset($cfg['y_axis_title']) ? (string) $cfg['y_axis_title'] : '',
            'x_axis_title'    => isset($cfg['x_axis_title']) ? (string) $cfg['x_axis_title'] : '',
            'number_format'   => in_array($cfg['number_format'] ?? '', ['colombiano', 'millones', 'internacional', 'sin_formato'], true) ? $cfg['number_format'] : 'colombiano',
            // v5.3.2: tooltip configurable. tooltip_count activa la columna de conteo
            // en build_chart_query solo cuando se pide «conteo».
            'tooltip_fields'  => $tf,
            'tooltip_count'   => in_array('conteo', $tf, true),
            'custom_query'    => '',
        ];
    }

    // ── Presets prediseñados ──────────────────────────────────────

    /** Definición estática de los presets de gráficas prediseñadas. */
    public function presets(): array
    {
        return [
            'por_dependencia' => [
                'titulo'      => __('Ejecución por dependencia (barras)', 'secop-suite'),
                'dimension'   => 'dependencia',
                'chart_type'  => 'bar',
                'metric'      => 'valor_contrato',
                'limit'       => 15,
                'descripcion' => __('Valor contratado por cada dependencia de la entidad en la vigencia actual.', 'secop-suite'),
            ],
            'top_contratistas' => [
                'titulo'      => __('Top 10 contratistas (barras)', 'secop-suite'),
                'dimension'   => 'tercero',
                'chart_type'  => 'bar',
                'metric'      => 'valor_contrato',
                'limit'       => 10,
                'descripcion' => __('Los diez contratistas con mayor valor contratado.', 'secop-suite'),
            ],
            'tipos_treemap' => [
                'titulo'      => __('Tipos de contrato (treemap)', 'secop-suite'),
                'dimension'   => 'tipo_contrato',
                'chart_type'  => 'treemap',
                'metric'      => 'valor_contrato',
                'limit'       => 0,
                'descripcion' => __('Distribución del valor contratado por tipo de contrato.', 'secop-suite'),
            ],
            'modalidad_donut' => [
                'titulo'      => __('Modalidades (donut)', 'secop-suite'),
                'dimension'   => 'modalidad',
                'chart_type'  => 'donut',
                'metric'      => 'valor_contrato',
                'limit'       => 0,
                'descripcion' => __('Participación de cada modalidad de contratación en el valor total.', 'secop-suite'),
            ],
            'modalidad_pie' => [
                'titulo'      => __('Modalidades por nº de contratos (pie)', 'secop-suite'),
                'dimension'   => 'modalidad',
                'chart_type'  => 'pie',
                'metric'      => 'contratos',
                'limit'       => 0,
                'descripcion' => __('Número de contratos por modalidad de contratación.', 'secop-suite'),
            ],
            'contratistas_pack' => [
                'titulo'      => __('Contratistas por valor (burbujas)', 'secop-suite'),
                'dimension'   => 'tercero',
                'chart_type'  => 'pack',
                'metric'      => 'valor_contrato',
                'limit'       => 40,
                'descripcion' => __('Los contratistas con mayor valor, representados como burbujas proporcionales.', 'secop-suite'),
            ],
            // v5.7.0: los presets 'evolucion_mensual'/'evolucion_area' se eliminaron.
            // Usaban DIM_COLUMN['mensual']='mes' (mes de actualización de Sysman ≈
            // constante → serie degenerada) y el motor Visualizer no puede parsear el
            // varchar `fecha` (DD/MM/YYYY). El nuevo shortcode [secop_dep_prediccion]
            // los reemplaza como visualización temporal (serie por mes del contrato +
            // proyección punteada).
        ];
    }

    /**
     * Resuelve (o crea) el post de tipo secop_dep_card que respalda un preset.
     * Es idempotente: busca primero por meta _secop_preset_id; sólo crea si no existe.
     *
     * @param string $presetId Clave del preset (ya sanitizada con sanitize_key).
     * @return int Post ID, o 0 si el preset no existe o falla la creación.
     */
    public function get_preset_post_id(string $presetId): int
    {
        $presets = $this->presets();
        if (!isset($presets[$presetId])) return 0;
        $p = $presets[$presetId];

        $query = [
            'post_type'   => self::POST_TYPE,
            'post_status' => 'any',
            'meta_key'    => '_secop_preset_id',
            'meta_value'  => $presetId,
            'numberposts' => 1,
            'fields'      => 'ids',
        ];
        $existing = get_posts($query);

        $id = !empty($existing) ? (int) $existing[0] : 0;

        if (empty($id)) {
            // Lock para evitar creación duplicada bajo concurrencia.
            $lock = SECOP_SUITE_PREFIX . 'preset_lock_' . $presetId;
            if (get_transient($lock)) {
                // Otra petición lo está creando; reintentar la búsqueda.
                $again = get_posts($query);
                if (!empty($again)) { $id = (int) $again[0]; }
            }
            if (empty($id)) {
                set_transient($lock, 1, 30);
                $id = wp_insert_post([
                    'post_type'   => self::POST_TYPE,
                    'post_status' => 'publish',
                    'post_title'  => $p['titulo'],
                ]);
                if (!$id || is_wp_error($id)) { delete_transient($lock); return 0; }
                update_post_meta($id, '_secop_preset_id', $presetId);
                delete_transient($lock);
            }
        }

        // Sólo reescribir la config si realmente cambió (evita writes por render).
        $desired = [
            'dimension'   => $p['dimension'],
            'chart_type'  => $p['chart_type'],
            'metric'      => $p['metric'],
            'dependencia' => '',
            'limit'       => $p['limit'] ?? 0,
        ];
        $stored = get_post_meta($id, '_secop_dep_card_config', true);
        if ($stored !== $desired) {
            update_post_meta($id, '_secop_dep_card_config', $desired);
            update_post_meta($id, '_secop_chart_config', $this->card_to_chart_config($desired));
        }
        return (int) $id;
    }

    /**
     * Resuelve (o crea) el post secop_dep_card que respalda CUALQUIER config
     * de card ad-hoc (la generada desde los atributos del shortcode). Es
     * idempotente por hash de la config: dos shortcodes con la misma config
     * efectiva comparten el mismo post de respaldo (sin duplicar posts). Así
     * el motor existente (chart.php + AJAX secop_suite_get_chart_data) puede
     * renderizar usando el ID del post sin cambios en el Visualizer.
     *
     * @param array $cardCfg Config efectiva de la card (dimension, chart_type, …).
     * @return int Post ID, o 0 si falla la creación.
     */
    private function get_config_post_id(array $cardCfg): int
    {
        $hash = md5(wp_json_encode($cardCfg));
        $q = [
            'post_type'   => self::POST_TYPE,
            'post_status' => 'any',
            'meta_key'    => '_secop_cfg_hash',
            'meta_value'  => $hash,
            'numberposts' => 1,
            'fields'      => 'ids',
        ];
        $existing = get_posts($q);
        if (!empty($existing)) {
            $id = (int) $existing[0];
        } else {
            // Lock para evitar creación duplicada bajo concurrencia.
            $lock = SECOP_SUITE_PREFIX . 'cfg_lock_' . $hash;
            if (get_transient($lock)) {
                $again = get_posts($q);
                if (!empty($again)) return (int) $again[0];
            }
            set_transient($lock, 1, 30);
            $id = wp_insert_post([
                'post_type'   => self::POST_TYPE,
                'post_status' => 'publish',
                'post_title'  => '(auto) ' . ($cardCfg['dimension'] ?? 'grafica'),
            ]);
            if (!$id || is_wp_error($id)) { delete_transient($lock); return 0; }
            update_post_meta($id, '_secop_cfg_hash', $hash);
            delete_transient($lock);
        }

        // Sólo reescribir la config si realmente cambió (evita writes por render).
        $stored = get_post_meta($id, '_secop_dep_card_config', true);
        if ($stored !== $cardCfg) {
            update_post_meta($id, '_secop_dep_card_config', $cardCfg);
            update_post_meta($id, '_secop_chart_config', $this->card_to_chart_config($cardCfg));
        }
        return (int) $id;
    }

    /**
     * Construye la config efectiva de card a partir de los atributos del
     * shortcode: parte de una base (card existente, preset, o dimensión por
     * defecto) y aplica los overrides de los atributos validados.
     *
     * @param array $atts Atributos ya pasados por shortcode_atts().
     * @return array Config efectiva de card lista para card_to_chart_config().
     */
    private function build_effective_card_config(array $atts): array
    {
        // 1) Config BASE.
        $base = [];
        if (!empty($atts['card'])) {
            $base = get_post_meta((int) $atts['card'], '_secop_dep_card_config', true) ?: [];
        } elseif (!empty($atts['preset'])) {
            $presetKey = sanitize_key($atts['preset']);
            $presets   = $this->presets();
            if (isset($presets[$presetKey])) {
                $p = $presets[$presetKey];
                $base = [
                    'dimension'   => $p['dimension'],
                    'chart_type'  => $p['chart_type'],
                    'metric'      => $p['metric'],
                    'dependencia' => '',
                    'limit'       => $p['limit'] ?? 0,
                ];
            }
        }
        if (empty($base)) {
            $dim = sanitize_text_field($atts['dimension'] ?? '');
            if (!isset(self::COMPAT[$dim])) $dim = 'dependencia';
            $base = ['dimension' => $dim];
        }

        // 2) Overrides de atributos.
        $dimension = sanitize_text_field($atts['dimension'] ?? '') !== ''
            ? sanitize_text_field($atts['dimension'])
            : ($base['dimension'] ?? 'dependencia');
        if (!isset(self::COMPAT[$dimension])) $dimension = 'dependencia';

        // chart_type: prioriza el att tipo (validado por compatibilidad), luego base.
        $type = sanitize_text_field($atts['tipo'] ?? '');
        if ($type === '') $type = (string) ($base['chart_type'] ?? '');
        if (!self::is_compatible($dimension, $type)) $type = self::default_type($dimension);

        // metric: valida contra metrics(); si no, conserva base/valor_contrato.
        $metric = sanitize_text_field($atts['metric'] ?? '');
        if ($metric === '' || !isset($this->metrics()[$metric])) {
            $metric = (string) ($base['metric'] ?? 'valor_contrato');
            if (!isset($this->metrics()[$metric])) $metric = 'valor_contrato';
        }

        // dependencia.
        $dep = sanitize_text_field($atts['dependencia'] ?? '');
        if ($dep === '') $dep = (string) ($base['dependencia'] ?? '');

        // order / order_dir.
        $order = sanitize_text_field($atts['order'] ?? '');
        if (!in_array($order, ['valor', 'etiqueta'], true)) $order = $base['order'] ?? 'valor';
        $order_dir = strtoupper(sanitize_text_field($atts['orderdir'] ?? ''));
        if (!in_array($order_dir, ['ASC', 'DESC'], true)) $order_dir = $base['order_dir'] ?? 'DESC';

        // limit.
        $limit = (isset($atts['limit']) && $atts['limit'] !== '')
            ? (int) $atts['limit']
            : (int) ($base['limit'] ?? 0);

        // colors: parsea la lista del att y conserva sólo hex válidos; si no, base.
        $colors = $base['colors'] ?? [];
        if (isset($atts['colors']) && $atts['colors'] !== '') {
            $parsed = array_values(array_filter(
                array_map('trim', explode(',', sanitize_text_field($atts['colors']))),
                static fn($c) => (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $c)
            ));
            if (!empty($parsed)) $colors = $parsed;
        }

        // legend: on→true / off→false. Sólo se fija si se pasó el att.
        $legend = strtolower(sanitize_text_field($atts['legend'] ?? ''));
        $show_legend = array_key_exists('show_legend', $base) ? (bool) $base['show_legend'] : true;
        if ($legend === 'on')  $show_legend = true;
        if ($legend === 'off') $show_legend = false;

        // v5.3.0: personalización del gráfico. Cada override se aplica sólo si el
        // atributo se proporcionó (no vacío), validado contra su conjunto permitido.
        // number_format.
        $number_format = strtolower(sanitize_text_field($atts['numberformat'] ?? ''));
        if (!in_array($number_format, ['colombiano', 'millones', 'internacional', 'sin_formato'], true)) {
            $number_format = in_array($base['number_format'] ?? '', ['colombiano', 'millones', 'internacional', 'sin_formato'], true) ? $base['number_format'] : 'colombiano';
        }

        // chart_height: prioriza height (ya existente); el att height se aplica también
        // en sc_chart sobre la config final, pero aquí lo fijamos para la config base.
        $chart_height = (isset($atts['height']) && (int) $atts['height'] > 0)
            ? (int) $atts['height']
            : ((!empty($base['chart_height']) && (int) $base['chart_height'] > 0) ? (int) $base['chart_height'] : 400);

        // x/y axis titles.
        $x_axis_title = (isset($atts['xtitle']) && $atts['xtitle'] !== '')
            ? sanitize_text_field($atts['xtitle'])
            : (isset($base['x_axis_title']) ? (string) $base['x_axis_title'] : '');
        $y_axis_title = (isset($atts['ytitle']) && $atts['ytitle'] !== '')
            ? sanitize_text_field($atts['ytitle'])
            : (isset($base['y_axis_title']) ? (string) $base['y_axis_title'] : '');

        // legend_mode / legend_position.
        $legend_mode = strtolower(sanitize_text_field($atts['legendmode'] ?? ''));
        if (!in_array($legend_mode, ['text', 'icon'], true)) {
            $legend_mode = in_array($base['legend_mode'] ?? '', ['text', 'icon'], true) ? $base['legend_mode'] : 'text';
        }
        $legend_position = strtolower(sanitize_text_field($atts['legendpos'] ?? ''));
        if (!in_array($legend_position, ['bottom', 'top', 'left', 'right'], true)) {
            $legend_position = in_array($base['legend_position'] ?? '', ['bottom', 'top', 'left', 'right'], true) ? $base['legend_position'] : 'bottom';
        }

        // toolbar: on→true / off→false. Sólo se fija si se pasó el att.
        $toolbar = strtolower(sanitize_text_field($atts['toolbar'] ?? ''));
        $show_toolbar = array_key_exists('show_toolbar', $base) ? (bool) $base['show_toolbar'] : true;
        if ($toolbar === 'on')  $show_toolbar = true;
        if ($toolbar === 'off') $show_toolbar = false;

        // toolbar_options: lista separada por comas, validada.
        $toolbar_options = (!empty($base['toolbar_options']) && is_array($base['toolbar_options']))
            ? array_values(array_intersect($base['toolbar_options'], ['detail', 'share', 'data', 'image', 'download']))
            : ['share', 'data', 'image', 'download'];
        if (isset($atts['toolbaropts']) && $atts['toolbaropts'] !== '') {
            $parsed = array_values(array_intersect(
                array_map('trim', explode(',', strtolower(sanitize_text_field($atts['toolbaropts'])))),
                ['detail', 'share', 'data', 'image', 'download']
            ));
            $toolbar_options = $parsed; // permitir vaciar la barra explícitamente.
        }

        // v5.3.2: campos del tooltip. Override sólo si se proporciona el atributo.
        $tooltip_fields = $this->sanitize_tooltip_fields($base['tooltip_fields'] ?? ['categoria', 'valor']);
        if (isset($atts['tooltip']) && $atts['tooltip'] !== '') {
            $tooltip_fields = $this->sanitize_tooltip_fields(strtolower(sanitize_text_field($atts['tooltip'])));
        }

        return [
            'dimension'   => $dimension,
            'chart_type'  => $type,
            'metric'      => $metric,
            'dependencia' => $dep,
            'order'       => $order,
            'order_dir'   => $order_dir,
            'limit'       => $limit,
            'colors'      => $colors,
            'show_legend' => $show_legend,
            // v5.3.0: personalización del gráfico.
            'number_format'   => $number_format,
            'chart_height'    => $chart_height,
            'x_axis_title'    => $x_axis_title,
            'y_axis_title'    => $y_axis_title,
            'legend_mode'     => $legend_mode,
            'legend_position' => $legend_position,
            'show_toolbar'    => $show_toolbar,
            'toolbar_options' => $toolbar_options,
            // v5.3.2: campos del tooltip.
            'tooltip_fields'  => $tooltip_fields,
        ];
    }

    public function sc_chart(array $atts): string
    {
        $atts = shortcode_atts(
            [
                'card' => 0, 'dimension' => '', 'tipo' => '', 'dependencia' => '',
                'height' => 400, 'preset' => '',
                // v5.1.8: parámetros de personalización del shortcode.
                'metric' => '', 'order' => '', 'orderdir' => '', 'limit' => '',
                'colors' => '', 'legend' => '',
                // v5.3.0: personalización del gráfico (mirror del editor).
                'numberformat' => '', 'xtitle' => '', 'ytitle' => '',
                'legendmode' => '', 'legendpos' => '', 'toolbar' => '', 'toolbaropts' => '',
                // v5.3.2: campos del tooltip (lista separada por comas: categoria,valor,conteo).
                'tooltip' => '',
                // v5.1.9: click-to-drill (popup con contratos de la categoría).
                'drill' => '',
            ],
            $atts, 'secop_dep_chart'
        );

        // ROBUSTEZ: encolar los assets del módulo AL RENDERIZAR el shortcode. El enqueue
        // por has_shortcode($post->post_content) falla con page builders/bloques/plantillas
        // → la gráfica no cargaba en el front. Encolar aquí lo garantiza (scripts en footer).
        $this->enqueue_module_stack();

        // Validate preset early (give a friendly error if unknown).
        if (!empty($atts['preset'])) {
            $presetKey = sanitize_key($atts['preset']);
            $presets   = $this->presets();
            if (!isset($presets[$presetKey])) {
                return '<p class="ss-error">' . esc_html(sprintf(
                    /* translators: 1: preset key, 2: comma-separated list of available preset keys */
                    __('Preset desconocido: "%1$s". Presets disponibles: %2$s.', 'secop-suite'),
                    $presetKey,
                    implode(', ', array_keys($presets))
                )) . '</p>';
            }
        }

        // Runtime dependency override from the shortcode attribute (used by sc_seguimiento).
        // Applied via data-dependencia on the container → AJAX filter.
        // NOT persisted into _secop_chart_config so the stored config stays general.
        $dependencia_filter = sanitize_text_field($atts['dependencia']);

        // OPTIMIZATION: card="N" (or preset="x") without any override attribute →
        // render the canonical card/preset post directly (no hash post created).
        // Keeps existing [secop_dep_chart card=N] / preset=x cheap and clutter-free.
        $override_atts = ['tipo', 'metric', 'order', 'orderdir', 'limit', 'colors', 'dependencia', 'legend',
            'numberformat', 'xtitle', 'ytitle', 'legendmode', 'legendpos', 'toolbar', 'toolbaropts', 'tooltip'];
        $has_override  = false;
        foreach ($override_atts as $k) {
            if (isset($atts[$k]) && $atts[$k] !== '') { $has_override = true; break; }
        }

        $direct_id = 0;
        if (!$has_override) {
            if (!empty($atts['preset'])) {
                $direct_id = $this->get_preset_post_id(sanitize_key($atts['preset']));
            } elseif (!empty($atts['card'])) {
                $direct_id = (int) $atts['card'];
            }
        }

        if ($direct_id) {
            $card_id = $direct_id;
            // Try existing Visualizer-compatible config (written by save_card_meta or a previous render).
            $config = get_post_meta($card_id, '_secop_chart_config', true);
            if (empty($config) || !is_array($config)) {
                $dep_cfg = get_post_meta($card_id, '_secop_dep_card_config', true) ?: [];
                if (empty($dep_cfg)) {
                    return '<p class="ss-error">' . esc_html__('Card no encontrada o sin configuración.', 'secop-suite') . '</p>';
                }
                $config = $this->card_to_chart_config($dep_cfg);
                update_post_meta($card_id, '_secop_chart_config', $config);
            }
        } else {
            // Build the effective card config from base (card/preset/dimension) + attribute overrides,
            // then map it to a backing post via config hash (find-or-create, no duplicates).
            $effectiveCfg = $this->build_effective_card_config($atts);
            $card_id      = $this->get_config_post_id($effectiveCfg);
            if (!$card_id) {
                return '<p class="ss-error">' . esc_html__('No se pudo preparar la gráfica.', 'secop-suite') . '</p>';
            }
            $config = get_post_meta($card_id, '_secop_chart_config', true);
            if (empty($config) || !is_array($config)) {
                $config = $this->card_to_chart_config($effectiveCfg);
            }
        }

        if (!$card_id) {
            return '<p class="ss-error">' . esc_html__('Especifique un ID de card válido: [secop_dep_chart card="N"]', 'secop-suite') . '</p>';
        }

        // Honor height shortcode attribute.
        if (!empty($atts['height'])) {
            $config['chart_height'] = (int) $atts['height'];
        }

        // Variables required by templates/frontend/chart.php.
        // $dependencia_filter is consumed by the template to set data-dependencia.
        $chart_id    = $card_id;
        $unique_id   = 'ss-dep-' . wp_unique_id();
        $extra_class = ' ss-dep-chart';

        // v5.1.9: click-to-drill. La columna de drill es la dimensión X efectiva.
        $drill_enabled = in_array(strtolower((string) $atts['drill']), ['on', '1', 'true', 'yes'], true);
        $drill_column  = $drill_enabled ? (string) ($config['x_field'] ?? '') : '';

        ob_start();
        include SECOP_SUITE_DIR . 'templates/frontend/chart.php';
        return ob_get_clean();
    }

    public function sc_analisis(array $atts): string
    {
        $atts = shortcode_atts(['card' => 0, 'tipo' => 'descripcion', 'preset' => ''], $atts, 'secop_dep_analisis');

        // Resolve preset → backing card post (find-or-create).
        if (!empty($atts['preset'])) {
            $presetKey = sanitize_key($atts['preset']);
            $presets   = $this->presets();
            if (!isset($presets[$presetKey])) {
                return '<p class="ss-error">' . esc_html(sprintf(
                    /* translators: 1: preset key, 2: comma-separated list of available preset keys */
                    __('Preset desconocido: "%1$s". Presets disponibles: %2$s.', 'secop-suite'),
                    $presetKey,
                    implode(', ', array_keys($presets))
                )) . '</p>';
            }
            $atts['card'] = $this->get_preset_post_id($presetKey);
        }

        $cfg = get_post_meta((int) $atts['card'], '_secop_dep_card_config', true) ?: [];
        if (empty($cfg['dimension'])) return '';
        $tipo = in_array($atts['tipo'], ['descripcion','cualitativo','cuantitativo','prediccion'], true)
            ? $atts['tipo'] : 'descripcion';
        $ds = $this->build_dataset($cfg['dimension'], $cfg['dependencia'] ?? null);
        $m = 'analisis_' . $tipo;
        return '<p class="ss-dep-analisis ss-dep-' . esc_attr($tipo) . '">'
             . esc_html(Stats::$m($ds)) . '</p>';
    }

    public function enqueue_frontend_assets(): void
    {
        global $post;
        if (!is_a($post, 'WP_Post')) return;
        $has = false;
        foreach (['secop_dep_chart','secop_seguimiento','secop_dep_contratos','secop_consulta','secop_dep_red','secop_dep_rings','secop_dep_explora','secop_dep_prediccion'] as $sc) {
            if (has_shortcode($post->post_content, $sc)) { $has = true; break; }
        }
        if (!$has) return;
        $this->enqueue_module_stack();
    }

    /**
     * Encola los assets del módulo (motor de gráficas + interactividad). Idempotente y
     * autosuficiente: seguro llamarlo desde wp_enqueue_scripts (gated por has_shortcode)
     * O durante el render de un shortcode — esto último cubre page builders, bloques,
     * widgets y plantillas donde has_shortcode($post->post_content) no detecta el shortcode
     * (por eso la gráfica no cargaba en el front).
     */
    public function enqueue_module_stack(): void
    {
        // Motor de gráficas (d3/d3plus + frontend.js + secopSuiteChart) del Visualizer.
        Plugin::get_instance()->visualizer()->enqueue_frontend_chart_stack();

        wp_enqueue_style('secop-suite-frontend', SECOP_SUITE_URL . 'assets/css/frontend.css', [], SECOP_SUITE_VERSION);
        // dep-tracking.js: tabla de contratos del seguimiento (depende de frontend.js para SSChartManager).
        wp_enqueue_script('secop-dep-tracking', SECOP_SUITE_URL . 'assets/js/dep-tracking.js',
            ['jquery', 'secop-suite-frontend'], SECOP_SUITE_VERSION, true);
        // v5.1.9: click-to-drill — popup con los contratos de la categoría clicada.
        wp_enqueue_script('secop-dep-drill', SECOP_SUITE_URL . 'assets/js/dep-drill.js',
            ['jquery'], SECOP_SUITE_VERSION, true);
        if (!wp_script_is('secop-dep-tracking', 'done')) {
            wp_localize_script('secop-dep-tracking', 'secopDep', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('secop_dep_frontend'),
                'strings' => [
                    'noData'      => __('No hay datos.', 'secop-suite'),
                    'noContracts' => __('Sin contratos.', 'secop-suite'),
                    'valueLabel'  => __('Valor ejecutado', 'secop-suite'),
                    'countLabel'  => __('Contratos', 'secop-suite'),
                ],
            ]);
        }
    }

    // ── Task 12: contratos por dependencia ────────────────────────

    /** Lista de contratos de una dependencia (vigencia actual), deduplicados por contrato. */
    public function contracts_by_dependency(string $dependencia, int $limit = 100): array
    {
        global $wpdb;

        $cache_key = 'secop_trk_' . md5('contracts_by_dependency|' . $dependencia . '|' . $limit . '|' . $this->current_vigencia());
        $cached = get_transient($cache_key);
        if (is_array($cached)) return $cached;

        $view = $this->db->get_view_name();
        $sql = "SELECT numero_del_contrato, url_contrato, nom_raz_social_contratista,
                       fecha_inicio_ejecucion, fecha_fin_ejecucion, valor_contrato, objeto_a_contratar
                FROM `{$view}` WHERE anio = %d AND nombredependencia = %s
                GROUP BY numero_del_contrato
                ORDER BY valor_contrato DESC LIMIT %d";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, $this->current_vigencia(), $dependencia, $limit), ARRAY_A);

        $result = $rows ?: [];
        set_transient($cache_key, $result, 30 * MINUTE_IN_SECONDS);
        return $result;
    }

    /** Contratos asociados a un valor de una columna del VIEW (vigencia actual), deduplicados. */
    public function contracts_by_value(string $column, string $value, int $limit = 200): array
    {
        global $wpdb;
        // SEGURIDAD: la columna debe ser una de las columnas de dimensión permitidas.
        $allowed = array_values(self::DIM_COLUMN);
        if (!in_array($column, $allowed, true)) return [];
        $view = $this->db->get_view_name();
        $cols = $this->db->get_table_columns($view);
        if (!isset($cols[$column])) return [];
        $sql = "SELECT numero_del_contrato, url_contrato, nom_raz_social_contratista,
                       fecha_inicio_ejecucion, fecha_fin_ejecucion, valor_contrato, objeto_a_contratar
                FROM `{$view}` WHERE anio = %d AND `{$column}` = %s
                GROUP BY numero_del_contrato ORDER BY valor_contrato DESC LIMIT %d";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, $this->current_vigencia(), $value, $limit), ARRAY_A);
        return $rows ?: [];
    }

    public function ajax_drill(): void
    {
        check_ajax_referer('secop_dep_frontend', 'nonce');
        // rate limit por IP (igual patrón que ajax_contratos)
        $ip_key = 'secop_dep_rl_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
        if ((int) get_transient($ip_key) > 60) wp_send_json_error(['message' => 'Demasiadas solicitudes'], 429);
        set_transient($ip_key, ((int) get_transient($ip_key)) + 1, MINUTE_IN_SECONDS);

        $column = sanitize_text_field(wp_unslash($_POST['column'] ?? ''));
        $value  = sanitize_text_field(wp_unslash($_POST['value'] ?? ''));
        if ($column === '' || $value === '') wp_send_json_error(['message' => 'Parámetros incompletos']);
        wp_send_json_success(['rows' => $this->contracts_by_value($column, $value)]);
    }

    public function ajax_contratos(): void
    {
        check_ajax_referer('secop_dep_frontend', 'nonce');
        // FIX 4: rate-limit por IP (60 req/min)
        $ip_key = 'secop_dep_rl_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
        if ((int) get_transient($ip_key) > 60) {
            wp_send_json_error(['message' => 'Demasiadas solicitudes'], 429);
        }
        set_transient($ip_key, ((int) get_transient($ip_key)) + 1, MINUTE_IN_SECONDS);

        $dep = sanitize_text_field($_POST['dependencia'] ?? '');
        if ($dep === '') wp_send_json_error(['message' => 'Dependencia requerida']);
        wp_send_json_success(['rows' => $this->contracts_by_dependency($dep)]);
    }

    // ── v5.4.0: Red de contratación (grafo de fuerza) ─────────────

    /**
     * Construye el modelo de nodos/enlaces de la red de contratación de la
     * vigencia actual: las dependencias son los nodos centrales, conectadas a
     * sus contratistas, tipos de contrato y modalidades de contratación.
     *
     * Los datos se deduplican por contrato (GROUP BY numero_del_contrato) para
     * que cada contrato cuente una sola vez con su valor_contrato. Sólo se usan
     * columnas de la vista y se prepara el año + la dependencia con $wpdb->prepare.
     *
     * @param string|null $dependencia        Filtra por una dependencia concreta (null = todas).
     * @param int         $limit_contratistas Top-N contratistas por valor (<= 0 = TODOS, sin límite).
     * @return array{nodes:array<int,array<string,mixed>>,links:array<int,array<string,string>>}
     */
    public function network_data(?string $dependencia = null, int $limit_contratistas = 0): array
    {
        global $wpdb;

        // <= 0 → sin límite (TODOS los contratistas, magnitud completa); si es
        // positivo, top-N por valor (acotado a 5000 por seguridad/rendimiento).
        $limit_contratistas = ($limit_contratistas <= 0) ? 0 : min(5000, $limit_contratistas);
        $dep = ($dependencia !== null && $dependencia !== '') ? $dependencia : null;

        $cache_key = 'secop_trk_' . md5('network_data|' . ($dep ?? '') . '|' . $limit_contratistas . '|' . $this->current_vigencia());
        $cached = get_transient($cache_key);
        if (is_array($cached)) return $cached;

        $view = $this->db->get_view_name();

        // WHERE de la subconsulta deduplicadora (año + dependencia opcional).
        $where  = ['anio = %d'];
        $params = [$this->current_vigencia()];
        if ($dep !== null) {
            $where[]  = 'nombredependencia = %s';
            $params[] = $dep;
        }
        $where_sql = implode(' AND ', $where);

        // Subconsulta: un registro por contrato (MAX por columna textual para
        // colapsar las múltiples filas presupuestales de un mismo contrato).
        $sub = "SELECT numero_del_contrato,
                       MAX(nombredependencia)         AS nombredependencia,
                       MAX(nombretercero)             AS nombretercero,
                       MAX(tipo_de_contrato)          AS tipo_de_contrato,
                       MAX(modalidad_de_contratacion) AS modalidad_de_contratacion,
                       MAX(valor_contrato)            AS valor_contrato
                FROM `{$view}` WHERE {$where_sql}
                GROUP BY numero_del_contrato";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sub, ...$params), ARRAY_A);
        $rows = $rows ?: [];

        // Agregados.
        $deps_agg = [];   // nombredependencia => [valor, count]
        $cons_agg = [];   // nombretercero => [valor, count, deps => [dep => count|valor]]
        $tipos    = [];   // dep => [tipo => true]
        $mods     = [];   // dep => [mod => true]

        foreach ($rows as $r) {
            $depName = (string) ($r['nombredependencia'] ?? '');
            $conName = (string) ($r['nombretercero'] ?? '');
            $tipo    = (string) ($r['tipo_de_contrato'] ?? '');
            $mod     = (string) ($r['modalidad_de_contratacion'] ?? '');
            $val     = (float) ($r['valor_contrato'] ?? 0);
            if ($depName === '') continue;

            if (!isset($deps_agg[$depName])) $deps_agg[$depName] = ['valor' => 0.0, 'count' => 0];
            $deps_agg[$depName]['valor'] += $val;
            $deps_agg[$depName]['count'] += 1;

            if ($tipo !== '') $tipos[$depName][$tipo] = true;
            if ($mod !== '')  $mods[$depName][$mod]   = true;

            if ($conName !== '') {
                if (!isset($cons_agg[$conName])) {
                    $cons_agg[$conName] = ['valor' => 0.0, 'count' => 0, 'deps' => []];
                }
                $cons_agg[$conName]['valor'] += $val;
                $cons_agg[$conName]['count'] += 1;
                // Acumula el valor por dependencia para elegir luego la principal (MAX valor).
                $cons_agg[$conName]['deps'][$depName] = ($cons_agg[$conName]['deps'][$depName] ?? 0.0) + $val;
            }
        }

        // Top-N contratistas por valor cuando NO hay filtro de dependencia y se
        // pasó un límite positivo. Con $limit_contratistas = 0 se incluyen TODOS.
        if ($dep === null && $limit_contratistas > 0 && count($cons_agg) > $limit_contratistas) {
            uasort($cons_agg, static fn($a, $b) => $b['valor'] <=> $a['valor']);
            $cons_agg = array_slice($cons_agg, 0, $limit_contratistas, true);
        }

        // Color por tipo de nodo (compartido por la red de fuerza y por Rings).
        $type_colors = [
            'dependencia' => '#0080c3',
            'contratista' => '#844e80',
            'tipo'        => '#3eba6a',
            'modalidad'   => '#ff7300',
        ];

        $nodes    = [];
        $node_ids = [];
        $add_node = static function (array $node) use (&$nodes, &$node_ids, $type_colors): void {
            if (!isset($node_ids[$node['id']])) {
                $node['color'] = $type_colors[$node['type'] ?? ''] ?? '#999999';
                $node_ids[$node['id']] = true;
                $nodes[] = $node;
            }
        };

        // Nodos dependencia.
        foreach ($deps_agg as $name => $a) {
            $add_node([
                'id'    => 'dep::' . $name,
                'label' => $name,
                'type'  => 'dependencia',
                'value' => $a['valor'],
                'count' => $a['count'],
            ]);
        }

        // Nodos contratista (con su dependencia principal = la de MAX valor).
        foreach ($cons_agg as $name => $a) {
            $depName = '';
            $best = -1.0;
            foreach ($a['deps'] as $dName => $dVal) {
                if ($dVal > $best) { $best = $dVal; $depName = $dName; }
            }
            $add_node([
                'id'          => 'con::' . $name,
                'label'       => $name,
                'type'        => 'contratista',
                'value'       => $a['valor'],
                'count'       => $a['count'],
                'dependencia' => $depName,
            ]);
        }

        // Nodos tipo y modalidad (distintos, asociados a la dependencia que los usa).
        foreach ($tipos as $depName => $set) {
            foreach (array_keys($set) as $t) {
                $add_node(['id' => 'tipo::' . $t, 'label' => $t, 'type' => 'tipo']);
            }
        }
        foreach ($mods as $depName => $set) {
            foreach (array_keys($set) as $m) {
                $add_node(['id' => 'mod::' . $m, 'label' => $m, 'type' => 'modalidad']);
            }
        }

        // Enlaces — sólo si ambos extremos están en el conjunto de nodos.
        $links     = [];
        $link_seen = [];
        $add_link = static function (string $source, string $target) use (&$links, &$link_seen, $node_ids): void {
            if (!isset($node_ids[$source]) || !isset($node_ids[$target])) return;
            $key = $source . '|' . $target;
            if (isset($link_seen[$key])) return;
            $link_seen[$key] = true;
            $links[] = ['source' => $source, 'target' => $target];
        };

        // contratista → su dependencia principal.
        foreach ($cons_agg as $name => $a) {
            $depName = '';
            $best = -1.0;
            foreach ($a['deps'] as $dName => $dVal) {
                if ($dVal > $best) { $best = $dVal; $depName = $dName; }
            }
            if ($depName !== '') $add_link('con::' . $name, 'dep::' . $depName);
        }
        // tipo → dependencia.
        foreach ($tipos as $depName => $set) {
            foreach (array_keys($set) as $t) {
                $add_link('tipo::' . $t, 'dep::' . $depName);
            }
        }
        // modalidad → dependencia.
        foreach ($mods as $depName => $set) {
            foreach (array_keys($set) as $m) {
                $add_link('mod::' . $m, 'dep::' . $depName);
            }
        }

        $result = ['nodes' => $nodes, 'links' => $links];
        set_transient($cache_key, $result, 30 * MINUTE_IN_SECONDS);
        return $result;
    }

    /** AJAX — datos de la red de contratación. */
    public function ajax_network(): void
    {
        check_ajax_referer('secop_dep_frontend', 'nonce');
        $ip_key = 'secop_dep_rl_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
        if ((int) get_transient($ip_key) > 60) wp_send_json_error(['message' => 'Demasiadas solicitudes'], 429);
        set_transient($ip_key, ((int) get_transient($ip_key)) + 1, MINUTE_IN_SECONDS);

        $dep   = sanitize_text_field(wp_unslash($_POST['dependencia'] ?? ''));
        // 0 = todos los contratistas; se acota a 5000 por seguridad/rendimiento.
        $limit = (int) ($_POST['limit'] ?? 0);
        $limit = ($limit < 0) ? 0 : min($limit, 5000);
        wp_send_json_success($this->network_data($dep !== '' ? $dep : null, $limit));
    }

    /** Shortcode [secop_dep_red] — red de contratación (grafo de fuerza d3). */
    public function sc_red(array $atts): string
    {
        $atts = shortcode_atts(['dependencia' => '', 'limit' => 0, 'height' => 560, 'selector' => 'on'], $atts, 'secop_dep_red');

        $this->enqueue_module_stack(); // d3 + d3plus + secopDep (ajaxUrl + nonce).
        wp_enqueue_script('secop-dep-network', SECOP_SUITE_URL . 'assets/js/dep-network.js',
            ['jquery', 'd3', 'secop-suite-frontend'], SECOP_SUITE_VERSION, true);

        $deps = ($atts['selector'] === 'on') ? $this->list_dependencies() : [];
        $uid  = 'ss-red-' . wp_unique_id();

        ob_start();
        include SECOP_SUITE_DIR . 'templates/frontend/red.php';
        return ob_get_clean();
    }

    /**
     * Shortcode [secop_dep_rings] — red ego (concéntrica) con d3plus.Rings.
     * Centra el grafo en UNA dependencia y dispone sus conexiones en anillos
     * concéntricos automáticamente. Reutiliza network_data + el endpoint AJAX
     * secop_dep_network. La elección del nodo central la resuelve dep-rings.js.
     */
    public function sc_rings(array $atts): string
    {
        $atts = shortcode_atts(['dependencia' => '', 'height' => 560, 'selector' => 'on'], $atts, 'secop_dep_rings');
        $this->enqueue_module_stack();
        wp_enqueue_script('secop-dep-rings', SECOP_SUITE_URL . 'assets/js/dep-rings.js', ['jquery', 'd3', 'd3plus', 'secop-suite-frontend'], SECOP_SUITE_VERSION, true);
        $deps = ($atts['selector'] === 'on') ? $this->list_dependencies() : [];
        $uid  = 'ss-rings-' . wp_unique_id();
        ob_start();
        include SECOP_SUITE_DIR . 'templates/frontend/rings.php';
        return ob_get_clean();
    }

    // ── v5.7.0: Predicción (serie mensual del contrato + proyección) ──

    /** AJAX — datos del gráfico de predicción (mismo nonce + rate-limit que ajax_drill). */
    public function ajax_prediccion(): void
    {
        check_ajax_referer('secop_dep_frontend', 'nonce');
        $ip_key = 'secop_dep_rl_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
        if ((int) get_transient($ip_key) > 60) wp_send_json_error(['message' => 'Demasiadas solicitudes'], 429);
        set_transient($ip_key, ((int) get_transient($ip_key)) + 1, MINUTE_IN_SECONDS);

        $dep = sanitize_text_field(wp_unslash($_POST['dependencia'] ?? ''));
        wp_send_json_success($this->prediccion_data($dep !== '' ? $dep : null));
    }

    /**
     * Shortcode [secop_dep_prediccion] — evolución mensual del valor contratado
     * (por mes del contrato) con línea de proyección punteada a fin de vigencia.
     */
    public function sc_prediccion(array $atts): string
    {
        $atts = shortcode_atts(['dependencia' => '', 'height' => 420, 'selector' => 'on'], $atts, 'secop_dep_prediccion');
        $this->enqueue_module_stack();
        wp_enqueue_script('secop-dep-prediccion', SECOP_SUITE_URL . 'assets/js/dep-prediccion.js',
            ['jquery', 'd3plus', 'secop-suite-frontend'], SECOP_SUITE_VERSION, true);
        $deps = ($atts['selector'] === 'on') ? $this->list_dependencies() : [];
        $uid  = 'ss-pred-' . wp_unique_id();
        ob_start();
        include SECOP_SUITE_DIR . 'templates/frontend/prediccion.php';
        return ob_get_clean();
    }

    /** Dependencias disponibles en la vigencia actual (para el selector). */
    public function list_dependencies(): array
    {
        global $wpdb;

        $cache_key = 'secop_trk_' . md5('list_dependencies|' . $this->current_vigencia());
        $cached = get_transient($cache_key);
        if (is_array($cached)) return $cached;

        $view = $this->db->get_view_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT nombredependencia FROM `{$view}` WHERE anio = %d AND nombredependencia <> '' ORDER BY nombredependencia",
            $this->current_vigencia()
        )) ?: [];

        set_transient($cache_key, $result, 30 * MINUTE_IN_SECONDS);
        return $result;
    }

    public function sc_contratos(array $atts): string
    {
        $atts = shortcode_atts(['dependencia' => '', 'per_page' => 50], $atts, 'secop_dep_contratos');
        $dep  = sanitize_text_field($atts['dependencia']);
        $rows = $dep ? $this->contracts_by_dependency($dep, (int) $atts['per_page']) : [];
        ob_start();
        include SECOP_SUITE_DIR . 'templates/frontend/dep-contratos.php';
        return ob_get_clean();
    }

    public function sc_seguimiento(array $atts): string
    {
        $atts = shortcode_atts(['dependencia' => '', 'cards' => ''], $atts, 'secop_seguimiento');

        // ROBUSTEZ: encolar los assets del módulo al renderizar (ver nota en sc_chart).
        $this->enqueue_module_stack();

        // Resolve the list of card post IDs to display.
        if (!empty($atts['cards'])) {
            $cards = array_values(array_filter(array_map('intval', explode(',', $atts['cards']))));
        } else {
            $posts = get_posts([
                'post_type'   => self::POST_TYPE,
                'post_status' => 'publish',
                'numberposts' => 20,
                'orderby'     => 'menu_order',
                'order'       => 'ASC',
            ]);
            $cards = wp_list_pluck($posts, 'ID');
        }

        $deps = $this->list_dependencies();
        $sel  = sanitize_text_field($atts['dependencia']);

        ob_start();
        include SECOP_SUITE_DIR . 'templates/frontend/dep-seguimiento.php';
        return ob_get_clean();
    }

    // ── Task 13: Datos Abiertos — consulta vigencia ───────────────

    public function sc_consulta(array $atts): string
    {
        $atts    = shortcode_atts(['formato' => 'tabla'], $atts, 'secop_consulta');
        $formato = in_array($atts['formato'], ['tabla', 'csv', 'txt', 'json'], true)
            ? $atts['formato'] : 'tabla';
        $rest    = rest_url('secop-suite/v1/consulta');
        $rows    = $this->group_by_dimension('dependencia');
        $vig     = $this->current_vigencia();
        ob_start();
        include SECOP_SUITE_DIR . 'templates/frontend/consulta.php';
        return ob_get_clean();
    }

    // ── v5.6.0: Explorador interactivo [secop_dep_explora] ────────

    /**
     * Árbol de dependencias para el treemap (vigencia actual). Reutiliza la
     * agregación existente group_by_dimension('dependencia') → [label, valor, conteo].
     *
     * @return array<int,array{label:string,valor:float,conteo:int}>
     */
    public function explora_tree(): array
    {
        return $this->group_by_dimension('dependencia');
    }

    /**
     * Modalidades de contratación de una dependencia (vigencia actual), con nº de
     * contratos distintos y valor agregado. Preparada y cacheada 15 min.
     *
     * @return array<int,array{label:string,conteo:int,valor:float}>
     */
    public function explora_modalidades(string $dep): array
    {
        global $wpdb;
        if ($dep === '') return [];

        $cache_key = 'secop_trk_' . md5('explora_modalidades|' . $dep . '|' . $this->current_vigencia());
        $cached = get_transient($cache_key);
        if (is_array($cached)) return $cached;

        $view = $this->db->get_view_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT modalidad_de_contratacion AS label,
                    COUNT(DISTINCT numero_del_contrato) AS conteo,
                    SUM(valor_contrato) AS valor
             FROM `{$view}` WHERE anio = %d AND nombredependencia = %s
             GROUP BY modalidad_de_contratacion ORDER BY valor DESC",
            $this->current_vigencia(), $dep
        ), ARRAY_A);

        $result = array_map(static fn($r) => [
            'label'  => (string) ($r['label'] ?? 'N/D'),
            'conteo' => (int) ($r['conteo'] ?? 0),
            'valor'  => (float) ($r['valor'] ?? 0),
        ], $rows ?: []);

        set_transient($cache_key, $result, 15 * MINUTE_IN_SECONDS);
        return $result;
    }

    /**
     * Contratistas (con sus contratos) de una dependencia, opcionalmente filtrada
     * por modalidad (vigencia actual). Los contratos se deduplican por
     * numero_del_contrato (MAX sobre las columnas textuales para colapsar las
     * múltiples filas presupuestales) y luego se agrupan EN PHP por nombretercero.
     *
     * @param string      $dep       Dependencia (requerida).
     * @param string|null $modalidad Modalidad opcional (null = todas).
     * @param array       $campos    Campos de fila 1 validados (sólo para el caché key; el JS elige columnas).
     * @return array<int,array{contratista:string,conteo:int,valor:float,contratos:array<int,array<string,mixed>>}>
     */
    public function explora_contratistas(string $dep, ?string $modalidad, array $campos): array
    {
        global $wpdb;
        if ($dep === '') return [];

        // Valida los campos contra la whitelist (defensa adicional; no se interpolan en SQL).
        $allowed = $this->explora_fields();
        $campos  = array_values(array_filter($campos, static fn($c) => isset($allowed[$c])));

        $cache_key = 'secop_trk_' . md5('explora_contratistas|' . $dep . '|' . ($modalidad ?? '') . '|' . $this->current_vigencia());
        $cached = get_transient($cache_key);
        if (is_array($cached)) return $cached;

        $view = $this->db->get_view_name();
        $where  = ['anio = %d', 'nombredependencia = %s'];
        $params = [$this->current_vigencia(), $dep];
        if ($modalidad !== null && $modalidad !== '') {
            $where[]  = 'modalidad_de_contratacion = %s';
            $params[] = $modalidad;
        }
        $where_sql = implode(' AND ', $where);

        // Un registro por contrato (MAX por columna textual). Sólo columnas de la
        // vista; ningún nombre de columna proviene del usuario.
        $sql = "SELECT numero_del_contrato,
                       MAX(nombretercero)             AS nombretercero,
                       MAX(valor_contrato)            AS valor_contrato,
                       MAX(fecha_inicio_ejecucion)    AS fecha_inicio_ejecucion,
                       MAX(fecha_fin_ejecucion)       AS fecha_fin_ejecucion,
                       MAX(modalidad_de_contratacion) AS modalidad_de_contratacion,
                       MAX(tipo_de_contrato)          AS tipo_de_contrato,
                       MAX(documento_proveedor)       AS documento_proveedor,
                       MAX(url_contrato)              AS url_contrato,
                       MAX(objeto_a_contratar)        AS objeto_a_contratar
                FROM `{$view}` WHERE {$where_sql}
                GROUP BY numero_del_contrato";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        $rows = $rows ?: [];

        // Agrupar EN PHP por contratista.
        $by = []; // name => ['valor','conteo','contratos']
        foreach ($rows as $r) {
            $name = (string) ($r['nombretercero'] ?? '');
            if ($name === '') $name = 'N/D';
            if (!isset($by[$name])) {
                $by[$name] = ['contratista' => $name, 'conteo' => 0, 'valor' => 0.0, 'contratos' => []];
            }
            $by[$name]['conteo'] += 1;
            $by[$name]['valor']  += (float) ($r['valor_contrato'] ?? 0);
            $by[$name]['contratos'][] = [
                'numero_del_contrato'       => (string) ($r['numero_del_contrato'] ?? ''),
                'valor_contrato'            => (float) ($r['valor_contrato'] ?? 0),
                'fecha_inicio_ejecucion'    => (string) ($r['fecha_inicio_ejecucion'] ?? ''),
                'fecha_fin_ejecucion'       => (string) ($r['fecha_fin_ejecucion'] ?? ''),
                'modalidad_de_contratacion' => (string) ($r['modalidad_de_contratacion'] ?? ''),
                'tipo_de_contrato'          => (string) ($r['tipo_de_contrato'] ?? ''),
                'documento_proveedor'       => (string) ($r['documento_proveedor'] ?? ''),
                'url_contrato'              => (string) ($r['url_contrato'] ?? ''),
                'objeto_a_contratar'        => (string) ($r['objeto_a_contratar'] ?? ''),
            ];
        }

        // Ordenar por valor DESC.
        usort($by, static fn($a, $b) => $b['valor'] <=> $a['valor']);
        $result = array_values($by);

        set_transient($cache_key, $result, 15 * MINUTE_IN_SECONDS);
        return $result;
    }

    /** Rate-limit por IP compartido por los endpoints del explorador. */
    private function explora_rate_limit(): bool
    {
        $ip_key = 'secop_dep_rl_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
        if ((int) get_transient($ip_key) > 60) return true;
        set_transient($ip_key, ((int) get_transient($ip_key)) + 1, MINUTE_IN_SECONDS);
        return false;
    }

    /** AJAX — árbol de dependencias para el treemap. */
    public function ajax_explora_tree(): void
    {
        check_ajax_referer('secop_dep_frontend', 'nonce');
        if ($this->explora_rate_limit()) wp_send_json_error(['message' => 'Demasiadas solicitudes'], 429);
        wp_send_json_success(['nodes' => $this->explora_tree()]);
    }

    /** AJAX — modalidades de una dependencia. */
    public function ajax_explora_modalidades(): void
    {
        check_ajax_referer('secop_dep_frontend', 'nonce');
        if ($this->explora_rate_limit()) wp_send_json_error(['message' => 'Demasiadas solicitudes'], 429);
        $dep = sanitize_text_field(wp_unslash($_POST['dependencia'] ?? ''));
        if ($dep === '') wp_send_json_error(['message' => 'Dependencia requerida']);
        wp_send_json_success(['rows' => $this->explora_modalidades($dep)]);
    }

    /** AJAX — contratistas (acordeón) de una dependencia y modalidad opcional. */
    public function ajax_explora_contratistas(): void
    {
        check_ajax_referer('secop_dep_frontend', 'nonce');
        if ($this->explora_rate_limit()) wp_send_json_error(['message' => 'Demasiadas solicitudes'], 429);
        $dep = sanitize_text_field(wp_unslash($_POST['dependencia'] ?? ''));
        if ($dep === '') wp_send_json_error(['message' => 'Dependencia requerida']);
        $mod = sanitize_text_field(wp_unslash($_POST['modalidad'] ?? ''));
        $allowed = $this->explora_fields();
        $campos = array_values(array_filter(
            array_map('trim', explode(',', sanitize_text_field(wp_unslash($_POST['campos'] ?? '')))),
            static fn($c) => isset($allowed[$c])
        ));
        if (empty($campos)) $campos = ['numero_del_contrato', 'valor_contrato', 'fecha_inicio_ejecucion', 'fecha_fin_ejecucion', 'modalidad_de_contratacion'];
        wp_send_json_success([
            'rows'   => $this->explora_contratistas($dep, $mod !== '' ? $mod : null, $campos),
            'campos' => $campos,
        ]);
    }

    /** Shortcode [secop_dep_explora] — treemap de dependencias + panel drill. */
    public function sc_explora(array $atts): string
    {
        $atts = shortcode_atts([
            'campos' => 'numero_del_contrato,valor_contrato,fecha_inicio_ejecucion,fecha_fin_ejecucion,modalidad_de_contratacion',
            'height' => 460,
        ], $atts, 'secop_dep_explora');

        $campos = array_values(array_filter(
            array_map('trim', explode(',', $atts['campos'])),
            fn($c) => isset($this->explora_fields()[$c])
        ));
        if (empty($campos)) {
            $campos = ['numero_del_contrato', 'valor_contrato', 'fecha_inicio_ejecucion', 'fecha_fin_ejecucion', 'modalidad_de_contratacion'];
        }

        $this->enqueue_module_stack();
        wp_enqueue_script('secop-dep-explora', SECOP_SUITE_URL . 'assets/js/dep-explora.js',
            ['jquery', 'd3plus', 'secop-suite-frontend'], SECOP_SUITE_VERSION, true);

        $field_labels = $this->explora_fields();
        $csv_url = rest_url('secop-suite/v1/consulta/csv'); // descarga de TODA la vista (vigencia actual).
        $uid = 'ss-explora-' . wp_unique_id();

        ob_start();
        include SECOP_SUITE_DIR . 'templates/frontend/explora.php';
        return ob_get_clean();
    }
}
