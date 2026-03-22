# SECOP Suite — Plugin WordPress

Plugin integral para la **importación, almacenamiento, visualización interactiva y filtrado** de datos contractuales del SECOP (Sistema Electrónico de Contratación Pública) de Colombia.

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
- **11 tipos de gráficas**: Barras, Líneas, Área, Pie, Donut, Treemap, **Árbol (Tree)**, **Burbujas (Pack)**, **Red (Network)**, Barras Apiladas, Barras Agrupadas
- Motor de renderizado **D3plus** (D3.js v5) + **d3plus-hierarchy**
- **Custom Post Type** para gestión de gráficas
- **Shortcodes** flexibles: `[secop_chart id="123"]` y `[sdv_chart id="123"]` (compatibilidad)
- Agrupación temporal de fechas: año, mes, trimestre, semana, nombre de mes
- Filtros dinámicos con operadores SQL (=, !=, >, <, >=, <=, LIKE)
- Funciones de agregación: SUM, COUNT, AVG, MAX, MIN
- Formato de números colombiano (1.000.000, Millones, MMll)
- Barra de herramientas: Compartir (redes sociales), Ver datos, Exportar imagen, Descargar CSV
- Vista previa en el editor de WordPress

### Módulo de Filtros (Nuevo v4.1.0)
- **Custom Post Type** para gestión de filtros de búsqueda
- **Shortcode**: `[secop_filter id="123"]`
- **4 tipos de campos**: Texto (input), Lista desplegable (select), Rango (desde-hasta), Opciones múltiples (checkbox)
- Configuración de operadores por campo (=, LIKE, !=, >, <, >=, <=)
- Selección de columnas visibles en resultados
- Paginación de resultados configurable
- **Enlace directo al proceso SECOP** (urlproceso) con icono en cada fila de resultado, abre en nueva ventana
- Carga dinámica de opciones para select y checkbox desde la base de datos
- Rate limiting por IP (60 req/min)
- Validación de tablas y columnas contra whitelist

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

## Changelog

### v4.1.2 (2026-03-22)

**Correcciones críticas:**
- **Fix d3plus.BarChart not a constructor**: CDN URL actualizada a `d3plus@2/build/d3plus.full.min.js` (UMD bundle completo). Eliminada dependencia separada de `d3plus-hierarchy` (ya incluida en el bundle full).
- **Renderizado robusto**: Nueva función `getD3PlusClass()` con fallback que busca constructores en múltiples namespaces. Manejo de errores con try/catch en `renderChart()`.
- **Fix heat map siempre rojo**: Reescrita `updateFieldRequirements()` — ahora solo muestra `*` rojo en campos requeridos según el tipo de gráfica seleccionado, sin alterar borders ni backgrounds.

**Mejoras UX:**
- **Multi-Y opcional**: Ahora se activa con checkbox "Habilitar múltiples campos Y". El contenido se oculta/muestra con toggle. Al desactivar, los campos se limpian.
- **Guía en acordeón**: "Guía de Variables Recomendadas" oculta por defecto, se expande al hacer clic con animación.
- **Etiquetas simplificadas**: Badges REQUERIDO/RECOMENDADO/NO APLICA reemplazados por simple `*` rojo en campos requeridos.

### v4.1.1 (2026-03-21)

**Correcciones críticas:**
- **Fix importación de datos**: Corregido bug donde el proceso de importación en background nunca se ejecutaba porque el transient `import_running` bloqueaba `run()` al ser establecido previamente por `ajax_start_import`. Ahora `run_background()` define `SECOP_SUITE_FORCE_IMPORT` para bypass correcto.
- **Fix Barras Apiladas**: Cambiado de `StackedArea` (incorrecto) a `BarChart` con `.stacked(true)` para renderizado correcto de barras apiladas.
- **Fix Barras Agrupadas**: Cambiado groupBy de `['x', 'group']` a `'group'` con `.stacked(false)` para agrupación correcta.

**Mejoras en configuración de gráficas:**
- **Campos dinámicos por tipo de gráfico**: Al seleccionar un tipo de gráfica, los campos se marcan con badges de color:
  - **REQUERIDO** (rojo): Campo obligatorio para este tipo de gráfica
  - **RECOMENDADO** (azul): Campo que mejora la visualización
  - **NO APLICA** (gris): Campo irrelevante para este tipo
- Nota de advertencia visible cuando Barras Apiladas/Agrupadas, Árbol o Red requieren "Agrupar Por"
- Preview admin: Añadidos casos faltantes (stacked_bar, grouped_bar, area, donut)

### v4.1.0 (2026-03-21)

**Nuevas funcionalidades:**
- **Módulo de Filtros**: Nuevo submenu "Filtros" con CPT dedicado para crear filtros de búsqueda insertables via shortcode `[secop_filter id="123"]`
  - Campos configurables: input, select, rango y checkbox
  - Resultados en lista con columnas personalizables
  - Icono de enlace al proceso SECOP (urlproceso) al final de cada fila, abre en nueva ventana
  - Paginación, ordenamiento y rate limiting
- **3 nuevos tipos de gráficas**: Árbol (Tree), Burbujas (Pack), Red (Network) — basados en [d3plus-hierarchy](https://github.com/d3plus/d3plus-hierarchy)
- Soporte para librería **d3plus-hierarchy** vía CDN con fallback local

**Mejoras:**
- Actualizado el selector visual de tipos de gráficas con iconos para los nuevos tipos
- Vista previa en admin para los nuevos tipos de gráficas
- Documentación actualizada

### v4.0.1
- Correcciones menores de estabilidad

### v4.0.0
- Unificación de plugins Elements API Data Upload v3.0.0 + SECOP Data Visualizer v1.0.0
- Mejoras masivas de seguridad (ver tabla abajo)
- PHP 8.1+ con strict types
- Arquitectura Singleton + Dependency Injection

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

## Uso de Shortcodes

### Gráficas
```
[secop_chart id="123"]
[secop_chart id="123" height="500" class="mi-grafica"]
[sdv_chart id="123"]  <!-- compatibilidad -->
```

### Filtros de Búsqueda
```
[secop_filter id="456"]
[secop_filter id="456" class="mi-filtro"]
```

## Estructura del Plugin

```
secop-suite/
├── secop-suite.php            # Archivo principal (autoload + clase Plugin)
├── uninstall.php              # Limpieza al desinstalar
├── includes/
│   ├── class-database.php     # Tabla, mapeo de campos, validación de columnas
│   ├── class-importer.php     # Importación SECOP con AJAX y cron
│   ├── class-visualizer.php   # CPT gráficas, shortcodes, datos (segurizado)
│   ├── class-filter.php       # CPT filtros, shortcodes, búsqueda AJAX
│   ├── class-rest-api.php     # Endpoints REST unificados
│   ├── class-cli.php          # Comandos WP-CLI
│   ├── class-logger.php       # Sistema de logs
│   └── class-updater.php      # Auto-actualización desde GitHub
├── templates/
│   ├── admin/
│   │   ├── import-page.php    # Dashboard de importación
│   │   ├── records-page.php   # Visor de contratos
│   │   ├── logs-page.php      # Logs e info del sistema
│   │   ├── chart-config.php   # Configuración de gráficas (metabox)
│   │   └── filter-config.php  # Configuración de filtros (metabox)
│   └── frontend/
│       ├── chart.php          # Renderizado público de gráficas
│       └── filter.php         # Renderizado público de filtros
├── assets/
│   ├── css/
│   │   ├── admin.css          # Estilos admin (importación + visualizador + filtros)
│   │   └── frontend.css       # Estilos frontend (gráficas + filtros)
│   └── js/
│       ├── admin-import.js    # Lógica de importación AJAX
│       ├── admin-charts.js    # Configurador de gráficas
│       ├── admin-filters.js   # Configurador de filtros
│       ├── frontend.js        # Motor de renderizado D3plus
│       └── frontend-filters.js # Motor de búsqueda y resultados
└── logs/
    └── import.log             # Registro de importaciones
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
