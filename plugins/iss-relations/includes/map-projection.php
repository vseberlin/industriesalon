<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_relations_get_map_projection_profiles(): array
{
    $profiles = apply_filters('iss_relations_map_projection_profiles', []);

    return is_array($profiles) ? $profiles : [];
}

function iss_relations_get_map_projection_profile(string $profile_id): array
{
    $profiles = iss_relations_get_map_projection_profiles();

    if ($profile_id !== '' && isset($profiles[$profile_id]) && is_array($profiles[$profile_id])) {
        return $profiles[$profile_id];
    }

    $first_id = array_key_first($profiles);

    return $first_id !== null && is_array($profiles[$first_id] ?? null)
        ? $profiles[$first_id]
        : [];
}

function iss_relations_map_projection_read_json(string $path): array
{
    if ($path === '' || !is_readable($path)) {
        throw new RuntimeException(sprintf('Unreadable JSON file: %s', esc_html($path)));
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        throw new RuntimeException(sprintf('Invalid JSON file: %s', esc_html($path)));
    }

    return $decoded;
}

function iss_relations_map_projection_solve_3x3(array $matrix, array $values): array
{
    for ($column = 0; $column < 3; $column++) {
        $pivot = $column;
        for ($row = $column + 1; $row < 3; $row++) {
            if (abs((float) $matrix[$row][$column]) > abs((float) $matrix[$pivot][$column])) {
                $pivot = $row;
            }
        }

        if (abs((float) $matrix[$pivot][$column]) < 1.0e-12) {
            throw new RuntimeException('Calibration control points do not define a stable affine transform.');
        }

        if ($pivot !== $column) {
            [$matrix[$column], $matrix[$pivot]] = [$matrix[$pivot], $matrix[$column]];
            [$values[$column], $values[$pivot]] = [$values[$pivot], $values[$column]];
        }

        $divisor = (float) $matrix[$column][$column];
        for ($index = $column; $index < 3; $index++) {
            $matrix[$column][$index] = (float) $matrix[$column][$index] / $divisor;
        }
        $values[$column] = (float) $values[$column] / $divisor;

        for ($row = 0; $row < 3; $row++) {
            if ($row === $column) {
                continue;
            }

            $factor = (float) $matrix[$row][$column];
            for ($index = $column; $index < 3; $index++) {
                $matrix[$row][$index] = (float) $matrix[$row][$index] - ($factor * (float) $matrix[$column][$index]);
            }
            $values[$row] = (float) $values[$row] - ($factor * (float) $values[$column]);
        }
    }

    return array_map('floatval', $values);
}

function iss_relations_map_projection_fit_axis(array $points, string $axis, float $origin_lng, float $origin_lat): array
{
    $matrix = array_fill(0, 3, array_fill(0, 3, 0.0));
    $values = array_fill(0, 3, 0.0);

    foreach ($points as $point) {
        $features = [
            (float) $point['lng'] - $origin_lng,
            (float) $point['lat'] - $origin_lat,
            1.0,
        ];
        $target = (float) $point[$axis];

        for ($row = 0; $row < 3; $row++) {
            $values[$row] += $features[$row] * $target;
            for ($column = 0; $column < 3; $column++) {
                $matrix[$row][$column] += $features[$row] * $features[$column];
            }
        }
    }

    return iss_relations_map_projection_solve_3x3($matrix, $values);
}

function iss_relations_map_projection_build_transform(array $calibration): array
{
    $points = is_array($calibration['control_points'] ?? null) ? $calibration['control_points'] : [];
    if (count($points) < 3) {
        throw new RuntimeException('Map calibration requires at least three control points.');
    }

    foreach ($points as $point) {
        foreach (['lng', 'lat', 'xNorm', 'yNorm'] as $key) {
            if (!is_numeric($point[$key] ?? null)) {
                throw new RuntimeException(sprintf('Calibration control point is missing numeric %s.', esc_html($key)));
            }
        }
    }

    $origin_lng = array_sum(array_column($points, 'lng')) / count($points);
    $origin_lat = array_sum(array_column($points, 'lat')) / count($points);
    $transform = [
        'origin' => [
            'lng' => $origin_lng,
            'lat' => $origin_lat,
        ],
        'x' => iss_relations_map_projection_fit_axis($points, 'xNorm', $origin_lng, $origin_lat),
        'y' => iss_relations_map_projection_fit_axis($points, 'yNorm', $origin_lng, $origin_lat),
    ];
    $errors = [];

    foreach ($points as $point) {
        $projected = iss_relations_map_projection_project((float) $point['lng'], (float) $point['lat'], $transform);
        $errors[] = hypot(
            $projected['xNorm'] - (float) $point['xNorm'],
            $projected['yNorm'] - (float) $point['yNorm']
        );
    }

    $rmse = sqrt(array_sum(array_map(static fn(float $error): float => $error * $error, $errors)) / count($errors));
    $max_error = max($errors);
    $max_rmse = (float) ($calibration['quality']['max_rmse_norm'] ?? 0.0002);
    $max_control_error = (float) ($calibration['quality']['max_control_error_norm'] ?? 0.0003);

    if ($rmse > $max_rmse || $max_error > $max_control_error) {
        throw new RuntimeException(sprintf(
            'Calibration quality failed: RMSE %.8f (max %.8f), control error %.8f (max %.8f).',
            esc_html(number_format($rmse, 8, '.', '')),
            esc_html(number_format($max_rmse, 8, '.', '')),
            esc_html(number_format($max_error, 8, '.', '')),
            esc_html(number_format($max_control_error, 8, '.', ''))
        ));
    }

    $transform['quality'] = [
        'control_points' => count($points),
        'rmse_norm' => $rmse,
        'max_error_norm' => $max_error,
    ];

    return $transform;
}

function iss_relations_map_projection_project(float $lng, float $lat, array $transform): array
{
    $delta_lng = $lng - (float) ($transform['origin']['lng'] ?? 0.0);
    $delta_lat = $lat - (float) ($transform['origin']['lat'] ?? 0.0);
    $x = is_array($transform['x'] ?? null) ? $transform['x'] : [0.0, 0.0, 0.0];
    $y = is_array($transform['y'] ?? null) ? $transform['y'] : [0.0, 0.0, 0.0];

    return [
        'xNorm' => ($delta_lng * (float) ($x[0] ?? 0.0)) + ($delta_lat * (float) ($x[1] ?? 0.0)) + (float) ($x[2] ?? 0.0),
        'yNorm' => ($delta_lng * (float) ($y[0] ?? 0.0)) + ($delta_lat * (float) ($y[1] ?? 0.0)) + (float) ($y[2] ?? 0.0),
    ];
}

function iss_relations_map_projection_collect_places(): array
{
    $post_ids = get_posts([
        'post_type' => iss_relations_get_place_post_type(),
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
        'suppress_filters' => true,
        'ignore_sticky_posts' => true,
    ]);
    $places = [];

    foreach ($post_ids as $post_id) {
        $post_id = (int) $post_id;
        $lat = get_post_meta($post_id, 'lat', true);
        $lng = get_post_meta($post_id, 'lng', true);
        if (!is_numeric($lat) || !is_numeric($lng)) {
            continue;
        }

        $places[] = [
            'post_id' => $post_id,
            'name' => (string) get_the_title($post_id),
            'lng' => (float) $lng,
            'lat' => (float) $lat,
        ];
    }

    return $places;
}

function iss_relations_map_projection_encode(array $payload): string
{
    $encoded = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($encoded)) {
        throw new RuntimeException('Could not encode map projection JSON.');
    }

    return $encoded . "\n";
}

function iss_relations_map_projection_build_expected(string $profile_id, array $profile): array
{
    foreach (['calibration_path', 'master_path', 'markers_path', 'manifest_path'] as $key) {
        if (trim((string) ($profile[$key] ?? '')) === '') {
            throw new RuntimeException(sprintf('Projection profile %s is missing %s.', esc_html($profile_id), esc_html($key)));
        }
    }

    $calibration_path = (string) $profile['calibration_path'];
    $master_path = (string) $profile['master_path'];
    $calibration = iss_relations_map_projection_read_json($calibration_path);
    if (!is_readable($master_path)) {
        throw new RuntimeException(sprintf('Unreadable canonical map master: %s', esc_html($master_path)));
    }

    $master_hash = hash_file('sha256', $master_path);
    $expected_master_hash = strtolower((string) ($calibration['source']['sha256'] ?? ''));
    if ($expected_master_hash === '' || !hash_equals($expected_master_hash, strtolower((string) $master_hash))) {
        throw new RuntimeException('Canonical map master checksum differs from the calibrated source. Recalibrate before generating markers.');
    }

    $image_size = getimagesize($master_path);
    $source_width = max(1, absint($calibration['source']['width'] ?? 0));
    $source_height = max(1, absint($calibration['source']['height'] ?? 0));
    if (!is_array($image_size) || (int) $image_size[0] !== $source_width || (int) $image_size[1] !== $source_height) {
        throw new RuntimeException('Canonical map master dimensions differ from the calibration contract.');
    }

    $transform = iss_relations_map_projection_build_transform($calibration);
    $places = iss_relations_map_projection_collect_places();
    $markers = [];

    foreach ($places as $place) {
        $projected = iss_relations_map_projection_project((float) $place['lng'], (float) $place['lat'], $transform);
        $x_norm = round($projected['xNorm'], 6);
        $y_norm = round($projected['yNorm'], 6);
        $post_id = (int) $place['post_id'];
        $markers[] = [
            'id' => (string) $post_id,
            'post_id' => (string) $post_id,
            'name' => (string) $place['name'],
            'lng' => (float) $place['lng'],
            'lat' => (float) $place['lat'],
            'x' => (int) round($x_norm * $source_width),
            'y' => (int) round($y_norm * $source_height),
            'xNorm' => $x_norm,
            'yNorm' => $y_norm,
        ];
    }

    $markers_json = iss_relations_map_projection_encode($markers);
    $derivatives = [];
    foreach ((array) ($profile['derivatives'] ?? []) as $derivative) {
        if (!is_array($derivative) || !is_readable((string) ($derivative['path'] ?? ''))) {
            throw new RuntimeException('A configured responsive map derivative is unreadable.');
        }

        $path = (string) $derivative['path'];
        $size = getimagesize($path);
        if (!is_array($size)) {
            throw new RuntimeException(sprintf('Could not read derivative dimensions: %s', esc_html($path)));
        }

        $derivatives[] = [
            'file' => basename($path),
            'width' => (int) $size[0],
            'height' => (int) $size[1],
            'bytes' => (int) filesize($path),
            'sha256' => (string) hash_file('sha256', $path),
        ];
    }

    $manifest = [
        'version' => 1,
        'profile' => $profile_id,
        'coordinate_source' => 'published register_place lat/lng',
        'source' => [
            'file' => basename($master_path),
            'width' => $source_width,
            'height' => $source_height,
            'sha256' => (string) $master_hash,
        ],
        'calibration' => [
            'file' => basename($calibration_path),
            'sha256' => (string) hash_file('sha256', $calibration_path),
            'origin' => array_map(static fn(float $value): float => round($value, 10), $transform['origin']),
            'x' => array_map(static fn(float $value): float => round($value, 12), $transform['x']),
            'y' => array_map(static fn(float $value): float => round($value, 12), $transform['y']),
            'control_points' => (int) $transform['quality']['control_points'],
            'rmse_norm' => round((float) $transform['quality']['rmse_norm'], 12),
            'max_error_norm' => round((float) $transform['quality']['max_error_norm'], 12),
        ],
        'markers' => [
            'file' => basename((string) $profile['markers_path']),
            'count' => count($markers),
            'sha256' => hash('sha256', $markers_json),
        ],
        'derivatives' => $derivatives,
    ];

    return [
        'markers' => $markers,
        'markers_json' => $markers_json,
        'manifest' => $manifest,
        'manifest_json' => iss_relations_map_projection_encode($manifest),
        'transform' => $transform,
        'source_width' => $source_width,
        'source_height' => $source_height,
        'master_path' => $master_path,
        'qa_image_href' => (string) ($profile['qa_image_href'] ?? basename($master_path)),
    ];
}

function iss_relations_map_projection_write(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) || !is_writable($directory)) {
        throw new RuntimeException(sprintf('Projection output directory is not writable: %s', esc_html($directory)));
    }

    $temporary = $path . '.tmp-' . getmypid();
    if (file_put_contents($temporary, $contents, LOCK_EX) === false || !rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException(sprintf('Could not write projection artifact: %s', esc_html($path)));
    }
}

function iss_relations_map_projection_generate_qa_svg(array $expected): string
{
    $width = (int) $expected['source_width'];
    $height = (int) $expected['source_height'];
    $image_href = (string) $expected['qa_image_href'];
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1600" viewBox="0 0 ' . $width . ' ' . $height . '">';
    $svg .= '<image href="' . esc_attr($image_href) . '" width="' . $width . '" height="' . $height . '"/>';

    foreach ($expected['markers'] as $marker) {
        $x = (int) $marker['x'];
        $y = (int) $marker['y'];
        $label = (string) $marker['post_id'];
        $svg .= '<g><circle cx="' . $x . '" cy="' . $y . '" r="18" fill="#e81d25" stroke="#fff" stroke-width="5"/>';
        $svg .= '<text x="' . ($x + 24) . '" y="' . ($y + 7) . '" fill="#111" stroke="#fff" stroke-width="6" paint-order="stroke" font-family="sans-serif" font-size="24">' . esc_html($label) . '</text></g>';
    }

    return $svg . '</svg>' . "\n";
}
