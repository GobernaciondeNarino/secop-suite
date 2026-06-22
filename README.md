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
- **Enlace directo al proceso SECOP** (url_contrato) con icono en cada fila de resultado, abre en nueva ventana
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

### v5.2.2 — Catálogo de Contratación más rápido
- El catálogo ya no renderiza gráficas ni análisis (carga instantánea): cada tarjeta muestra solo título, descripción y los shortcodes copiables, sin ejecutar `build_dataset()` ni `do_shortcode()` al cargar la página.
- La vista previa y los cuatro análisis (descripción, cualitativo, cuantitativo, predicción) se ven solo en el editor individual de la card, cargados en vivo por AJAX (`secop_dep_preview`), sin consultas pesadas al renderizar el metabox.

### v5.2.0 — Nuevo origen `vista_secop_sysman`, `valor_contrato` como valor principal y fix de rendimiento
- **Nuevo origen de datos**: el módulo de Contratación usa la vista `{prefix}vista_secop_sysman` (creada por el usuario) como única fuente. `get_view_name()` y la definición de `create_view()` se alinean exactamente con esa vista para que una instalación nueva la reproduzca.
- **`valor_contrato` como valor principal**: es la primera métrica por defecto del módulo; las agregaciones de análisis y las gráficas usan `SUM(valor_contrato)`. (Caveat documentado: un contrato puede aparecer en varias filas presupuestales, por lo que `SUM(valor_contrato)` sobrecuenta levemente los contratos multi-comprobante.)
- **Dimensiones limitadas a las columnas reales de la vista**: dependencia, tipo de contrato, modalidad de contratación, contratista y mensual. Se eliminaron estado, tipo de documento, programa, rubro, fuente y ejecución (sus columnas no existen en la vista).
- **Rendimiento (fix severo)**: se eliminaron los conteos con JOIN pesado del aviso que corría en **cada carga del admin** (causaban lentitud severa/cuelgues). El aviso solo hace comprobaciones baratas (`SHOW TABLES`). Además la caché de las lecturas de la vista sube de 10 a **30 minutos**.
- **Referencias de columnas**: `objeto_del_proceso` → `objeto_a_contratar` en todas las consultas/renderizados de la vista. La actualización elimina la vista huérfana antigua `dat_seguimiento_dependencias`.

### v5.1.9 — Click-to-drill en gráficas de Contratación
- **Drill por click**: el click en una barra/sector/elemento de una gráfica de Contratación abre un popup con los contratos asociados a esa categoría (vigencia actual, deduplicados por contrato, hasta 200). Se activa por gráfica con `drill="on"` en `[secop_dep_chart]` (también `1`/`true`/`yes`). Reutiliza el motor de gráficas del Visualizer (handler `chart.on('click')`).
- **Capa de datos genérica**: `contracts_by_value(column, value)` consulta contratos por cualquier dimensión, con la columna validada contra la lista blanca `DIM_COLUMN` y el valor parametrizado (`$wpdb->prepare`).
- **Endpoint AJAX seguro**: `secop_dep_drill` (nonce `secop_dep_frontend` + rate-limit por IP). El modal se construye con `.text()` por celda y enlaza al `url_contrato` solo si tiene esquema `http(s)`.
- `[secop_chart]` y `[secop_dep_chart]` sin `drill` no cambian su comportamiento.

### v5.1.8 — Parámetros de personalización en `[secop_dep_chart]`
- **Configuración desde el shortcode**: `[secop_dep_chart]` ahora acepta parámetros de personalización (`metric`, `order`, `orderdir`, `limit`, `colors`, `legend`, `dimension`, `tipo`, `dependencia`, `height`) que se resuelven a una **card de respaldo automática**, reutilizando el motor del Visualizer sin cambios. Es posible configurar una gráfica completa sin crear una card manualmente.
- **Card de respaldo por hash de configuración**: cada combinación de parámetros se mapea (find-or-create) a un único post `secop_dep_card` identificado por `_secop_cfg_hash`, evitando posts duplicados. `[secop_dep_chart card="N"]` y `preset="x"` sin overrides siguen renderizando el post canónico directamente (sin crear posts auxiliares).
- **Leyenda configurable**: `card_to_chart_config()` respeta `show_legend` cuando la card lo define explícitamente.

### v5.1.6 — Vista previa EN VIVO del editor de Contratación
- **Vista previa en vivo**: el metabox de vista previa de la card ya no renderiza el shortcode guardado; ahora reacciona al instante a los cambios del formulario (tipo de gráfica, colores, dimensión, métrica, orden, dependencia y límite) mediante un endpoint AJAX admin (`secop_dep_preview`, protegido con nonce + capacidad `edit_posts`). La gráfica se redibuja reutilizando el motor del Visualizer expuesto como `window.SSChartRender` en `frontend.js` (sin cambiar el comportamiento de `[secop_chart]`/`[secop_dep_chart]`).
- **Sección de Datos**: tabla con las filas devueltas por la consulta (`x_value`, `y_value`, `group_value`), construida con `.text()` por celda (sin `innerHTML` de valores de BD).
- **Sección de Consulta SQL generada**: muestra el SQL realmente ejecutado, obtenido SIN caché vía `Visualizer::get_chart_data_with_sql()` (`$wpdb->last_query`), reutilizando exactamente el mismo `build_chart_query`.
- **Campo «Límite de filas»**: nuevo control `dep_limit` (persistido en la config de la card) para acotar el número de categorías mostradas.

### v5.1.5 — Editor avanzado de Contratación (Fase 2)
- **Dimensiones ampliadas**: además de dependencia, tipo de contrato y modalidad, el módulo de Contratación ahora permite agrupar por **estado del proceso**, **tipo de documento del proveedor**, **programa presupuestal**, **rubro presupuestal** y **contratista**. El desplegable de dimensión muestra etiquetas amigables.
- **Métricas configurables**: nuevo selector de métrica con **Valor ejecutado** (`SUM(valordebito)`), **Saldo por ejecutar** (`SUM(saldoporejecutaresp)`), **Nº de contratos** (`COUNT(DISTINCT numero_del_contrato)`) y **Nº de registros** (`COUNT(numero_de_proceso)`). `valor_contrato` se omite a propósito porque se duplica por fila.
- **Ordenamiento de barras**: opción de ordenar por **valor** (métrica agregada) o por **etiqueta**, en dirección ascendente o descendente; la dimensión «Mensual» mantiene siempre el orden cronológico.
- **Motor de gráficas (Visualizer)**: dos extensiones seguras y retrocompatibles — soporte de la agregación **`COUNT_DISTINCT`** (emitida como `COUNT(DISTINCT col)`) y ordenamiento por el valor agregado mediante el sentinela **`order_by = '__value__'`** (`ORDER BY y_value`). El comportamiento de `[secop_chart]` no cambia salvo que se activen explícitamente.

### v5.1.4 — Editor avanzado de Contratación (Fase 1)
- **Vista previa del editor sin textos de análisis**: el metabox de vista previa de la tarjeta ya no imprime los párrafos `Stats::analisis_*`; ahora renderiza únicamente la gráfica (vía `[secop_dep_chart]`) cargando el stack de gráficas del frontend en la pantalla de edición. Si la tarjeta no está configurada, muestra una nota para guardarla.
- **Paleta de 8 colores editable**: `card_to_chart_config` aplica una paleta por defecto de 8 colores (`#844e80, #ff7300, #ffc53b, #3eba6a, #0080c3, #e74c3c, #9b59b6, #1abc9c`) y un nuevo campo «Colores» permite sobrescribirla con una lista de hex `#rrggbb` separados por comas; si se deja vacía, se usa la paleta por defecto.
- **Dependencia como lista desplegable**: el campo «Dependencia» pasa de texto libre a un `<select>` poblado con las dependencias de la vigencia actual (`list_dependencies`), con opción «— Todas las dependencias —»; si aún no hay datos, conserva el campo de texto como respaldo.

### v5.1.3 — Rendimiento y limpieza
- **Caché de consultas del VIEW** (TTL 10 min) en los métodos de lectura del módulo de seguimiento (`group_by_dimension`, `monthly_series`, `build_dataset`, `contracts_by_dependency`, `list_dependencies`) y en el endpoint REST `/consulta` — evita golpear el JOIN de 3 tablas en cada render. Los exports streaming CSV/TXT siguen sin caché (ya tienen rate-limit).
- **`get_preset_post_id` sin escrituras por render**: la config sólo se reescribe cuando realmente cambió; creación del post de respaldo protegida con un lock transitorio para evitar duplicados bajo concurrencia.
- **Fix de fuga de manejadores en gráficas interactivas** (`frontend.js`): el binding de `keyup` (Escape) en `document` ahora usa un namespace por instancia (`keyup.ssc<uniqueId>`) y los botones de la toolbar usan `click.ssc`, eliminando handlers apilados al reinicializar `SSChartManager` desde `[secop_seguimiento]`.
- **Eliminación de código muerto**: plantilla huérfana `templates/frontend/dep-chart.php` y el handler AJAX sin uso `secop_dep_chart_data` (`ajax_chart_data()` + sus dos hooks).

### v5.1.2 — Seguridad: exports públicos, CSV anti-fórmula, headers, nonce
- **Rate-limit + streaming paginado en exports públicos** (`/export/csv` y `/export/txt`): comparten el limitador por IP de los endpoints `/consulta` (max 30 req/min); la tabla completa ya no se carga en memoria — se emite en lotes de 2 000 filas vía `LIMIT/OFFSET` (anti-DoS / memory exhaustion).
- **CSV anti-fórmula** (`csv_safe`) aplicado a cabeceras y celdas en `get_chart_csv()` y en el nuevo `export_csv()` paginado (mitiga inyección de fórmulas Excel/LibreOffice).
- **Header `X-Content-Type-Options` corregido** en `export_csv()` y `export_txt()`: se usaba la forma incorrecta `header('X-Content-Type-Options', 'nosniff')` (segundo argumento ignorado) → cambiado a `header('X-Content-Type-Options: nosniff')`.
- **Nonce unslash/sanitize** antes de `wp_verify_nonce()` en `save_filter_meta()` y `save_chart_meta()`: se aplica `wp_unslash()` + `sanitize_text_field()` para cumplir las buenas prácticas de WordPress y evitar fallos con magic quotes activas.

### v5.1.0 — Módulo Seguimiento de Dependencias y Datos Abiertos
- VIEW `dat_seguimiento_dependencias` (contratos × ejecución presupuestal por dependencia).
- Gráficas prediseñadas + análisis autogenerados (regresión + R²), vigencia actual.
- Shortcodes: `[secop_seguimiento]`, `[secop_dep_chart]`, `[secop_dep_analisis]`, `[secop_dep_contratos]`, `[secop_consulta]`.
- Submenús admin: "Seguimiento Dependencias" y "Datos Abiertos".

### v5.0.0 (2026-03-23) — ⚠ BREAKING: Nueva API SECOP

**Migración a nueva API del SECOP (rpmr-utcd.json):**
La API oficial del SECOP cambió de estructura. Esta versión migra completamente el plugin al nuevo endpoint y schema.

**Nueva URL de API:**
`https://www.datos.gov.co/resource/rpmr-utcd.json`

**Mapeo de campos (antiguo → nuevo):**
| Antiguo (jbjy-vk9h) | Nuevo (rpmr-utcd) |
|---|---|
| `nombre_entidad` | `nombre_de_la_entidad` |
| `nit_entidad` | `nit_de_la_entidad` |
| `departamento` | `departamento_entidad` |
| `ciudad` | `municipio_entidad` |
| `estado_contrato` | `estado_del_proceso` |
| `modalidad_de_contratacion` | `modalidad_de_contratacion` (mismo) |
| `descripcion_del_proceso` | `objeto_del_proceso` |
| `fecha_de_firma` | `fecha_de_firma_del_contrato` |
| `fecha_de_inicio_del_contrato` | `fecha_inicio_ejecucion` |
| `fecha_de_fin_del_contrato` | `fecha_fin_ejecucion` |
| `id_contrato` + `referencia_del_contrato` | `numero_del_contrato` (unique key) |
| `proceso_de_compra` | `numero_de_proceso` |
| `valor_del_contrato` | `valor_contrato` |
| `proveedor_adjudicado` | `nom_raz_social_contratista` |
| `urlproceso` | `url_contrato` |
| `tipodocproveedor` | `tipo_documento_proveedor` |
| `anno_bpin` | `YEAR(fecha_de_firma_del_contrato)` |

**Nuevos campos:**
- `nivel_entidad` (Nacional/Territorial)
- `objeto_a_contratar`
- `origen` (SECOPII / SECOPI)

**Campos removidos** (no existen en la nueva API):
- `valor_pagado`, `valor_facturado`, `valor_pendiente_de_pago`, etc.
- `anno_bpin`, `codigo_bpin`, `estado_bpin`
- Datos de representante legal
- Datos de presupuesto (PGN, SGP, SGR, etc.)
- `es_pyme`, `es_grupo`, `liquidacion`, `obligacion_ambiental`, etc.

**Migración automática:**
- Al actualizar desde v4.x, el plugin detecta la versión y ejecuta `migrate_to_new_schema()`:
  - Drop de la tabla antigua
  - Creación de la nueva tabla con el schema correcto
  - Reset de contadores de importación
  - Actualización automática de la URL de API
- **Los datos antiguos se eliminan**. Después de actualizar, ejecutar una nueva importación desde el dashboard.

**Módulos actualizados:**
- **Database**: Nuevo schema con 22 campos + índices optimizados
- **Importer**: Nueva URL + WHERE clause con campos actualizados
- **Visualizer**: Todos los ejemplos y placeholders actualizados
- **Filter**: Default URL field → `url_contrato`, default order → `fecha_de_firma_del_contrato`
- **REST API**: Todos los endpoints con nuevos nombres de columna
- **CLI**: Comando `stats` actualizado
- **Dashboard**: Queries de año usan `YEAR(fecha_de_firma_del_contrato)`
- **Records Page**: Filtros y columnas de tabla con nuevos campos
- **Admin Import**: Modal de detalles con nueva estructura

### v4.2.0 (2026-03-22)

**Nuevas funcionalidades:**
- **Limpiar Tabla**: Botón "Limpiar Tabla" en el módulo de importación con confirmación de doble paso (muestra total de registros antes de eliminar), TRUNCATE seguro con verificación de importación no activa.

**Correcciones:**
- **Fix filtros sin columnas**: Corregido nonce mismatch — el módulo de filtros usaba `secop_suite_filter_admin` pero el handler AJAX esperaba `secop_suite_chart_admin`. Ahora acepta ambos nonces.
- **Fix timeline interactiva**: Verificación segura de `.time()` y `.timeline()` con `typeof` antes de invocar, para evitar errores en tipos de gráfica que no soportan timeline.

**Limpieza:**
- `uninstall.php`: Ahora también elimina CPTs de filtros (`secop_filter`) y transients de cache de gráficas.
- CSS: Corregida referencia `.ss-guide-title` → `.ss-guide-toggle` en documentación.
- Verificación completa de transients, nonces, PHP syntax y DB cleanup.

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
  - Icono de enlace al proceso SECOP (url_contrato) al final de cada fila, abre en nueva ventana
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

### Seguimiento de Dependencias (v5.1.0)

#### Landing Interactiva
```
[secop_seguimiento dependencia="" dimensiones="dependencia,modalidad,fuente"]
```
Selector interactivo de dependencia que re-renderiza las gráficas y carga la tabla de contratos de esa dependencia.

#### Gráficas Prediseñadas
```
[secop_dep_chart card="ID" dimension="" tipo="" dependencia="" height="400"]
```
Gráfica prediseñada D3plus para análisis por dependencia.

**Parámetros:**
- `card` (string): ID de la gráfica prediseñada (hereda configuración)
- `dimension` (string): `dependencia` | `tipo_contrato` | `modalidad` | `fuente` | `mensual` | `ejecucion`
- `tipo` (string): 
  - Dimensiones categóricas: `bar` | `stacked_bar` | `treemap` | `pie` | `donut`
  - Dimensión mensual: `line` | `area`
  - Dimensión ejecución: `donut` | `bar`
- `dependencia` (string): Filtrar por dependencia específica
- `height` (int): Alto del contenedor en píxeles (default: 400)

#### Análisis Autogenerado
```
[secop_dep_analisis card="ID" tipo="descripcion"]
```
Párrafo de análisis autogenerado (≤564 caracteres) con regresión lineal y estadísticas.

**Parámetros:**
- `card` (string): ID de la gráfica (requerido para contexto)
- `tipo` (string): 
  - `descripcion` — Resumen de datos
  - `cualitativo` — Análisis descriptivo
  - `cuantitativo` — Estadísticas y métricas
  - `prediccion` — Regresión lineal con R² e intervalo de incertidumbre (serie mensual)

#### Tabla de Contratos
```
[secop_dep_contratos dependencia="" per_page="50"]
```
Tabla interactiva de contratos de una dependencia (Nº de contrato con enlace, proveedor, fechas, valor, descripción). Vigencia actual.

**Parámetros:**
- `dependencia` (string): Nombre o ID de dependencia (requerido)
- `per_page` (int): Registros por página (default: 50)

#### Consulta de Datos Abiertos
```
[secop_consulta formato="tabla"]
```
Datos abiertos de seguimiento por dependencia (vigencia actual).

**Parámetros:**
- `formato` (string): `tabla` | `csv` | `txt` | `json` (default: tabla)

**Nota:** El módulo funciona únicamente con la vigencia actual (`anio = YEAR(CURDATE())`) y se auto-actualiza automáticamente cuando cambia el año. Requiere tablas Sysman (`sysman_auxiliar_cuentas`, `sysman_plan_presupuestal`) en la misma base de datos. Usa la vista SQL `{prefix}vista_secop_sysman` (creada por el usuario; el plugin la crea automáticamente si no existe y hay tablas Sysman).

## API REST — Módulo Seguimiento de Dependencias (v5.1.0)

Endpoints públicos de consulta de datos de seguimiento (vigencia actual). **Rate limit:** 30 req/min por IP.

```
GET /wp-json/secop-suite/v1/consulta?page=1&per_page=100
```
JSON paginado de la consulta de seguimiento.

**Parámetros:**
- `page` (int): Página (default: 1)
- `per_page` (int): Registros por página (default: 100, máximo: 500)

**Respuesta:**
```json
{
  "data": [...],
  "total": 1250,
  "page": 1,
  "per_page": 100,
  "pages": 13
}
```

```
GET /wp-json/secop-suite/v1/consulta/csv
```
Descarga CSV de la vigencia actual.

```
GET /wp-json/secop-suite/v1/consulta/txt
```
Descarga TXT delimitada por tabulación de la vigencia actual.

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
