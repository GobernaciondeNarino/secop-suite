<?php if (!defined('ABSPATH')) exit; ?>
<div class="ss-seguimiento" data-nonce="<?php echo esc_attr(wp_create_nonce('secop_dep_frontend')); ?>">
  <div class="ss-seguimiento-controls">
    <label><?php esc_html_e('Dependencia:', 'secop-suite'); ?>
      <select class="ss-dep-selector">
        <option value=""><?php esc_html_e('— Todas —', 'secop-suite'); ?></option>
        <?php foreach ($deps as $d) : ?>
          <option value="<?php echo esc_attr($d); ?>" <?php selected($sel, $d); ?>><?php echo esc_html($d); ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <div class="ss-seguimiento-charts">
    <?php foreach ($dimensiones as $dim) : ?>
      <div class="ss-dep-chart-wrapper" data-dimension="<?php echo esc_attr($dim); ?>"
           data-type="<?php echo esc_attr(\SecopSuite\Tracking::default_type($dim)); ?>"
           data-dependencia="<?php echo esc_attr($sel); ?>" style="min-height:380px">
        <h4><?php echo esc_html(ucfirst($dim)); ?></h4>
        <div class="ss-dep-chart-target"></div>
      </div>
    <?php endforeach; ?>
  </div>
  <h3><?php esc_html_e('Contratos de la dependencia', 'secop-suite'); ?></h3>
  <div class="ss-seguimiento-contratos"><?php echo do_shortcode('[secop_dep_contratos dependencia="' . esc_attr($sel) . '"]'); ?></div>
</div>
