<?php

if (!defined('ABSPATH')) {
    exit;
}

$is_active = ($view['active_tab'] ?? 'discover') === 'then-now';
?>
<section class="iss-register-panel<?php echo $is_active ? ' is-active' : ''; ?>" data-panel="then-now" aria-labelledby="iss-register-panel-then-now">
  <header class="iss-register-panel__header">
    <h3 id="iss-register-panel-then-now">Damals &amp; Heute</h3>
    <p>Bildvergleich mit kurzem Kontext. Fehlende Bilder werden transparent als Suchbedarf markiert.</p>
  </header>

  <div class="iss-register-then-now-list" data-then-now-list>
    <div class="iss-register-then-now-grid" data-then-now-grid></div>
    <p class="iss-register-empty" data-then-now-empty>Lade Orte…</p>
  </div>
</section>
