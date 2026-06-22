<?php
/**
 * Template: Red ego de contratación (Rings d3plus).
 *
 * Red concéntrica centrada en UNA dependencia: el nodo central es la dependencia
 * y sus conexiones (contratistas, tipos y modalidades) se disponen en anillos
 * automáticamente. Reutiliza el endpoint AJAX secop_dep_network (limit=0) y se
 * renderiza con assets/js/dep-rings.js (d3plus.Rings).
 *
 * Variables disponibles:
 * - $atts array  Atributos del shortcode (dependencia, height, selector).
 * - $deps array  Lista de dependencias para el selector (vacío si selector="off").
 * - $uid  string ID único del contenedor.
 *
 * @package SecopSuite
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="ss-rings-module">
    <?php if (!empty($deps)) : ?>
        <div class="ss-rings-controls">
            <label><?php esc_html_e('Dependencia central:', 'secop-suite'); ?>
                <select class="ss-rings-selector">
                    <option value=""><?php esc_html_e('— Automática (mayor valor) —', 'secop-suite'); ?></option>
                    <?php foreach ($deps as $d) : ?>
                        <option value="<?php echo esc_attr($d); ?>" <?php selected($atts['dependencia'], $d); ?>><?php echo esc_html($d); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    <?php endif; ?>

    <div id="<?php echo esc_attr($uid); ?>" class="ss-rings-wrapper"
         data-dependencia="<?php echo esc_attr($atts['dependencia']); ?>"
         style="min-height:<?php echo (int) $atts['height']; ?>px">
        <div class="ss-rings-chart"></div>
    </div>
</div>
