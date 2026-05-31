<?php

if (!defined('ABSPATH')) {
    exit;
}

$show_intro = !empty($view['show_intro']);
?>
<section class="iss-register-hero" aria-label="Schöneweide Register Überblick">
  <div class="iss-register-hero__copy">
    <p class="iss-register-hero__kicker">Schöneweide im Wandel</p>
    <h2 class="iss-register-hero__title">Orte, Geschichten und Veränderungen sichtbar machen</h2>
    <?php if ($show_intro) : ?>
      <p class="iss-register-hero__text">Eine interaktive Übersicht zu Standorten, Projekten und Erinnerungen in Schöneweide – vom industriellen Erbe bis zu heutigen Entwicklungen.</p>
    <?php endif; ?>
  </div>
  <div class="iss-register-hero__stats" aria-label="Register Kennzahlen">
    <article class="iss-register-kpi">
      <span class="iss-register-kpi__value" data-stat="count">0</span>
      <span class="iss-register-kpi__label">Orte im Register</span>
    </article>
    <article class="iss-register-kpi">
      <span class="iss-register-kpi__value" data-stat="active">0</span>
      <span class="iss-register-kpi__label">Aktive Standorte</span>
    </article>
    <article class="iss-register-kpi">
      <span class="iss-register-kpi__value" data-stat="development">0</span>
      <span class="iss-register-kpi__label">In Entwicklung</span>
    </article>
    <article class="iss-register-kpi">
      <span class="iss-register-kpi__value" data-stat="planned">0</span>
      <span class="iss-register-kpi__label">Geplant / offen</span>
    </article>
  </div>
</section>
