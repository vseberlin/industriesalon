<?php

if (!defined('ABSPATH')) {
    exit;
}

$places = isset($places) && is_array($places) ? $places : [];

$count_total = count($places);
$count_active = 0;
$count_development = 0;
$count_planned = 0;

foreach ($places as $place) {
    if (!is_array($place)) {
        continue;
    }

    $status = strtolower(trim((string) ($place['status'] ?? '')));

    if ($status === 'aktiv') {
        $count_active++;
    } elseif ($status === 'entwicklung') {
        $count_development++;
    } elseif (in_array($status, ['geplant', 'unklar'], true)) {
        $count_planned++;
    }
}
?>
<section class="iss-register-panel is-active" data-panel="discover" aria-labelledby="iss-register-panel-discover">
  <header class="iss-register-panel__header">
    <h3 id="iss-register-panel-discover">Entdecken</h3>
    <p>Ein schneller Einstieg in die wichtigsten Orte, Entwicklungen und offenen Fragen in Schöneweide.</p>
  </header>

  <div class="iss-register-discover-featured" data-discover-featured>
    <?php
    if (function_exists('iss_register_render_featured_cards')) {
        echo iss_register_render_featured_cards($places, 6);
    } else {
        echo '<p class="iss-register-empty">Featured-Ansicht nicht verfügbar.</p>';
    }
    ?>
  </div>

  <div class="iss-register-discover-map-wrap">
    <h4>Standorte im Überblick</h4>
    <?php
    if (function_exists('iss_register_render_discover_map')) {
        echo iss_register_render_discover_map($places, 36);
    } else {
        echo '<p class="iss-register-empty">Kartenansicht nicht verfügbar.</p>';
    }
    ?>
  </div>

  <div class="iss-register-discover-list-wrap">
    <h4>Schnellauswahl</h4>
    <ul class="iss-register-discover-list" data-discover-list>
      <?php
      if (function_exists('iss_register_render_discover_quick_list')) {
          echo iss_register_render_discover_quick_list($places, 8);
      }
      ?>
    </ul>
  </div>

  <div class="iss-register-discover-stats">
    <article class="iss-register-discover-stat">
      <h4>Gesamt</h4>
      <p data-stat="count"><?php echo esc_html((string) $count_total); ?></p>
    </article>
    <article class="iss-register-discover-stat">
      <h4>Aktiv</h4>
      <p data-stat="active"><?php echo esc_html((string) $count_active); ?></p>
    </article>
    <article class="iss-register-discover-stat">
      <h4>In Entwicklung</h4>
      <p data-stat="development"><?php echo esc_html((string) $count_development); ?></p>
    </article>
    <article class="iss-register-discover-stat">
      <h4>Geplant/Unklar</h4>
      <p data-stat="planned"><?php echo esc_html((string) $count_planned); ?></p>
    </article>
  </div>
</section>
