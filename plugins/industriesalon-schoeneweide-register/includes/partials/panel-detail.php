<?php

if (!defined('ABSPATH')) {
    exit;
}

$show_feedback = !empty($view['show_feedback']);
?>
<section class="iss-register-panel" data-panel="detail" aria-labelledby="iss-register-panel-detail">
  <header class="iss-register-panel__header iss-register-detail-header">
    <div>
      <h3 id="iss-register-panel-detail" data-detail-title>Kein Standort ausgewählt</h3>
      <p class="iss-register-detail-header__meta" data-detail-owner>Bitte im Tab „Orte“ einen Standort auswählen.</p>
    </div>
    <span class="iss-register-badge" data-detail-status>—</span>
  </header>

  <figure class="iss-register-detail-hero" data-detail-hero>
    <img src="" alt="" loading="lazy" decoding="async" data-detail-hero-image hidden>
    <div class="iss-register-detail-hero__empty" data-detail-hero-empty>Bild gesucht</div>
    <figcaption data-detail-narrative>Kurze Einordnung folgt nach Auswahl eines Standorts.</figcaption>
  </figure>

  <div class="iss-register-detail-facts">
    <span class="iss-register-detail-meta__item" data-detail-role>—</span>
    <span class="iss-register-detail-meta__item" data-detail-area>—</span>
  </div>

  <div class="iss-register-detail-grid">
    <article class="iss-register-detail-card">
      <h4>Adresse</h4>
      <p data-detail-address>—</p>
    </article>
    <article class="iss-register-detail-card">
      <h4>Fläche</h4>
      <p data-detail-size>—</p>
    </article>
    <article class="iss-register-detail-card">
      <h4>Investition</h4>
      <p data-detail-investment>—</p>
    </article>
    <article class="iss-register-detail-card">
      <h4>Arbeitsplätze/Nutzung</h4>
      <p data-detail-jobs>—</p>
    </article>
    <article class="iss-register-detail-card">
      <h4>Branche</h4>
      <p data-detail-branch>—</p>
    </article>
  </div>

  <div class="iss-register-detail-then-now">
    <article class="iss-register-detail-media">
      <h4>Damals</h4>
      <img src="" alt="" loading="lazy" decoding="async" data-detail-old-image hidden>
      <div class="iss-register-detail-media__empty" data-detail-old-image-empty>Archivbild gesucht</div>
      <p data-detail-old>—</p>
    </article>
    <article class="iss-register-detail-media">
      <h4>Heute</h4>
      <img src="" alt="" loading="lazy" decoding="async" data-detail-new-image hidden>
      <div class="iss-register-detail-media__empty" data-detail-new-image-empty>Aktuelles Bild gesucht</div>
      <p data-detail-current>—</p>
    </article>
  </div>

  <article class="iss-register-detail-section">
    <h4>Hintergrund</h4>
    <p data-detail-history>—</p>
  </article>

  <article class="iss-register-detail-section">
    <h4>Offene Fragen</h4>
    <ul class="iss-register-detail-questions" data-detail-questions>
      <li>—</li>
    </ul>
  </article>

  <details class="iss-register-detail-sources">
    <summary>Quellen</summary>
    <ul data-detail-sources-list>
      <li>—</li>
    </ul>
    <a href="#" class="iss-register-link" data-detail-website hidden target="_blank" rel="noopener">Website öffnen</a>
  </details>

  <?php if ($show_feedback) : ?>
    <div class="iss-register-detail-feedback">
      <button type="button" class="iss-register-button iss-register-button--ghost" data-action="open-feedback" data-feedback-type="correction">Fehler melden</button>
      <button type="button" class="iss-register-button iss-register-button--ghost" data-action="open-feedback" data-feedback-type="memory">Erinnerung senden</button>
      <button type="button" class="iss-register-button iss-register-button--ghost" data-action="open-feedback" data-feedback-type="source">Quelle ergänzen</button>
      <button type="button" class="iss-register-button iss-register-button--ghost" data-action="open-feedback" data-feedback-type="image_contribution">Bild beitragen</button>
    </div>
  <?php endif; ?>
</section>
