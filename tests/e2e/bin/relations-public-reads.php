<?php
/** Read-only relation lookup regression: no public term creation or repair. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($label); } };
$terms = get_terms(['taxonomy' => ISS_RELATIONS_TAXONOMY, 'hide_empty' => false]);
$places = [];
foreach ($terms as $term) {
    $place = (int) get_term_meta($term->term_id, 'place_post_id', true);
    if ($place > 0 && iss_relations_is_usable_place($place)) { $places[$place] = (int) $term->term_id; }
    if (count($places) === 3) { break; }
}
$expect(count($places) === 3, 'Fixture contains three established place projections');
global $wpdb;
$fake_id = (int) $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts}") + 1000000;
$fake = clone get_post(array_key_first($places));
$fake->ID = $fake_id;
wp_cache_set($fake_id, $fake, 'posts');
$queries = [];
$observe = static function ($query) use (&$queries) {
    if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP)\b/i', $query)) { throw new RuntimeException('Public lookup attempted a write'); }
    $queries[] = $query;
    return $query;
};
add_filter('query', $observe);
try {
    $ids = array_keys($places);
    $expect(iss_relations_get_place_term_ids(array_merge($ids, [$ids[0], 0])) === array_values($places), 'Batched lookup preserves order and removes duplicate/invalid IDs');
    foreach ($places as $place => $term) { $expect(iss_relations_get_place_term_id($place) === $term, 'Single existing lookup returns the same term without repair'); }
    $expect(iss_relations_get_place_term_id($fake_id) === 0, 'A usable place missing its projection stays read-only');
    $expect(iss_relations_get_place_term_ids([]) === [], 'Empty lookup returns no terms');
    $expect(!get_term_by('slug', iss_relations_term_slug_for_place($fake_id), ISS_RELATIONS_TAXONOMY), 'Lookup did not manufacture a missing relation term');
} finally { remove_filter('query', $observe); wp_cache_delete($fake_id, 'posts'); }
$expect((bool) $queries, 'Write guard observed real database reads');
WP_CLI::success("Public relation reads: $checks checks passed; no records changed.");
