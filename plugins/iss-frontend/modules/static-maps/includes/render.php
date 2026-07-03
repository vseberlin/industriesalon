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

    return max(0.9, min(2.0, round($scale, 3)));
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

function iss_frontend_static_maps_get_marker_bounds(array $mapped_places): ?array
{
    if (!$mapped_places) {
        return null;
    }

    $xs = [];
    $ys = [];

    foreach ($mapped_places as $item) {
        $xs[] = (float) ($item['position']['x'] ?? 0.0);
        $ys[] = (float) ($item['position']['y'] ?? 0.0);
    }

    return [
        'x_min' => min($xs),
        'x_max' => max($xs),
        'y_min' => min($ys),
        'y_max' => max($ys),
    ];
}

function iss_frontend_static_maps_normalize_edge_padding(array $padding = []): array
{
    return [
        'left' => iss_frontend_static_maps_clamp_float(
            is_numeric($padding['left'] ?? null) ? (float) $padding['left'] : 7.0,
            0.0,
            35.0
        ),
        'right' => iss_frontend_static_maps_clamp_float(
            is_numeric($padding['right'] ?? null) ? (float) $padding['right'] : 7.0,
            0.0,
            35.0
        ),
        'top' => iss_frontend_static_maps_clamp_float(
            is_numeric($padding['top'] ?? null) ? (float) $padding['top'] : 12.0,
            0.0,
            40.0
        ),
        'bottom' => iss_frontend_static_maps_clamp_float(
            is_numeric($padding['bottom'] ?? null) ? (float) $padding['bottom'] : 12.0,
            0.0,
            40.0
        ),
    ];
}

function iss_frontend_static_maps_get_edge_focus_ratio_height(array $mapped_places, array $config, int $ratio_width, int $fallback_ratio_height, array $padding = [], array $limits = []): int
{
    if (count($mapped_places) < 2) {
        return $fallback_ratio_height;
    }

    $bounds = iss_frontend_static_maps_get_marker_bounds($mapped_places);
    if ($bounds === null) {
        return $fallback_ratio_height;
    }

    $source_width = max(1, absint($config['width'] ?? 4096));
    $source_height = max(1, absint($config['height'] ?? 2389));
    $source_ratio = $source_width / $source_height;
    $padding = iss_frontend_static_maps_normalize_edge_padding($padding);
    $available_x = max(10.0, 100.0 - $padding['left'] - $padding['right']);
    $available_y = max(10.0, 100.0 - $padding['top'] - $padding['bottom']);
    $bbox_width = max(1.0, $bounds['x_max'] - $bounds['x_min']);
    $bbox_height = max(1.0, $bounds['y_max'] - $bounds['y_min']);
    $window_width = $bbox_width / ($available_x / 100.0);
    $window_height = $bbox_height / ($available_y / 100.0);
    $stage_ratio = ($window_width / max(0.0001, $window_height)) * $source_ratio;

    if ($stage_ratio <= 0.0) {
        return $fallback_ratio_height;
    }

    $ratio_height = (int) round($ratio_width / $stage_ratio);
    $min_height = max(1, absint($limits['min_height'] ?? 480));
    $max_height = max($min_height, absint($limits['max_height'] ?? 1200));

    return max($min_height, min($max_height, $ratio_height));
}

function iss_frontend_static_maps_get_edge_focus_window(array $mapped_places, array $config, int $ratio_width, int $ratio_height, array $padding = [], array $limits = []): array
{
    if (count($mapped_places) < 2) {
        return iss_frontend_static_maps_get_focus_window($mapped_places, $config, $ratio_width, $ratio_height);
    }

    $source_width = max(1, absint($config['width'] ?? 4096));
    $source_height = max(1, absint($config['height'] ?? 2389));
    $stage_ratio = $ratio_width / max(1, $ratio_height);
    $source_ratio = $source_width / $source_height;
    $source_target_ratio = $stage_ratio / max(0.0001, $source_ratio);
    $padding = iss_frontend_static_maps_normalize_edge_padding($padding);
    $bounds = iss_frontend_static_maps_get_marker_bounds($mapped_places);

    if ($bounds === null) {
        return iss_frontend_static_maps_get_focus_window($mapped_places, $config, $ratio_width, $ratio_height);
    }

    $x_min = $bounds['x_min'];
    $x_max = $bounds['x_max'];
    $y_min = $bounds['y_min'];
    $y_max = $bounds['y_max'];
    $bbox_width = max(1.0, $x_max - $x_min);
    $bbox_height = max(1.0, $y_max - $y_min);
    $available_x = max(10.0, 100.0 - $padding['left'] - $padding['right']);
    $available_y = max(10.0, 100.0 - $padding['top'] - $padding['bottom']);
    $window_width = $bbox_width / ($available_x / 100.0);
    $window_height = $window_width / max(0.0001, $source_target_ratio);
    $min_window_height = iss_frontend_static_maps_clamp_float(
        is_numeric($limits['min_height'] ?? null) ? (float) $limits['min_height'] : 24.0,
        1.0,
        100.0
    );
    $min_window_width = $min_window_height * $source_target_ratio;

    if ($window_height < ($bbox_height / ($available_y / 100.0))) {
        $window_height = $bbox_height / ($available_y / 100.0);
        $window_width = $window_height * $source_target_ratio;
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

    return [
        'x' => iss_frontend_static_maps_clamp_float($x_min - (($padding['left'] / 100.0) * $window_width), 0.0, 100.0 - $window_width),
        'y' => iss_frontend_static_maps_clamp_float($y_min - (($padding['top'] / 100.0) * $window_height), 0.0, 100.0 - $window_height),
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
    $ratio_mode = sanitize_key((string) ($options['ratio_mode'] ?? 'fixed'));
    $plane_bias = [
        'x' => is_numeric($options['bias_x'] ?? null) ? (float) $options['bias_x'] : 0.0,
        'y' => is_numeric($options['bias_y'] ?? null) ? (float) $options['bias_y'] : 0.0,
    ];
    $crop_mode = sanitize_key((string) ($config['crop_mode'] ?? 'dynamic'));
    $fit_mode = sanitize_key((string) ($options['fit_mode'] ?? 'focus'));
    $show_markers = !array_key_exists('show_markers', $options) || !empty($options['show_markers']);
    $line_mode = sanitize_key((string) ($options['line_mode'] ?? 'none'));
    $fit_padding = [
        'left' => $options['fit_padding_left'] ?? null,
        'right' => $options['fit_padding_right'] ?? null,
        'top' => $options['fit_padding_top'] ?? null,
        'bottom' => $options['fit_padding_bottom'] ?? null,
    ];
    $fit_limits = [
        'min_height' => $options['fit_min_window_height'] ?? null,
    ];

    if ($crop_mode !== 'fixed' && $fit_mode === 'markers-edge' && $ratio_mode === 'markers-box') {
        $ratio_height = iss_frontend_static_maps_get_edge_focus_ratio_height($mapped_places, $config, $ratio_width, $ratio_height, $fit_padding, [
            'min_height' => $options['ratio_min_height'] ?? null,
            'max_height' => $options['ratio_max_height'] ?? null,
        ]);
    }

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
        if ($fit_mode === 'markers-edge') {
            $window = iss_frontend_static_maps_get_edge_focus_window($mapped_places, $config, $ratio_width, $ratio_height, $fit_padding, $fit_limits);
        } else {
            $window = iss_frontend_static_maps_get_focus_window($mapped_places, $config, $ratio_width, $ratio_height);
        }

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
    $route_points = [];

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

        $route_points[] = [
            'x' => iss_frontend_static_maps_clamp_float($marker_x, -20.0, 120.0),
            'y' => iss_frontend_static_maps_clamp_float($marker_y, -20.0, 120.0),
        ];

        if ($show_markers) {
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

    $route_line = '';
    if ($line_mode === 'route' && count($route_points) > 1) {
        $points = array_map(
            static fn($point) => number_format((float) $point['x'], 3, '.', '') . ',' . number_format((float) $point['y'], 3, '.', ''),
            $route_points
        );
        $route_line = '<svg class="iss-atlas-slice__route-line iss-gesture-atlas-map__route-line" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true" focusable="false"><polyline points="' . esc_attr(implode(' ', $points)) . '" vector-effect="non-scaling-stroke"></polyline></svg>';
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

    return '<div class="' . esc_attr(implode(' ', $stage_classes)) . '"' . $stage_attr . '><div class="' . esc_attr(implode(' ', $plane_classes)) . '"' . $plane_attr . '><div class="iss-atlas-slice__viewport"><img class="iss-atlas-slice__image" src="' . esc_url($image_url) . '" alt="' . esc_attr($image_alt) . '" loading="lazy" decoding="async">' . $route_line . '<div class="iss-atlas-slice__markers">' . $markers . '</div></div></div></div>';
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

function iss_frontend_render_atlas_map_block(array $attributes, array $places, array $config): string
{
    $variant = sanitize_key((string) ($attributes['variant'] ?? 'place-locator'));
    $skin = sanitize_key((string) ($attributes['skin'] ?? $variant));
    $treatment = sanitize_key((string) ($attributes['treatment'] ?? 'stage'));
    $panel_mode = sanitize_key((string) ($attributes['panelMode'] ?? 'hide'));
    $panel_position = sanitize_key((string) ($attributes['panelPosition'] ?? 'right'));
    $line_mode = sanitize_key((string) ($attributes['lineMode'] ?? 'none'));
    $fit_mode = sanitize_key((string) ($attributes['fitMode'] ?? 'markers-edge'));
    $ratio_mode = sanitize_key((string) ($attributes['ratioMode'] ?? 'fixed'));
    $ratio_width = max(1, absint($attributes['ratioWidth'] ?? 1600));
    $ratio_height = max(1, absint($attributes['ratioHeight'] ?? 720));
    $rotation_deg = function_exists('iss_relations_get_map_rotation_degrees')
        ? iss_relations_get_map_rotation_degrees($attributes, $config)
        : iss_frontend_static_maps_normalize_rotation_degrees($attributes['rotationDeg'] ?? ($config['rotation_deg'] ?? 0));
    $plane_bias = function_exists('iss_relations_get_map_plane_bias')
        ? iss_relations_get_map_plane_bias($attributes)
        : [
            'x' => is_numeric($attributes['biasX'] ?? null) ? (float) $attributes['biasX'] : 0.0,
            'y' => is_numeric($attributes['biasY'] ?? null) ? (float) $attributes['biasY'] : 0.0,
        ];
    $plane_scale = function_exists('iss_relations_get_map_plane_scale')
        ? iss_relations_get_map_plane_scale($attributes)
        : iss_frontend_static_maps_normalize_plane_scale($attributes['mapScale'] ?? 1.0);

    if (!in_array($panel_mode, ['show', 'hide'], true)) {
        $panel_mode = 'hide';
    }

    if (!in_array($panel_position, ['right', 'below'], true)) {
        $panel_position = 'right';
    }

    if (!in_array($line_mode, ['none', 'route'], true)) {
        $line_mode = 'none';
    }

    if (!in_array($fit_mode, ['focus', 'markers-edge'], true)) {
        $fit_mode = 'markers-edge';
    }

    if (!in_array($ratio_mode, ['fixed', 'markers-box'], true)) {
        $ratio_mode = 'fixed';
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
    $stage_html = iss_frontend_static_maps_render_atlas_slice_stage($places, $config, [
        'class_name' => 'iss-gesture-atlas-map__stage iss-gesture-atlas-map__stage--' . $treatment,
        'ratio_width' => $ratio_width,
        'ratio_height' => $ratio_height,
        'rotation_deg' => $rotation_deg,
        'bias_x' => $plane_bias['x'],
        'bias_y' => $plane_bias['y'],
        'map_scale' => $plane_scale,
        'show_markers' => $show_markers,
        'line_mode' => $line_mode,
        'fit_mode' => $fit_mode,
        'ratio_mode' => $ratio_mode,
        'ratio_min_height' => $attributes['ratioMinHeight'] ?? null,
        'ratio_max_height' => $attributes['ratioMaxHeight'] ?? null,
        'fit_min_window_height' => $attributes['fitMinWindowHeight'] ?? null,
        'fit_padding_left' => $attributes['fitPaddingLeft'] ?? null,
        'fit_padding_right' => $attributes['fitPaddingRight'] ?? null,
        'fit_padding_top' => $attributes['fitPaddingTop'] ?? null,
        'fit_padding_bottom' => $attributes['fitPaddingBottom'] ?? null,
    ]);

    $out = '<div class="' . esc_attr(implode(' ', $body_classes)) . '">';
    $out .= '<div class="iss-gesture-atlas-map__map">' . $stage_html . '</div>';
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
