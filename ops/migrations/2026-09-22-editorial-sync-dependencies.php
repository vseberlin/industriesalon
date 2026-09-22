<?php
/**
 * Complete required report/media dependencies after editorial-staging-sync.php.
 * wp eval-file this-file.php check|apply|verify --use-include
 * Target: staging only. Same full backup as the parent migration; exclusive
 * targeted before-image in /tmp/iss-editorial-dependencies-20260922-before.json.
 * Restore its metadata/report connections via WordPress and report APIs, then
 * remove only newly created event 26813 and its owner-created context/projections
 * for targeted rollback. Do not delete pre-existing Sets or import Set contents.
 * Event programme opt-in stays empty; no occurrence or booking rows transferred.
 */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$mode = $args[0] ?? 'check';
if (!in_array($mode, ['check', 'apply', 'verify'], true) || home_url() !== 'https://staging.industriesalon.info') { WP_CLI::error('Staging only; use check, apply or verify.'); }
$source = json_decode(<<<'ISS_DEPENDENCIES'
{
    "post": {
        "post_date": "2026-06-29 21:37:43",
        "post_date_gmt": "2026-06-29 19:37:43",
        "post_content": "",
        "post_title": "Repair-Café",
        "post_excerpt": "Regelmäßiges Repair-Café im Industriesalon Schöneweide.",
        "post_status": "publish",
        "post_name": "repair-cafe-terminreihe",
        "post_type": "veranstaltung"
    },
    "meta": {
        "iss_end_datetime": "2026-09-09 19:00:00",
        "_wp_page_template": "default",
        "_iss_datetime_start": "2026-09-09 17:00:00",
        "_iss_datetime_end": "2026-09-09 19:00:00",
        "iss_primary_place_id": "17960",
        "iss_location": "Industriesalon Schöneweide",
        "_iss_entity_key": "event.series",
        "iss_start_datetime": "2026-07-01 17:00:00",
        "iss_programme_enabled": "",
        "iss_timeline_target_url": "http://192.168.2.31:8082/repair-cafe/",
        "iss_booking_enabled": ""
    },
    "terms": {
        "category": [],
        "post_tag": [],
        "iss_topic": [],
        "veranstaltung_art": [
            "repair-cafe"
        ],
        "iss_place_ref": [
            "place-17960"
        ]
    },
    "media_rights": {
        "26694": {
            "_event_drop_attribution": "vladimir",
            "_event_drop_license": "all-rights-reserved",
            "_event_drop_consent": "1",
            "_event_drop_sha256": "a12473a147fe6ed2c26f0c115a56e1512d10452b037bcffc8098e9c861f5ce33",
            "_event_drop_original_name": "fest.png"
        },
        "26695": {
            "_event_drop_attribution": "",
            "_event_drop_license": "all-rights-reserved",
            "_event_drop_consent": "0",
            "_event_drop_sha256": "03ba508588a8a2c131abf5dbd058ec3839e3823ff46368a1680731aae5d1ff2c",
            "_event_drop_original_name": "Brand-Guidelines_Industriesalon-Schoneweide.pdf"
        }
    }
}
ISS_DEPENDENCIES
, true, 512, JSON_THROW_ON_ERROR);
$source['meta']['iss_timeline_target_url'] = home_url('/repair-cafe/');
wp_set_current_user(1);
$verify = static function () use ($source): void {
    $post = get_post(26813, ARRAY_A);
    if (!$post) { throw new RuntimeException('Missing source event.'); }
    foreach ($source['post'] as $key => $value) { if ((string) $post[$key] !== (string) $value) { throw new RuntimeException('Event differs: ' . $key); } }
    foreach ($source['meta'] as $key => $value) { if (get_post_meta(26813, $key, true) !== $value) { throw new RuntimeException('Event metadata differs: ' . $key); } }
    if (array_map(static fn($p) => $p->ID, iss_content_report_connections(27388, true)) !== [26813]) { throw new RuntimeException('Report source link differs.'); }
    foreach ($source['media_rights'] as $id => $values) { foreach ($values as $key => $value) { if (get_post_meta((int) $id, $key, true) !== $value) { throw new RuntimeException("Media metadata differs: $id $key"); } } }
};
if ($mode === 'verify') { $verify(); WP_CLI::success('Report source and media rights verified.'); return; }
if (get_post(26813) || get_posts(['post_type' => 'veranstaltung', 'post_status' => 'any', 'name' => $source['post']['post_name']])) { WP_CLI::error('Expected missing event; refusing to replace existing data.'); }
if (get_post_type(27388) !== 'rueckblick' || iss_content_report_connections(27388)) { WP_CLI::error('Report absent or its sources changed.'); }
foreach ($source['media_rights'] as $id => $values) {
    if (get_post_type((int) $id) !== 'attachment') { WP_CLI::error('Missing transferred attachment.'); }
    foreach ($values as $key => $value) { if (metadata_exists('post', (int) $id, $key)) { WP_CLI::error("Media metadata already exists: $id $key"); } }
}
foreach ($source['terms'] as $tax => $slugs) { foreach ($slugs as $slug) { if (!get_term_by('slug', $slug, $tax)) { WP_CLI::error("Missing prerequisite term: $tax $slug"); } } }
if ($mode === 'check') { WP_CLI::success('Report event, connection and two media-rights records ready.'); return; }
$backup = ['new_post' => 26813, 'report_sources' => [], 'media_meta' => []];
foreach (array_keys($source['media_rights']) as $id) { $backup['media_meta'][$id] = get_post_meta((int) $id); }
$file = '/tmp/iss-editorial-dependencies-20260922-before.json';
$handle = fopen($file, 'x');
if (!$handle) { WP_CLI::error('Backup exists or cannot be created.'); }
$encoded = wp_json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (fwrite($handle, $encoded) !== strlen($encoded)) { fclose($handle); WP_CLI::error('Incomplete backup.'); }
fclose($handle); chmod($file, 0600);
add_filter('pre_wp_mail', '__return_true', PHP_INT_MAX);
global $wpdb;
$wpdb->query('START TRANSACTION');
try {
    $post = $source['post']; $post['import_id'] = 26813; $post['post_author'] = 1; $post['meta_input'] = $source['meta'];
    if (wp_insert_post(wp_slash($post), true) !== 26813) { throw new RuntimeException('Could not create report source event.'); }
    foreach ($source['terms'] as $tax => $slugs) { $result = wp_set_object_terms(26813, $slugs, $tax); if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); } }
    iss_relations_ensure_post_has_place_relation(26813, 17960, ['relation_type' => 'venue', 'relation_role' => 'venue', 'role' => 'venue', 'label' => 'Veranstaltungsort']);
    if (!iss_content_report_set_sources(27388, [26813])) { throw new RuntimeException('Could not save report source.'); }
    foreach ($source['media_rights'] as $id => $values) { foreach ($values as $key => $value) { update_post_meta((int) $id, $key, wp_slash($value)); } }
    $verify();
    $wpdb->query('COMMIT');
} catch (Throwable $error) {
    $wpdb->query('ROLLBACK'); wp_cache_flush(); WP_CLI::error('Rolled back dependencies: ' . $error->getMessage());
}
WP_CLI::success('Report event/link and media rights synchronized.');
