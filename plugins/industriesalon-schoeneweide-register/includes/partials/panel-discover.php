<?php

if (!defined('ABSPATH')) {
    exit;
}

$is_active = ($view['active_tab'] ?? 'discover') === 'discover';
?>
<section class="iss-register-panel<?php echo $is_active ? ' is-active' : ''; ?>" data-panel="discover" aria-labelledby="iss-register-panel-discover">
  <header class="iss-register-panel__header">
    <h3 id="iss-register-panel-discover">Entdecken</h3>
    <p>Ein schneller Einstieg in die wichtigsten Orte, Entwicklungen und offenen Fragen in Schöneweide.</p>
  </header>

  <div class="iss-register-discover-featured" data-discover-featured>
    <div class="iss-register-featured-grid" data-discover-featured-grid></div>
    <p class="iss-register-empty" data-discover-featured-empty>Keine Standorte verfügbar.</p>
  </div>

  <div class="iss-register-discover-map-wrap">
    <h4>Standorte im Überblick</h4>
    <div class="iss-register-map" data-register-map>
      <div class="iss-register-map-canvas" data-map-canvas hidden></div>
      <p class="iss-register-empty" data-map-empty>Keine Koordinaten vorhanden.</p>
      <ul class="iss-register-map-list"></ul>
    </div>
  </div>

  <div class="iss-register-discover-list-wrap">
    <h4>Schnellauswahl</h4>
    <ul class="iss-register-discover-list" data-discover-list></ul>
  </div>

  <div class="iss-register-discover-stats">
    <article class="iss-register-discover-stat">
      <h4>Gesamt</h4>
      <p data-stat="count">0</p>
    </article>
    <article class="iss-register-discover-stat">
      <h4>Aktiv</h4>
      <p data-stat="active">0</p>
    </article>
    <article class="iss-register-discover-stat">
      <h4>In Entwicklung</h4>
      <p data-stat="development">0</p>
    </article>
    <article class="iss-register-discover-stat">
      <h4>Geplant/Unklar</h4>
      <p data-stat="planned">0</p>
    </article>
  </div>
</section>
