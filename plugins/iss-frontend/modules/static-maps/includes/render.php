<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_frontend_static_maps_get_coord_key(float $lat, float $lng): string
{
    return number_format($lat, 6, '.', '') . ':' . number_format($lng, 6, '.', '');
}

function iss_frontend_static_maps_clamp_float(float $value, float $min, float $max): float
{
    if ($value < $min) {
        return $min;
    }

    if ($value > $max) {
        return $max;
    }

    return $value;
}

function iss_frontend_static_maps_get_marker_lookup(string $markers_path): array
{
    static $cache = [];

    $markers_path = wp_normalize_path($markers_path);

    if ($markers_path === '') {
        return [];
    }

    if (array_key_exists($markers_path, $cache)) {
        return $cache[$markers_path];
    }

    if (!is_readable($markers_path)) {
        $cache[$markers_path] = [];
        return $cache[$markers_path];
    }

    $contents = file_get_contents($markers_path);
    if (!is_string($contents) || $contents === '') {
        $cache[$markers_path] = [];
        return $cache[$markers_path];
    }

    $decoded = json_decode($contents, true);
    if (!is_array($decoded)) {
        $cache[$markers_path] = [];
        return $cache[$markers_path];
    }

    $lookup = [
        'by_id' => [],
        'by_name' => [],
        'by_coords' => [],
    ];

    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }

        $place_id = isset($item['id']) ? (string) $item['id'] : '';
        $post_id = isset($item['post_id']) ? (string) $item['post_id'] : '';
        $name = sanitize_title((string) ($item['name'] ?? ''));
        $lat = isset($item['lat']) && is_numeric($item['lat']) ? (float) $item['lat'] : null;
        $lng = isset($item['lng']) && is_numeric($item['lng']) ? (float) $item['lng'] : null;
        $x = isset($item['xNorm']) && is_numeric($item['xNorm']) ? (float) $item['xNorm'] : null;
        $y = isset($item['yNorm']) && is_numeric($item['yNorm']) ? (float) $item['yNorm'] : null;

        if ($x === null || $y === null) {
            continue;
        }

        $position = [
            'x' => max(0, min(100, $x * 100)),
            'y' => max(0, min(100, $y * 100)),
        ];

        if ($place_id !== '') {
            $lookup['by_id'][$place_id] = $position;
        }

        if ($post_id !== '') {
            $lookup['by_id'][$post_id] = $position;
        }

        if ($name !== '') {
            $lookup['by_name'][$name] = $position;
        }

        if ($lat !== null && $lng !== null) {
            $lookup['by_coords'][iss_frontend_static_maps_get_coord_key($lat, $lng)] = $position;
        }
    }

    if (!$lookup['by_id'] && !$lookup['by_name'] && !$lookup['by_coords']) {
        $cache[$markers_path] = [];
        return $cache[$markers_path];
    }

    $cache[$markers_path] = $lookup;

    return $cache[$markers_path];
}

function iss_frontend_static_maps_get_marker_position(array $place, array $marker_lookup): ?array
{
    $place_id = isset($place['place_id']) ? (string) $place['place_id'] : '';
    if ($place_id !== '' && isset($marker_lookup['by_id'][$place_id])) {
        return $marker_lookup['by_id'][$place_id];
    }

    $lat = isset($place['lat']) && is_numeric($place['lat']) ? (float) $place['lat'] : null;
    $lng = isset($place['lng']) && is_numeric($place['lng']) ? (float) $place['lng'] : null;
    if ($lat !== null && $lng !== null) {
        $coord_key = iss_frontend_static_maps_get_coord_key($lat, $lng);
        if (isset($marker_lookup['by_coords'][$coord_key])) {
            return $marker_lookup['by_coords'][$coord_key];
        }
    }

    $title = sanitize_title((string) ($place['title'] ?? ''));
    if ($title !== '' && isset($marker_lookup['by_name'][$title])) {
        return $marker_lookup['by_name'][$title];
    }

    return null;
}

function iss_frontend_static_maps_collect_mapped_places(array $places, array $config): array
{
    $marker_lookup = iss_frontend_static_maps_get_marker_lookup((string) ($config['markers_path'] ?? ''));
    if (!$marker_lookup) {
        return [];
    }

    $mapped_places = [];

    foreach ($places as $index => $place) {
        $position = iss_frontend_static_maps_get_marker_position($place, $marker_lookup);
        if ($position === null) {
            continue;
        }

        $mapped_places[] = [
            'index' => (int) $index,
            'place' => $place,
            'position' => [
                'x' => (float) $position['x'],
                'y' => (float) $position['y'],
            ],
        ];
    }

    return $mapped_places;
}

function iss_frontend_static_maps_get_panel_entry_title(array $place, string $presentation): string
{
    if ($presentation === 'station-detail') {
        $route_title = trim((string) ($place['route_title'] ?? ''));
        if ($route_title !== '') {
            return $route_title;
        }
    }

    $title = trim((string) ($place['title'] ?? ''));
    if ($title !== '') {
        return $title;
    }

    return trim((string) ($place['label'] ?? ''));
}

function iss_frontend_static_maps_get_panel_entry_text(array $place, string $presentation): string
{
    if ($presentation === 'station-detail') {
        $route_teaser = trim((string) ($place['route_teaser'] ?? ''));
        if ($route_teaser !== '') {
            return $route_teaser;
        }
    }

    return trim((string) ($place['excerpt'] ?? ''));
}

function iss_frontend_static_maps_render_place_map_panel_entry(array $place, int $index, string $presentation = 'list', array $options = []): string
{
    $permalink = (string) ($place['permalink'] ?? '');
    $title = iss_frontend_static_maps_get_panel_entry_title($place, $presentation);
    $label = trim((string) ($place['label'] ?? ''));
    $excerpt = iss_frontend_static_maps_get_panel_entry_text($place, $presentation);
    $entry_classes = ['iss-related-place-map__entry'];
    $entry_attrs = '';

    if (!empty($options['detail'])) {
        $entry_classes[] = 'iss-related-place-map__entry--station-detail';
    }

    if (!empty($options['attrs']) && is_array($options['attrs'])) {
        foreach ($options['attrs'] as $name => $value) {
            $name = sanitize_key((string) $name);
            if ($name === '') {
                continue;
            }
            $entry_attrs .= ' ' . $name . '="' . esc_attr((string) $value) . '"';
        }
    }

    $out = '<article class="' . esc_attr(implode(' ', $entry_classes)) . '"' . $entry_attrs . '>';
    $out .= '<div class="iss-related-place-map__entry-index">' . esc_html((string) ($index + 1)) . '</div>';
    $out .= '<div class="iss-related-place-map__entry-body">';
    if ($label !== '') {
        $out .= '<p class="iss-related-place-map__entry-kicker">' . esc_html($label) . '</p>';
    }
    if ($title !== '') {
        $out .= '<h3 class="iss-related-place-map__entry-title"><a href="' . esc_url($permalink) . '">' . esc_html($title) . '</a></h3>';
    }
    if ($excerpt !== '') {
        $out .= '<p class="iss-related-place-map__entry-text">' . esc_html($excerpt) . '</p>';
    }
    $out .= '<p class="iss-related-place-map__entry-link"><a class="iss-action-link" href="' . esc_url($permalink) . '">' . esc_html__('Zum Ort', 'iss-frontend') . '</a></p>';
    $out .= '</div>';
    $out .= '</article>';

    return $out;
}

function iss_frontend_static_maps_render_place_map_panel(array $places, array $options = []): string
{
    $presentation = sanitize_key((string) ($options['presentation'] ?? 'list'));
    $presentation = $presentation === 'station-detail' ? 'station-detail' : 'list';
    $map_id = sanitize_html_class((string) ($options['map_id'] ?? ''));
    $panel_classes = [
        'iss-related-place-map__panel',
        'iss-related-place-map__panel--' . $presentation,
    ];
    $panel_attrs = ' class="' . esc_attr(implode(' ', $panel_classes)) . '"';

    if ($presentation === 'station-detail') {
        $panel_attrs .= ' data-iss-map-panel';
    }

    $out = '<div' . $panel_attrs . '>';

    if ($presentation === 'station-detail') {
        $detail_id = $map_id !== '' ? $map_id . '-detail' : '';
        $detail_attrs = ' class="iss-related-place-map__panel-detail" data-iss-map-active-detail hidden';
        if ($detail_id !== '') {
            $detail_attrs .= ' id="' . esc_attr($detail_id) . '"';
        }

        $out .= '<div class="iss-related-place-map__panel-fallback" data-iss-map-panel-fallback>';
        foreach ($places as $index => $place) {
            $out .= iss_frontend_static_maps_render_place_map_panel_entry($place, (int) $index, $presentation);
        }
        $out .= '</div>';
        $out .= '<div' . $detail_attrs . '></div>';

        foreach ($places as $index => $place) {
            $out .= '<template data-iss-map-detail-template="' . esc_attr((string) $index) . '">';
            $out .= iss_frontend_static_maps_render_place_map_panel_entry($place, (int) $index, $presentation, [
                'detail' => true,
            ]);
            $out .= '</template>';
        }
    } else {
        foreach ($places as $index => $place) {
            $out .= iss_frontend_static_maps_render_place_map_panel_entry($place, (int) $index, $presentation);
        }
    }

    $out .= '</div>';

    return $out;
}

function iss_frontend_static_maps_get_atlas_slice_model(array $places, array $config, array $options = []): array
{
    $mapped_places = iss_frontend_static_maps_collect_mapped_places($places, $config);
    if (!$mapped_places) {
        return [
            'empty_html' => '<div class="iss-related-place-map__empty">' . esc_html__('Für diese Auswahl sind noch keine Koordinaten hinterlegt.', 'iss-frontend') . '</div>',
            'stations' => [],
        ];
    }

    $image_url = (string) ($config['image_url'] ?? '');
    if ($image_url === '') {
        return [
            'empty_html' => '<div class="iss-related-place-map__empty">' . esc_html__('Für diese Auswahl ist noch keine Atlaskarte hinterlegt.', 'iss-frontend') . '</div>',
            'stations' => [],
        ];
    }

    $image_alt = (string) ($config['image_alt'] ?? '');
    $ratio_width = max(1, absint($options['ratio_width'] ?? 1600));
    $ratio_height = max(1, absint($options['ratio_height'] ?? 720));
    $show_markers = !array_key_exists('show_markers', $options) || !empty($options['show_markers']);
    $line_mode = sanitize_key((string) ($options['line_mode'] ?? 'none'));

    $stage_classes = ['iss-atlas-slice__stage'];
    $extra_class = trim((string) ($options['class_name'] ?? ''));

    if ($extra_class !== '') {
        foreach (preg_split('/\s+/', $extra_class) as $class_name) {
            $class_name = sanitize_html_class($class_name);
            if ($class_name !== '') {
                $stage_classes[] = $class_name;
            }
        }
    }

    $stage_styles = [
        '--iss-atlas-slice-ratio:' . $ratio_width . ' / ' . $ratio_height,
    ];
    $stage_attr = ' style="' . esc_attr(implode(';', $stage_styles)) . '"';
    $route_points = [];
    $stations = [];

    foreach ($mapped_places as $item) {
        $raw_x = (float) ($item['position']['x'] ?? 0.0);
        $raw_y = (float) ($item['position']['y'] ?? 0.0);
        $marker_x = $raw_x;
        $marker_y = $raw_y;

        $route_points[] = [
            'x' => iss_frontend_static_maps_clamp_float($marker_x, -20.0, 120.0),
            'y' => iss_frontend_static_maps_clamp_float($marker_y, -20.0, 120.0),
        ];

        if ($show_markers) {
            $place = $item['place'];
            $index = (int) $item['index'];
            $label = trim((string) ($place['label'] ?? ''));
            $marker_label = $label !== '' ? ($label . ': ' . $place['title']) : $place['title'];

            $stations[] = [
                'index' => $index,
                'place' => $place,
                'marker_label' => $marker_label,
                'raw_x' => $raw_x,
                'raw_y' => $raw_y,
                'x' => iss_frontend_static_maps_clamp_float($marker_x, -20.0, 120.0),
                'y' => iss_frontend_static_maps_clamp_float($marker_y, -20.0, 120.0),
            ];
        }
    }

    $route_line = '';
    if ($line_mode === 'route' && count($route_points) > 1) {
        $points = array_map(
            static fn($point) => number_format((float) $point['x'], 3, '.', '') . ',' . number_format((float) $point['y'], 3, '.', ''),
            $route_points
        );
        $route_line = '<svg class="iss-atlas-slice__route-line iss-gesture-atlas-map__route-line" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true" focusable="false"><polyline points="' . esc_attr(implode(' ', $points)) . '" vector-effect="non-scaling-stroke"></polyline></svg>';
    }

    return [
        'empty_html' => '',
        'image_url' => $image_url,
        'image_alt' => $image_alt,
        'image_width' => max(1, absint($config['width'] ?? 1)),
        'image_height' => max(1, absint($config['height'] ?? 1)),
        'image_sources' => is_array($config['image_sources'] ?? null) ? $config['image_sources'] : [],
        'line_mode' => $line_mode,
        'route_line' => $route_line,
        'stage_classes' => $stage_classes,
        'stage_attr' => $stage_attr,
        'stations' => $stations,
    ];
}

function iss_frontend_static_maps_render_atlas_slice_stage_from_model(array $model, array $options = []): string
{
    if (!empty($model['empty_html'])) {
        return (string) $model['empty_html'];
    }

    $markers = '';
    $interactive_markers = !empty($options['interactive_markers']);
    $map_id = sanitize_html_class((string) ($options['map_id'] ?? ''));

    foreach (($model['stations'] ?? []) as $station) {
        if (!is_array($station)) {
            continue;
        }

        $index = (int) ($station['index'] ?? 0);
        $place = is_array($station['place'] ?? null) ? $station['place'] : [];
        $place_name = trim((string) ($place['label'] ?? ''));
        if ($place_name === '') {
            $place_name = trim((string) ($place['title'] ?? ''));
        }
        $marker_classes = ['iss-related-place-map__marker'];
        $marker_attrs = ' data-place-name="' . esc_attr($place_name) . '"';

        if ($interactive_markers) {
            if ($index === 0) {
                $marker_classes[] = 'is-active';
                $marker_attrs .= ' aria-current="location"';
            }
            if ($map_id !== '') {
                $marker_attrs .= ' aria-controls="' . esc_attr($map_id . '-detail') . '"';
            }
            $marker_attrs .= ' data-iss-map-marker="' . esc_attr((string) $index) . '"';
            $marker_attrs .= ' data-iss-map-index="' . esc_attr((string) $index) . '"';
        }

        $markers .= sprintf(
            '<a class="%1$s" href="%2$s" style="--x:%3$s%%;--y:%4$s%%" aria-label="%5$s"%6$s><span class="iss-related-place-map__marker-dot" aria-hidden="true"></span><span class="iss-related-place-map__marker-label">%7$s</span></a>',
            esc_attr(implode(' ', $marker_classes)),
            esc_url((string) ($place['permalink'] ?? '')),
            esc_attr(number_format((float) ($station['x'] ?? 0.0), 3, '.', '')),
            esc_attr(number_format((float) ($station['y'] ?? 0.0), 3, '.', '')),
            esc_attr((string) ($station['marker_label'] ?? '')),
            $marker_attrs,
            esc_html((string) ($index + 1))
        );
    }

    $stage_classes = (array) ($model['stage_classes'] ?? []);
    $stage_classes[] = 'has-leaflet-image-viewport';
    $viewport_markers = [];

    foreach ((array) ($model['stations'] ?? []) as $station) {
        if (!is_array($station)) {
            continue;
        }

        $place = is_array($station['place'] ?? null) ? $station['place'] : [];
        $place_name = trim((string) ($place['label'] ?? ''));
        if ($place_name === '') {
            $place_name = trim((string) ($place['title'] ?? ''));
        }
        $viewport_markers[] = [
            'index' => (int) ($station['index'] ?? 0),
            'x' => (float) ($station['raw_x'] ?? 0.0),
            'y' => (float) ($station['raw_y'] ?? 0.0),
            'label' => (string) ($station['marker_label'] ?? ''),
            'placeName' => $place_name,
            'url' => (string) ($place['permalink'] ?? ''),
        ];
    }

    $viewport_config = [
        'imageUrl' => (string) ($model['image_url'] ?? ''),
        'imageAlt' => (string) ($model['image_alt'] ?? ''),
        'imageWidth' => max(1, absint($model['image_width'] ?? 1)),
        'imageHeight' => max(1, absint($model['image_height'] ?? 1)),
        'imageSources' => is_array($model['image_sources'] ?? null) ? $model['image_sources'] : [],
        'lineMode' => sanitize_key((string) ($model['line_mode'] ?? 'none')),
        'interactiveMarkers' => !empty($options['interactive_markers']),
        'mapId' => sanitize_html_class((string) ($options['map_id'] ?? '')),
        'markers' => $viewport_markers,
    ];
    $leaflet_markup = '<div class="iss-static-map-leaflet" data-iss-static-map-leaflet data-map-config="' . esc_attr((string) wp_json_encode($viewport_config)) . '" aria-hidden="true"></div>';
    $source_set = [];
    foreach ((array) ($model['image_sources'] ?? []) as $source) {
        if (!is_array($source) || trim((string) ($source['url'] ?? '')) === '' || absint($source['width'] ?? 0) <= 0) {
            continue;
        }

        $source_set[] = esc_url((string) $source['url']) . ' ' . absint($source['width']) . 'w';
    }
    $responsive_attrs = $source_set
        ? ' srcset="' . esc_attr(implode(', ', $source_set)) . '" sizes="(max-width: 680px) calc(100vw - 2.5rem), 75vw"'
        : '';
    $fallback = '<div class="iss-static-map-fallback"><div class="iss-atlas-slice__viewport"><img class="iss-atlas-slice__image" src="' . esc_url((string) ($model['image_url'] ?? '')) . '"' . $responsive_attrs . ' alt="' . esc_attr((string) ($model['image_alt'] ?? '')) . '" loading="lazy" decoding="async">' . (string) ($model['route_line'] ?? '') . '<div class="iss-atlas-slice__markers">' . $markers . '</div></div></div>';

    return '<div class="' . esc_attr(implode(' ', $stage_classes)) . '"' . (string) ($model['stage_attr'] ?? '') . '>' . $fallback . $leaflet_markup . '</div>';
}

function iss_frontend_static_maps_get_atlas_slice_model_places(array $model): array
{
    $places = [];

    foreach (($model['stations'] ?? []) as $station) {
        if (!is_array($station) || !is_array($station['place'] ?? null)) {
            continue;
        }

        $places[] = $station['place'];
    }

    return $places;
}

function iss_frontend_static_maps_render_atlas_slice_stage(array $places, array $config, array $options = []): string
{
    $model = iss_frontend_static_maps_get_atlas_slice_model($places, $config, $options);

    return iss_frontend_static_maps_render_atlas_slice_stage_from_model($model, $options);
}

function iss_frontend_render_related_place_map_body(array $attributes, array $places, array $config): string
{
    $panel_mode = function_exists('iss_relations_normalize_place_map_panel_mode')
        ? iss_relations_normalize_place_map_panel_mode($attributes)
        : 'show';
    $panel_position = function_exists('iss_relations_normalize_place_map_panel_position')
        ? iss_relations_normalize_place_map_panel_position($attributes)
        : 'right';
    $body_classes = ['iss-related-place-map__body', 'iss-related-place-map__body--panel-' . $panel_mode];

    if ($panel_mode === 'show') {
        $body_classes[] = 'iss-related-place-map__body--panel-' . $panel_position;
    }

    if (function_exists('iss_frontend_static_maps_enqueue_image_viewport_assets')) {
        iss_frontend_static_maps_enqueue_image_viewport_assets();
    }
    $stage_options = [
        'class_name' => 'iss-related-place-map__stage',
        'ratio_width' => max(1, absint($config['width'] ?? 1600)),
        'ratio_height' => max(1, absint($config['height'] ?? 900)),
    ];
    $stage_model = iss_frontend_static_maps_get_atlas_slice_model($places, $config, $stage_options);

    $out = '<div class="' . esc_attr(implode(' ', $body_classes)) . '">';
    $out .= iss_frontend_static_maps_render_atlas_slice_stage_from_model($stage_model, $stage_options);
    if ($panel_mode === 'show') {
        $out .= iss_frontend_static_maps_render_place_map_panel($places);
    }
    $out .= '</div>';

    return $out;
}

function iss_frontend_render_atlas_map_block(array $attributes, array $places, array $config): string
{
    $variant = sanitize_key((string) ($attributes['variant'] ?? 'place-locator'));
    $skin = sanitize_key((string) ($attributes['skin'] ?? $variant));
    $treatment = sanitize_key((string) ($attributes['treatment'] ?? 'stage'));
    $panel_mode = sanitize_key((string) ($attributes['panelMode'] ?? 'hide'));
    $panel_position = sanitize_key((string) ($attributes['panelPosition'] ?? 'right'));
    $line_mode = sanitize_key((string) ($attributes['lineMode'] ?? 'none'));
    $ratio_width = max(1, absint($attributes['ratioWidth'] ?? 1600));
    $ratio_height = max(1, absint($attributes['ratioHeight'] ?? 720));

    if (!in_array($panel_mode, ['show', 'hide'], true)) {
        $panel_mode = 'hide';
    }

    if (!in_array($panel_position, ['right', 'below'], true)) {
        $panel_position = 'right';
    }

    if (!in_array($line_mode, ['none', 'route'], true)) {
        $line_mode = 'none';
    }

    $body_classes = [
        'iss-gesture-atlas-map',
        'iss-gesture-atlas-map--variant-' . sanitize_html_class($variant),
        'iss-gesture-atlas-map--skin-' . sanitize_html_class($skin),
        'iss-gesture-atlas-map--treatment-' . sanitize_html_class($treatment),
        'iss-related-place-map__body',
        'iss-related-place-map__body--panel-' . $panel_mode,
    ];

    if ($panel_mode === 'show') {
        $body_classes[] = 'iss-related-place-map__body--panel-' . $panel_position;
    }

    if ($line_mode === 'route') {
        $body_classes[] = 'iss-gesture-atlas-map--has-route-line';
    }

    $show_markers = !array_key_exists('showMarkers', $attributes) || !empty($attributes['showMarkers']);
    $interactive_panel = $variant === 'tour-route' && $panel_mode === 'show';
    $map_id = function_exists('wp_unique_id')
        ? wp_unique_id('iss-atlas-map-')
        : uniqid('iss-atlas-map-', false);
    $stage_options = [
        'class_name' => 'iss-gesture-atlas-map__stage iss-gesture-atlas-map__stage--' . $treatment,
        'ratio_width' => $ratio_width,
        'ratio_height' => $ratio_height,
        'show_markers' => $show_markers,
        'line_mode' => $line_mode,
        'interactive_markers' => $interactive_panel,
        'map_id' => $map_id,
    ];
    if (function_exists('iss_frontend_static_maps_enqueue_image_viewport_assets')) {
        iss_frontend_static_maps_enqueue_image_viewport_assets();
    }
    $slice_model = iss_frontend_static_maps_get_atlas_slice_model($places, $config, $stage_options);
    $panel_places = $interactive_panel
        ? iss_frontend_static_maps_get_atlas_slice_model_places($slice_model)
        : $places;
    $stage_html = iss_frontend_static_maps_render_atlas_slice_stage_from_model($slice_model, $stage_options);

    $body_attrs = ' class="' . esc_attr(implode(' ', $body_classes)) . '"';
    if ($interactive_panel) {
        $body_attrs .= ' data-iss-atlas-map-interactive="station-detail"';
    }

    $out = '<div' . $body_attrs . '>';
    $out .= '<div class="iss-gesture-atlas-map__map">' . $stage_html . '</div>';
    if ($panel_mode === 'show') {
        $out .= iss_frontend_static_maps_render_place_map_panel($panel_places, [
            'presentation' => $interactive_panel ? 'station-detail' : 'list',
            'map_id' => $map_id,
        ]);
    }
    $out .= '</div>';

    return $out;
}

function iss_frontend_render_atlas_slice_block(array $attributes, array $places, array $config): string
{
    $body_mode = function_exists('iss_relations_normalize_atlas_slice_body_mode')
        ? iss_relations_normalize_atlas_slice_body_mode($attributes)
        : 'text';
    $body_position = function_exists('iss_relations_normalize_atlas_slice_body_position')
        ? iss_relations_normalize_atlas_slice_body_position($attributes)
        : 'end';
    $layout_mode = function_exists('iss_relations_normalize_atlas_slice_layout_mode')
        ? iss_relations_normalize_atlas_slice_layout_mode($attributes)
        : 'band';
    $body_html = $body_mode === 'image' && function_exists('iss_relations_render_atlas_slice_image_body')
        ? iss_relations_render_atlas_slice_image_body($attributes)
        : '';

    if ($body_html === '' && function_exists('iss_relations_render_atlas_slice_copy_body')) {
        $body_mode = 'text';
        $body_html = iss_relations_render_atlas_slice_copy_body($attributes, $places);
    }

    $ratio_width = $layout_mode === 'split' ? 760 : 1600;
    $ratio_height = $layout_mode === 'split' ? 1140 : 720;
    $stage_options = [
        'class_name' => 'iss-atlas-slice__stage--' . $layout_mode,
        'ratio_width' => $ratio_width,
        'ratio_height' => $ratio_height,
    ];
    if (function_exists('iss_frontend_static_maps_enqueue_image_viewport_assets')) {
        iss_frontend_static_maps_enqueue_image_viewport_assets();
    }
    $stage_html = iss_frontend_static_maps_render_atlas_slice_stage($places, $config, $stage_options);

    $classes = [
        'iss-atlas-slice',
        'iss-atlas-slice--layout-' . $layout_mode,
        'iss-atlas-slice--body-' . $body_mode,
        'iss-atlas-slice--body-' . $body_position,
    ];

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => implode(' ', $classes),
        ])
        : 'class="' . esc_attr(implode(' ', $classes)) . '"';

    $map_shell = '<div class="iss-atlas-slice__map">' . $stage_html . '</div>';
    $parts = $body_position === 'start'
        ? [$body_html, $map_shell]
        : [$map_shell, $body_html];

    return '<div ' . $wrapper . '>' . implode('', $parts) . '</div>';
}

function iss_frontend_render_atlas_strip_block(array $attributes, array $places, array $config): string
{
    $variant = function_exists('iss_relations_normalize_atlas_strip_variant')
        ? iss_relations_normalize_atlas_strip_variant($attributes)
        : 'place';

    if ($variant === 'spine' && function_exists('iss_frontend_render_spine_strip_block')) {
        return iss_frontend_render_spine_strip_block($attributes, $places, $config);
    }

    $body_position = function_exists('iss_relations_normalize_atlas_slice_body_position')
        ? iss_relations_normalize_atlas_slice_body_position($attributes)
        : 'end';
    $body_html = $variant === 'minimal' || !function_exists('iss_relations_render_atlas_slice_copy_body')
        ? ''
        : iss_relations_render_atlas_slice_copy_body($attributes, $places);
    $ratio_width = 1600;
    $ratio_height = $variant === 'corridor' ? 420 : ($variant === 'minimal' ? 220 : 520);

    $stage_options = [
        'class_name' => 'iss-atlas-strip__stage iss-atlas-strip__stage--' . $variant,
        'ratio_width' => $ratio_width,
        'ratio_height' => $ratio_height,
    ];
    if (function_exists('iss_frontend_static_maps_enqueue_image_viewport_assets')) {
        iss_frontend_static_maps_enqueue_image_viewport_assets();
    }
    $stage_html = iss_frontend_static_maps_render_atlas_slice_stage($places, $config, $stage_options);

    $classes = [
        'iss-atlas-strip',
        'iss-atlas-strip--' . $variant,
        'iss-atlas-slice',
        'iss-atlas-slice--layout-band',
    ];

    if ($body_html !== '') {
        $classes[] = 'iss-atlas-slice--body-text';
        $classes[] = 'iss-atlas-slice--body-' . $body_position;
    } else {
        $classes[] = 'iss-atlas-strip--body-none';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => implode(' ', $classes),
        ])
        : 'class="' . esc_attr(implode(' ', $classes)) . '"';
    $map_shell = '<div class="iss-atlas-slice__map iss-atlas-strip__map">' . $stage_html . '</div>';

    if ($body_html === '') {
        return '<div ' . $wrapper . '>' . $map_shell . '</div>';
    }

    $parts = $body_position === 'start'
        ? [$body_html, $map_shell]
        : [$map_shell, $body_html];

    return '<div ' . $wrapper . '>' . implode('', $parts) . '</div>';
}

function iss_frontend_render_spine_strip_block(array $attributes, array $places, array $config): string
{
    return function_exists('iss_relations_render_spine_strip_core')
        ? iss_relations_render_spine_strip_core($attributes, $places, $config)
        : '';
}
