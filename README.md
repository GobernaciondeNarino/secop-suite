# SECOP Suite — Plugin WordPress

Plugin integral para la **importación, almacenamiento y visualización interactiva** de datos contractuales del SECOP (Sistema Electrónico de Contratación Pública) de Colombia.

Desarrollado para la **Gobernación de Nariño** — Secretaría de TIC, Innovación y Gobierno Abierto.

---

## Características

### Módulo de Importación
- Importación automatizada desde la API de [datos.gov.co](https://www.datos.gov.co/resource/jbjy-vk9h.json) (protocolo SODA)
- Filtrado por NIT de entidad y rango de fechas
- Procesamiento por lotes (1,000 registros/batch) con reintentos automáticos
- Barra de progreso en tiempo real vía AJAX
- Cancelación de importación en curso
- Cron programable: diario, semanal o mensual
- Sistema UPSERT (inserta nuevos, actualiza existentes)
- Más de 60 campos contractuales mapeados

### Módulo de Visualización
- **8 tipos de gráficas**: Barras, Líneas, Área, Pie, Donut, Treemap, Barras Apiladas, Barras Agrupadas
- Motor de renderizado **D3plus** (D3.js v5)
- **Custom Post Type** para gestión de gráficas
- **Shortcodes** flexibles: `[secop_chart id="123"]` y `[sdv_chart id="123"]` (compatibilidad)
- Agrupación temporal de fechas: año, mes, trimestre, semana, nombre de mes
- Filtros dinámicos con operadores SQL (=, !=, >, <, >=, <=, LIKE)
- Funciones de agregación: SUM, COUNT, AVG, MAX, MIN
- Formato de números colombiano (1.000.000, Millones, MMll)
- Barra de herramientas: Compartir (redes sociales), Ver datos, Exportar imagen, Descargar CSV
- Vista previa en el editor de WordPress

### API REST Unificada
```
GET /wp-json/secop-suite/v1/contracts        # Lista con paginación y filtros
GET /wp-json/secop-suite/v1/contracts/{id}   # Detalle de contrato
GET /wp-json/secop-suite/v1/stats            # Estadísticas generales
GET /wp-json/secop-suite/v1/chart/{id}/data  # Datos de gráfica
GET /wp-json/secop-suite/v1/chart/{id}/csv   # Descargar CSV
```

### Comandos WP-CLI
```bash
wp secop import                                    # Importar datos
wp secop import --nit=800103923 --desde=2020-01-01 # Con parámetros
wp secop stats                                     # Estadísticas
wp secop truncate --yes                            # Limpiar datos
```

### Funcionalidades Administrativas
- Visor de contratos con filtros (búsqueda, año, estado) y paginación
- Modal de detalle de contrato con información completa
- Sistema de logs con información del sistema
- Panel de información de API REST y comandos CLI

---

## Mejoras de Seguridad (v4.0.0)

| Vulnerabilidad | Plugin Original | SECOP Suite v4 |
|---|---|---|
| SQL Injection en custom queries | `$wpdb->get_results($sql)` directo | Validación: solo SELECT, palabras prohibidas (DROP, DELETE, ALTER, etc.) |
| SQL Injection en nombres de columna | Sin validación | Validación contra `DESCRIBE` real de la tabla |
| SQL Injection en nombres de tabla | Sin validación | Whitelist de tablas disponibles |
| SQL Injection en operadores de filtro | Parcialmente validado | Whitelist estricta de operadores |
| SQL Injection en funciones de agregación | Sin validación | Whitelist: SUM, COUNT, AVG, MAX, MIN |
| Valores LIKE sin escapar | Directo | `$wpdb->esc_like()` aplicado |

---

## Requisitos

- WordPress 6.0+
- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+

## Instalación

1. Descargar o clonar este repositorio
2. Subir la carpeta `secop-suite` a `/wp-content/plugins/`
3. Activar el plugin desde el panel de WordPress
4. Ir a **SECOP Suite** en el menú lateral
5. Configurar la URL de la API y el NIT de la entidad
6. Ejecutar la primera importación

## Estructura del Plugin

```
secop-suite/
├── secop-suite.php          # Archivo principal (autoload + clase Plugin)
├── uninstall.php            # Limpieza al desinstalar
├── includes/
│   ├── class-database.php   # Tabla, mapeo de campos, validación de columnas
│   ├── class-importer.php   # Importación SECOP con AJAX y cron
│   ├── class-visualizer.php # CPT, shortcodes, datos de gráficas (segurizado)
│   ├── class-rest-api.php   # Endpoints REST unificados
│   ├── class-cli.php        # Comandos WP-CLI
│   └── class-logger.php     # Sistema de logs
├── templates/
│   ├── admin/
│   │   ├── import-page.php  # Dashboard de importación
│   │   ├── records-page.php # Visor de contratos
│   │   ├── logs-page.php    # Logs e info del sistema
│   │   └── chart-config.php # Configuración de gráficas (metabox)
│   └── frontend/
│       └── chart.php        # Renderizado público de gráficas
├── assets/
│   ├── css/
│   │   ├── admin.css        # Estilos admin (importación + visualizador)
│   │   └── frontend.css     # Estilos frontend de gráficas
│   └── js/
│       ├── admin-import.js  # Lógica de importación AJAX
│       ├── admin-charts.js  # Configurador de gráficas
│       └── frontend.js      # Motor de renderizado D3plus
└── logs/
    └── import.log           # Registro de importaciones
```

## Origen

Este plugin unifica dos plugins independientes:
- **Elements API Data Upload** v3.0.0 — Importador de datos SECOP
- **SECOP Data Visualizer** v1.0.0 — Visualizador de gráficas D3plus

## Autor

**Jonnathan Bucheli Galindo**
Secretaría de TIC, Innovación y Gobierno Abierto
Gobernación de Nariño — Colombia

## Licencia

GPL v2 or later — [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
