<?php
/**
 * Rest_Api — Endpoints REST unificados para contratos y gráficas.
 *
 * @package SecopSuite
 */

declare(strict_types=1);

namespace SecopSuite;

if (!defined('ABSPATH')) {
    exit;
}

final class Rest_Api
{
    private Database $db;
    private const NAMESPACE = 'secop-suite/v1';

    public function __construct(Database $db)
    {
        $this->db = $db;
        add_action('rest_api_init', [$this, 'register_routes']);
        add_filter('rest_post_dispatch', [$this, 'add_security_headers'], 10, 3);
    }

    /**
     * Agregar headers de seguridad a respuestas REST.
     */
    public function add_security_headers(\WP_HTTP_Response $response, \WP_REST_Server $server, \WP_REST_Request $request): \WP_HTTP_Response
    {
        $route = $request->get_route();
        if (str_starts_with($route, '/' . self::NAMESPACE)) {
            $response->header('X-Content-Type-Options', 'nosniff');
            $response->header('X-Frame-Options', 'DENY');
            $response->header('Cache-Control', 'no-store, max-age=0');
        }
        return $response;
    }

    public function register_routes(): void
    {
        // ── Contratos ──────────────────────────────────────────
        register_rest_route(self::NAMESPACE, '/contracts', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_contracts'],
            'permission_callback' => '__return_true',
            'args'                => [
                'per_page'    => ['default' => 10, 'sanitize_callback' => 'absint'],
                'page'        => ['default' => 1,  'sanitize_callback' => 'absint'],
                'anno'        => ['sanitize_callback' => 'sanitize_text_field'],
                'estado'      => ['sanitize_callback' => 'sanitize_text_field'],
                'search'      => ['sanitize_callback' => 'sanitize_text_field'],
                'fecha_desde' => ['sanitize_callback' => 'sanitize_text_field'],
                'fecha_hasta' => ['sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/contracts/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_contract'],
            'permission_callback' => '__return_true',
        ]);

        // ── Estadísticas ───────────────────────────────────────
        register_rest_route(self::NAMESPACE, '/stats', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_stats'],
            'permission_callback' => '__return_true',
        ]);

        // ── Datos de gráfica ───────────────────────────────────
        register_rest_route(self::NAMESPACE, '/chart/(?P<id>\d+)/data', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_chart_data'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/chart/(?P<id>\d+)/csv', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_chart_csv'],
            'permission_callback' => '__return_true',
        ]);
    }

    // ── Contratos ──────────────────────────────────────────────
    public function get_contracts(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table = $this->db->get_table_name();

        $per_page = min($request->get_param('per_page'), 100);
        $page     = $request->get_param('page');
        $offset   = ($page - 1) * $per_page;

        $where  = ['1=1'];
        $values = [];

        if ($anno = $request->get_param('anno')) {
            $where[]  = 'anno_bpin = %s';
            $values[] = $anno;
        }
        if ($estado = $request->get_param('estado')) {
            $where[]  = 'estado_contrato = %s';
            $values[] = $estado;
        }
        if ($search = $request->get_param('search')) {
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $where[]  = '(proveedor_adjudicado LIKE %s OR descripcion_del_proceso LIKE %s)';
            $values[] = $like;
            $values[] = $like;
        }
        if ($fecha_desde = $request->get_param('fecha_desde')) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) {
                $where[]  = 'fecha_de_firma >= %s';
                $values[] = $fecha_desde . ' 00:00:00';
            }
        }
        if ($fecha_hasta = $request->get_param('fecha_hasta')) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) {
                $where[]  = 'fecha_de_firma <= %s';
                $values[] = $fecha_hasta . ' 23:59:59';
            }
        }

        $where_sql = implode(' AND ', $where);
        $values[]  = $per_page;
        $values[]  = $offset;

        $contracts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY fecha_de_firma DESC LIMIT %d OFFSET %d",
            $values
        ));

        // Total filtrado (sin LIMIT/OFFSET) para paginación correcta
        $count_values = array_slice($values, 0, -2); // Quitar per_page y offset
        if (!empty($count_values)) {
            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}",
                $count_values
            ));
        } else {
            $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where_sql}");
        }

        return new \WP_REST_Response([
            'data' => $contracts,
            'meta' => [
                'total'        => $total,
                'per_page'     => $per_page,
                'current_page' => $page,
                'total_pages'  => max(1, (int) ceil($total / $per_page)),
            ],
        ]);
    }

    public function get_contract(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table = $this->db->get_table_name();
        $id    = (int) $request->get_param('id');

        $contract = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));

        if (!$contract) {
            return new \WP_REST_Response(['message' => 'Contrato no encontrado'], 404);
        }

        return new \WP_REST_Response($contract);
    }

    // ── Estadísticas ───────────────────────────────────────────
    public function get_stats(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table = $this->db->get_table_name();

        return new \WP_REST_Response([
            'total_contracts' => $this->db->get_total_records(),
            'total_value'     => $this->db->get_total_value(),
            'by_year'         => $wpdb->get_results("SELECT anno_bpin, COUNT(*) AS count, SUM(valor_del_contrato) AS total_value FROM {$table} WHERE anno_bpin IS NOT NULL GROUP BY anno_bpin ORDER BY anno_bpin DESC"),
            'by_status'       => $wpdb->get_results("SELECT estado_contrato, COUNT(*) AS count FROM {$table} WHERE estado_contrato IS NOT NULL GROUP BY estado_contrato"),
            'by_type'         => $wpdb->get_results("SELECT tipo_de_contrato, COUNT(*) AS count, SUM(valor_del_contrato) AS total_value FROM {$table} WHERE tipo_de_contrato IS NOT NULL GROUP BY tipo_de_contrato ORDER BY count DESC LIMIT 10"),
            'last_import'     => get_option(SECOP_SUITE_PREFIX . 'last_import'),
        ]);
    }

    // ── Datos de gráfica ───────────────────────────────────────
    public function get_chart_data(\WP_REST_Request $request): \WP_REST_Response
    {
        $chart_id = (int) $request->get_param('id');
        $config   = get_post_meta($chart_id, '_secop_chart_config', true);

        if (!$config) {
            return new \WP_REST_Response(['error' => 'Chart not found'], 404);
        }

        $visualizer = Plugin::get_instance()->visualizer();

        return new \WP_REST_Response([
            'data'   => $visualizer->get_chart_data($config),
            'config' => ['type' => $config['chart_type'], 'title' => get_the_title($chart_id)],
        ]);
    }

    public function get_chart_csv(\WP_REST_Request $request): void
    {
        $chart_id = (int) $request->get_param('id');
        $config   = get_post_meta($chart_id, '_secop_chart_config', true);

        if (!$config) {
            status_header(404);
            echo 'Chart not found';
            exit;
        }

        $data = Plugin::get_instance()->visualizer()->get_chart_data($config);

        if (empty($data)) {
            status_header(404);
            echo 'No data';
            exit;
        }

        // Cabeceras para descarga CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="chart-' . intval($chart_id) . '.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Content-Type-Options: nosniff');

        $output = fopen('php://output', 'w');
        // BOM para Excel
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
}
