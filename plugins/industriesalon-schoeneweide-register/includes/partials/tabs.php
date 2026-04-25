<?php

if (!defined('ABSPATH')) {
    exit;
}
?>
<nav class="iss-register-tabs" role="tablist" aria-label="Register Bereiche">
  <button type="button" class="iss-register-tab is-active" role="tab" aria-selected="true" data-tab-target="discover">Entdecken</button>
  <button type="button" class="iss-register-tab" role="tab" aria-selected="false" data-tab-target="places">Orte</button>
  <button type="button" class="iss-register-tab" role="tab" aria-selected="false" data-tab-target="then-now">Damals &amp; Heute</button>
  <button type="button" class="iss-register-tab" role="tab" aria-selected="false" data-tab-target="research">Recherche</button>
  <button type="button" class="iss-register-tab" role="tab" aria-selected="false" data-tab-target="detail" data-detail-tab hidden>Detail</button>
  <?php if (!empty($show_feedback)) : ?>
    <button type="button" class="iss-register-feedback-trigger" data-action="open-feedback" data-feedback-type="general">Feedback senden</button>
  <?php endif; ?>
</nav>
