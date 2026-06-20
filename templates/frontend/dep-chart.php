<?php if (!defined('ABSPATH')) exit; ?>
<div class="ss-dep-chart-wrapper" id="<?php echo esc_attr($uid); ?>"
     data-dimension="<?php echo esc_attr($cfg['dimension']); ?>"
     data-type="<?php echo esc_attr($cfg['chart_type']); ?>"
     data-dependencia="<?php echo esc_attr($cfg['dependencia']); ?>"
     style="min-height:<?php echo (int) $atts['height']; ?>px">
  <div class="ss-dep-chart-target"></div>
  <p class="ss-dep-help description"><?php echo esc_html($help); ?></p>
</div>
