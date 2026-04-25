<?php

if (!defined('ABSPATH')) {
    exit;
}

$places = isset($places) && is_array($places) ? $places : [];
?>
<section class="iss-register-panel" data-panel="then-now" aria-labelledby="iss-register-panel-then-now">
  <header class="iss-register-panel__header">
    <h3 id="iss-register-panel-then-now">Damals &amp; Heute</h3>
    <p>Bildvergleich mit kurzem Kontext. Fehlende Bilder werden transparent als Suchbedarf markiert.</p>
  </header>

  <div class="iss-register-then-now-list" data-then-now-list>
    <?php
    if (function_exists('iss_register_render_then_now_cards')) {
        echo iss_register_render_then_now_cards($places, 12);
    } else {
        echo '<p class="iss-register-empty">Bildvergleich nicht verfügbar.</p>';
    }
    ?>
  </div>
</section>
