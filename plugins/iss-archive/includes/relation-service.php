<?php

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- This service owns and queries iss-wf-import custom archive relation tables.

class ISS_WF_Import_Relation_Service
{
    /** @var array<int, array<string, mixed>|null> */
    private array $projection_cache = [];

    public function get_relation_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_archive_relations';
    }

    public function maybe_install_schema(): void
    {
        $stored = (string) get_option(ISS_WF_IMPORT_RELATION_SCHEMA_OPTION, '');
        if ($stored === ISS_WF_IMPORT_RELATION_SCHEMA_VERSION) {
            return;
        }

        $this->install_schema();
    }

    public function install_schema(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = $this->get_relation_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            object_id bigint(20) unsigned NOT NULL,
            relation_family varchar(40) NOT NULL,
            relation_source varchar(40) NOT NULL DEFAULT 'source',
            relation_type varchar(60) NOT NULL DEFAULT '',
            target_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            target_source_id varchar(191) NOT NULL DEFAULT '',
            target_name text NOT NULL,
            source_url text NOT NULL,
            note text NOT NULL,
            relation_context varchar(191) NOT NULL DEFAULT '',
            relation_weight int(11) NOT NULL DEFAULT 0,
            relation_label text NOT NULL,
            event_source_id bigint(20) unsigned NOT NULL DEFAULT 0,
            event_type_id bigint(20) unsigned NOT NULL DEFAULT 0,
            event_type_name varchar(191) NOT NULL DEFAULT '',
            time_label varchar(191) NOT NULL DEFAULT '',
            time_start varchar(40) NOT NULL DEFAULT '',
            time_end varchar(40) NOT NULL DEFAULT '',
            people_source_id bigint(20) unsigned NOT NULL DEFAULT 0,
            people_name text NOT NULL,
            place_source_id bigint(20) unsigned NOT NULL DEFAULT 0,
            place_name text NOT NULL,
            place_latitude decimal(10,7) NOT NULL DEFAULT 0.0000000,
            place_longitude decimal(10,7) NOT NULL DEFAULT 0.0000000,
            position int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY object_family_position (object_id, relation_family, position, id),
            KEY family_target_post (relation_family, target_post_id),
            KEY family_target_source (relation_family, target_source_id),
            KEY relation_source (relation_source),
            KEY relation_type (relation_type),
            KEY place_source_id (place_source_id)
        ) {$charset_collate};";

        dbDelta($sql);
        update_option(ISS_WF_IMPORT_RELATION_SCHEMA_OPTION, ISS_WF_IMPORT_RELATION_SCHEMA_VERSION, false);
    }

    public function maybe_backfill(): void
    {
        $stored = (string) get_option(ISS_WF_IMPORT_RELATION_BACKFILL_OPTION, '');
        if ($stored === ISS_WF_IMPORT_RELATION_BACKFILL_VERSION) {
            return;
        }

        $this->backfill_all_relations();
        update_option(ISS_WF_IMPORT_RELATION_BACKFILL_OPTION, ISS_WF_IMPORT_RELATION_BACKFILL_VERSION, false);
    }

    public function backfill_all_relations(): void
    {
        $post_ids = get_posts([
            'post_type' => ISS_WF_IMPORT_OBJECT_POST_TYPE,
            'post_status' => 'any',
            'numberposts' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'suppress_filters' => true,
        ]);

        foreach ($post_ids as $post_id) {
            $this->sync_object_relations((int) $post_id);
        }
    }

    public function sync_object_relations(int $post_id): void
    {
        $snapshot = $this->build_snapshot($post_id);
        if (!$snapshot) {
            return;
        }

        $object_row = $this->get_or_create_object_row($post_id);
        if (!$object_row) {
            return;
        }

        $timestamp = current_time('mysql', true);
        $this->replace_relation_rows((int) $object_row['id'], $snapshot, $timestamp);
        unset($this->projection_cache[$post_id]);
    }

    public function delete_object_relations(int $post_id): void
    {
        global $wpdb;

        $object_row = iss_wf_import_get_object_service()->get_projection_for_post($post_id);
        if (!is_array($object_row)) {
            unset($this->projection_cache[$post_id]);
            return;
        }

        $wpdb->delete($this->get_relation_table_name(), ['object_id' => (int) $object_row['id']], ['%d']);
        unset($this->projection_cache[$post_id]);
    }

    public function get_projection_for_post(int $post_id): ?array
    {
        if ($post_id <= 0) {
            return null;
        }

        if (array_key_exists($post_id, $this->projection_cache)) {
            return $this->projection_cache[$post_id];
        }

        $object_row = iss_wf_import_get_object_service()->get_projection_for_post($post_id);
        if (!is_array($object_row)) {
            $this->projection_cache[$post_id] = null;
            return null;
        }

        $rows = $this->get_relation_rows((int) $object_row['id']);
        if (!$rows && $this->relation_has_payload($post_id)) {
            $this->sync_object_relations($post_id);
            $rows = $this->get_relation_rows((int) $object_row['id']);
        }

        $projection = [
            'object' => $object_row,
            'tags' => [],
            'collections' => [],
            'series' => [],
            'events' => [],
            'places' => [],
            'people' => [],
            'related_objects' => [],
            'local_places' => [],
        ];

        foreach ($rows as $row) {
            $family = (string) ($row['relation_family'] ?? '');
            if ($family === 'tag') {
                $projection['tags'][] = $this->map_named_ref_row($row);
                continue;
            }

            if ($family === 'collection') {
                $projection['collections'][] = $this->map_named_ref_row($row);
                continue;
            }

            if ($family === 'series') {
                $projection['series'][] = $this->map_named_ref_row($row);
                continue;
            }

            if ($family === 'place') {
                $projection['places'][] = $this->map_named_ref_row($row);
                continue;
            }

            if ($family === 'person') {
                $projection['people'][] = $this->map_named_ref_row($row);
                continue;
            }

            if ($family === 'event') {
                $projection['events'][] = $this->map_event_row($row);
                continue;
            }

            if ($family === 'object') {
                $projection['related_objects'][] = $this->map_object_relation_row($row);
                continue;
            }

            if ($family === 'local_place') {
                $projection['local_places'][] = $this->map_local_place_row($row);
            }
        }

        $this->projection_cache[$post_id] = $projection;

        return $projection;
    }

    public function get_object_post_ids_for_local_place(int $place_id): array
    {
        if ($place_id <= 0) {
            return [];
        }

        global $wpdb;

        $object_table = iss_wf_import_get_object_service()->get_object_table_name();
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT o.object_post_id
            FROM {$this->get_relation_table_name()} r
            INNER JOIN {$object_table} o ON o.id = r.object_id
            WHERE r.relation_family = %s
              AND r.target_post_id = %d
            ORDER BY r.relation_weight ASC, r.position ASC, o.object_post_id ASC",
            'local_place',
            $place_id
        ));

        return array_values(array_unique(array_filter(array_map('absint', is_array($rows) ? $rows : []))));
    }

    public function get_local_place_post_ids_for_object(int $post_id): array
    {
        $projection = $this->get_projection_for_post($post_id);
        if (!is_array($projection)) {
            return [];
        }

        $ids = [];
        foreach ((array) ($projection['local_places'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $ids[] = absint($item['place_id'] ?? 0);
        }

        return array_values(array_unique(array_filter($ids)));
    }

    public function normalizeLocalPlaceRelationsForVerify(int $post_id): array
    {
        return $this->normalize_local_place_relations($this->get_local_place_meta($post_id));
    }

    public function normalizeEventsArrayForVerify(array $events): array
    {
        $events = iss_wf_import_sanitize_archive_events($events);
        $clean = [];

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $normalized = $this->normalize_event_row($event);
            $normalized['place_latitude'] = $this->format_event_coordinate($normalized['place_latitude'] ?? 0.0);
            $normalized['place_longitude'] = $this->format_event_coordinate($normalized['place_longitude'] ?? 0.0);
            $clean[] = $normalized;
        }

        return array_values($clean);
    }

    public function normalizeLocalPlaceRelationsArrayForVerify(array $relations): array
    {
        return $this->normalize_local_place_relations($relations);
    }

    protected function build_snapshot(int $post_id): ?array
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || $post->post_type !== ISS_WF_IMPORT_OBJECT_POST_TYPE) {
            return null;
        }

        if (in_array($post->post_status, ['auto-draft', 'trash'], true)) {
            return null;
        }

        return [
            'post_id' => $post_id,
            'tags' => $this->get_named_ref_meta($post_id, ISS_WF_IMPORT_OBJECT_TAGS_META),
            'collections' => $this->get_named_ref_meta($post_id, ISS_WF_IMPORT_OBJECT_COLLECTIONS_META),
            'series' => $this->get_named_ref_meta($post_id, ISS_WF_IMPORT_OBJECT_SERIES_META),
            'events' => $this->get_event_meta($post_id),
            'places' => $this->get_named_ref_meta($post_id, ISS_WF_IMPORT_OBJECT_PLACE_RELATIONS_META),
            'people' => $this->get_named_ref_meta($post_id, ISS_WF_IMPORT_OBJECT_PEOPLE_RELATIONS_META),
            'related_objects' => $this->get_object_relation_meta($post_id),
            'local_places' => $this->get_local_place_meta($post_id),
        ];
    }

    protected function get_or_create_object_row(int $post_id): ?array
    {
        $object_service = iss_wf_import_get_object_service();
        $object_row = $object_service->get_projection_for_post($post_id);
        if (is_array($object_row)) {
            return $object_row;
        }

        $object_service->sync_object_post($post_id);

        $object_row = $object_service->get_projection_for_post($post_id);

        return is_array($object_row) ? $object_row : null;
    }

    protected function replace_relation_rows(int $object_id, array $snapshot, string $timestamp): void
    {
        global $wpdb;

        $table = $this->get_relation_table_name();
        $wpdb->delete($table, ['object_id' => $object_id], ['%d']);

        $position = 0;
        foreach ((array) $snapshot['tags'] as $item) {
            $this->insert_named_ref_row($object_id, 'tag', $item, $position++, $timestamp);
        }
        foreach ((array) $snapshot['collections'] as $item) {
            $this->insert_named_ref_row($object_id, 'collection', $item, $position++, $timestamp);
        }
        foreach ((array) $snapshot['series'] as $item) {
            $this->insert_named_ref_row($object_id, 'series', $item, $position++, $timestamp);
        }
        foreach ((array) $snapshot['places'] as $item) {
            $this->insert_named_ref_row($object_id, 'place', $item, $position++, $timestamp);
        }
        foreach ((array) $snapshot['people'] as $item) {
            $this->insert_named_ref_row($object_id, 'person', $item, $position++, $timestamp);
        }
        foreach ((array) $snapshot['events'] as $item) {
            $this->insert_event_row($object_id, $item, $position++, $timestamp);
        }
        foreach ((array) $snapshot['related_objects'] as $item) {
            $this->insert_object_relation_row($object_id, $item, $position++, $timestamp);
        }
        foreach ((array) $snapshot['local_places'] as $item) {
            $this->insert_local_place_row($object_id, $item, $position++, $timestamp);
        }
    }

    protected function insert_named_ref_row(int $object_id, string $family, array $item, int $position, string $timestamp): void
    {
        global $wpdb;

        $wpdb->insert($this->get_relation_table_name(), [
            'object_id' => $object_id,
            'relation_family' => $family,
            'relation_source' => 'source_named_ref',
            'relation_type' => sanitize_key((string) ($item['type'] ?? $family)),
            'target_post_id' => 0,
            'target_source_id' => $this->truncate_value(sanitize_text_field((string) ($item['source_id'] ?? '')), 191),
            'target_name' => sanitize_text_field((string) ($item['name'] ?? '')),
            'source_url' => esc_url_raw((string) ($item['source_url'] ?? '')),
            'note' => sanitize_textarea_field((string) ($item['note'] ?? '')),
            'relation_context' => '',
            'relation_weight' => 0,
            'relation_label' => '',
            'event_source_id' => 0,
            'event_type_id' => 0,
            'event_type_name' => '',
            'time_label' => '',
            'time_start' => '',
            'time_end' => '',
            'people_source_id' => 0,
            'people_name' => '',
            'place_source_id' => 0,
            'place_name' => '',
            'place_latitude' => 0,
            'place_longitude' => 0,
            'position' => $position,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ], [
            '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s',
            '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s',
            '%d', '%s', '%f', '%f', '%d', '%s', '%s',
        ]);
    }

    protected function insert_event_row(int $object_id, array $item, int $position, string $timestamp): void
    {
        global $wpdb;

        $wpdb->insert($this->get_relation_table_name(), [
            'object_id' => $object_id,
            'relation_family' => 'event',
            'relation_source' => 'source_event',
            'relation_type' => 'event',
            'target_post_id' => 0,
            'target_source_id' => '',
            'target_name' => '',
            'source_url' => '',
            'note' => sanitize_textarea_field((string) ($item['note'] ?? '')),
            'relation_context' => '',
            'relation_weight' => 0,
            'relation_label' => '',
            'event_source_id' => absint($item['event_id'] ?? 0),
            'event_type_id' => absint($item['event_type'] ?? 0),
            'event_type_name' => sanitize_text_field((string) ($item['event_type_name'] ?? '')),
            'time_label' => sanitize_text_field((string) ($item['time_label'] ?? '')),
            'time_start' => sanitize_text_field((string) ($item['time_start'] ?? '')),
            'time_end' => sanitize_text_field((string) ($item['time_end'] ?? '')),
            'people_source_id' => absint($item['people_id'] ?? 0),
            'people_name' => sanitize_text_field((string) ($item['people_name'] ?? '')),
            'place_source_id' => absint($item['place_id'] ?? 0),
            'place_name' => sanitize_text_field((string) ($item['place_name'] ?? '')),
            'place_latitude' => $this->format_event_coordinate($item['place_latitude'] ?? 0.0),
            'place_longitude' => $this->format_event_coordinate($item['place_longitude'] ?? 0.0),
            'position' => $position,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ], [
            '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s',
            '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s',
            '%d', '%s', '%s', '%s', '%d', '%s', '%s',
        ]);
    }

    protected function insert_object_relation_row(int $object_id, array $item, int $position, string $timestamp): void
    {
        global $wpdb;

        $target_post_id = absint($item['object_id'] ?? 0);

        $wpdb->insert($this->get_relation_table_name(), [
            'object_id' => $object_id,
            'relation_family' => 'object',
            'relation_source' => 'source_object',
            'relation_type' => sanitize_key((string) ($item['relation_type'] ?? 'related')),
            'target_post_id' => $target_post_id,
            'target_source_id' => $this->truncate_value(sanitize_text_field((string) ($item['source_object_id'] ?? '')), 191),
            'target_name' => '',
            'source_url' => '',
            'note' => '',
            'relation_context' => $this->truncate_value(sanitize_text_field((string) ($item['context'] ?? '')), 191),
            'relation_weight' => max(0, min(1000, absint($item['weight'] ?? 0))),
            'relation_label' => sanitize_text_field((string) ($item['label'] ?? '')),
            'event_source_id' => 0,
            'event_type_id' => 0,
            'event_type_name' => '',
            'time_label' => '',
            'time_start' => '',
            'time_end' => '',
            'people_source_id' => 0,
            'people_name' => '',
            'place_source_id' => 0,
            'place_name' => '',
            'place_latitude' => 0,
            'place_longitude' => 0,
            'position' => $position,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ], [
            '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s',
            '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s',
            '%d', '%s', '%f', '%f', '%d', '%s', '%s',
        ]);
    }

    protected function insert_local_place_row(int $object_id, array $item, int $position, string $timestamp): void
    {
        global $wpdb;

        $place_id = absint($item['place_id'] ?? 0);

        $wpdb->insert($this->get_relation_table_name(), [
            'object_id' => $object_id,
            'relation_family' => 'local_place',
            'relation_source' => 'editorial_place',
            'relation_type' => sanitize_key((string) ($item['role'] ?? 'related')),
            'target_post_id' => $place_id,
            'target_source_id' => '',
            'target_name' => $place_id > 0 ? sanitize_text_field((string) get_the_title($place_id)) : '',
            'source_url' => '',
            'note' => '',
            'relation_context' => '',
            'relation_weight' => (int) ($item['weight'] ?? 0),
            'relation_label' => sanitize_text_field((string) ($item['label'] ?? '')),
            'event_source_id' => 0,
            'event_type_id' => 0,
            'event_type_name' => '',
            'time_label' => '',
            'time_start' => '',
            'time_end' => '',
            'people_source_id' => 0,
            'people_name' => '',
            'place_source_id' => 0,
            'place_name' => '',
            'place_latitude' => 0,
            'place_longitude' => 0,
            'position' => $position,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ], [
            '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s',
            '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s',
            '%d', '%s', '%f', '%f', '%d', '%s', '%s',
        ]);
    }

    protected function get_relation_rows(int $object_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT *
            FROM {$this->get_relation_table_name()}
            WHERE object_id = %d
            ORDER BY position ASC, id ASC",
            $object_id
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    protected function map_named_ref_row(array $row): array
    {
        return [
            'id' => absint($row['target_source_id'] ?? 0),
            'source_id' => (string) ($row['target_source_id'] ?? ''),
            'name' => (string) ($row['target_name'] ?? ''),
            'type' => (string) ($row['relation_type'] ?? ''),
            'note' => (string) ($row['note'] ?? ''),
            'source_url' => (string) ($row['source_url'] ?? ''),
        ];
    }

    protected function map_event_row(array $row): array
    {
        return $this->normalize_event_row([
            'event_id' => absint($row['event_source_id'] ?? 0),
            'event_type' => absint($row['event_type_id'] ?? 0),
            'event_type_name' => (string) ($row['event_type_name'] ?? ''),
            'note' => (string) ($row['note'] ?? ''),
            'time_label' => (string) ($row['time_label'] ?? ''),
            'time_start' => (string) ($row['time_start'] ?? ''),
            'time_end' => (string) ($row['time_end'] ?? ''),
            'people_id' => absint($row['people_source_id'] ?? 0),
            'people_name' => (string) ($row['people_name'] ?? ''),
            'place_id' => absint($row['place_source_id'] ?? 0),
            'place_name' => (string) ($row['place_name'] ?? ''),
            'place_latitude' => isset($row['place_latitude']) ? (float) $row['place_latitude'] : 0.0,
            'place_longitude' => isset($row['place_longitude']) ? (float) $row['place_longitude'] : 0.0,
        ]);
    }

    protected function map_object_relation_row(array $row): array
    {
        return [
            'object_id' => absint($row['target_post_id'] ?? 0),
            'source_object_id' => (string) ($row['target_source_id'] ?? ''),
            'relation_type' => (string) ($row['relation_type'] ?? 'related'),
            'context' => (string) ($row['relation_context'] ?? ''),
            'weight' => max(0, min(1000, absint($row['relation_weight'] ?? 0))),
            'label' => (string) ($row['relation_label'] ?? ''),
        ];
    }

    protected function map_local_place_row(array $row): array
    {
        return [
            'place_id' => absint($row['target_post_id'] ?? 0),
            'role' => (string) ($row['relation_type'] ?? 'related'),
            'weight' => (int) ($row['relation_weight'] ?? 0),
            'label' => (string) ($row['relation_label'] ?? ''),
            'route_title' => '',
            'route_teaser' => '',
            'station_object_id' => 0,
            'station_story_id' => 0,
        ];
    }

    protected function get_named_ref_meta(int $post_id, string $meta_key): array
    {
        $value = get_post_meta($post_id, $meta_key, true);

        return iss_wf_import_sanitize_archive_named_refs(is_array($value) ? $value : []);
    }

    protected function get_event_meta(int $post_id): array
    {
        $value = get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_EVENTS_META, true);

        $events = iss_wf_import_sanitize_archive_events(is_array($value) ? $value : []);

        return array_values(array_map([$this, 'normalize_event_row'], $events));
    }

    protected function get_object_relation_meta(int $post_id): array
    {
        $value = get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_RELATIONS_META, true);

        return iss_wf_import_sanitize_archive_relations(is_array($value) ? $value : []);
    }

    protected function get_local_place_meta(int $post_id): array
    {
        if (function_exists('iss_relations_get_post_relations')) {
            return $this->normalize_local_place_relations(iss_relations_get_post_relations($post_id));
        }

        $value = get_post_meta($post_id, 'iss_related_places', true);

        return $this->normalize_local_place_relations(is_array($value) ? $value : []);
    }

    protected function normalize_local_place_relations(array $relations): array
    {
        $clean = [];

        foreach ($relations as $relation) {
            if (!is_array($relation)) {
                continue;
            }

            $place_id = absint($relation['place_id'] ?? 0);
            if ($place_id <= 0) {
                continue;
            }

            $role = sanitize_key((string) ($relation['role'] ?? 'related'));
            if (!in_array($role, ['primary', 'venue', 'stop', 'subject', 'related'], true)) {
                $role = 'related';
            }

            $clean[] = [
                'place_id' => $place_id,
                'role' => $role,
                'weight' => (int) ($relation['weight'] ?? 0),
                'label' => sanitize_text_field((string) ($relation['label'] ?? '')),
                'route_title' => sanitize_text_field((string) ($relation['route_title'] ?? '')),
                'route_teaser' => sanitize_textarea_field((string) ($relation['route_teaser'] ?? '')),
                'station_object_id' => absint($relation['station_object_id'] ?? 0),
                'station_story_id' => absint($relation['station_story_id'] ?? 0),
            ];
        }

        return array_values($clean);
    }

    protected function normalize_event_row(array $item): array
    {
        $item['place_latitude'] = isset($item['place_latitude'])
            ? round((float) $item['place_latitude'], 7)
            : 0.0;
        $item['place_longitude'] = isset($item['place_longitude'])
            ? round((float) $item['place_longitude'], 7)
            : 0.0;

        return $item;
    }

    protected function format_event_coordinate($value): string
    {
        return number_format(round((float) $value, 7), 7, '.', '');
    }

    protected function relation_has_payload(int $post_id): bool
    {
        foreach ([
            ISS_WF_IMPORT_OBJECT_TAGS_META,
            ISS_WF_IMPORT_OBJECT_COLLECTIONS_META,
            ISS_WF_IMPORT_OBJECT_SERIES_META,
            ISS_WF_IMPORT_OBJECT_EVENTS_META,
            ISS_WF_IMPORT_OBJECT_PLACE_RELATIONS_META,
            ISS_WF_IMPORT_OBJECT_PEOPLE_RELATIONS_META,
            ISS_WF_IMPORT_OBJECT_RELATIONS_META,
        ] as $meta_key) {
            $value = get_post_meta($post_id, $meta_key, true);
            if (is_array($value) && $value) {
                return true;
            }
        }

        $local_places = get_post_meta($post_id, 'iss_related_places', true);

        return is_array($local_places) && $local_places;
    }

    protected function truncate_value(string $value, int $limit): string
    {
        if ($limit <= 0) {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit);
        }

        return substr($value, 0, $limit);
    }
}

function iss_wf_import_get_relation_service(): ISS_WF_Import_Relation_Service
{
    static $service = null;

    if (!$service instanceof ISS_WF_Import_Relation_Service) {
        $service = new ISS_WF_Import_Relation_Service();
    }

    return $service;
}

function iss_wf_import_maybe_install_relation_schema(): void
{
    iss_wf_import_get_relation_service()->maybe_install_schema();
}
add_action('init', 'iss_wf_import_maybe_install_relation_schema', 31);

function iss_wf_import_maybe_backfill_relation_tables(): void
{
    iss_wf_import_get_relation_service()->maybe_backfill();
}

function iss_wf_import_sync_object_relations_on_save(int $post_id, WP_Post $post): void
{
    if ($post->post_type !== ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    iss_wf_import_get_relation_service()->sync_object_relations($post_id);
}
add_action('save_post', 'iss_wf_import_sync_object_relations_on_save', 33, 2);

function iss_wf_import_delete_object_relations_on_before_delete(int $post_id): void
{
    if (get_post_type($post_id) !== ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        return;
    }

    iss_wf_import_get_relation_service()->delete_object_relations($post_id);
}
add_action('before_delete_post', 'iss_wf_import_delete_object_relations_on_before_delete', 4);

function iss_wf_import_sync_object_relations_on_place_meta_change($meta_id, int $post_id, string $meta_key): void
{
    if ($meta_key !== 'iss_related_places') {
        return;
    }

    if (get_post_type($post_id) !== ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        return;
    }

    iss_wf_import_get_relation_service()->sync_object_relations($post_id);
}
add_action('added_post_meta', 'iss_wf_import_sync_object_relations_on_place_meta_change', 10, 3);
add_action('updated_post_meta', 'iss_wf_import_sync_object_relations_on_place_meta_change', 10, 3);
add_action('deleted_post_meta', 'iss_wf_import_sync_object_relations_on_place_meta_change', 10, 3);

if (defined('WP_CLI') && WP_CLI) {
    class ISS_WF_Import_Relation_CLI_Command
    {
        public function sync(): void
        {
            iss_wf_import_get_relation_service()->backfill_all_relations();
            update_option(ISS_WF_IMPORT_RELATION_BACKFILL_OPTION, ISS_WF_IMPORT_RELATION_BACKFILL_VERSION, false);

            \WP_CLI::success('Synced archive relation projection table.');
        }

        public function verify(): void
        {
            $service = iss_wf_import_get_relation_service();
            $post_ids = get_posts([
                'post_type' => ISS_WF_IMPORT_OBJECT_POST_TYPE,
                'post_status' => 'any',
                'numberposts' => -1,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
                'suppress_filters' => true,
            ]);

            $errors = [];
            $verified_posts = 0;
            $verified_rows = 0;

            foreach ($post_ids as $post_id) {
                $post = get_post((int) $post_id);
                if (!$post instanceof WP_Post || in_array($post->post_status, ['auto-draft', 'trash'], true)) {
                    continue;
                }

                $projected = [
                    'tags' => iss_wf_import_sanitize_archive_named_refs((array) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_TAGS_META, true)),
                    'collections' => iss_wf_import_sanitize_archive_named_refs((array) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_COLLECTIONS_META, true)),
                    'series' => iss_wf_import_sanitize_archive_named_refs((array) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_SERIES_META, true)),
                    'events' => $service->normalizeEventsArrayForVerify((array) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_EVENTS_META, true)),
                    'places' => iss_wf_import_sanitize_archive_named_refs((array) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_PLACE_RELATIONS_META, true)),
                    'people' => iss_wf_import_sanitize_archive_named_refs((array) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_PEOPLE_RELATIONS_META, true)),
                    'related_objects' => iss_wf_import_sanitize_archive_relations((array) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_RELATIONS_META, true)),
                    'local_places' => $service->normalizeLocalPlaceRelationsForVerify((int) $post_id),
                ];

                $projection = $service->get_projection_for_post((int) $post_id);
                if (!$projection && array_filter($projected)) {
                    $errors[] = sprintf('Object %d has projected relations but no canonical projection.', (int) $post_id);
                    continue;
                }

                $canonical = is_array($projection) ? [
                    'tags' => iss_wf_import_sanitize_archive_named_refs((array) ($projection['tags'] ?? [])),
                    'collections' => iss_wf_import_sanitize_archive_named_refs((array) ($projection['collections'] ?? [])),
                    'series' => iss_wf_import_sanitize_archive_named_refs((array) ($projection['series'] ?? [])),
                    'events' => $service->normalizeEventsArrayForVerify((array) ($projection['events'] ?? [])),
                    'places' => iss_wf_import_sanitize_archive_named_refs((array) ($projection['places'] ?? [])),
                    'people' => iss_wf_import_sanitize_archive_named_refs((array) ($projection['people'] ?? [])),
                    'related_objects' => iss_wf_import_sanitize_archive_relations((array) ($projection['related_objects'] ?? [])),
                    'local_places' => $service->normalizeLocalPlaceRelationsArrayForVerify((array) ($projection['local_places'] ?? [])),
                ] : [
                    'tags' => [],
                    'collections' => [],
                    'series' => [],
                    'events' => [],
                    'places' => [],
                    'people' => [],
                    'related_objects' => [],
                    'local_places' => [],
                ];

                foreach ($projected as $key => $value) {
                    if ($canonical[$key] !== $value) {
                        $errors[] = sprintf('Object %d relation group %s does not match the projected relation state.', (int) $post_id, $key);
                        continue 2;
                    }
                }

                $verified_posts++;
                $verified_rows += count($canonical['tags'])
                    + count($canonical['collections'])
                    + count($canonical['series'])
                    + count($canonical['events'])
                    + count($canonical['places'])
                    + count($canonical['people'])
                    + count($canonical['related_objects'])
                    + count($canonical['local_places']);
            }

            if ($errors) {
                \WP_CLI::error_multi_line($errors);
                \WP_CLI::error('Relation verification failed.');
            }

            \WP_CLI::success(sprintf(
                'Verified %d archive objects with %d relation rows.',
                $verified_posts,
                $verified_rows
            ));
        }
    }

    \WP_CLI::add_command('iss-archive relations-sync', ['ISS_WF_Import_Relation_CLI_Command', 'sync']);
    \WP_CLI::add_command('iss-archive relations-verify', ['ISS_WF_Import_Relation_CLI_Command', 'verify']);
}
