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
    <td><input type="text" name="dep_dependencia" id="dep_dependencia"
        value="<?php echo esc_attr($config['dependencia'] ?? ''); ?>" class="regular-text"
        placeholder="<?php esc_attr_e('Vacío = todas las dependencias', 'secop-suite'); ?>"></td>
  </tr>
</table>
