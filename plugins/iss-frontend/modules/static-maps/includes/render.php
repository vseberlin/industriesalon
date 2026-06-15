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

function iss_frontend_static_maps_normalize_rotation_degrees($value): float
{
    if (!is_numeric($value)) {
        return 0.0;
    }

    $rotation = fmod((float) $value, 360.0);

    if ($rotation > 180.0) {
        $rotation -= 360.0;
    }

    if ($rotation <= -180.0) {
        $rotation += 360.0;
    }

    return round($rotation, 3);
}

function iss_frontend_static_maps_normalize_plane_scale($value): float
{
    $scale = is_numeric($value) ? (float) $value : 1.0;

    return max(0.9, min(1.25, round($scale, 3)));
}

function iss_frontend_static_maps_get_stage_rotation_class(): string
{
    return 'is-map-rotation';
}

function iss_frontend_static_maps_get_rotation_fit_scale(float $rotation_deg, int $ratio_width, int $ratio_height): float
{
    if (abs($rotation_deg) < 0.001) {
        return 1.0;
    }

    if (abs(fmod(abs($rotation_deg), 90.0)) > 0.001) {
        return 1.0;
    }

    $width = max(1.0, (float) $ratio_width);
    $height = max(1.0, (float) $ratio_height);
    $theta = deg2rad($rotation_deg);
    $cos = abs(cos($theta));
    $sin = abs(sin($theta));
    $bbox_width = ($width * $cos) + ($height * $sin);
    $bbox_height = ($width * $sin) + ($height * $cos);

    if ($bbox_width <= 0.0 || $bbox_height <= 0.0) {
        return 1.0;
    }

    return min($width / $bbox_width, $height / $bbox_height);
}

function iss_frontend_static_maps_project_plane_point(float $x, float $y, float $rotation_deg, float $rotation_scale, float $bias_x = 0.0, float $bias_y = 0.0): array
{
    $theta = deg2rad($rotation_deg);
    $dx = ($x - 50.0) * $rotation_scale;
    $dy = ($y - 50.0) * $rotation_scale;

    return [
        'x' => 50.0 + (($dx * cos($theta)) - ($dy * sin($theta))) + $bias_x,
        'y' => 50.0 + (($dx * sin($theta)) + ($dy * cos($theta))) + $bias_y,
    ];
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

function iss_frontend_static_maps_get_focus_window(array $mapped_places, array $config, int $ratio_width, int $ratio_height): array
{
    $source_width = max(1, absint($config['width'] ?? 4096));
    $source_height = max(1, absint($config['height'] ?? 2389));
    $stage_ratio = $ratio_width / max(1, $ratio_height);
    $source_ratio = $source_width / $source_height;
    $source_target_ratio = $stage_ratio / max(0.0001, $source_ratio);
    $xs = [];
    $ys = [];

    foreach ($mapped_places as $item) {
        $xs[] = (float) ($item['position']['x'] ?? 0.0);
        $ys[] = (float) ($item['position']['y'] ?? 0.0);
    }

    $x_min = min($xs);
    $x_max = max($xs);
    $y_min = min($ys);
    $y_max = max($ys);
    $bbox_width = max(2.0, $x_max - $x_min);
    $bbox_height = max(2.0, $y_max - $y_min);
    $margin_x = 7.5;
    $margin_y = 9.5;
    $window_width = $bbox_width + ($margin_x * 2);
    $window_height = $bbox_height + ($margin_y * 2);
    $min_window_height = count($mapped_places) === 1 ? 34.0 : 28.0;
    $min_window_width = $min_window_height * $source_target_ratio;

    if (($window_width / $window_height) < $source_target_ratio) {
        $window_width = $window_height * $source_target_ratio;
    } else {
        $window_height = $window_width / max(0.0001, $source_target_ratio);
    }

    $window_width = max($window_width, $min_window_width);
    $window_height = max($window_height, $min_window_height);

    if ($window_width > 100.0) {
        $window_width = 100.0;
        $window_height = $window_width / max(0.0001, $source_target_ratio);
    }

    if ($window_height > 100.0) {
        $window_height = 100.0;
        $window_width = min(100.0, $window_height * $source_target_ratio);
    }

    $center_x = ($x_min + $x_max) / 2;
    $center_y = ($y_min + $y_max) / 2;

    return [
        'x' => iss_frontend_static_maps_clamp_float($center_x - ($window_width / 2), 0.0, 100.0 - $window_width),
        'y' => iss_frontend_static_maps_clamp_float($center_y - ($window_height / 2), 0.0, 100.0 - $window_height),
        'width' => $window_width,
        'height' => $window_height,
    ];
}

function iss_frontend_static_maps_render_place_map_stage(array $places, array $config, array $options = []): string
{
    $image_url = $config['image_url'] ?? '';
    $image_alt = $config['image_alt'] ?? '';
    $marker_lookup = iss_frontend_static_maps_get_marker_lookup((string) ($config['markers_path'] ?? ''));
    $rotation_deg = iss_frontend_static_maps_normalize_rotation_degrees($options['rotation_deg'] ?? ($config['rotation_deg'] ?? 0));

    if ($image_url === '' || !$marker_lookup) {
        return '<div class="iss-related-place-map__empty">' . esc_html__('Für diese Auswahl ist noch keine Atlaskarte hinterlegt.', 'iss-frontend') . '</div>';
    }

    $mapped_places = [];

    foreach ($places as $index => $place) {
        $position = iss_frontend_static_maps_get_marker_position($place, $marker_lookup);
        if ($position === null) {
            continue;
        }

        $mapped_places[] = [
            'index' => $index,
            'place' => $place,
            'position' => $position,
        ];
    }

    if (!$mapped_places) {
        return '<div class="iss-related-place-map__empty">' . esc_html__('Für diese Auswahl sind noch keine Koordinaten hinterlegt.', 'iss-frontend') . '</div>';
    }

    $ratio_width = max(1, absint($options['ratio_width'] ?? ($config['width'] ?? 0)));
    $ratio_height = max(1, absint($options['ratio_height'] ?? ($config['height'] ?? 0)));
    $plane_bias = [
        'x' => is_numeric($options['bias_x'] ?? null) ? (float) $options['bias_x'] : 0.0,
        'y' => is_numeric($options['bias_y'] ?? null) ? (float) $options['bias_y'] : 0.0,
    ];
    $stage_styles = [];

    if ($ratio_width > 0 && $ratio_height > 0) {
        $stage_styles[] = '--iss-related-place-map-ratio:' . $ratio_width . ' / ' . $ratio_height;
    }

    $viewport = is_array($config['viewport'] ?? null) ? $config['viewport'] : [];
    $scale = isset($viewport['scale']) && is_numeric($viewport['scale']) ? (float) $viewport['scale'] : 1.0;
    $scale_x = isset($viewport['scale_x']) && is_numeric($viewport['scale_x']) ? (float) $viewport['scale_x'] : $scale;
    $scale_y = isset($viewport['scale_y']) && is_numeric($viewport['scale_y']) ? (float) $viewport['scale_y'] : $scale;
    $offset_x = isset($viewport['offset_x']) && is_numeric($viewport['offset_x']) ? (float) $viewport['offset_x'] : 0.0;
    $offset_y = isset($viewport['offset_y']) && is_numeric($viewport['offset_y']) ? (float) $viewport['offset_y'] : 0.0;

    $stage_styles[] = '--iss-related-place-map-scale-x:' . number_format($scale_x > 0 ? $scale_x : 1.0, 4, '.', '');
    $stage_styles[] = '--iss-related-place-map-scale-y:' . number_format($scale_y > 0 ? $scale_y : 1.0, 4, '.', '');
    $stage_styles[] = '--iss-related-place-map-offset-x:' . number_format($offset_x, 3, '.', '') . '%';
    $stage_styles[] = '--iss-related-place-map-offset-y:' . number_format($offset_y, 3, '.', '') . '%';

    $markers = '';

    foreach ($mapped_places as $item) {
        $index = (int) $item['index'];
        $place = $item['place'];
        $position = $item['position'];
        $marker_x = ((float) $position['x'] * ($scale_x > 0 ? $scale_x : 1.0)) + $offset_x;
        $marker_y = ((float) $position['y'] * ($scale_y > 0 ? $scale_y : 1.0)) + $offset_y;
        $label = trim((string) ($place['label'] ?? ''));
        $marker_label = $label !== '' ? ($label . ': ' . $place['title']) : $place['title'];

        $markers .= sprintf(
            '<a class="iss-related-place-map__marker" href="%1$s" style="--x:%2$s%%;--y:%3$s%%" aria-label="%4$s"><span class="iss-related-place-map__marker-dot" aria-hidden="true"></span><span class="iss-related-place-map__marker-label">%5$s</span></a>',
            esc_url((string) ($place['permalink'] ?? '')),
            esc_attr(number_format($marker_x, 3, '.', '')),
            esc_attr(number_format($marker_y, 3, '.', '')),
            esc_attr($marker_label),
            esc_html((string) ($index + 1))
        );
    }

    $stage_classes = ['iss-related-place-map__stage'];
    $extra_class = trim((string) ($options['class_name'] ?? ''));
    if ($extra_class !== '') {
        foreach (preg_split('/\s+/', $extra_class) as $class_name) {
            $class_name = sanitize_html_class($class_name);
            if ($class_name !== '') {
                $stage_classes[] = $class_name;
            }
        }
    }

    $plane_scale = iss_frontend_static_maps_normalize_plane_scale($options['map_scale'] ?? 1.0);
    $rotation_scale = iss_frontend_static_maps_get_rotation_fit_scale($rotation_deg, $ratio_width, $ratio_height) * $plane_scale;
    $plane_classes = [
        'iss-related-place-map__plane',
        iss_frontend_static_maps_get_stage_rotation_class(),
    ];
    $plane_attr = ' style="' . esc_attr(implode(';', [
        '--iss-map-rotation-deg:' . number_format($rotation_deg, 3, '.', '') . 'deg',
        '--iss-map-rotation-scale:' . number_format($rotation_scale, 6, '.', ''),
        '--iss-map-bias-x:' . number_format($plane_bias['x'], 3, '.', '') . '%',
        '--iss-map-bias-y:' . number_format($plane_bias['y'], 3, '.', '') . '%',
    ])) . '"';
    $stage_attr = $stage_styles ? ' style="' . esc_attr(implode(';', $stage_styles)) . '"' : '';

    return '<div class="' . esc_attr(implode(' ', $stage_classes)) . '"' . $stage_attr . '><div class="' . esc_attr(implode(' ', $plane_classes)) . '"' . $plane_attr . '><div class="iss-related-place-map__viewport"><img class="iss-related-place-map__image" src="' . esc_url($image_url) . '" alt="' . esc_attr($image_alt) . '" loading="lazy" decoding="async"></div><div class="iss-related-place-map__markers">' . $markers . '</div></div></div>';
}

function iss_frontend_static_maps_render_place_map_panel(array $places): string
{
    $out = '<div class="iss-related-place-map__panel">';

    foreach ($places as $index => $place) {
        $permalink = (string) ($place['permalink'] ?? '');
        $title = trim((string) ($place['title'] ?? ''));
        $label = trim((string) ($place['label'] ?? ''));
        $excerpt = trim((string) ($place['excerpt'] ?? ''));

        $out .= '<article class="iss-related-place-map__entry">';
        $out .= '<div class="iss-related-place-map__entry-index">' . esc_html((string) ($index + 1)) . '</div>';
        $out .= '<div class="iss-related-place-map__entry-body">';
        if ($label !== '') {
            $out .= '<p class="iss-related-place-map__entry-kicker">' . esc_html($label) . '</p>';
        }
        $out .= '<h3 class="iss-related-place-map__entry-title"><a href="' . esc_url($permalink) . '">' . esc_html($title) . '</a></h3>';
        if ($excerpt !== '') {
            $out .= '<p class="iss-related-place-map__entry-text">' . esc_html($excerpt) . '</p>';
        }
        $out .= '<p class="iss-related-place-map__entry-link"><a class="iss-action-link" href="' . esc_url($permalink) . '">' . esc_html__('Zum Ort', 'iss-frontend') . '</a></p>';
        $out .= '</div>';
        $out .= '</article>';
    }

    $out .= '</div>';

    return $out;
}

function iss_frontend_static_maps_render_atlas_slice_stage(array $places, array $config, array $options = []): string
{
    $mapped_places = iss_frontend_static_maps_collect_mapped_places($places, $config);
    $rotation_deg = iss_frontend_static_maps_normalize_rotation_degrees($options['rotation_deg'] ?? ($config['rotation_deg'] ?? 0));
    if (!$mapped_places) {
        return '<div class="iss-related-place-map__empty">' . esc_html__('Für diese Auswahl sind noch keine Koordinaten hinterlegt.', 'iss-frontend') . '</div>';
    }

    $image_url = (string) ($config['image_url'] ?? '');
    if ($image_url === '') {
        return '<div class="iss-related-place-map__empty">' . esc_html__('Für diese Auswahl ist noch keine Atlaskarte hinterlegt.', 'iss-frontend') . '</div>';
    }

    $image_alt = (string) ($config['image_alt'] ?? '');
    $ratio_width = max(1, absint($options['ratio_width'] ?? 1600));
    $ratio_height = max(1, absint($options['ratio_height'] ?? 720));
    $plane_bias = [
        'x' => is_numeric($options['bias_x'] ?? null) ? (float) $options['bias_x'] : 0.0,
        'y' => is_numeric($options['bias_y'] ?? null) ? (float) $options['bias_y'] : 0.0,
    ];
    $crop_mode = sanitize_key((string) ($config['crop_mode'] ?? 'dynamic'));
    $show_markers = !array_key_exists('show_markers', $options) || !empty($options['show_markers']);

    if ($crop_mode === 'fixed') {
        $window = [
            'x' => 0.0,
            'y' => 0.0,
            'width' => 100.0,
            'height' => 100.0,
        ];
        $image_width = 100.0;
        $image_height = 100.0;
        $image_left = 0.0;
        $image_top = 0.0;
    } else {
        $window = iss_frontend_static_maps_get_focus_window($mapped_places, $config, $ratio_width, $ratio_height);
        $image_width = 10000 / max(0.001, $window['width']);
        $image_height = 10000 / max(0.001, $window['height']);
        $image_left = -($window['x'] / max(0.001, $window['width'])) * 100;
        $image_top = -($window['y'] / max(0.001, $window['height'])) * 100;
    }

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

    $stage_attr = ' style="' . esc_attr(implode(';', [
        '--iss-atlas-slice-ratio:' . $ratio_width . ' / ' . $ratio_height,
        '--iss-atlas-slice-image-width:' . number_format($image_width, 4, '.', '') . '%',
        '--iss-atlas-slice-image-height:' . number_format($image_height, 4, '.', '') . '%',
        '--iss-atlas-slice-image-left:' . number_format($image_left, 4, '.', '') . '%',
        '--iss-atlas-slice-image-top:' . number_format($image_top, 4, '.', '') . '%',
    ])) . '"';
    $markers = '';

    if ($show_markers) {
        foreach ($mapped_places as $item) {
            $raw_x = (float) ($item['position']['x'] ?? 0.0);
            $raw_y = (float) ($item['position']['y'] ?? 0.0);
            if ($crop_mode === 'fixed') {
                $marker_x = $raw_x;
                $marker_y = $raw_y;
            } else {
                $marker_x = (($raw_x - $window['x']) / max(0.001, $window['width'])) * 100;
                $marker_y = (($raw_y - $window['y']) / max(0.001, $window['height'])) * 100;
            }
            $place = $item['place'];
            $index = (int) $item['index'];
            $label = trim((string) ($place['label'] ?? ''));
            $marker_label = $label !== '' ? ($label . ': ' . $place['title']) : $place['title'];

            $markers .= sprintf(
                '<a class="iss-related-place-map__marker" href="%1$s" style="--x:%2$s%%;--y:%3$s%%" aria-label="%4$s"><span class="iss-related-place-map__marker-dot" aria-hidden="true"></span><span class="iss-related-place-map__marker-label">%5$s</span></a>',
                esc_url((string) ($place['permalink'] ?? '')),
                esc_attr(number_format($marker_x, 3, '.', '')),
                esc_attr(number_format($marker_y, 3, '.', '')),
                esc_attr($marker_label),
                esc_html((string) ($index + 1))
            );
        }
    }

    $plane_scale = iss_frontend_static_maps_normalize_plane_scale($options['map_scale'] ?? 1.0);
    $rotation_scale = iss_frontend_static_maps_get_rotation_fit_scale($rotation_deg, $ratio_width, $ratio_height) * $plane_scale;
    $plane_classes = [
        'iss-atlas-slice__plane',
        iss_frontend_static_maps_get_stage_rotation_class(),
    ];
    $plane_attr = ' style="' . esc_attr(implode(';', [
        '--iss-map-rotation-deg:' . number_format($rotation_deg, 3, '.', '') . 'deg',
        '--iss-map-rotation-scale:' . number_format($rotation_scale, 6, '.', ''),
        '--iss-map-bias-x:' . number_format($plane_bias['x'], 3, '.', '') . '%',
        '--iss-map-bias-y:' . number_format($plane_bias['y'], 3, '.', '') . '%',
    ])) . '"';

    return '<div class="' . esc_attr(implode(' ', $stage_classes)) . '"' . $stage_attr . '><div class="' . esc_attr(implode(' ', $plane_classes)) . '"' . $plane_attr . '><div class="iss-atlas-slice__viewport"><img class="iss-atlas-slice__image" src="' . esc_url($image_url) . '" alt="' . esc_attr($image_alt) . '" loading="lazy" decoding="async"><div class="iss-atlas-slice__markers">' . $markers . '</div></div></div></div>';
}

function iss_frontend_render_related_place_map_body(array $attributes, array $places, array $config): string
{
    $panel_mode = function_exists('iss_relations_normalize_place_map_panel_mode')
        ? iss_relations_normalize_place_map_panel_mode($attributes)
        : 'show';
    $panel_position = function_exists('iss_relations_normalize_place_map_panel_position')
        ? iss_relations_normalize_place_map_panel_position($attributes)
        : 'right';
    $rotation_deg = function_exists('iss_relations_get_map_rotation_degrees')
        ? iss_relations_get_map_rotation_degrees($attributes, $config)
        : 0.0;
    $plane_bias = function_exists('iss_relations_get_map_plane_bias')
        ? iss_relations_get_map_plane_bias($attributes)
        : ['x' => 0.0, 'y' => 0.0];
    $plane_scale = function_exists('iss_relations_get_map_plane_scale')
        ? iss_relations_get_map_plane_scale($attributes)
        : 1.0;
    $body_classes = ['iss-related-place-map__body', 'iss-related-place-map__body--panel-' . $panel_mode];

    if ($panel_mode === 'show') {
        $body_classes[] = 'iss-related-place-map__body--panel-' . $panel_position;
    }

    $out = '<div class="' . esc_attr(implode(' ', $body_classes)) . '">';
    $out .= iss_frontend_static_maps_render_place_map_stage($places, $config, [
        'rotation_deg' => $rotation_deg,
        'bias_x' => $plane_bias['x'],
        'bias_y' => $plane_bias['y'],
        'map_scale' => $plane_scale,
    ]);
    if ($panel_mode === 'show') {
        $out .= iss_frontend_static_maps_render_place_map_panel($places);
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
    $framing_mode = function_exists('iss_relations_normalize_map_framing_mode')
        ? iss_relations_normalize_map_framing_mode($attributes)
        : 'inherit';
    $rotation_deg = function_exists('iss_relations_get_map_rotation_degrees')
        ? iss_relations_get_map_rotation_degrees($attributes, $config)
        : 0.0;
    $plane_bias = function_exists('iss_relations_get_map_plane_bias')
        ? iss_relations_get_map_plane_bias($attributes)
        : ['x' => 0.0, 'y' => 0.0];
    $plane_scale = function_exists('iss_relations_get_map_plane_scale')
        ? iss_relations_get_map_plane_scale($attributes)
        : 1.0;
    $body_html = $body_mode === 'image' && function_exists('iss_relations_render_atlas_slice_image_body')
        ? iss_relations_render_atlas_slice_image_body($attributes)
        : '';

    if ($body_html === '' && function_exists('iss_relations_render_atlas_slice_copy_body')) {
        $body_mode = 'text';
        $body_html = iss_relations_render_atlas_slice_copy_body($attributes, $places);
    }

    $ratio_width = $layout_mode === 'split' ? 760 : 1600;
    $ratio_height = $layout_mode === 'split' ? 1140 : 720;
    if ($framing_mode === 'inherit') {
        $framing_mode = $layout_mode === 'split' ? 'auto' : 'preset';
    }

    $stage_options = [
        'class_name' => 'iss-atlas-slice__stage--' . $layout_mode,
        'ratio_width' => $ratio_width,
        'ratio_height' => $ratio_height,
        'rotation_deg' => $rotation_deg,
        'bias_x' => $plane_bias['x'],
        'bias_y' => $plane_bias['y'],
        'map_scale' => $plane_scale,
    ];

    if ($framing_mode === 'preset') {
        $stage_options['class_name'] = 'iss-atlas-slice__stage iss-atlas-slice__stage--' . $layout_mode;
        $stage_html = iss_frontend_static_maps_render_place_map_stage($places, $config, $stage_options);
    } else {
        $stage_html = iss_frontend_static_maps_render_atlas_slice_stage($places, $config, $stage_options);
    }

    $classes = [
        'iss-atlas-slice',
        'iss-atlas-slice--layout-' . $layout_mode,
        'iss-atlas-slice--body-' . $body_mode,
        'iss-atlas-slice--body-' . $body_position,
        'iss-atlas-slice--framing-' . $framing_mode,
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

    $framing_mode = function_exists('iss_relations_normalize_map_framing_mode')
        ? iss_relations_normalize_map_framing_mode($attributes)
        : 'inherit';
    $rotation_deg = function_exists('iss_relations_get_map_rotation_degrees')
        ? iss_relations_get_map_rotation_degrees($attributes, $config)
        : 0.0;
    $plane_bias = function_exists('iss_relations_get_map_plane_bias')
        ? iss_relations_get_map_plane_bias($attributes)
        : ['x' => 0.0, 'y' => 0.0];
    $plane_scale = function_exists('iss_relations_get_map_plane_scale')
        ? iss_relations_get_map_plane_scale($attributes)
        : 1.0;

    if ($framing_mode === 'inherit') {
        $framing_mode = 'auto';
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
        'rotation_deg' => $rotation_deg,
        'bias_x' => $plane_bias['x'],
        'bias_y' => $plane_bias['y'],
        'map_scale' => $plane_scale,
    ];

    if ($framing_mode === 'preset') {
        $stage_html = iss_frontend_static_maps_render_place_map_stage($places, $config, $stage_options);
    } else {
        $stage_html = iss_frontend_static_maps_render_atlas_slice_stage($places, $config, $stage_options);
    }

    $classes = [
        'iss-atlas-strip',
        'iss-atlas-strip--' . $variant,
        'iss-atlas-slice',
        'iss-atlas-slice--layout-band',
        'iss-atlas-strip--framing-' . $framing_mode,
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
