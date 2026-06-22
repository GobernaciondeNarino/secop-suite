<?php if (!defined('ABSPATH')) exit; ?>
<table class="form-table">
  <tr>
    <th><label for="dep_dimension"><?php esc_html_e('Dimensión', 'secop-suite'); ?></label></th>
    <td>
      <select name="dep_dimension" id="dep_dimension">
        <?php foreach ($dim_labels as $dim => $label) : if (!isset($dimensions[$dim])) continue; ?>
          <option value="<?php echo esc_attr($dim); ?>" <?php selected($config['dimension'] ?? '', $dim); ?>>
            <?php echo esc_html($label); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="description"><?php esc_html_e('Cada dimensión admite ciertos tipos de gráfica.', 'secop-suite'); ?></p>
    </td>
  </tr>
  <tr>
    <th><label for="dep_metric"><?php esc_html_e('Métrica', 'secop-suite'); ?></label></th>
    <td>
      <select name="dep_metric" id="dep_metric">
        <?php foreach ($metrics as $key => $m) : ?>
          <option value="<?php echo esc_attr($key); ?>" <?php selected($config['metric'] ?? 'valor_contrato', $key); ?>>
            <?php echo esc_html($m['label']); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="description"><?php esc_html_e('Valor a medir en el eje de la gráfica.', 'secop-suite'); ?></p>
    </td>
  </tr>
  <tr>
    <th><label for="dep_order"><?php esc_html_e('Ordenar por', 'secop-suite'); ?></label></th>
    <td>
      <select name="dep_order" id="dep_order">
        <option value="valor" <?php selected($config['order'] ?? 'valor', 'valor'); ?>><?php esc_html_e('Valor', 'secop-suite'); ?></option>
        <option value="etiqueta" <?php selected($config['order'] ?? 'valor', 'etiqueta'); ?>><?php esc_html_e('Etiqueta', 'secop-suite'); ?></option>
      </select>
      <select name="dep_order_dir" id="dep_order_dir">
        <option value="DESC" <?php selected($config['order_dir'] ?? 'DESC', 'DESC'); ?>><?php esc_html_e('Descendente', 'secop-suite'); ?></option>
        <option value="ASC" <?php selected($config['order_dir'] ?? 'DESC', 'ASC'); ?>><?php esc_html_e('Ascendente', 'secop-suite'); ?></option>
      </select>
      <p class="description"><?php esc_html_e('En la dimensión «Mensual» el orden es siempre cronológico.', 'secop-suite'); ?></p>
    </td>
  </tr>
  <tr>
    <th><label for="dep_limit"><?php esc_html_e('Límite de filas', 'secop-suite'); ?></label></th>
    <td>
      <input type="number" name="dep_limit" id="dep_limit" min="0" step="1"
        value="<?php echo esc_attr((string) (int) ($config['limit'] ?? 0)); ?>" class="small-text">
      <p class="description"><?php esc_html_e('Número máximo de categorías a mostrar. 0 = sin límite (por defecto se aplican 50, excepto en «Mensual»).', 'secop-suite'); ?></p>
    </td>
  </tr>
  <tr>
    <th><label for="dep_chart_type"><?php esc_html_e('Tipo de gráfica', 'secop-suite'); ?></label></th>
    <td>
      <select name="dep_chart_type" id="dep_chart_type">
        <?php foreach ($dimensions as $dim => $types) : foreach ($types as $tp) : ?>
          <option value="<?php echo esc_attr($tp); ?>" data-dim="<?php echo esc_attr($dim); ?>"
            <?php selected($config['chart_type'] ?? '', $tp); ?>><?php echo esc_html($tp); ?></option>
        <?php endforeach; endforeach; ?>
      </select>
    </td>
  </tr>
  <tr>
    <th><label for="dep_dependencia"><?php esc_html_e('Dependencia (opcional)', 'secop-suite'); ?></label></th>
    <td>
      <?php if (!empty($dependencias)) : ?>
        <select name="dep_dependencia" id="dep_dependencia">
          <option value="" <?php selected($config['dependencia'] ?? '', ''); ?>><?php esc_html_e('— Todas las dependencias —', 'secop-suite'); ?></option>
          <?php foreach ($dependencias as $dep) : ?>
            <option value="<?php echo esc_attr($dep); ?>" <?php selected($config['dependencia'] ?? '', $dep); ?>>
              <?php echo esc_html($dep); ?>
            </option>
          <?php endforeach; ?>
        </select>
      <?php else : ?>
        <input type="text" name="dep_dependencia" id="dep_dependencia"
          value="<?php echo esc_attr($config['dependencia'] ?? ''); ?>" class="regular-text"
          placeholder="<?php esc_attr_e('Vacío = todas las dependencias', 'secop-suite'); ?>">
      <?php endif; ?>
    </td>
  </tr>
  <tr>
    <th><label for="dep_colors"><?php esc_html_e('Colores', 'secop-suite'); ?></label></th>
    <td>
      <input type="text" name="dep_colors" id="dep_colors"
        value="<?php echo esc_attr(!empty($config['colors']) && is_array($config['colors']) ? implode(', ', $config['colors']) : ''); ?>"
        class="large-text" placeholder="#844e80, #ff7300, #ffc53b, #3eba6a, #0080c3, #e74c3c, #9b59b6, #1abc9c">
      <p class="description">
        <?php esc_html_e('Lista de colores en formato hexadecimal (#rrggbb) separados por comas. Vacío = paleta por defecto:', 'secop-suite'); ?>
        <code>#844e80, #ff7300, #ffc53b, #3eba6a, #0080c3, #e74c3c, #9b59b6, #1abc9c</code>
      </p>
    </td>
  </tr>
</table>

<h3 style="margin:1em 0 .5em;"><?php esc_html_e('Personalización del gráfico', 'secop-suite'); ?></h3>
<?php
// v5.3.0: opciones de personalización del gráfico (mirror del Visualizer).
$dep_number_formats = [
    'colombiano'    => __('Colombiano (1.000.000)', 'secop-suite'),
    'millones'      => __('Millones (1M)', 'secop-suite'),
    'internacional' => __('Internacional (1,000,000)', 'secop-suite'),
    'sin_formato'   => __('Sin formato', 'secop-suite'),
];
$dep_toolbar_options = [
    'detail'   => __('Detalle', 'secop-suite'),
    'share'    => __('Compartir', 'secop-suite'),
    'data'     => __('Datos', 'secop-suite'),
    'image'    => __('Imagen', 'secop-suite'),
    'download' => __('Descargar', 'secop-suite'),
];
$dep_sel_toolbar = (!empty($config['toolbar_options']) && is_array($config['toolbar_options']))
    ? $config['toolbar_options']
    : ['share', 'data', 'image', 'download'];
?>
<table class="form-table">
  <tr>
    <th><label for="dep_number_format"><?php esc_html_e('Formato de números', 'secop-suite'); ?></label></th>
    <td>
      <select name="dep_number_format" id="dep_number_format">
        <?php foreach ($dep_number_formats as $fmt => $label) : ?>
          <option value="<?php echo esc_attr($fmt); ?>" <?php selected($config['number_format'] ?? 'colombiano', $fmt); ?>>
            <?php echo esc_html($label); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </td>
  </tr>
  <tr>
    <th><label for="dep_chart_height"><?php esc_html_e('Altura (px)', 'secop-suite'); ?></label></th>
    <td>
      <input type="number" name="dep_chart_height" id="dep_chart_height" min="150" step="10"
        value="<?php echo esc_attr($config['chart_height'] ?? 400); ?>" class="small-text"> px
    </td>
  </tr>
  <tr>
    <th><label for="dep_x_title"><?php esc_html_e('Título Eje X', 'secop-suite'); ?></label></th>
    <td>
      <input type="text" name="dep_x_title" id="dep_x_title"
        value="<?php echo esc_attr($config['x_axis_title'] ?? ''); ?>" class="regular-text">
    </td>
  </tr>
  <tr>
    <th><label for="dep_y_title"><?php esc_html_e('Título Eje Y', 'secop-suite'); ?></label></th>
    <td>
      <input type="text" name="dep_y_title" id="dep_y_title"
        value="<?php echo esc_attr($config['y_axis_title'] ?? ''); ?>" class="regular-text">
    </td>
  </tr>
  <tr>
    <th><?php esc_html_e('Leyenda', 'secop-suite'); ?></th>
    <td>
      <label>
        <input type="checkbox" name="dep_show_legend" id="dep_show_legend" value="1" <?php checked($config['show_legend'] ?? true); ?>>
        <?php esc_html_e('Mostrar leyenda', 'secop-suite'); ?>
      </label>
    </td>
  </tr>
  <tr>
    <th><label for="dep_legend_mode"><?php esc_html_e('Modo de leyenda', 'secop-suite'); ?></label></th>
    <td>
      <select name="dep_legend_mode" id="dep_legend_mode">
        <option value="text" <?php selected($config['legend_mode'] ?? 'text', 'text'); ?>><?php esc_html_e('Texto + Icono', 'secop-suite'); ?></option>
        <option value="icon" <?php selected($config['legend_mode'] ?? 'text', 'icon'); ?>><?php esc_html_e('Solo Icono', 'secop-suite'); ?></option>
      </select>
    </td>
  </tr>
  <tr>
    <th><label for="dep_legend_position"><?php esc_html_e('Posición de leyenda', 'secop-suite'); ?></label></th>
    <td>
      <select name="dep_legend_position" id="dep_legend_position">
        <option value="bottom" <?php selected($config['legend_position'] ?? 'bottom', 'bottom'); ?>><?php esc_html_e('Abajo', 'secop-suite'); ?></option>
        <option value="top" <?php selected($config['legend_position'] ?? 'bottom', 'top'); ?>><?php esc_html_e('Arriba', 'secop-suite'); ?></option>
        <option value="left" <?php selected($config['legend_position'] ?? 'bottom', 'left'); ?>><?php esc_html_e('Izquierda', 'secop-suite'); ?></option>
        <option value="right" <?php selected($config['legend_position'] ?? 'bottom', 'right'); ?>><?php esc_html_e('Derecha', 'secop-suite'); ?></option>
      </select>
    </td>
  </tr>
  <tr>
    <th><?php esc_html_e('Barra de herramientas', 'secop-suite'); ?></th>
    <td>
      <label>
        <input type="checkbox" name="dep_show_toolbar" id="dep_show_toolbar" value="1" <?php checked($config['show_toolbar'] ?? true); ?>>
        <?php esc_html_e('Mostrar barra de herramientas', 'secop-suite'); ?>
      </label>
      <p style="margin:8px 0 0;">
        <?php foreach ($dep_toolbar_options as $opt => $label) : ?>
          <label style="margin-right:14px; display:inline-block;">
            <input type="checkbox" name="dep_toolbar_options[]" value="<?php echo esc_attr($opt); ?>"
              <?php checked(in_array($opt, $dep_sel_toolbar, true)); ?>>
            <?php echo esc_html($label); ?>
          </label>
        <?php endforeach; ?>
      </p>
    </td>
  </tr>
</table>
