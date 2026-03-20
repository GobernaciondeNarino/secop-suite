<?php
/**
 * Plugin Name: SECOP Suite
 * Plugin URI: https://github.com/GobernaciondeNarino/secop-suite
 * Description: Plugin integral para la importación, almacenamiento y visualización interactiva de datos contractuales del SECOP (Sistema Electrónico de Contratación Pública) de Colombia. Combina importación automatizada desde datos.gov.co con gráficas D3plus configurables mediante shortcodes.
 * Version: 4.2.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Jonnathan Bucheli Galindo - Gobernación de Nariño
 * Author URI: https://narino.gov.co
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: secop-suite
 * Domain Path: /languages
 *
 * @package SecopSuite
 */

declare(strict_types=1);

namespace SecopSuite;

if (!defined('ABSPATH')) {
    exit;
}

// ─── Constantes ────────────────────────────────────────────────
define('SECOP_SUITE_VERSION', '4.2.0');
define('SECOP_SUITE_DB_VERSION', '4.2.0');
define('SECOP_SUITE_DIR', plugin_dir_path(__FILE__));
define('SECOP_SUITE_URL', plugin_dir_url(__FILE__));
define('SECOP_SUITE_BASENAME', plugin_basename(__FILE__));
define('SECOP_SUITE_PREFIX', 'secop_suite_');

// ─── Autoload de clases ────────────────────────────────────────
spl_autoload_register(static function (string $class): void {
    $prefix = 'SecopSuite\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = SECOP_SUITE_DIR . 'includes/class-' . strtolower(str_replace('_', '-', $relative)) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// ─── Clase principal ───────────────────────────────────────────
final class Plugin
{
    private static ?Plugin $instance = null;
    private Database $database;
    private Importer $importer;
    private Visualizer $visualizer;
    private Rest_Api $rest_api;
    private Updater $updater;

    private function __construct()
    {
        $this->database   = new Database();
        $this->importer   = new Importer($this->database);
        $this->visualizer = new Visualizer($this->database);
        $this->rest_api   = new Rest_Api($this->database);
        $this->updater    = new Updater();

        $this->register_hooks();
    }

    public static function get_instance(): self
    {
        return self::$instance ??= new self();
    }

    // ── Getters públicos ───────────────────────────────────────
    public function database(): Database   { return $this->database; }
    public function importer(): Importer   { return $this->importer; }
    public function visualizer(): Visualizer { return $this->visualizer; }

    // ── Hooks ──────────────────────────────────────────────────
    private function register_hooks(): void
    {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
        add_action('secop_suite_scheduled_import', [$this->importer, 'run_scheduled']);

        // Background import hook
        add_action('secop_suite_run_import', [$this->importer, 'run_background']);

        // WP-CLI
        if (defined('WP_CLI') && \WP_CLI) {
            \WP_CLI::add_command('secop', Cli::class);
        }

        // Plugin action links
        add_filter('plugin_action_links_' . SECOP_SUITE_BASENAME, [$this, 'add_action_links']);
    }

    // ── Internacionalización ─────────────────────────────────────
    public function load_textdomain(): void
    {
        load_plugin_textdomain('secop-suite', false, dirname(SECOP_SUITE_BASENAME) . '/languages');
    }

    // ── Activación / Desactivación ─────────────────────────────
    public function activate(): void
    {
        $this->database->create_table();
        $this->set_default_options();
        $this->maybe_upgrade();

        if (get_option(SECOP_SUITE_PREFIX . 'auto_update_enabled', false)) {
            $this->schedule_import();
        }

        flush_rewrite_rules();
    }

    /**
     * Ejecutar migraciones pendientes al actualizar el plugin.
     */
    private function maybe_upgrade(): void
    {
        $current_version = get_option(SECOP_SUITE_PREFIX . 'db_version', '0');

        if (version_compare($current_version, SECOP_SUITE_DB_VERSION, '<')) {
            // Re-crear tabla (dbDelta es idempotente, agrega columnas faltantes)
            $this->database->create_table();

            // Hook para extensiones
            do_action('secop_suite_after_upgrade', $current_version, SECOP_SUITE_DB_VERSION);

            update_option(SECOP_SUITE_PREFIX . 'db_version', SECOP_SUITE_DB_VERSION);
        }
    }

    public function deactivate(): void
    {
        wp_clear_scheduled_hook('secop_suite_scheduled_import');
        delete_transient(SECOP_SUITE_PREFIX . 'import_progress');
        delete_transient(SECOP_SUITE_PREFIX . 'import_running');
    }

    private function set_default_options(): void
    {
        $defaults = [
            'api_url'               => 'https://www.datos.gov.co/resource/jbjy-vk9h.json',
            'nit_entidad'           => '800103923',
            'fecha_inicio'          => '2016-01-01',
            'fecha_fin'             => date('Y-12-31'),
            'auto_update_enabled'   => false,
            'auto_update_frequency' => 'daily',
            'last_import'           => null,
            'total_records'         => 0,
        ];

        foreach ($defaults as $key => $value) {
            $option_name = SECOP_SUITE_PREFIX . $key;
            if (get_option($option_name) === false) {
                add_option($option_name, $value);
            }
        }
    }

    // ── Menú de administración ─────────────────────────────────
    public function register_admin_menu(): void
    {
        add_menu_page(
            __('SECOP Suite', 'secop-suite'),
            __('SECOP Suite', 'secop-suite'),
            'manage_options',
            'secop-suite',
            [$this, 'render_import_page'],
            'dashicons-chart-area',
            80
        );

        add_submenu_page(
            'secop-suite',
            __('Importar Datos', 'secop-suite'),
            __('Importar Datos', 'secop-suite'),
            'manage_options',
            'secop-suite',
            [$this, 'render_import_page']
        );

        add_submenu_page(
            'secop-suite',
            __('Ver Registros', 'secop-suite'),
            __('Ver Registros', 'secop-suite'),
            'manage_options',
            'secop-suite-records',
            [$this, 'render_records_page']
        );

        add_submenu_page(
            'secop-suite',
            __('Logs', 'secop-suite'),
            __('Logs', 'secop-suite'),
            'manage_options',
            'secop-suite-logs',
            [$this, 'render_logs_page']
        );
    }

    // ── Registro de configuraciones ────────────────────────────
    public function register_settings(): void
    {
        $sanitize_date = static function (string $value): string {
            $value = sanitize_text_field($value);
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) ? $value : '';
        };

        $sanitize_frequency = static function (string $value): string {
            return in_array($value, ['daily', 'weekly', 'monthly'], true) ? $value : 'daily';
        };

        $fields = [
            'api_url'               => ['sanitize_callback' => 'esc_url_raw'],
            'nit_entidad'           => ['sanitize_callback' => 'sanitize_text_field'],
            'fecha_inicio'          => ['sanitize_callback' => $sanitize_date],
            'fecha_fin'             => ['sanitize_callback' => $sanitize_date],
            'auto_update_enabled'   => ['type' => 'boolean'],
            'auto_update_frequency' => ['sanitize_callback' => $sanitize_frequency],
        ];

        foreach ($fields as $key => $args) {
            register_setting('secop_suite_settings', SECOP_SUITE_PREFIX . $key, $args);
        }
    }

    // ── Assets de administración ───────────────────────────────
    public function enqueue_admin_assets(string $hook): void
    {
        // Import pages
        if (str_contains($hook, 'secop-suite')) {
            wp_enqueue_style(
                'secop-suite-admin',
                SECOP_SUITE_URL . 'assets/css/admin.css',
                [],
                SECOP_SUITE_VERSION
            );

            wp_enqueue_script(
                'secop-suite-admin-import',
                SECOP_SUITE_URL . 'assets/js/admin-import.js',
                ['jquery'],
                SECOP_SUITE_VERSION,
                true
            );

            wp_localize_script('secop-suite-admin-import', 'secopSuiteAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('secop_suite_import'),
                'strings' => [
                    'importing'      => __('Importando...', 'secop-suite'),
                    'complete'       => __('Importación completada', 'secop-suite'),
                    'error'          => __('Error durante la importación', 'secop-suite'),
                    'confirm_cancel' => __('¿Está seguro de cancelar la importación?', 'secop-suite'),
                ],
            ]);
        }
    }

    // ── Render de páginas ──────────────────────────────────────
    public function render_import_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tiene permisos para acceder a esta página.', 'secop-suite'));
        }

        $total_records = $this->database->get_total_records();
        $last_import   = get_option(SECOP_SUITE_PREFIX . 'last_import');
        $is_importing  = (bool) get_transient(SECOP_SUITE_PREFIX . 'import_running');
        $total_value   = $this->database->get_total_value();

        include SECOP_SUITE_DIR . 'templates/admin/import-page.php';
    }

    public function render_records_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tiene permisos para acceder a esta página.', 'secop-suite'));
        }

        global $wpdb;
        $table_name = $this->database->get_table_name();

        $per_page     = 50;
        $current_page = max(1, intval($_GET['paged'] ?? 1));
        $offset       = ($current_page - 1) * $per_page;

        $total_records = $this->database->get_total_records();
        $total_pages   = (int) ceil($total_records / $per_page);

        // Filtros
        $where_clauses = ['1=1'];
        $where_values  = [];

        if (!empty($_GET['search'])) {
            $search          = '%' . $wpdb->esc_like(sanitize_text_field($_GET['search'])) . '%';
            $where_clauses[] = '(proveedor_adjudicado LIKE %s OR descripcion_del_proceso LIKE %s OR referencia_del_contrato LIKE %s)';
            array_push($where_values, $search, $search, $search);
        }

        if (!empty($_GET['anno'])) {
            $where_clauses[] = 'anno_bpin = %s';
            $where_values[]  = sanitize_text_field($_GET['anno']);
        }

        if (!empty($_GET['estado'])) {
            $where_clauses[] = 'estado_contrato = %s';
            $where_values[]  = sanitize_text_field($_GET['estado']);
        }

        $where_sql      = implode(' AND ', $where_clauses);
        $where_values[] = $per_page;
        $where_values[] = $offset;

        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY fecha_de_firma DESC LIMIT %d OFFSET %d",
            $where_values
        ));

        $years   = $wpdb->get_col("SELECT DISTINCT anno_bpin FROM {$table_name} WHERE anno_bpin IS NOT NULL ORDER BY anno_bpin DESC");
        $estados = $wpdb->get_col("SELECT DISTINCT estado_contrato FROM {$table_name} WHERE estado_contrato IS NOT NULL ORDER BY estado_contrato");

        include SECOP_SUITE_DIR . 'templates/admin/records-page.php';
    }

    public function render_logs_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tiene permisos para acceder a esta página.', 'secop-suite'));
        }

        // Procesar limpieza de logs
        if (
            isset($_POST['secop_suite_action'], $_POST['secop_suite_logs_nonce']) &&
            $_POST['secop_suite_action'] === 'clear_logs' &&
            wp_verify_nonce($_POST['secop_suite_logs_nonce'], 'secop_suite_clear_logs') &&
            current_user_can('manage_options')
        ) {
            Logger::clear();
            wp_safe_redirect(admin_url('admin.php?page=secop-suite-logs&cleared=1'));
            exit;
        }

        $logs = Logger::read();

        include SECOP_SUITE_DIR . 'templates/admin/logs-page.php';
    }

    // ── Cron ───────────────────────────────────────────────────
    public function add_cron_schedules(array $schedules): array
    {
        $schedules['weekly']  = ['interval' => WEEK_IN_SECONDS,  'display' => __('Semanalmente', 'secop-suite')];
        $schedules['monthly'] = ['interval' => MONTH_IN_SECONDS, 'display' => __('Mensualmente', 'secop-suite')];
        return $schedules;
    }

    public function schedule_import(): void
    {
        $frequency = get_option(SECOP_SUITE_PREFIX . 'auto_update_frequency', 'daily');
        if (!wp_next_scheduled('secop_suite_scheduled_import')) {
            wp_schedule_event(time(), $frequency, 'secop_suite_scheduled_import');
        }
    }

    // ── Plugin action links ────────────────────────────────────
    public function add_action_links(array $links): array
    {
        $settings_link = '<a href="' . admin_url('admin.php?page=secop-suite') . '">' . __('Configuración', 'secop-suite') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    // ── Prevenir clonación ─────────────────────────────────────
    private function __clone() {}
    public function __wakeup() { throw new \Exception('Cannot unserialize singleton'); }
}

// ─── Inicializar ───────────────────────────────────────────────
Plugin::get_instance();
