<?php

if (!defined('ABSPATH')) {
    exit;
}

define('ISS_REGISTER_ACTOR_SCHEMA_VERSION', '2026-05-13-v1');
define('ISS_REGISTER_ACTOR_SCHEMA_OPTION', 'iss_register_actor_schema_version');
define('ISS_REGISTER_ACTOR_SEED_VERSION', '2026-05-13-v1');
define('ISS_REGISTER_ACTOR_SEED_OPTION', 'iss_register_actor_seed_version');

final class ISS_Register_Industry_Actor_Service
{
    private static ?ISS_Register_Industry_Actor_Service $instance = null;

    public static function get_instance(): ISS_Register_Industry_Actor_Service
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function get_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_register_place_industry_actors';
    }

    public function get_actor_definitions(): array
    {
        return [
            'tro' => [
                'key' => 'tro',
                'label' => 'TRO',
                'name' => 'Transformatorenwerk Oberschöneweide',
                'color' => '#8f1e14',
            ],
            'nag' => [
                'key' => 'nag',
                'label' => 'NAG',
                'name' => 'Nationale Automobil-Gesellschaft',
                'color' => '#6f4d16',
            ],
            'wf' => [
                'key' => 'wf',
                'label' => 'WF',
                'name' => 'Werk für Fernsehelektronik',
                'color' => '#2b5c7a',
            ],
            'kwo' => [
                'key' => 'kwo',
                'label' => 'KWO',
                'name' => 'Kabelwerk Oberspree',
                'color' => '#5f2d22',
            ],
            'bae' => [
                'key' => 'bae',
                'label' => 'BAE',
                'name' => 'BAE Batterien',
                'color' => '#25534b',
            ],
            'admos' => [
                'key' => 'admos',
                'label' => 'ADMOS',
                'name' => 'Allgemeine Deutsche Metallwerke Oberspree',
                'color' => '#8a5a18',
            ],
        ];
    }

    public function maybe_install_schema(): void
    {
        $installed = (string) get_option(ISS_REGISTER_ACTOR_SCHEMA_OPTION, '');
        if ($installed !== ISS_REGISTER_ACTOR_SCHEMA_VERSION) {
            $this->install_schema();
        }

        $this->maybe_seed_relations();
    }

    public function rebuild_relations_for_all_places(): array
    {
        global $wpdb;

        $places = function_exists('iss_register_get_place_entities')
            ? iss_register_get_place_entities()
            : [];

        $table = $this->get_table_name();
        $wpdb->query("TRUNCATE TABLE {$table}");

        $updated = 0;
        $with_rows = 0;

        foreach ((array) $places as $place) {
            $post_id = (int) ($place['post_id'] ?? 0);
            if ($post_id <= 0) {
                continue;
            }

            $rows = $this->infer_relations_for_place($place);
            if (!$rows) {
                continue;
            }

            $this->replace_relations_for_place($post_id, $rows);
            $updated++;
            $with_rows += count($rows);
        }

        update_option(ISS_REGISTER_ACTOR_SEED_OPTION, ISS_REGISTER_ACTOR_SEED_VERSION, false);
        if (function_exists('iss_register_clear_places_cache')) {
            iss_register_clear_places_cache();
        }

        return [
            'places_updated' => $updated,
            'rows_written' => $with_rows,
        ];
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
            actor_key varchar(64) NOT NULL,
            era_slug varchar(100) NOT NULL DEFAULT '',
            relation_role varchar(100) NOT NULL DEFAULT '',
            strength varchar(20) NOT NULL DEFAULT 'relevant',
            source_confidence varchar(40) NOT NULL DEFAULT 'unknown',
            source_links_json longtext NOT NULL,
            note text NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY place_post_id (place_post_id),
            KEY actor_key (actor_key),
            KEY era_slug (era_slug),
            KEY place_actor_era (place_post_id, actor_key, era_slug)
        ) {$charset_collate};";

        dbDelta($sql);
        update_option(ISS_REGISTER_ACTOR_SCHEMA_OPTION, ISS_REGISTER_ACTOR_SCHEMA_VERSION, false);
    }

    public function get_relations_for_places(array $place_post_ids): array
    {
        global $wpdb;

        $place_post_ids = array_values(array_filter(array_map('absint', $place_post_ids)));
        if (!$place_post_ids) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($place_post_ids), '%d'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->get_table_name()}
                WHERE place_post_id IN ({$placeholders})
                ORDER BY place_post_id ASC, actor_key ASC, era_slug ASC, id ASC",
                $place_post_ids
            ),
            ARRAY_A
        );

        $definitions = $this->get_actor_definitions();
        $grouped = [];

        foreach ($place_post_ids as $place_post_id) {
            $grouped[$place_post_id] = [];
        }

        foreach ((array) $rows as $row) {
            $post_id = (int) ($row['place_post_id'] ?? 0);
            $actor_key = sanitize_key((string) ($row['actor_key'] ?? ''));
            if ($post_id <= 0 || $actor_key === '') {
                continue;
            }

            $definition = $definitions[$actor_key] ?? [];
            $grouped[$post_id][] = [
                'id' => (int) ($row['id'] ?? 0),
                'actor_key' => $actor_key,
                'actor_label' => (string) ($definition['label'] ?? strtoupper($actor_key)),
                'actor_name' => (string) ($definition['name'] ?? strtoupper($actor_key)),
                'actor_color' => (string) ($definition['color'] ?? ''),
                'era_slug' => sanitize_title((string) ($row['era_slug'] ?? '')),
                'relation_role' => sanitize_text_field((string) ($row['relation_role'] ?? '')),
                'strength' => sanitize_key((string) ($row['strength'] ?? 'relevant')),
                'source_confidence' => sanitize_key((string) ($row['source_confidence'] ?? 'unknown')),
                'source_links' => is_array(json_decode((string) ($row['source_links_json'] ?? ''), true))
                    ? array_values(array_filter(array_map('esc_url_raw', (array) json_decode((string) ($row['source_links_json'] ?? ''), true))))
                    : [],
                'note' => sanitize_textarea_field((string) ($row['note'] ?? '')),
            ];
        }

        return $grouped;
    }

    public function build_actor_context_from_places(array $atlas_places): array
    {
        $definitions = $this->get_actor_definitions();
        $actors = [];

        foreach ($definitions as $key => $definition) {
            $actors[$key] = [
                'key' => $key,
                'label' => (string) ($definition['label'] ?? strtoupper($key)),
                'name' => (string) ($definition['name'] ?? strtoupper($key)),
                'color' => (string) ($definition['color'] ?? ''),
                'place_count' => 0,
                'era_counts' => [],
            ];
        }

        foreach ($atlas_places as $place) {
            $relations = is_array($place['industry_actor_relations'] ?? null)
                ? $place['industry_actor_relations']
                : [];
            if (!$relations) {
                continue;
            }

            $seen_actor = [];
            $seen_actor_era = [];

            foreach ($relations as $relation) {
                $actor_key = sanitize_key((string) ($relation['actor_key'] ?? ''));
                $era_slug = sanitize_title((string) ($relation['era_slug'] ?? ''));
                if ($actor_key === '' || !isset($actors[$actor_key])) {
                    continue;
                }

                if (!isset($seen_actor[$actor_key])) {
                    $actors[$actor_key]['place_count']++;
                    $seen_actor[$actor_key] = true;
                }

                if ($era_slug !== '') {
                    $token = $actor_key . '|' . $era_slug;
                    if (!isset($seen_actor_era[$token])) {
                        $actors[$actor_key]['era_counts'][$era_slug] = (int) ($actors[$actor_key]['era_counts'][$era_slug] ?? 0) + 1;
                        $seen_actor_era[$token] = true;
                    }
                }
            }
        }

        return array_values($actors);
    }

    public function replace_relations_for_place(int $place_post_id, array $rows): void
    {
        global $wpdb;

        if ($place_post_id <= 0) {
            return;
        }

        $wpdb->delete($this->get_table_name(), ['place_post_id' => $place_post_id], ['%d']);

        $definitions = $this->get_actor_definitions();
        $now = current_time('mysql', true);

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $actor_key = sanitize_key((string) ($row['actor_key'] ?? ''));
            if ($actor_key === '' || !isset($definitions[$actor_key])) {
                continue;
            }

            $era_slug = sanitize_title((string) ($row['era_slug'] ?? ''));
            if ($era_slug !== '' && !isset(iss_register_get_atlas_era_definitions()[$era_slug])) {
                $era_slug = '';
            }

            $relation_role = sanitize_text_field((string) ($row['relation_role'] ?? ''));
            $strength = sanitize_key((string) ($row['strength'] ?? 'relevant'));
            if (!in_array($strength, ['core', 'relevant', 'weak'], true)) {
                $strength = 'relevant';
            }

            $source_confidence = sanitize_key((string) ($row['source_confidence'] ?? 'unknown'));
            if (!in_array($source_confidence, ['unknown', 'oral', 'archive', 'publication', 'url'], true)) {
                $source_confidence = 'unknown';
            }

            $source_links = is_array($row['source_links'] ?? null) ? $row['source_links'] : [];
            $source_links = array_values(array_filter(array_map('esc_url_raw', $source_links)));
            $note = sanitize_textarea_field((string) ($row['note'] ?? ''));

            $wpdb->insert(
                $this->get_table_name(),
                [
                    'place_post_id' => $place_post_id,
                    'actor_key' => $actor_key,
                    'era_slug' => $era_slug,
                    'relation_role' => $relation_role,
                    'strength' => $strength,
                    'source_confidence' => $source_confidence,
                    'source_links_json' => wp_json_encode($source_links),
                    'note' => $note,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );
        }
    }

    private function maybe_seed_relations(): void
    {
        $seeded = (string) get_option(ISS_REGISTER_ACTOR_SEED_OPTION, '');
        if ($seeded === ISS_REGISTER_ACTOR_SEED_VERSION) {
            return;
        }

        global $wpdb;
        $existing = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->get_table_name()}");
        if ($existing > 0) {
            update_option(ISS_REGISTER_ACTOR_SEED_OPTION, ISS_REGISTER_ACTOR_SEED_VERSION, false);
            return;
        }

        $places = function_exists('iss_register_get_place_entities')
            ? iss_register_get_place_entities()
            : [];

        foreach ((array) $places as $place) {
            $post_id = (int) ($place['post_id'] ?? 0);
            if ($post_id <= 0) {
                continue;
            }

            $rows = $this->infer_relations_for_place($place);
            if (!$rows) {
                continue;
            }

            $this->replace_relations_for_place($post_id, $rows);
        }

        update_option(ISS_REGISTER_ACTOR_SEED_OPTION, ISS_REGISTER_ACTOR_SEED_VERSION, false);
        if (function_exists('iss_register_clear_places_cache')) {
            iss_register_clear_places_cache();
        }
    }

    private function infer_relations_for_place(array $place): array
    {
        $global_text = mb_strtolower(trim(implode("\n", array_filter([
            (string) ($place['slug'] ?? ''),
            (string) ($place['name'] ?? ''),
            (string) ($place['address'] ?? ''),
            (string) ($place['excerpt'] ?? ''),
            (string) ($place['history_short'] ?? ''),
            (string) ($place['history'] ?? ''),
            (string) ($place['current'] ?? ''),
            (string) ($place['vornutzung'] ?? ''),
            (string) ($place['sources'] ?? ''),
        ]))));

        $rows = [];
        $seen = [];
        $global_keys = $this->infer_actor_keys_from_text($global_text);

        $epochs = is_array($place['epochs'] ?? null) ? $place['epochs'] : [];
        foreach ($epochs as $epoch) {
            if (!is_array($epoch)) {
                continue;
            }

            $era_slug = sanitize_title((string) ($epoch['era_slug'] ?? ''));
            if ($era_slug === '') {
                continue;
            }

            $epoch_text = mb_strtolower(trim(implode("\n", array_filter([
                (string) ($epoch['phase_name'] ?? ''),
                (string) ($epoch['summary'] ?? ''),
            ]))));

            // Epoch tags must come from epoch-local evidence to avoid cross-era drift.
            $keys = $this->infer_actor_keys_from_text($epoch_text);
            foreach ($keys as $key) {
                $token = $key . '|' . $era_slug;
                if (isset($seen[$token])) {
                    continue;
                }

                $rows[] = [
                    'actor_key' => $key,
                    'era_slug' => $era_slug,
                    'relation_role' => '',
                    'strength' => 'relevant',
                    'source_confidence' => 'unknown',
                    'source_links' => [],
                    'note' => '',
                ];
                $seen[$token] = true;
            }
        }

        if ($rows) {
            return $rows;
        }

        if (!$global_keys) {
            return [];
        }

        // If only global evidence is available, keep the relation era-agnostic.
        foreach ($global_keys as $key) {
            $rows[] = [
                'actor_key' => $key,
                'era_slug' => '',
                'relation_role' => '',
                'strength' => 'relevant',
                'source_confidence' => 'unknown',
                'source_links' => [],
                'note' => '',
            ];
        }

        return $rows;
    }

    private function infer_actor_keys_from_text(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $patterns = [
            'tro' => '/\btro\b|transformatorenwerk|rathenau[-\s]?hallen|basecamp|urban banks|atelierhaus 79|wilhelminenhofstr(?:aße|asse|\.?)\s*83[-–]\s*85/ui',
            'nag' => '/\bnag\b|nationale automobil|niles[-\s]?werkzeugmaschinen|werkzeugmaschinen[-\s]?fabrik/ui',
            'wf' => '/\bwf\b|werk f(?:u|ü)r fernsehelektronik|fernsehelektronik|kulturhaus des wf/ui',
            'kwo' => '/\bkwo\b|kabelwerk oberspree|deutsche kupferwerke|kaos 93|kaos berlin/ui',
            'bae' => '/\bbae\b|akkumulatorenwerke oberspree|berliner akkumulatoren|belfa|afo/ui',
            'admos' => '/\badmos\b|allgemeine deutsche metallwerke|spreeh(?:ö|oe)fe/ui',
        ];

        $keys = [];
        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $text)) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }
}

function iss_register_get_industry_actor_service(): ISS_Register_Industry_Actor_Service
{
    return ISS_Register_Industry_Actor_Service::get_instance();
}

add_action('init', static function (): void {
    iss_register_get_industry_actor_service()->maybe_install_schema();
}, 40);
