<?php

if (!defined('ABSPATH')) {
    exit;
}

$is_active = ($view['active_tab'] ?? 'discover') === 'places';
?>
<section class="iss-register-panel<?php echo $is_active ? ' is-active' : ''; ?>" data-panel="places" aria-labelledby="iss-register-panel-places">
  <header class="iss-register-panel__header">
    <h3 id="iss-register-panel-places">Orte</h3>
    <p>Suche nach Gebiet und Status. Karten zeigen Bild, Kurzprofil und direkten Einstieg in die Detailansicht.</p>
  </header>

  <div class="iss-register-filterbar">
    <label class="iss-register-field">
      <span>Suche</span>
      <input type="search" placeholder="Name, Adresse, Nutzung …" data-filter-search>
    </label>
    <label class="iss-register-field">
      <span>Gebiet</span>
      <select data-filter-area>
        <option value="">Alle Gebiete</option>
      </select>
    </label>
    <label class="iss-register-field">
      <span>Status</span>
      <select data-filter-status>
        <option value="">Alle Status</option>
        <option value="aktiv">Aktiv</option>
        <option value="entwicklung">In Entwicklung</option>
        <option value="geplant">Geplant</option>
        <option value="unklar">Unklar</option>
        <option value="abzug">Abzug geplant</option>
        <option value="sucht">Sucht Standort</option>
        <option value="mieter">Mieter</option>
      </select>
    </label>
  </div>

  <details class="iss-register-advanced-filters">
    <summary>Erweiterte Filter</summary>
    <div class="iss-register-filterbar iss-register-filterbar--advanced">
      <label class="iss-register-field">
        <span>Rolle</span>
        <select data-filter-role>
          <option value="">Alle Rollen</option>
        </select>
      </label>
      <label class="iss-register-field">
        <span>Unklar-Status</span>
        <select data-filter-unclear>
          <option value="">Alle</option>
          <option value="1">Nur unklare Fälle</option>
          <option value="0">Nur klare Fälle</option>
        </select>
      </label>
      <label class="iss-register-field">
        <span>Sortierung</span>
        <select data-filter-sort>
          <option value="id">Register-ID</option>
          <option value="name">Name A–Z</option>
          <option value="status">Status</option>
          <option value="area">Gebiet</option>
        </select>
      </label>
    </div>
  </details>

  <div class="iss-register-places-meta">
    <span data-results-count>0 Ergebnisse</span>
  </div>

  <div class="iss-register-places-list" data-places-list>
    <div class="iss-register-places-grid" data-places-grid></div>
    <p class="iss-register-empty" data-places-empty>Lade Orte…</p>
  </div>
</section>
