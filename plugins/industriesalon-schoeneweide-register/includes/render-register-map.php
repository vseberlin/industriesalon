<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('iss_register_render_discover_map')) {
    function iss_register_render_discover_map(array $places, int $limit = 36): string
    {
        $geo_places = [];

        foreach ($places as $place) {
            if (!is_array($place)) {
                continue;
            }

            $id = iss_register_safe_text($place['id'] ?? '');
            $lat = $place['lat'] ?? null;
            $lng = $place['lng'] ?? null;

            if ($id === '' || !is_numeric($lat) || !is_numeric($lng)) {
                continue;
            }

            $geo_places[] = [
                'id' => $id,
                'name' => iss_register_safe_text($place['name'] ?? 'Unbenannter Standort'),
                'lat' => (float) $lat,
                'lng' => (float) $lng,
            ];
        }

        if (!$geo_places) {
            return '<div class="iss-register-map" data-register-map><div class="iss-register-map-canvas" data-map-canvas hidden></div><p class="iss-register-empty" data-map-empty>Keine Koordinaten vorhanden.</p></div>';
        }

        $geo_places = array_slice($geo_places, 0, max(1, $limit));

        $lats = array_column($geo_places, 'lat');
        $lngs = array_column($geo_places, 'lng');
        $min_lat = min($lats);
        $max_lat = max($lats);
        $min_lng = min($lngs);
        $max_lng = max($lngs);

        $lat_range = max(0.0001, $max_lat - $min_lat);
        $lng_range = max(0.0001, $max_lng - $min_lng);

        $markers = '';
        $list = '';

        foreach ($geo_places as $item) {
            $x = (($item['lng'] - $min_lng) / $lng_range) * 100;
            $y = (($max_lat - $item['lat']) / $lat_range) * 100;

            $x = max(4, min(96, $x));
            $y = max(6, min(94, $y));

            $markers .= sprintf(
                '<button type="button" class="iss-register-map-marker" data-place-id="%1$s" style="--x:%2$s%%;--y:%3$s%%" aria-label="%4$s"><span class="iss-register-map-marker__dot" aria-hidden="true"></span></button>',
                esc_attr($item['id']),
                esc_attr(number_format($x, 3, '.', '')),
                esc_attr(number_format($y, 3, '.', '')),
                esc_attr($item['name'])
            );

            $list .= sprintf(
                '<li><button type="button" class="iss-register-map-list__button" data-place-id="%1$s">%2$s</button></li>',
                esc_attr($item['id']),
                esc_html($item['name'])
            );
        }

        return sprintf(
            '<div class="iss-register-map" data-register-map><div class="iss-register-map-canvas" data-map-canvas>%1$s</div><p class="iss-register-empty" data-map-empty hidden>Keine Koordinaten vorhanden.</p><ul class="iss-register-map-list">%2$s</ul></div>',
            $markers,
            $list
        );
    }
}
