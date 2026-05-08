<?php

if (!defined('ABSPATH')) {
    exit;
}

$tab_items = isset($view['tab_items']) && is_array($view['tab_items'])
    ? $view['tab_items']
    : [];
$show_feedback = !empty($view['show_feedback']);
?>
<nav class="iss-register-tabs" role="tablist" aria-label="Register Bereiche">
  <?php foreach ($tab_items as $tab_item) : ?>
    <?php
    $target = (string) ($tab_item['target'] ?? '');
    $is_active = !empty($tab_item['is_active']);
    $is_hidden = !empty($tab_item['is_hidden']);
    $classes = 'iss-register-tab' . ($is_active ? ' is-active' : '');
    ?>
    <button
      type="button"
      class="<?php echo esc_attr($classes); ?>"
      role="tab"
      aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
      data-tab-target="<?php echo esc_attr($target); ?>"
      <?php echo $target === 'detail' ? 'data-detail-tab ' : ''; ?>
      <?php echo $is_hidden ? 'hidden' : ''; ?>
    ><?php echo esc_html((string) ($tab_item['label'] ?? '')); ?></button>
  <?php endforeach; ?>
  <?php if ($show_feedback) : ?>
    <button type="button" class="iss-register-feedback-trigger" data-action="open-feedback" data-feedback-type="general">Feedback senden</button>
  <?php endif; ?>
</nav>
