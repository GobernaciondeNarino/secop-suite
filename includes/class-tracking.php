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

    /** Dimensiones del módulo → tipos de gráfica compatibles. */
    private const COMPAT = [
        'dependencia'   => ['bar', 'stacked_bar', 'treemap', 'pie', 'donut'],
        'tipo_contrato' => ['bar', 'stacked_bar', 'treemap', 'pie', 'donut'],
        'modalidad'     => ['bar', 'stacked_bar', 'treemap', 'pie', 'donut'],
        'estado'        => ['bar', 'stacked_bar', 'treemap', 'pie', 'donut'],
        'tipo_documento'=> ['bar', 'stacked_bar', 'treemap', 'pie', 'donut'],
        'programa'      => ['bar', 'stacked_bar', 'treemap', 'pie', 'donut'],
        'rubro'         => ['bar', 'stacked_bar', 'treemap', 'pie', 'donut'],
        'fuente'        => ['bar', 'stacked_bar', 'treemap', 'pie', 'donut'],
        'mensual'       => ['line', 'area'],
        'ejecucion'     => ['donut', 'bar'],
        'tercero'       => ['bar', 'treemap', 'pie', 'donut', 'stacked_bar'],
    ];

    /** Columna del VIEW que agrupa cada dimensión. */
    private const DIM_COLUMN = [
        'dependencia'   => 'nombredependencia',
        'tipo_contrato' => 'tipo_de_contrato',
        'modalidad'     => 'modalidad_de_contratacion',
        'estado'        => 'estado_del_proceso',
        'tipo_documento'=> 'tipo_documento_proveedor',
        'programa'      => 'nombreplan',
        'rubro'         => 'rubro',
        'fuente'        => 'origen',
        'mensual'       => 'mes',
        'ejecucion'     => 'nombredependencia',
        'tercero'       => 'nombretercero',
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
     * COUNT(DISTINCT col); valor_contrato se omite a propósito porque se
     * duplica por fila y daría sumas infladas.
     */
    public function metrics(): array
    {
        return [
            'valordebito'         => ['label' => __('Valor ejecutado', 'secop-suite'),    'agg' => 'SUM',            'col' => 'valordebito'],
            'saldoporejecutaresp' => ['label' => __('Saldo por ejecutar', 'secop-suite'), 'agg' => 'SUM',            'col' => 'saldoporejecutaresp'],
            'contratos'           => ['label' => __('Nº de contratos', 'secop-suite'),     'agg' => 'COUNT_DISTINCT', 'col' => 'numero_del_contrato'],
            'registros'           => ['label' => __('Nº de registros', 'secop-suite'),     'agg' => 'COUNT',          'col' => 'numero_de_proceso'],
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

        // Métrica: suma de débito (ejecución) y conteo de contratos distintos.
        $sql = "SELECT `{$col}` AS label,
                       SUM(valordebito) AS valor,
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

        set_transient($cache_key, $result, 10 * MINUTE_IN_SECONDS);
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
        $sql = "SELECT mes, SUM(valordebito) AS valor FROM `{$view}`
                WHERE {$where_sql} GROUP BY mes ORDER BY mes ASC";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $acc = 0.0; $serie = [];
        foreach ($rows ?: [] as $r) {
            $acc += (float) $r['valor'];
            $serie[] = [(int) $r['mes'], $acc];
        }

        set_transient($cache_key, $serie, 10 * MINUTE_IN_SECONDS);
        return $serie;
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

        set_transient($cache_key, $result, 10 * MINUTE_IN_SECONDS);
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
        // Etiquetas amigables para el desplegable de dimensión. 'fuente' se excluye
        // del menú (columna de valor único), pero se conserva en COMPAT por compatibilidad.
        $dim_labels = [
            'dependencia'    => __('Dependencia', 'secop-suite'),
            'tipo_contrato'  => __('Tipo de contrato', 'secop-suite'),
            'modalidad'      => __('Modalidad de contratación', 'secop-suite'),
            'estado'         => __('Estado del proceso', 'secop-suite'),
            'tipo_documento' => __('Tipo de documento del proveedor', 'secop-suite'),
            'programa'       => __('Programa presupuestal', 'secop-suite'),
            'rubro'          => __('Rubro presupuestal', 'secop-suite'),
            'tercero'        => __('Contratista', 'secop-suite'),
            'mensual'        => __('Mensual (evolución)', 'secop-suite'),
        ];
        $dependencias = $this->list_dependencies();
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
        $chart_config = get_post_meta($post->ID, '_secop_chart_config', true);
        $dep_config   = get_post_meta($post->ID, '_secop_dep_card_config', true) ?: [];
        $configured   = (is_array($chart_config) && !empty($chart_config))
            || !empty($dep_config['dimension']);
        if (!$configured) {
            echo '<p>' . esc_html__('Guarde la tarjeta para ver la vista previa de la gráfica.', 'secop-suite') . '</p>';
            return;
        }
        echo '<div class="secop-dep-card-preview">';
        echo do_shortcode('[secop_dep_chart card="' . (int) $post->ID . '"]');
        echo '</div>';
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

        $metric = sanitize_text_field($_POST['dep_metric'] ?? 'valordebito');
        if (!in_array($metric, array_keys($this->metrics()), true)) $metric = 'valordebito';
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
            'colors'      => $colors,
        ];
        update_post_meta($post_id, '_secop_dep_card_config', $config);
        update_post_meta($post_id, '_secop_chart_config', $this->card_to_chart_config($config));
    }

    public function enqueue_admin_assets(string $hook): void
    {
        global $post_type;
        if ($post_type !== self::POST_TYPE) return;
        // Reutiliza las librerías de gráfica del Visualizer vía el handle compartido.
        wp_enqueue_style('secop-suite-admin', SECOP_SUITE_URL . 'assets/css/admin.css', [], SECOP_SUITE_VERSION);
        // Carga el stack de gráficas del frontend para que ChartManager pueda renderizar
        // la vista previa en la pantalla de edición de la tarjeta.
        \SecopSuite\Plugin::get_instance()->visualizer()->enqueue_frontend_chart_stack();
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
        $raw_metric = $cfg['metric'] ?? 'valordebito';
        $metric_key = isset($metrics[$raw_metric]) ? $raw_metric : 'valordebito';
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

        $filters = [['field' => 'anio', 'operator' => '=', 'value' => (string) $this->current_vigencia()]];
        $dep = ($dependencia !== null && $dependencia !== '') ? $dependencia : ($cfg['dependencia'] ?? '');
        if ($dep !== '') {
            $filters[] = ['field' => 'nombredependencia', 'operator' => '=', 'value' => $dep];
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
            'show_legend'     => true,
            'legend_mode'     => 'text',
            'legend_position' => 'bottom',
            'show_timeline'   => false,
            'show_toolbar'    => true,
            'toolbar_options' => ['share', 'data', 'image', 'download'],
            'chart_height'    => 400,
            'y_axis_title'    => '',
            'x_axis_title'    => '',
            'number_format'   => 'colombiano',
            'custom_query'    => '',
        ];
    }

    // ── Presets prediseñados ──────────────────────────────────────

    /** Definición estática de los presets de gráficas prediseñadas. */
    public function presets(): array
    {
        return [
            'por_dependencia' => [
                'titulo'      => __('Ejecución por dependencia', 'secop-suite'),
                'dimension'   => 'dependencia',
                'chart_type'  => 'bar',
                'metric'      => 'valordebito',
                'limit'       => 15,
                'descripcion' => __('Valor ejecutado (compromisos RES) y número de contratos por cada dependencia de la entidad en la vigencia actual.', 'secop-suite'),
            ],
            'top_contratistas' => [
                'titulo'      => __('Top 10 contratistas', 'secop-suite'),
                'dimension'   => 'tercero',
                'chart_type'  => 'bar',
                'metric'      => 'valordebito',
                'limit'       => 10,
                'descripcion' => __('Los diez contratistas (terceros) con mayor valor ejecutado en la vigencia actual.', 'secop-suite'),
            ],
            'evolucion_mensual' => [
                'titulo'      => __('Evolución mensual y predicción', 'secop-suite'),
                'dimension'   => 'mensual',
                'chart_type'  => 'line',
                'metric'      => 'valordebito',
                'limit'       => 0,
                'descripcion' => __('Ejecución acumulada mes a mes de la vigencia actual, con proyección de cierre por regresión lineal.', 'secop-suite'),
            ],
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

    public function sc_chart(array $atts): string
    {
        $atts = shortcode_atts(
            ['card' => 0, 'dimension' => '', 'tipo' => '', 'dependencia' => '', 'height' => 400, 'preset' => ''],
            $atts, 'secop_dep_chart'
        );

        // Resolve preset → backing card post (find-or-create).
        if (!empty($atts['preset'])) {
            $presetKey = sanitize_key($atts['preset']);
            $presets   = $this->presets();
            if (!isset($presets[$presetKey])) {
                return '<p class="ss-error">' . esc_html(sprintf(
                    /* translators: %s: preset key */
                    __('Preset desconocido: "%s". Presets disponibles: por_dependencia, top_contratistas, evolucion_mensual.', 'secop-suite'),
                    $presetKey
                )) . '</p>';
            }
            $atts['card'] = $this->get_preset_post_id($presetKey);
        }

        $card_id = (int) $atts['card'];
        if (!$card_id) {
            return '<p class="ss-error">' . esc_html__('Especifique un ID de card válido: [secop_dep_chart card="N"]', 'secop-suite') . '</p>';
        }

        // Runtime dependency override from the shortcode attribute (used by sc_seguimiento).
        // Applied via data-dependencia on the container → AJAX filter.
        // NOT persisted into _secop_chart_config so the stored config stays general.
        $dependencia_filter = sanitize_text_field($atts['dependencia']);

        // Try existing Visualizer-compatible config (written by save_card_meta or a previous render).
        $config = get_post_meta($card_id, '_secop_chart_config', true);

        // On-the-fly build for legacy cards that lack _secop_chart_config.
        if (empty($config) || !is_array($config)) {
            $dep_cfg = get_post_meta($card_id, '_secop_dep_card_config', true) ?: [];
            if (empty($dep_cfg)) {
                return '<p class="ss-error">' . esc_html__('Card no encontrada o sin configuración.', 'secop-suite') . '</p>';
            }
            // Apply dimension/type overrides but keep the card's own dependencia for the stored
            // config — the runtime $dependencia_filter is applied via data-dependencia at render time.
            $resolved             = $this->resolve_config($atts);
            $dep_cfg['dimension'] = $resolved['dimension'];
            $dep_cfg['chart_type'] = $resolved['chart_type'];
            // Persist a general config (card's configured dependencia only, not the shortcode override).
            $dep_cfg['dependencia'] = sanitize_text_field($dep_cfg['dependencia'] ?? '');
            $config = $this->card_to_chart_config($dep_cfg);
            // Persist so the Visualizer AJAX can serve data using this card's post ID.
            update_post_meta($card_id, '_secop_chart_config', $config);
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
                    /* translators: %s: preset key */
                    __('Preset desconocido: "%s". Presets disponibles: por_dependencia, top_contratistas, evolucion_mensual.', 'secop-suite'),
                    $presetKey
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
        foreach (['secop_dep_chart','secop_seguimiento','secop_dep_contratos','secop_consulta'] as $sc) {
            if (has_shortcode($post->post_content, $sc)) { $has = true; break; }
        }
        if (!$has) return;

        // Reutilizar las librerías d3/d3plus del Visualizer.
        Plugin::get_instance()->visualizer(); // asegura registro
        do_action('secop_suite_enqueue_chart_libs');

        wp_enqueue_style('secop-suite-frontend', SECOP_SUITE_URL . 'assets/css/frontend.css', [], SECOP_SUITE_VERSION);
        // dep-tracking.js no longer renders charts directly; Visualizer/frontend.js handles them.
        // Depends only on jquery (contracts table) + secop-suite-frontend (for SSChartManager).
        wp_enqueue_script('secop-dep-tracking', SECOP_SUITE_URL . 'assets/js/dep-tracking.js',
            ['jquery', 'secop-suite-frontend'], SECOP_SUITE_VERSION, true);
        // FIX 6: cadenas i18n expuestas al JS para evitar literales hardcoded en el bundle
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
                       fecha_inicio_ejecucion, fecha_fin_ejecucion, valor_contrato, objeto_del_proceso
                FROM `{$view}` WHERE anio = %d AND nombredependencia = %s
                GROUP BY numero_del_contrato
                ORDER BY valor_contrato DESC LIMIT %d";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, $this->current_vigencia(), $dependencia, $limit), ARRAY_A);

        $result = $rows ?: [];
        set_transient($cache_key, $result, 10 * MINUTE_IN_SECONDS);
        return $result;
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

        set_transient($cache_key, $result, 10 * MINUTE_IN_SECONDS);
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
}
