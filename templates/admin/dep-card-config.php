<?php if (!defined('ABSPATH')) exit; ?>
<table class="form-table">
  <tr>
    <th><label for="dep_dimension"><?php esc_html_e('Dimensión', 'secop-suite'); ?></label></th>
    <td>
      <select name="dep_dimension" id="dep_dimension">
        <?php foreach ($dimensions as $dim => $types) : ?>
          <option value="<?php echo esc_attr($dim); ?>" <?php selected($config['dimension'] ?? '', $dim); ?>>
            <?php echo esc_html($dim); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="description"><?php esc_html_e('Cada dimensión admite ciertos tipos de gráfica.', 'secop-suite'); ?></p>
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
