<?php

if (!defined('ABSPATH')) {
    exit;
}

$is_active = ($view['active_tab'] ?? 'discover') === 'research';
$research_links = isset($view['research_links']) && is_array($view['research_links'])
    ? $view['research_links']
    : [];
$places_url = (string) ($research_links['places_url'] ?? '');
$export_url = (string) ($research_links['export_url'] ?? '');
$feedback_url = (string) ($research_links['feedback_url'] ?? '');
$enable_export = !empty($view['enable_export']);
?>
<section class="iss-register-panel<?php echo $is_active ? ' is-active' : ''; ?>" data-panel="research" aria-labelledby="iss-register-panel-research">
  <header class="iss-register-panel__header">
    <h3 id="iss-register-panel-research">Recherche</h3>
    <p>Vollständiger Analysemodus mit erweiterten Filtern, Rollenblick, Quellenübersicht und Export.</p>
  </header>

  <div class="iss-register-research-controls">
    <div class="iss-register-filterbar iss-register-filterbar--research">
      <label class="iss-register-field">
        <span>Suche</span>
        <input type="search" placeholder="Name, Branche, Verlauf …" data-filter-search>
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
  </div>

  <div class="iss-register-research-actions">
    <?php if ($enable_export) : ?>
      <button type="button" class="iss-register-button iss-register-button--ghost" data-action-export-json>Gefilterten Voll-Export laden</button>
    <?php endif; ?>
    <a href="<?php echo $places_url; ?>" target="_blank" rel="noopener" class="iss-register-link">Summary-JSON-Feed</a>
    <a href="<?php echo $export_url; ?>" target="_blank" rel="noopener" class="iss-register-link">Vollständiger Export-Feed</a>
    <a href="<?php echo $feedback_url; ?>" target="_blank" rel="noopener" class="iss-register-link">Feedback-Endpunkt</a>
  </div>

  <div class="iss-register-research-stats">
    <span><strong data-stat="count">0</strong> Profile</span>
    <span><strong data-stat="active">0</strong> aktiv</span>
    <span><strong data-stat="development">0</strong> in Entwicklung</span>
    <span><strong data-stat="planned">0</strong> geplant/unklar</span>
  </div>

  <div class="iss-register-research-summary" data-research-summary>Keine Daten geladen.</div>

  <div class="iss-register-research-sources">
    <h4>Quellen im aktuellen Filter</h4>
    <ul data-research-sources-list></ul>
    <p class="iss-register-empty" data-research-sources-empty>Keine Quellenhinweise vorhanden.</p>
  </div>

  <div class="iss-register-research-results" data-research-results>
    <table class="iss-register-research-table" data-research-table hidden>
      <thead>
        <tr>
          <th>Standort</th>
          <th>Status</th>
          <th>Rollen</th>
          <th>Aktion</th>
        </tr>
      </thead>
      <tbody data-research-results-body></tbody>
    </table>
    <p class="iss-register-empty" data-research-results-empty>Keine Datensätze für die Recherche-Ansicht.</p>
  </div>
</section>
