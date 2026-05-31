<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_normalize_schoneweide_atlas_attributes(array $attributes = []): array
{
    $class_name = trim((string) ($attributes['className'] ?? $attributes['class'] ?? ''));

    return [
        'className' => $class_name,
    ];
}

function iss_register_get_schoneweide_atlas_bootstrap_config(array $attributes = []): array
{
    return [
        'placesUrl' => untrailingslashit(rest_url(ISS_REGISTER_REST_NAMESPACE)) . '/atlas',
        'contextUrl' => untrailingslashit(rest_url(ISS_REGISTER_REST_NAMESPACE)) . '/atlas-context',
        'overlaysUrl' => iss_register_get_theme_asset_url('/assets/maps/schoneweide-overlays.geojson'),
        'relationStaticMapUrl' => iss_register_get_theme_asset_url('/assets/maps/schoneweide-static-map-big.png'),
        'basemapProvider' => defined('INDUSTRIESALON_MAP_BASEMAP_PROVIDER') ? (string) INDUSTRIESALON_MAP_BASEMAP_PROVIDER : 'carto',
        'maptilerKey' => defined('INDUSTRIESALON_MAPTILER_KEY') ? (string) INDUSTRIESALON_MAPTILER_KEY : '',
        'maptilerStyle' => defined('INDUSTRIESALON_MAPTILER_STYLE') ? (string) INDUSTRIESALON_MAPTILER_STYLE : 'streets-v2',
    ];
}

function iss_register_enqueue_schoneweide_atlas_assets(): void
{
    if (wp_style_is('iss-register-schoneweide-atlas-style', 'registered')) {
        wp_enqueue_style('iss-register-schoneweide-atlas-style');
    }

    if (wp_style_is('iss-register-schoneweide-atlas-leaflet', 'registered')) {
        wp_enqueue_style('iss-register-schoneweide-atlas-leaflet');
    }

    if (wp_script_is('iss-register-schoneweide-atlas-view', 'registered')) {
        wp_enqueue_script('iss-register-schoneweide-atlas-view');
    }
}

function iss_register_render_schoneweide_atlas(array $attributes = []): string
{
    $attributes = iss_register_normalize_schoneweide_atlas_attributes($attributes);
    iss_register_enqueue_schoneweide_atlas_assets();
    $search_id = wp_unique_id('iss-schoneweide-atlas-search-');
    $bootstrap_json = wp_json_encode(
        iss_register_get_schoneweide_atlas_bootstrap_config($attributes),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    $class_name = 'iss-atlas-app';

    if ($attributes['className'] !== '') {
        $class_name .= ' ' . $attributes['className'];
    }

    $html = '<div class="' . esc_attr($class_name) . '" data-iss-schoneweide-atlas>';
    $html .= '<aside class="iss-atlas-app__sidebar">';
    $html .= '<div class="iss-heading iss-heading--uncaged iss-atlas-app__panel-heading">';
    $html .= '<p class="iss-kicker iss-kicker--compact">Index</p>';
    $html .= '<h2 class="iss-heading__title">Orte</h2>';
    $html .= '<p class="iss-heading__text">Heutige Nutzung filtern, Zeitfenster wechseln und im Korridor suchen.</p>';
    $html .= '</div>';
    $html .= '<p class="iss-atlas-app__scope-note">Ausschnitt: Schöneweide beidseits der Spree mit Nalepastraßen-Korridor.</p>';
    $html .= '<div class="iss-atlas-app__search">';
    $html .= '<label class="screen-reader-text" for="' . esc_attr($search_id) . '">Ort suchen</label>';
    $html .= '<input id="' . esc_attr($search_id) . '" type="search" placeholder="Ort, Straße, Werk, Firma ..." data-iss-schoneweide-search />';
    $html .= '</div>';
    $html .= '<div class="iss-atlas-app__filter-block">';
    $html .= '<p class="iss-atlas-app__filter-label" data-iss-schoneweide-use-type-label>Nutzung heute</p>';
    $html .= '<div class="iss-atlas-app__filter-group" data-iss-schoneweide-use-type-filters>';
    $html .= '<p class="iss-atlas-loading">Filter werden geladen.</p>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div data-iss-schoneweide-story-intro></div>';
    $html .= '<p class="iss-atlas-app__result-count" data-iss-schoneweide-count>Atlas wird geladen.</p>';
    $html .= '<button class="iss-atlas-app__reset" type="button" data-iss-schoneweide-reset>Filter zurücksetzen</button>';
    $html .= '</aside>';
    $html .= '<div class="iss-atlas-app__main">';
    $html .= '<div class="iss-atlas-app__era-strip">';
    $html .= '<div class="iss-atlas-app__era-strip-head">';
    $html .= '<p class="iss-atlas-app__filter-label">Zeitfenster</p>';
    $html .= '</div>';
    $html .= '<div class="iss-atlas-app__era-strip-body" data-iss-schoneweide-era-filters>';
    $html .= '<p class="iss-atlas-loading">Epochen werden geladen.</p>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="iss-atlas-app__actor-strip">';
    $html .= '<div class="iss-atlas-app__actor-strip-head">';
    $html .= '<p class="iss-atlas-app__filter-label" data-iss-schoneweide-actor-label>Industrieakteure</p>';
    $html .= '</div>';
    $html .= '<div class="iss-atlas-app__actor-strip-body" data-iss-schoneweide-actor-filters>';
    $html .= '<p class="iss-atlas-loading">Akteure werden geladen.</p>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="iss-atlas-app__map-stage">';
    $html .= '<div class="iss-atlas-app__map-surface">';
    $html .= '<div class="iss-atlas-app__map-canvas" data-iss-schoneweide-map></div>';
    $html .= '<div class="iss-atlas-app__map-status" data-iss-schoneweide-map-status>';
    $html .= '<p class="iss-atlas-loading">Atlas wird geladen.</p>';
    $html .= '</div>';
    $html .= '<div class="iss-atlas-app__legend">';
    $html .= '<span class="iss-atlas-app__legend-item"><span class="iss-atlas-app__legend-dot is-selected"></span> Ausgewählter Ort</span>';
    $html .= '<span class="iss-atlas-app__legend-item"><span class="iss-atlas-app__legend-dot"></span> Weitere Orte</span>';
    $html .= '</div>';
    $html .= '<div class="iss-atlas-app__map-summary" data-iss-schoneweide-summary></div>';
    $html .= '</div>';
    $html .= '<div class="iss-atlas-app__popup-slot is-empty" data-iss-schoneweide-popup></div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div hidden><div data-iss-schoneweide-stories></div></div>';
    $html .= '<script type="application/json" data-iss-schoneweide-atlas-config>' . ($bootstrap_json ?: '{}') . '</script>';
    $html .= '</div>';

    return $html;
}

function iss_register_render_schoneweide_atlas_shortcode(array $attributes = []): string
{
    return iss_register_render_schoneweide_atlas($attributes);
}

add_shortcode('iss_schoneweide_atlas', 'iss_register_render_schoneweide_atlas_shortcode');
