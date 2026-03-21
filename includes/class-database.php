<?php
/**
 * Database — Gestión de la tabla de contratos y operaciones de datos.
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
        'fecha_de_firma',
        'fecha_de_inicio_del_contrato',
        'fecha_de_fin_del_contrato',
        'ultima_actualizacion',
    ];

    /** Campos de tipo moneda / decimal */
    private const CURRENCY_FIELDS = [
        'valor_del_contrato', 'valor_de_pago_adelantado', 'valor_facturado',
        'valor_pendiente_de_pago', 'valor_pagado', 'valor_amortizado',
        'valor_pendiente_de', 'valor_pendiente_de_ejecucion',
        'saldo_cdp', 'saldo_vigencia', 'presupuesto_general_nacion',
        'sistema_general_participaciones', 'sistema_general_regalias',
        'recursos_propios_territorial', 'recursos_de_credito', 'recursos_propios',
    ];

    /** Campos enteros */
    private const INTEGER_FIELDS = ['dias_adicionados'];

    /** Mapeo API SECOP → columna de la BD */
    private const FIELD_MAPPING = [
        'nombre_entidad'                                                  => 'nombre_entidad',
        'nit_entidad'                                                     => 'nit_entidad',
        'departamento'                                                    => 'departamento',
        'ciudad'                                                          => 'ciudad',
        'localizaci_n'                                                    => 'localizacion',
        'orden'                                                           => 'orden',
        'sector'                                                          => 'sector',
        'rama'                                                            => 'rama',
        'entidad_centralizada'                                            => 'entidad_centralizada',
        'proceso_de_compra'                                               => 'proceso_de_compra',
        'id_contrato'                                                     => 'id_contrato',
        'referencia_del_contrato'                                         => 'referencia_del_contrato',
        'estado_contrato'                                                 => 'estado_contrato',
        'codigo_de_categoria_principal'                                   => 'codigo_de_categoria_principal',
        'descripcion_del_proceso'                                         => 'descripcion_del_proceso',
        'tipo_de_contrato'                                                => 'tipo_de_contrato',
        'modalidad_de_contratacion'                                       => 'modalidad_de_contratacion',
        'justificacion_modalidad_de'                                      => 'justificacion_modalidad_de',
        'fecha_de_firma'                                                  => 'fecha_de_firma',
        'fecha_de_inicio_del_contrato'                                    => 'fecha_de_inicio_del_contrato',
        'fecha_de_fin_del_contrato'                                       => 'fecha_de_fin_del_contrato',
        'condiciones_de_entrega'                                          => 'condiciones_de_entrega',
        'tipodocproveedor'                                                => 'tipodocproveedor',
        'documento_proveedor'                                             => 'documento_proveedor',
        'proveedor_adjudicado'                                            => 'proveedor_adjudicado',
        'es_grupo'                                                        => 'es_grupo',
        'es_pyme'                                                         => 'es_pyme',
        'habilita_pago_adelantado'                                        => 'habilita_pago_adelantado',
        'liquidaci_n'                                                     => 'liquidacion',
        'obligaci_n_ambiental'                                            => 'obligacion_ambiental',
        'obligaciones_postconsumo'                                        => 'obligaciones_postconsumo',
        'reversion'                                                       => 'reversion',
        'origen_de_los_recursos'                                          => 'origen_de_los_recursos',
        'destino_gasto'                                                   => 'destino_gasto',
        'valor_del_contrato'                                              => 'valor_del_contrato',
        'valor_de_pago_adelantado'                                        => 'valor_de_pago_adelantado',
        'valor_facturado'                                                 => 'valor_facturado',
        'valor_pendiente_de_pago'                                         => 'valor_pendiente_de_pago',
        'valor_pagado'                                                    => 'valor_pagado',
        'valor_amortizado'                                                => 'valor_amortizado',
        'valor_pendiente_de'                                              => 'valor_pendiente_de',
        'valor_pendiente_de_ejecucion'                                    => 'valor_pendiente_de_ejecucion',
        'estado_bpin'                                                     => 'estado_bpin',
        'c_digo_bpin'                                                     => 'codigo_bpin',
        'anno_bpin'                                                       => 'anno_bpin',
        'saldo_cdp'                                                       => 'saldo_cdp',
        'saldo_vigencia'                                                  => 'saldo_vigencia',
        'espostconflicto'                                                 => 'espostconflicto',
        'dias_adicionados'                                                => 'dias_adicionados',
        'puntos_del_acuerdo'                                              => 'puntos_del_acuerdo',
        'pilares_del_acuerdo'                                             => 'pilares_del_acuerdo',
        'urlproceso'                                                      => 'urlproceso',
        'nombre_representante_legal'                                      => 'nombre_representante_legal',
        'nacionalidad_representante_legal'                                => 'nacionalidad_representante_legal',
        'domicilio_representante_legal'                                   => 'domicilio_representante_legal',
        'tipo_de_identificaci_n_representante_legal'                      => 'tipo_identificacion_representante',
        'identificaci_n_representante_legal'                              => 'identificacion_representante',
        'g_nero_representante_legal'                                      => 'genero_representante_legal',
        'presupuesto_general_de_la_nacion_pgn'                            => 'presupuesto_general_nacion',
        'sistema_general_de_participaciones'                              => 'sistema_general_participaciones',
        'sistema_general_de_regal_as'                                     => 'sistema_general_regalias',
        'recursos_propios_alcald_as_gobernaciones_y_resguardos_ind_genas_'=> 'recursos_propios_territorial',
        'recursos_de_credito'                                             => 'recursos_de_credito',
        'recursos_propios'                                                => 'recursos_propios',
        'ultima_actualizacion'                                            => 'ultima_actualizacion',
        'codigo_entidad'                                                  => 'codigo_entidad',
        'codigo_proveedor'                                                => 'codigo_proveedor',
        'objeto_del_contrato'                                             => 'objeto_del_contrato',
        'duraci_n_del_contrato'                                           => 'duracion_del_contrato',
        'nombre_del_banco'                                                => 'nombre_del_banco',
        'tipo_de_cuenta'                                                  => 'tipo_de_cuenta',
        'n_mero_de_cuenta'                                                => 'numero_de_cuenta',
        'el_contrato_puede_ser_prorrogado'                                => 'contrato_prorrogado',
        'nombre_ordenador_del_gasto'                                      => 'nombre_ordenador_gasto',
        'tipo_de_documento_ordenador_del_gasto'                           => 'tipo_doc_ordenador_gasto',
        'n_mero_de_documento_ordenador_del_gasto'                         => 'numero_doc_ordenador_gasto',
        'nombre_supervisor'                                               => 'nombre_supervisor',
        'tipo_de_documento_supervisor'                                    => 'tipo_doc_supervisor',
        'n_mero_de_documento_supervisor'                                  => 'numero_doc_supervisor',
        'nombre_ordenador_de_pago'                                        => 'nombre_ordenador_pago',
        'tipo_de_documento_ordenador_de_pago'                             => 'tipo_doc_ordenador_pago',
        'n_mero_de_documento_ordenador_de_pago'                           => 'numero_doc_ordenador_pago',
        'documentos_tipo'                                                 => 'documentos_tipo',
        'descripcion_documentos_tipo'                                     => 'descripcion_documentos_tipo',
    ];

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'secop_contracts';
    }

    // ── Getters ────────────────────────────────────────────────
    public function get_table_name(): string
    {
        return $this->table_name;
    }

    public function get_field_mapping(): array
    {
        return self::FIELD_MAPPING;
    }

    // ── Creación de tabla ──────────────────────────────────────
    public function create_table(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nombre_entidad VARCHAR(255) DEFAULT NULL,
            nit_entidad VARCHAR(20) DEFAULT NULL,
            departamento VARCHAR(100) DEFAULT NULL,
            ciudad VARCHAR(100) DEFAULT NULL,
            localizacion VARCHAR(255) DEFAULT NULL,
            orden VARCHAR(50) DEFAULT NULL,
            sector VARCHAR(100) DEFAULT NULL,
            rama VARCHAR(50) DEFAULT NULL,
            entidad_centralizada VARCHAR(50) DEFAULT NULL,
            proceso_de_compra VARCHAR(50) NOT NULL,
            id_contrato VARCHAR(50) NOT NULL,
            referencia_del_contrato VARCHAR(100) NOT NULL,
            estado_contrato VARCHAR(50) DEFAULT NULL,
            codigo_de_categoria_principal VARCHAR(50) DEFAULT NULL,
            descripcion_del_proceso TEXT DEFAULT NULL,
            tipo_de_contrato VARCHAR(100) DEFAULT NULL,
            modalidad_de_contratacion VARCHAR(100) DEFAULT NULL,
            justificacion_modalidad_de VARCHAR(255) DEFAULT NULL,
            fecha_de_firma DATETIME DEFAULT NULL,
            fecha_de_inicio_del_contrato DATETIME DEFAULT NULL,
            fecha_de_fin_del_contrato DATETIME DEFAULT NULL,
            condiciones_de_entrega VARCHAR(100) DEFAULT NULL,
            tipodocproveedor VARCHAR(50) DEFAULT NULL,
            documento_proveedor VARCHAR(50) DEFAULT NULL,
            proveedor_adjudicado VARCHAR(255) DEFAULT NULL,
            es_grupo VARCHAR(10) DEFAULT NULL,
            es_pyme VARCHAR(10) DEFAULT NULL,
            habilita_pago_adelantado VARCHAR(10) DEFAULT NULL,
            liquidacion VARCHAR(10) DEFAULT NULL,
            obligacion_ambiental VARCHAR(10) DEFAULT NULL,
            obligaciones_postconsumo VARCHAR(10) DEFAULT NULL,
            reversion VARCHAR(10) DEFAULT NULL,
            origen_de_los_recursos VARCHAR(100) DEFAULT NULL,
            destino_gasto VARCHAR(100) DEFAULT NULL,
            valor_del_contrato DECIMAL(20,2) DEFAULT 0,
            valor_de_pago_adelantado DECIMAL(20,2) DEFAULT 0,
            valor_facturado DECIMAL(20,2) DEFAULT 0,
            valor_pendiente_de_pago DECIMAL(20,2) DEFAULT 0,
            valor_pagado DECIMAL(20,2) DEFAULT 0,
            valor_amortizado DECIMAL(20,2) DEFAULT 0,
            valor_pendiente_de DECIMAL(20,2) DEFAULT 0,
            valor_pendiente_de_ejecucion DECIMAL(20,2) DEFAULT 0,
            estado_bpin VARCHAR(50) DEFAULT NULL,
            codigo_bpin VARCHAR(50) DEFAULT NULL,
            anno_bpin VARCHAR(10) DEFAULT NULL,
            saldo_cdp DECIMAL(20,2) DEFAULT 0,
            saldo_vigencia DECIMAL(20,2) DEFAULT 0,
            espostconflicto VARCHAR(10) DEFAULT NULL,
            dias_adicionados INT DEFAULT 0,
            puntos_del_acuerdo VARCHAR(100) DEFAULT NULL,
            pilares_del_acuerdo VARCHAR(100) DEFAULT NULL,
            urlproceso VARCHAR(500) DEFAULT NULL,
            nombre_representante_legal VARCHAR(255) DEFAULT NULL,
            nacionalidad_representante_legal VARCHAR(100) DEFAULT NULL,
            tipo_identificacion_representante VARCHAR(50) DEFAULT NULL,
            identificacion_representante VARCHAR(50) DEFAULT NULL,
            genero_representante_legal VARCHAR(20) DEFAULT NULL,
            presupuesto_general_nacion DECIMAL(20,2) DEFAULT 0,
            sistema_general_participaciones DECIMAL(20,2) DEFAULT 0,
            sistema_general_regalias DECIMAL(20,2) DEFAULT 0,
            recursos_propios_territorial DECIMAL(20,2) DEFAULT 0,
            recursos_de_credito DECIMAL(20,2) DEFAULT 0,
            recursos_propios DECIMAL(20,2) DEFAULT 0,
            ultima_actualizacion DATETIME DEFAULT NULL,
            codigo_entidad VARCHAR(50) DEFAULT NULL,
            codigo_proveedor VARCHAR(50) DEFAULT NULL,
            objeto_del_contrato TEXT DEFAULT NULL,
            duracion_del_contrato VARCHAR(100) DEFAULT NULL,
            nombre_del_banco VARCHAR(255) DEFAULT NULL,
            tipo_de_cuenta VARCHAR(50) DEFAULT NULL,
            numero_de_cuenta VARCHAR(50) DEFAULT NULL,
            contrato_prorrogado VARCHAR(10) DEFAULT NULL,
            nombre_ordenador_gasto VARCHAR(255) DEFAULT NULL,
            tipo_doc_ordenador_gasto VARCHAR(50) DEFAULT NULL,
            numero_doc_ordenador_gasto VARCHAR(50) DEFAULT NULL,
            nombre_supervisor VARCHAR(255) DEFAULT NULL,
            tipo_doc_supervisor VARCHAR(50) DEFAULT NULL,
            numero_doc_supervisor VARCHAR(50) DEFAULT NULL,
            nombre_ordenador_pago VARCHAR(255) DEFAULT NULL,
            tipo_doc_ordenador_pago VARCHAR(50) DEFAULT NULL,
            numero_doc_ordenador_pago VARCHAR(50) DEFAULT NULL,
            domicilio_representante_legal VARCHAR(255) DEFAULT NULL,
            documentos_tipo VARCHAR(255) DEFAULT NULL,
            descripcion_documentos_tipo TEXT DEFAULT NULL,
            fecha_importacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_contract (id_contrato, referencia_del_contrato),
            KEY idx_proceso_compra (proceso_de_compra),
            KEY idx_nit_entidad (nit_entidad),
            KEY idx_estado_contrato (estado_contrato),
            KEY idx_fecha_firma (fecha_de_firma),
            KEY idx_proveedor (documento_proveedor),
            KEY idx_tipo_contrato (tipo_de_contrato),
            KEY idx_modalidad (modalidad_de_contratacion),
            KEY idx_anno_bpin (anno_bpin)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(SECOP_SUITE_PREFIX . 'db_version', SECOP_SUITE_DB_VERSION);
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
        return (float) $wpdb->get_var("SELECT COALESCE(SUM(valor_del_contrato), 0) FROM {$this->table_name}");
    }

    // ── Procesamiento de registros ─────────────────────────────
    public function process_record(array $item): ?string
    {
        global $wpdb;

        if (empty($item['id_contrato']) || empty($item['referencia_del_contrato'])) {
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

        // UPSERT con INSERT ... ON DUPLICATE KEY UPDATE (una sola query)
        $columns      = array_keys($data);
        $placeholders = implode(', ', $formats);
        $col_list     = '`' . implode('`, `', $columns) . '`';

        $update_parts = [];
        foreach ($columns as $col) {
            if ($col !== 'id_contrato' && $col !== 'referencia_del_contrato') {
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

        // INSERT ON DUPLICATE: 1 = inserted, 2 = updated, 0 = no change
        return $result === 1 ? 'inserted' : 'updated';
    }

    /**
     * Extraer valor del item de la API, manejar objetos anidados.
     */
    private function extract_value(array $item, string $field): mixed
    {
        if ($field === 'urlproceso') {
            return $item['urlproceso']['url']
                ?? (is_string($item['urlproceso'] ?? null) ? $item['urlproceso'] : null);
        }
        return $item[$field] ?? null;
    }

    /**
     * Sanitizar valor según el tipo de campo.
     */
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

    /**
     * Obtener placeholder de formato para wpdb.
     */
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

    /**
     * Eliminar todos los registros de un rango de fechas (para reimportación limpia).
     *
     * @return int Número de registros eliminados
     */
    public function delete_by_date_range(string $fecha_inicio, string $fecha_fin): int
    {
        global $wpdb;

        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE fecha_de_firma >= %s AND fecha_de_firma <= %s",
            $fecha_inicio . ' 00:00:00',
            $fecha_fin . ' 23:59:59'
        ));

        return $result !== false ? $result : 0;
    }

    // ── Validación de columnas (seguridad para el visualizador) ─
    /**
     * Obtener columnas reales de la tabla.
     * Retorna array asociativo [nombre => tipo].
     */
    public function get_table_columns(string $table): array
    {
        // Cache en object cache para evitar DESCRIBE repetidos
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

        wp_cache_set($cache_key, $columns, 'secop_suite', 300); // 5 minutos
        return $columns;
    }

    /**
     * Verificar que un nombre de columna existe realmente en la tabla.
     */
    public function is_valid_column(string $table, string $column): bool
    {
        $columns = $this->get_table_columns($table);
        return isset($columns[$column]);
    }

    /**
     * Obtener tablas de datos disponibles para el visualizador.
     */
    public function get_available_tables(): array
    {
        $cached = wp_cache_get('secop_available_tables', 'secop_suite');
        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;
        $tables = [];

        // Tabla principal del plugin
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_name)) === $this->table_name) {
            $tables[$this->table_name] = 'SECOP Contracts (Plugin Import)';
        }

        // Tabla legacy
        $legacy = $wpdb->prefix . 'wp_data_contracting';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $legacy)) === $legacy) {
            $tables[$legacy] = 'Data Contracting (Legacy)';
        }

        // Otras tablas de datos personalizadas
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
