<?php

if (!defined('ABSPATH')) {
    exit;
}

define('ISS_REGISTER_PLACE_STATE_SCHEMA_VERSION', '2026-05-12-v1');
define('ISS_REGISTER_PLACE_STATE_SCHEMA_OPTION', 'iss_register_place_state_schema_version');
define('ISS_REGISTER_PLACE_STATE_BACKFILL_OPTION', 'iss_register_place_state_backfill_version');
define('ISS_REGISTER_PLACE_STATE_BACKFILL_VERSION', '2026-05-12-v1');

final class ISS_Register_Place_State_Service
{
    private static ?ISS_Register_Place_State_Service $instance = null;

    public static function get_instance(): ISS_Register_Place_State_Service
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function get_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_register_place_states';
    }

    public function maybe_install_schema(): void
    {
        $installed = (string) get_option(ISS_REGISTER_PLACE_STATE_SCHEMA_OPTION, '');
        if ($installed === ISS_REGISTER_PLACE_STATE_SCHEMA_VERSION) {
            return;
        }

        $this->install_schema();
    }

    public function install_schema(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = $this->get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            place_post_id bigint(20) unsigned NOT NULL,
            state_key varchar(191) NOT NULL,
            state_kind varchar(40) NOT NULL DEFAULT 'historical',
            state_label varchar(255) NOT NULL,
            summary text NOT NULL,
            era_slug varchar(100) NOT NULL,
            function_key varchar(100) NOT NULL,
            start_year smallint(6) DEFAULT NULL,
            end_year smallint(6) DEFAULT NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            is_current tinyint(1) NOT NULL DEFAULT 0,
            current_status_key varchar(100) NOT NULL DEFAULT '',
            current_use_type_key varchar(100) NOT NULL DEFAULT '',
            source_summary text NOT NULL,
            source_links_json longtext NOT NULL,
            media_ids_json longtext NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY place_state_key (place_post_id, state_key),
            KEY place_kind (place_post_id, state_kind),
            KEY place_current (place_post_id, is_current),
            KEY era_function (era_slug, function_key),
            KEY chronology (start_year, end_year)
        ) {$charset_collate};";

        dbDelta($sql);
        update_option(ISS_REGISTER_PLACE_STATE_SCHEMA_OPTION, ISS_REGISTER_PLACE_STATE_SCHEMA_VERSION, false);
    }

    public function maybe_backfill(): void
    {
        $stored = (string) get_option(ISS_REGISTER_PLACE_STATE_BACKFILL_OPTION, '');
        if ($stored === ISS_REGISTER_PLACE_STATE_BACKFILL_VERSION) {
            return;
        }

        $this->backfill_all_places();
        update_option(ISS_REGISTER_PLACE_STATE_BACKFILL_OPTION, ISS_REGISTER_PLACE_STATE_BACKFILL_VERSION, false);
    }

    public function backfill_all_places(): void
    {
        if (!post_type_exists(ISS_REGISTER_POST_TYPE)) {
            return;
        }

        $post_ids = get_posts([
            'post_type' => ISS_REGISTER_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'ASC',
            'suppress_filters' => true,
        ]);

        foreach ($post_ids as $post_id) {
            $this->sync_place_state_for_post((int) $post_id);
        }
    }

    public function get_states_for_place(int $place_post_id): array
    {
        $states = $this->get_states_for_places([$place_post_id]);

        return $states[$place_post_id] ?? [];
    }

    public function get_states_for_places(array $place_post_ids): array
    {
        global $wpdb;

        $place_post_ids = array_values(array_filter(array_map('absint', $place_post_ids)));
        if (!$place_post_ids) {
            return [];
        }

        $table_name = $this->get_table_name();
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        if ($table_exists !== $table_name) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($place_post_ids), '%d'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name}
                WHERE place_post_id IN ({$placeholders})
                ORDER BY place_post_id ASC, is_current DESC, start_year ASC, sort_order ASC, id ASC",
                $place_post_ids
            ),
            ARRAY_A
        );

        $grouped = [];
        foreach ($place_post_ids as $place_post_id) {
            $grouped[$place_post_id] = [];
        }

        foreach ((array) $rows as $row) {
            $place_post_id = (int) ($row['place_post_id'] ?? 0);
            if ($place_post_id <= 0) {
                continue;
            }

            $grouped[$place_post_id][] = $this->normalize_db_row($row);
        }

        return $grouped;
    }

    public function sync_place_state_for_post(int $post_id, ?array $place = null): void
    {
        if ($post_id <= 0 || get_post_type($post_id) !== ISS_REGISTER_POST_TYPE) {
            return;
        }

        $states = $this->build_state_rows_for_post($post_id, $place);
        $this->replace_state_rows($post_id, $states);
        iss_register_clear_places_cache();
    }

    public function delete_states_for_place(int $post_id): void
    {
        global $wpdb;

        if ($post_id <= 0) {
            return;
        }

        $wpdb->delete($this->get_table_name(), ['place_post_id' => $post_id], ['%d']);
        iss_register_clear_places_cache();
    }

    public function get_verification_report(): array
    {
        global $wpdb;

        $table_name = $this->get_table_name();
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name;
        $total_places = (int) wp_count_posts(ISS_REGISTER_POST_TYPE)->publish;

        if (!$table_exists) {
            return [
                'table_exists' => false,
                'total_places' => $total_places,
                'rows_total' => 0,
                'places_with_states' => 0,
                'current_rows' => 0,
                'historical_rows' => 0,
                'sample_place_state_keys' => [],
            ];
        }

        $rows_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $places_with_states = (int) $wpdb->get_var("SELECT COUNT(DISTINCT place_post_id) FROM {$table_name}");
        $current_rows = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE state_kind = %s",
            'current'
        ));
        $historical_rows = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE state_kind = %s",
            'historical'
        ));
        $sample_keys = $wpdb->get_col("SELECT state_key FROM {$table_name} ORDER BY place_post_id ASC, is_current DESC, sort_order ASC, id ASC LIMIT 5");

        return [
            'table_exists' => true,
            'total_places' => $total_places,
            'rows_total' => $rows_total,
            'places_with_states' => $places_with_states,
            'current_rows' => $current_rows,
            'historical_rows' => $historical_rows,
            'sample_place_state_keys' => array_values(array_map('strval', is_array($sample_keys) ? $sample_keys : [])),
        ];
    }

    private function build_state_rows_for_post(int $post_id, ?array $place = null): array
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || $post->post_type !== ISS_REGISTER_POST_TYPE || $post->post_status !== 'publish') {
            return [];
        }

        if (!is_array($place)) {
            $place = iss_register_map_place_entity($post);
        }

        $epochs = iss_register_get_epoch_service()->get_epochs_for_place($post_id);
        $historical_rows = [];

        foreach ($epochs as $index => $epoch) {
            if (!is_array($epoch)) {
                continue;
            }

            $historical_rows[] = $this->build_historical_state_row($place, $epoch, $index);
        }

        $current_row = $this->build_current_state_row($place, count($historical_rows));
        $rows = array_values(array_filter(array_merge($historical_rows, [$current_row]), static function ($row): bool {
            return is_array($row) && !empty($row['state_key']);
        }));

        usort($rows, static function (array $left, array $right): int {
            if ((int) $left['is_current'] !== (int) $right['is_current']) {
                return (int) $right['is_current'] <=> (int) $left['is_current'];
            }

            $left_year = $left['start_year'] === null ? PHP_INT_MAX : (int) $left['start_year'];
            $right_year = $right['start_year'] === null ? PHP_INT_MAX : (int) $right['start_year'];
            if ($left_year !== $right_year) {
                return $left_year <=> $right_year;
            }

            return (int) $left['sort_order'] <=> (int) $right['sort_order'];
        });

        return $rows;
    }

    private function build_historical_state_row(array $place, array $epoch, int $position): array
    {
        $epoch_id = (int) ($epoch['id'] ?? 0);

        return [
            'state_key' => $epoch_id > 0 ? 'epoch:' . $epoch_id : 'epoch-position:' . $position,
            'state_kind' => 'historical',
            'state_label' => trim((string) ($epoch['phase_name'] ?? '')) ?: 'Historische Phase',
            'summary' => trim((string) ($epoch['summary'] ?? '')),
            'era_slug' => sanitize_title((string) ($epoch['era_slug'] ?? '')),
            'function_key' => sanitize_key((string) ($epoch['function_key'] ?? '')),
            'start_year' => isset($epoch['start_year']) && $epoch['start_year'] !== null ? (int) $epoch['start_year'] : null,
            'end_year' => isset($epoch['end_year']) && $epoch['end_year'] !== null ? (int) $epoch['end_year'] : null,
            'sort_order' => (int) ($epoch['sort_order'] ?? $position),
            'is_current' => !empty($epoch['is_current']) ? 1 : 0,
            'current_status_key' => '',
            'current_use_type_key' => '',
            'source_summary' => trim((string) ($epoch['source_summary'] ?? '')),
            'source_links' => is_array($epoch['source_links'] ?? null) ? array_values($epoch['source_links']) : [],
            'media_ids' => is_array($epoch['media_ids'] ?? null) ? array_values($epoch['media_ids']) : [],
        ];
    }

    private function build_current_state_row(array $place, int $position): array
    {
        $current_status = function_exists('iss_register_detect_current_status')
            ? iss_register_detect_current_status($place)
            : [];
        $current_use_type = function_exists('iss_register_detect_current_use_type')
            ? iss_register_detect_current_use_type($place)
            : [];

        $summary = trim((string) ($place['current'] ?? ''));
        if ($summary === '') {
            $summary = trim((string) ($place['excerpt'] ?? ''));
        }
        if ($summary === '') {
            $summary = trim((string) ($place['history_short'] ?? $place['history'] ?? ''));
        }

        return [
            'state_key' => 'current',
            'state_kind' => 'current',
            'state_label' => 'Aktuelle Situation',
            'summary' => $summary,
            'era_slug' => $this->get_present_era_slug(),
            'function_key' => $this->map_current_use_type_to_function_key((string) ($current_use_type['key'] ?? '')),
            'start_year' => null,
            'end_year' => null,
            'sort_order' => $position + 1000,
            'is_current' => 1,
            'current_status_key' => sanitize_key((string) ($current_status['key'] ?? '')),
            'current_use_type_key' => sanitize_key((string) ($current_use_type['key'] ?? '')),
            'source_summary' => trim((string) ($place['source_summary'] ?? $place['sources'] ?? '')),
            'source_links' => is_array($place['source_links'] ?? null) ? array_values($place['source_links']) : [],
            'media_ids' => $this->extract_public_media_ids($place['current_images'] ?? []),
        ];
    }

    private function replace_state_rows(int $post_id, array $rows): void
    {
        global $wpdb;

        $table_name = $this->get_table_name();
        $wpdb->delete($table_name, ['place_post_id' => $post_id], ['%d']);

        foreach ($rows as $row) {
            $db_row = $this->prepare_row_for_db($post_id, $row);
            $wpdb->insert(
                $table_name,
                $db_row,
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );
        }
    }

    private function prepare_row_for_db(int $post_id, array $row): array
    {
        $now = current_time('mysql', true);

        return [
            'place_post_id' => $post_id,
            'state_key' => sanitize_key((string) ($row['state_key'] ?? '')),
            'state_kind' => sanitize_key((string) ($row['state_kind'] ?? 'historical')),
            'state_label' => sanitize_text_field((string) ($row['state_label'] ?? '')),
            'summary' => sanitize_textarea_field((string) ($row['summary'] ?? '')),
            'era_slug' => sanitize_title((string) ($row['era_slug'] ?? '')),
            'function_key' => sanitize_key((string) ($row['function_key'] ?? '')),
            'start_year' => isset($row['start_year']) && $row['start_year'] !== null ? (int) $row['start_year'] : null,
            'end_year' => isset($row['end_year']) && $row['end_year'] !== null ? (int) $row['end_year'] : null,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'is_current' => !empty($row['is_current']) ? 1 : 0,
            'current_status_key' => sanitize_key((string) ($row['current_status_key'] ?? '')),
            'current_use_type_key' => sanitize_key((string) ($row['current_use_type_key'] ?? '')),
            'source_summary' => sanitize_textarea_field((string) ($row['source_summary'] ?? '')),
            'source_links_json' => wp_json_encode($this->sanitize_source_links($row['source_links'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'media_ids_json' => wp_json_encode($this->sanitize_media_ids($row['media_ids'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function normalize_db_row(array $row): array
    {
        $source_links = json_decode((string) ($row['source_links_json'] ?? '[]'), true);
        $media_ids = json_decode((string) ($row['media_ids_json'] ?? '[]'), true);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'place_post_id' => (int) ($row['place_post_id'] ?? 0),
            'state_key' => sanitize_key((string) ($row['state_key'] ?? '')),
            'state_kind' => sanitize_key((string) ($row['state_kind'] ?? 'historical')),
            'state_label' => (string) ($row['state_label'] ?? ''),
            'summary' => (string) ($row['summary'] ?? ''),
            'era_slug' => sanitize_title((string) ($row['era_slug'] ?? '')),
            'function_key' => sanitize_key((string) ($row['function_key'] ?? '')),
            'start_year' => isset($row['start_year']) && $row['start_year'] !== null ? (int) $row['start_year'] : null,
            'end_year' => isset($row['end_year']) && $row['end_year'] !== null ? (int) $row['end_year'] : null,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'is_current' => !empty($row['is_current']),
            'current_status_key' => sanitize_key((string) ($row['current_status_key'] ?? '')),
            'current_use_type_key' => sanitize_key((string) ($row['current_use_type_key'] ?? '')),
            'source_summary' => (string) ($row['source_summary'] ?? ''),
            'source_links' => $this->sanitize_source_links(is_array($source_links) ? $source_links : []),
            'media_ids' => $this->sanitize_media_ids(is_array($media_ids) ? $media_ids : []),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function sanitize_source_links($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $item) {
            $url = esc_url_raw(trim((string) $item));
            if ($url !== '' && wp_http_validate_url($url)) {
                $clean[$url] = $url;
            }
        }

        return array_values($clean);
    }

    private function sanitize_media_ids($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $item) {
            $media_id = absint($item);
            if ($media_id > 0) {
                $clean[$media_id] = $media_id;
            }
        }

        return array_values($clean);
    }

    private function extract_public_media_ids($images): array
    {
        if (!is_array($images)) {
            return [];
        }

        $media_ids = [];
        foreach ($images as $image) {
            if (!is_array($image)) {
                continue;
            }

            $media_id = absint($image['media_id'] ?? 0);
            if ($media_id > 0) {
                $media_ids[] = $media_id;
            }
        }

        return $this->sanitize_media_ids($media_ids);
    }

    private function get_present_era_slug(): string
    {
        $slug = function_exists('iss_register_map_legacy_era_to_editorial_slug')
            ? iss_register_map_legacy_era_to_editorial_slug('heute')
            : '';

        return $slug !== '' ? $slug : 'heute';
    }

    private function map_current_use_type_to_function_key(string $current_use_type_key): string
    {
        $map = [
            'culture' => 'culture',
            'education' => 'education',
            'commercial' => 'commercial',
            'industrial' => 'industrial',
            'residential' => 'residential',
            'community' => 'community',
            'mixed' => 'mixed',
            'administration' => 'commercial',
        ];

        $current_use_type_key = sanitize_key($current_use_type_key);

        return $map[$current_use_type_key] ?? 'mixed';
    }
}

function iss_register_get_place_state_service(): ISS_Register_Place_State_Service
{
    return ISS_Register_Place_State_Service::get_instance();
}

function iss_register_map_state_row_to_epoch(array $state): ?array
{
    if (($state['state_kind'] ?? '') !== 'historical') {
        return null;
    }

    return [
        'id' => (int) ($state['id'] ?? 0),
        'place_post_id' => (int) ($state['place_post_id'] ?? 0),
        'era_slug' => (string) ($state['era_slug'] ?? ''),
        'function_key' => (string) ($state['function_key'] ?? ''),
        'phase_name' => (string) ($state['state_label'] ?? ''),
        'summary' => (string) ($state['summary'] ?? ''),
        'start_year' => $state['start_year'] ?? null,
        'end_year' => $state['end_year'] ?? null,
        'sort_order' => (int) ($state['sort_order'] ?? 0),
        'is_current' => !empty($state['is_current']),
        'source_confidence' => 'unknown',
        'source_summary' => (string) ($state['source_summary'] ?? ''),
        'source_links' => is_array($state['source_links'] ?? null) ? array_values($state['source_links']) : [],
        'media_ids' => is_array($state['media_ids'] ?? null) ? array_values($state['media_ids']) : [],
    ];
}

function iss_register_enrich_place_with_states(array $place, array $states): array
{
    $place['place_states'] = array_values($states);

    $current_state = [];
    $epochs = [];

    foreach ($states as $state) {
        if (!is_array($state)) {
            continue;
        }

        if (($state['state_kind'] ?? '') === 'current' && !$current_state) {
            $current_state = $state;
            continue;
        }

        $epoch = iss_register_map_state_row_to_epoch($state);
        if ($epoch) {
            $epochs[] = $epoch;
        }
    }

    $place['current_state'] = $current_state;

    $era_slugs = array_map(static function (array $epoch): string {
        return sanitize_title((string) ($epoch['era_slug'] ?? ''));
    }, $epochs);
    $function_keys = array_map(static function (array $epoch): string {
        return sanitize_key((string) ($epoch['function_key'] ?? ''));
    }, $epochs);
    $phase_labels = array_map(static function (array $epoch): string {
        return trim((string) ($epoch['phase_name'] ?? ''));
    }, $epochs);

    $place['epochs'] = array_values($epochs);
    $place['has_epochs'] = !empty($epochs);
    $place['available_era_slugs'] = array_values(array_unique(array_filter($era_slugs)));
    $place['available_function_keys'] = array_values(array_unique(array_filter($function_keys)));
    $place['historical_phase_labels'] = array_values(array_filter($phase_labels));
    $place['primary_historical_era_slug'] = $place['available_era_slugs'][0] ?? '';

    if ($current_state) {
        $place['normalized_current_status'] = (string) ($current_state['current_status_key'] ?? ($place['normalized_current_status'] ?? ''));
        $place['current_use_type'] = (string) ($current_state['current_use_type_key'] ?? ($place['current_use_type'] ?? ''));
    }

    return $place;
}

function iss_register_sync_place_states_on_save(int $post_id): void
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id)) {
        return;
    }

    iss_register_get_place_state_service()->sync_place_state_for_post($post_id);
}

function iss_register_delete_place_states_on_before_delete(int $post_id): void
{
    if (get_post_type($post_id) !== ISS_REGISTER_POST_TYPE) {
        return;
    }

    iss_register_get_place_state_service()->delete_states_for_place($post_id);
}

add_action('init', static function (): void {
    iss_register_get_place_state_service()->maybe_install_schema();
}, 22);

add_action('init', static function (): void {
    iss_register_get_place_state_service()->maybe_backfill();
}, 23);

add_action('save_post_' . ISS_REGISTER_POST_TYPE, 'iss_register_sync_place_states_on_save', 40);
add_action('before_delete_post', 'iss_register_delete_place_states_on_before_delete', 4);
