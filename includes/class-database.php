<?php
/**
 * Database — Gestión de la tabla de contratos y operaciones de datos.
 *
 * API: https://www.datos.gov.co/resource/rpmr-utcd.json
 *
 * @package SecopSuite
 */

declare(strict_types=1);

namespace SecopSuite;

if (!defined('ABSPATH')) {
    exit;
}

final class Database
{
    private string $table_name;

    /** Campos de tipo fecha */
    private const DATE_FIELDS = [
        'fecha_de_firma_del_contrato',
        'fecha_inicio_ejecucion',
        'fecha_fin_ejecucion',
    ];

    /** Campos de tipo moneda / decimal */
    private const CURRENCY_FIELDS = [
        'valor_contrato',
    ];

    /** Campos enteros */
    private const INTEGER_FIELDS = [];

    /**
     * Mapeo API SECOP (rpmr-utcd) → columna de la BD.
     * Las claves con `_` al final en la API (ej. modalidad_de_contrataci_n)
     * representan caracteres especiales (ó) decodificados por Socrata.
     */
    private const FIELD_MAPPING = [
        'nivel_entidad'              => 'nivel_entidad',
        'codigo_entidad_en_secop'    => 'codigo_entidad_en_secop',
        'nombre_de_la_entidad'       => 'nombre_de_la_entidad',
        'nit_de_la_entidad'          => 'nit_de_la_entidad',
        'departamento_entidad'       => 'departamento_entidad',
        'municipio_entidad'          => 'municipio_entidad',
        'estado_del_proceso'         => 'estado_del_proceso',
        'modalidad_de_contrataci_n'  => 'modalidad_de_contratacion',
        'objeto_a_contratar'         => 'objeto_a_contratar',
        'objeto_del_proceso'         => 'objeto_del_proceso',
        'tipo_de_contrato'           => 'tipo_de_contrato',
        'fecha_de_firma_del_contrato'=> 'fecha_de_firma_del_contrato',
        'fecha_inicio_ejecuci_n'     => 'fecha_inicio_ejecucion',
        'fecha_fin_ejecuci_n'        => 'fecha_fin_ejecucion',
        'numero_del_contrato'        => 'numero_del_contrato',
        'numero_de_proceso'          => 'numero_de_proceso',
        'valor_contrato'             => 'valor_contrato',
        'nom_raz_social_contratista' => 'nom_raz_social_contratista',
        'url_contrato'               => 'url_contrato',
        'origen'                     => 'origen',
        'tipo_documento_proveedor'   => 'tipo_documento_proveedor',
        'documento_proveedor'        => 'documento_proveedor',
    ];

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'secop_contracts';
    }

    public function get_table_name(): string
    {
        return $this->table_name;
    }

    public function get_field_mapping(): array
    {
        return self::FIELD_MAPPING;
    }

    // ── Creación/migración de tabla ────────────────────────────
    public function create_table(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nivel_entidad VARCHAR(50) DEFAULT NULL,
            codigo_entidad_en_secop VARCHAR(50) DEFAULT NULL,
            nombre_de_la_entidad VARCHAR(255) DEFAULT NULL,
            nit_de_la_entidad VARCHAR(20) DEFAULT NULL,
            departamento_entidad VARCHAR(100) DEFAULT NULL,
            municipio_entidad VARCHAR(100) DEFAULT NULL,
            estado_del_proceso VARCHAR(50) DEFAULT NULL,
            modalidad_de_contratacion VARCHAR(100) DEFAULT NULL,
            objeto_a_contratar TEXT DEFAULT NULL,
            objeto_del_proceso TEXT DEFAULT NULL,
            tipo_de_contrato VARCHAR(100) DEFAULT NULL,
            fecha_de_firma_del_contrato DATETIME DEFAULT NULL,
            fecha_inicio_ejecucion DATETIME DEFAULT NULL,
            fecha_fin_ejecucion DATETIME DEFAULT NULL,
            numero_del_contrato VARCHAR(100) NOT NULL,
            numero_de_proceso VARCHAR(100) DEFAULT NULL,
            valor_contrato DECIMAL(20,2) DEFAULT 0,
            nom_raz_social_contratista VARCHAR(255) DEFAULT NULL,
            url_contrato VARCHAR(500) DEFAULT NULL,
            origen VARCHAR(50) DEFAULT NULL,
            tipo_documento_proveedor VARCHAR(50) DEFAULT NULL,
            documento_proveedor VARCHAR(50) DEFAULT NULL,
            fecha_importacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_contract (numero_del_contrato),
            KEY idx_nit_entidad (nit_de_la_entidad),
            KEY idx_estado_proceso (estado_del_proceso),
            KEY idx_fecha_firma (fecha_de_firma_del_contrato),
            KEY idx_proveedor (documento_proveedor),
            KEY idx_tipo_contrato (tipo_de_contrato),
            KEY idx_modalidad (modalidad_de_contratacion),
            KEY idx_origen (origen),
            KEY idx_nivel (nivel_entidad)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(SECOP_SUITE_PREFIX . 'db_version', SECOP_SUITE_DB_VERSION);
    }

    /**
     * Migración destructiva desde schema antiguo (pre-5.0.0) al nuevo.
     * Elimina la tabla antigua y crea la nueva.
     */
    public function migrate_to_new_schema(): void
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
        $this->create_table();
        update_option(SECOP_SUITE_PREFIX . 'total_records', 0);
        delete_option(SECOP_SUITE_PREFIX . 'last_import');
        Logger::info("Migración v5.0.0 completada: tabla recreada con nuevo schema");
    }

    // ── Estadísticas ───────────────────────────────────────────
    public function get_total_records(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
    }

    public function get_total_value(): float
    {
        global $wpdb;
        return (float) $wpdb->get_var("SELECT COALESCE(SUM(valor_contrato), 0) FROM {$this->table_name}");
    }

    // ── Procesamiento de registros ─────────────────────────────
    public function process_record(array $item): ?string
    {
        global $wpdb;

        if (empty($item['numero_del_contrato'])) {
            return null;
        }

        $data    = [];
        $formats = [];

        foreach (self::FIELD_MAPPING as $api_field => $db_field) {
            $value = $this->extract_value($item, $api_field);
            if ($value !== null) {
                $data[$db_field]    = $this->sanitize_value($db_field, $value);
                $formats[]          = $this->get_format($db_field);
            }
        }

        if (empty($data)) {
            return null;
        }

        $columns      = array_keys($data);
        $placeholders = implode(', ', $formats);
        $col_list     = '`' . implode('`, `', $columns) . '`';

        $update_parts = [];
        foreach ($columns as $col) {
            if ($col !== 'numero_del_contrato') {
                $update_parts[] = "`{$col}` = VALUES(`{$col}`)";
            }
        }
        $update_sql = implode(', ', $update_parts);

        $sql = "INSERT INTO {$this->table_name} ({$col_list}) VALUES ({$placeholders})
                ON DUPLICATE KEY UPDATE {$update_sql}";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->query($wpdb->prepare($sql, array_values($data)));

        if ($result === false) {
            return null;
        }

        return $result === 1 ? 'inserted' : 'updated';
    }

    private function extract_value(array $item, string $field): mixed
    {
        return $item[$field] ?? null;
    }

    public function sanitize_value(string $field, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($field, self::DATE_FIELDS, true)) {
            $ts = strtotime((string) $value);
            return $ts ? gmdate('Y-m-d H:i:s', $ts) : null;
        }

        if (in_array($field, self::CURRENCY_FIELDS, true)) {
            return (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
        }

        if (in_array($field, self::INTEGER_FIELDS, true)) {
            return (int) $value;
        }

        return sanitize_text_field((string) $value);
    }

    public function get_format(string $field): string
    {
        if (in_array($field, self::INTEGER_FIELDS, true)) {
            return '%d';
        }
        if (in_array($field, self::CURRENCY_FIELDS, true)) {
            return '%f';
        }
        return '%s';
    }

    // ── Validación de columnas ─────────────────────────────────
    public function get_table_columns(string $table): array
    {
        $cache_key = 'secop_cols_' . md5($table);
        $cached    = wp_cache_get($cache_key, 'secop_suite');
        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;
        $columns = [];
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results("DESCRIBE `{$table}`", ARRAY_A);
        foreach ($rows as $row) {
            $columns[$row['Field']] = $row['Type'];
        }

        wp_cache_set($cache_key, $columns, 'secop_suite', 300);
        return $columns;
    }

    public function is_valid_column(string $table, string $column): bool
    {
        $columns = $this->get_table_columns($table);
        return isset($columns[$column]);
    }

    public function get_available_tables(): array
    {
        $cached = wp_cache_get('secop_available_tables', 'secop_suite');
        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;
        $tables = [];

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_name)) === $this->table_name) {
            $tables[$this->table_name] = 'SECOP Contracts';
        }

        // Legacy tables (backwards compatibility)
        $legacy = $wpdb->prefix . 'wp_data_contracting';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $legacy)) === $legacy) {
            $tables[$legacy] = 'Data Contracting (Legacy)';
        }

        $custom = $wpdb->get_results(
            $wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'dat_%'),
            ARRAY_N
        );
        foreach ($custom as $row) {
            $tables[$row[0]] = str_replace($wpdb->prefix, '', $row[0]);
        }

        wp_cache_set('secop_available_tables', $tables, 'secop_suite', 300);
        return $tables;
    }
}
