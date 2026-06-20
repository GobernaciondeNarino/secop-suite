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
        'fuente'        => ['bar', 'stacked_bar', 'treemap', 'pie', 'donut'],
        'mensual'       => ['line', 'area'],
        'ejecucion'     => ['donut', 'bar'],
    ];

    /** Columna del VIEW que agrupa cada dimensión. */
    private const DIM_COLUMN = [
        'dependencia'   => 'nombredependencia',
        'tipo_contrato' => 'tipo_de_contrato',
        'modalidad'     => 'modalidad_de_contratacion',
        'fuente'        => 'origen',
        'mensual'       => 'mes',
        'ejecucion'     => 'nombredependencia',
    ];

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->register_hooks();
    }

    // ── Compatibilidad dimensión ↔ tipo (puro, testeable) ──────
    public static function dimensions(): array { return array_keys(self::COMPAT); }

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
        add_action('wp_ajax_secop_dep_chart_data',        [$this, 'ajax_chart_data']);
        add_action('wp_ajax_nopriv_secop_dep_chart_data', [$this, 'ajax_chart_data']);
        add_shortcode('secop_seguimiento',               [$this, 'sc_seguimiento']);
        add_shortcode('secop_dep_contratos',             [$this, 'sc_contratos']);
        add_action('wp_ajax_secop_dep_contratos',        [$this, 'ajax_contratos']);
        add_action('wp_ajax_nopriv_secop_dep_contratos', [$this, 'ajax_contratos']);
    }

    public function register_post_type(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'          => __('Cards de Dependencias', 'secop-suite'),
                'singular_name' => __('Card', 'secop-suite'),
                'menu_name'     => __('Seguimiento Dependencias', 'secop-suite'),
                'add_new_item'  => __('Nueva Card', 'secop-suite'),
                'edit_item'     => __('Editar Card', 'secop-suite'),
            ],
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'secop-suite',
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

        return array_map(fn($r) => [
            'label'  => $r['label'] ?? 'N/D',
            'valor'  => (float) $r['valor'],
            'conteo' => (int) $r['conteo'],
        ], $rows ?: []);
    }

    /** Serie mensual acumulada de ejecución (para predicción). */
    public function monthly_series(?string $dependencia = null): array
    {
        global $wpdb;
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
        return $serie;
    }

    /** Construir el dataset de análisis completo para una card. */
    public function build_dataset(string $dimension, ?string $dependencia = null): array
    {
        global $wpdb;
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

        return [
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
        $config = get_post_meta($post->ID, '_secop_dep_card_config', true) ?: [];
        if (empty($config['dimension'])) {
            echo '<p>' . esc_html__('Guarde la card para ver el análisis.', 'secop-suite') . '</p>';
            return;
        }
        $ds = \SecopSuite\Plugin::get_instance()->tracking()->build_dataset(
            $config['dimension'], $config['dependencia'] ?? null
        );
        foreach (['descripcion', 'cualitativo', 'cuantitativo', 'prediccion'] as $tipo) {
            $m = 'analisis_' . $tipo;
            echo '<h4>' . esc_html(ucfirst($tipo)) . '</h4><p>' . esc_html(Stats::$m($ds)) . '</p>';
        }
    }

    public function save_card_meta(int $post_id, \WP_Post $post): void
    {
        if (!isset($_POST['secop_dep_card_nonce']) ||
            !wp_verify_nonce($_POST['secop_dep_card_nonce'], 'secop_dep_card_config')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $dimension = sanitize_text_field($_POST['dep_dimension'] ?? 'dependencia');
        if (!isset(self::COMPAT[$dimension])) $dimension = 'dependencia';
        $type = sanitize_text_field($_POST['dep_chart_type'] ?? '');
        if (!self::is_compatible($dimension, $type)) $type = self::default_type($dimension);

        update_post_meta($post_id, '_secop_dep_card_config', [
            'dimension'   => $dimension,
            'chart_type'  => $type,
            'dependencia' => sanitize_text_field($_POST['dep_dependencia'] ?? ''),
            'metric'      => sanitize_text_field($_POST['dep_metric'] ?? 'valordebito'),
        ]);
    }

    public function enqueue_admin_assets(string $hook): void
    {
        global $post_type;
        if ($post_type !== self::POST_TYPE) return;
        // Reutiliza las librerías de gráfica del Visualizer vía el handle compartido.
        wp_enqueue_style('secop-suite-admin', SECOP_SUITE_URL . 'assets/css/admin.css', [], SECOP_SUITE_VERSION);
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

    public function sc_chart(array $atts): string
    {
        $atts = shortcode_atts(
            ['card' => 0, 'dimension' => '', 'tipo' => '', 'dependencia' => '', 'height' => 400],
            $atts, 'secop_dep_chart'
        );
        $cfg = $this->resolve_config($atts);
        $uid = 'ss-dep-' . wp_unique_id();
        $help = self::compat_help($cfg['dimension']);
        ob_start();
        include SECOP_SUITE_DIR . 'templates/frontend/dep-chart.php';
        return ob_get_clean();
    }

    public function sc_analisis(array $atts): string
    {
        $atts = shortcode_atts(['card' => 0, 'tipo' => 'descripcion'], $atts, 'secop_dep_analisis');
        $cfg = get_post_meta((int) $atts['card'], '_secop_dep_card_config', true) ?: [];
        if (empty($cfg['dimension'])) return '';
        $tipo = in_array($atts['tipo'], ['descripcion','cualitativo','cuantitativo','prediccion'], true)
            ? $atts['tipo'] : 'descripcion';
        $ds = $this->build_dataset($cfg['dimension'], $cfg['dependencia'] ?? null);
        $m = 'analisis_' . $tipo;
        return '<p class="ss-dep-analisis ss-dep-' . esc_attr($tipo) . '">'
             . esc_html(Stats::$m($ds)) . '</p>';
    }

    public function ajax_chart_data(): void
    {
        check_ajax_referer('secop_dep_frontend', 'nonce');
        $ip_key = 'secop_dep_rl_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
        if ((int) get_transient($ip_key) > 60) wp_send_json_error(['message' => 'Demasiadas solicitudes'], 429);
        set_transient($ip_key, ((int) get_transient($ip_key)) + 1, MINUTE_IN_SECONDS);

        $dimension = sanitize_text_field($_POST['dimension'] ?? 'dependencia');
        if (!isset(self::COMPAT[$dimension])) wp_send_json_error(['message' => 'Dimensión inválida']);
        $dep  = sanitize_text_field($_POST['dependencia'] ?? '');
        $rows = $this->group_by_dimension($dimension, $dep ?: null);
        wp_send_json_success(['data' => $rows]);
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
        wp_enqueue_script('secop-dep-tracking', SECOP_SUITE_URL . 'assets/js/dep-tracking.js',
            ['jquery', 'd3plus'], SECOP_SUITE_VERSION, true);
        wp_localize_script('secop-dep-tracking', 'secopDep', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('secop_dep_frontend'),
        ]);
    }

    // ── Task 12: contratos por dependencia ────────────────────────

    /** Lista de contratos de una dependencia (vigencia actual), deduplicados por contrato. */
    public function contracts_by_dependency(string $dependencia, int $limit = 100): array
    {
        global $wpdb;
        $view = $this->db->get_view_name();
        $sql = "SELECT numero_del_contrato, url_contrato, nom_raz_social_contratista,
                       fecha_inicio_ejecucion, fecha_fin_ejecucion, valor_contrato, objeto_del_proceso
                FROM `{$view}` WHERE anio = %d AND nombredependencia = %s
                GROUP BY numero_del_contrato
                ORDER BY valor_contrato DESC LIMIT %d";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, $this->current_vigencia(), $dependencia, $limit), ARRAY_A);
        return $rows ?: [];
    }

    public function ajax_contratos(): void
    {
        check_ajax_referer('secop_dep_frontend', 'nonce');
        $dep = sanitize_text_field($_POST['dependencia'] ?? '');
        if ($dep === '') wp_send_json_error(['message' => 'Dependencia requerida']);
        wp_send_json_success(['rows' => $this->contracts_by_dependency($dep)]);
    }

    /** Dependencias disponibles en la vigencia actual (para el selector). */
    public function list_dependencies(): array
    {
        global $wpdb;
        $view = $this->db->get_view_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT nombredependencia FROM `{$view}` WHERE anio = %d AND nombredependencia <> '' ORDER BY nombredependencia",
            $this->current_vigencia()
        )) ?: [];
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
        $atts = shortcode_atts(['dependencia' => '', 'dimensiones' => 'dependencia,modalidad,fuente'], $atts, 'secop_seguimiento');
        $deps = $this->list_dependencies();
        $dimensiones = array_filter(array_map('trim', explode(',', $atts['dimensiones'])),
            fn($d) => isset(self::COMPAT[$d]));
        $sel = sanitize_text_field($atts['dependencia']);
        ob_start();
        include SECOP_SUITE_DIR . 'templates/frontend/dep-seguimiento.php';
        return ob_get_clean();
    }
}
