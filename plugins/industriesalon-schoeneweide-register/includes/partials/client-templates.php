<?php

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="iss-register-client-templates" hidden aria-hidden="true">
  <template data-register-template="featured-card">
    <article class="iss-register-featured-card">
      <button type="button" class="iss-register-featured-card__button" data-place-id="">
        <span data-template-slot="media"></span>
        <div class="iss-register-featured-card__body">
          <h4 class="iss-register-featured-card__title" data-template-field="name"></h4>
          <p class="iss-register-featured-card__meta">
            <span class="iss-register-badge" data-template-field="status"></span>
            <span data-template-field="area"></span>
          </p>
          <p class="iss-register-featured-card__text" data-template-field="summary"></p>
          <span class="iss-register-inline-link">Details ansehen</span>
        </div>
      </button>
    </article>
  </template>

  <template data-register-template="quick-link-item">
    <li>
      <button type="button" class="iss-register-quick-link" data-place-id="">
        <strong data-template-field="name"></strong>
        <span data-template-field="meta"></span>
      </button>
    </li>
  </template>

  <template data-register-template="map-marker">
    <button type="button" class="iss-register-map-marker" data-place-id="" aria-label="">
      <span class="iss-register-map-marker__dot" aria-hidden="true"></span>
    </button>
  </template>

  <template data-register-template="map-list-item">
    <li>
      <button type="button" class="iss-register-map-list__button" data-place-id="" data-template-field="name"></button>
    </li>
  </template>

  <template data-register-template="place-card">
    <article class="iss-register-place-card">
      <button type="button" class="iss-register-place-card__button" data-place-id="">
        <span data-template-slot="media"></span>
        <div class="iss-register-place-card__body">
          <h4 class="iss-register-place-card__title" data-template-field="name"></h4>
          <p class="iss-register-place-card__meta">
            <span data-template-field="area"></span>
            <span class="iss-register-badge" data-template-field="status"></span>
          </p>
          <p class="iss-register-place-card__text" data-template-field="summary"></p>
          <span class="iss-register-inline-link">Details ansehen</span>
        </div>
      </button>
    </article>
  </template>

  <template data-register-template="then-now-card">
    <article class="iss-register-then-now-card">
      <button type="button" class="iss-register-then-now-card__button" data-place-id="">
        <div class="iss-register-then-now-card__media-grid">
          <span data-template-slot="old-media"></span>
          <span data-template-slot="new-media"></span>
        </div>
        <div class="iss-register-then-now-card__body">
          <h4 class="iss-register-then-now-card__title" data-template-field="name"></h4>
          <p class="iss-register-then-now-card__text" data-template-field="summary"></p>
          <span class="iss-register-inline-link">Mehr erfahren</span>
        </div>
      </button>
    </article>
  </template>

  <template data-register-template="research-source-item">
    <li>
      <span data-template-field="label"></span>
      <strong data-template-field="count"></strong>
    </li>
  </template>

  <template data-register-template="research-row">
    <tr>
      <td>
        <strong data-template-field="name"></strong><br>
        <small data-template-field="area"></small>
      </td>
      <td>
        <span class="iss-register-badge" data-template-field="status"></span>
      </td>
      <td data-template-field="role-summary"></td>
      <td>
        <button type="button" class="iss-register-inline-link-button" data-place-id="">Detail</button>
      </td>
    </tr>
  </template>
</div>
