<?php

if (!defined('ABSPATH')) {
    exit;
}

if (empty($view['show_feedback'])) {
    return;
}
?>
<div class="iss-register-feedback" data-feedback-modal hidden>
  <div class="iss-register-feedback__backdrop" data-action="close-feedback"></div>
  <div class="iss-register-feedback__dialog" role="dialog" aria-modal="true" aria-labelledby="iss-register-feedback-title">
    <button type="button" class="iss-register-feedback__close" data-action="close-feedback" aria-label="Feedback schließen">×</button>
    <h3 id="iss-register-feedback-title">Feedback senden</h3>
    <p>Hinweise gehen als <strong>pending</strong> in die Redaktion und werden dort geprüft.</p>
    <form class="iss-register-feedback-form" data-feedback-form>
      <label class="iss-register-field">
        <span>Bezug zum Standort</span>
        <select name="place_reference" data-feedback-place>
          <option value="">Ohne direkten Standortbezug</option>
        </select>
      </label>
      <label class="iss-register-field">
        <span>Feedback-Typ</span>
        <select name="feedback_type" required>
          <option value="correction">Fehler melden</option>
          <option value="image_contribution">Bild beitragen</option>
          <option value="memory">Erinnerung / Hinweis senden</option>
          <option value="source">Quelle ergänzen</option>
          <option value="general">Allgemeines Feedback</option>
        </select>
      </label>
      <label class="iss-register-field">
        <span>Nachricht</span>
        <textarea name="message" rows="5" minlength="8" required></textarea>
      </label>
      <div class="iss-register-feedback-form__grid" data-feedback-image-fields hidden>
        <label class="iss-register-field">
          <span>Bild-ID aus der Mediathek (optional)</span>
          <input type="number" name="image_attachment_id" min="1" step="1" placeholder="z. B. 4711">
        </label>
        <label class="iss-register-field">
          <span>Bild- oder Quellen-URL</span>
          <input type="url" name="image_source_url" placeholder="https://...">
        </label>
      </div>
      <div class="iss-register-feedback-form__grid">
        <label class="iss-register-field">
          <span>Name (optional)</span>
          <input type="text" name="name">
        </label>
        <label class="iss-register-field">
          <span>E-Mail (optional)</span>
          <input type="email" name="email">
        </label>
      </div>
      <label class="iss-register-field">
        <span>Quellen-URL (optional)</span>
        <input type="url" name="source_url" placeholder="https://...">
      </label>
      <label class="iss-register-feedback-form__check">
        <input type="checkbox" name="rights_confirmation" value="1">
        <span>Ich bestätige, dass ich Nutzungsrechte an übermittelten Inhalten besitze.</span>
      </label>
      <div class="iss-register-feedback-form__actions">
        <button type="submit" class="iss-register-button">Feedback absenden</button>
      </div>
      <p class="iss-register-feedback-form__status" data-feedback-status aria-live="polite"></p>
    </form>
  </div>
</div>
