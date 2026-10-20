<?php
/** Rename imported display labels through native taxonomy APIs; preserve IDs and slugs.
 * wp eval-file FILE dry-run|apply|verify --use-include --user=admin
 * Rollback: check for subsequent edits, then wp_update_term each receipt row's
 * before name. No memberships, term IDs, slugs or projection tables are changed.
 */
if (!defined('WP_CLI') || !WP_CLI || !current_user_can('manage_options')) { throw new RuntimeException('Administrator WP-CLI required.'); }
$mode = $args[0] ?? 'dry-run';
if (!in_array($mode, ['dry-run', 'apply', 'verify'], true)) { throw new RuntimeException('Invalid mode.'); }
$key = 'iss_backup_20261020_archive_labels';
$receipt = get_option($key);
$labels = ['alltag' => 'Alltag', 'arbeit' => 'Arbeit', 'menschen' => 'Menschen', 'fernsehtechnik' => 'Fernsehtechnik',
    'telekommunikation' => 'Telekommunikation', 'betriebsorte' => 'Betriebsorte', 'kultur' => 'Kultur', 'soziales' => 'Soziales',
    'freizeit' => 'Freizeit', 'sport' => 'Sport', 'menschen-im-wf' => 'Menschen im WF', 'portraet' => 'Porträt'];
$verify = static function () use (&$receipt): void {
    if (!is_array($receipt) || empty($receipt['complete'])) { throw new RuntimeException('Missing or partial receipt.'); }
    foreach ($receipt['terms'] as $row) {
        $term = get_term($row['id'], $row['taxonomy']);
        if (!$term instanceof WP_Term || $term->name !== $row['after'] || $term->slug !== $row['slug']
            || get_objects_in_term($row['id'], $row['taxonomy']) !== $row['members']) {
            throw new RuntimeException('Term or membership changed.');
        }
    }
    echo 'Verified ' . count($receipt['terms']) . " display labels; IDs, slugs and memberships preserved.\n";
};
if ($mode === 'verify' || is_array($receipt)) { $verify(); return; }
$receipt = ['complete' => false, 'terms' => []];
foreach ([ISS_WF_IMPORT_FIELD_TAXONOMY, ISS_WF_IMPORT_FAMILY_TAXONOMY] as $taxonomy) {
    foreach ($labels as $before => $after) {
        $term = get_term_by('slug', $before, $taxonomy);
        if (!$term instanceof WP_Term || $term->name !== $before) { continue; }
        $members = get_objects_in_term($term->term_id, $taxonomy);
        if (is_wp_error($members)) { throw new RuntimeException('Cannot read term membership.'); }
        $receipt['terms'][] = ['id' => $term->term_id, 'taxonomy' => $taxonomy, 'slug' => $term->slug, 'before' => $term->name, 'after' => $after, 'members' => $members];
    }
}
if ($mode === 'dry-run') { echo 'Ready: ' . count($receipt['terms']) . " labels.\n"; return; }
if (!add_option($key, $receipt, '', false) || get_option($key) !== $receipt) { throw new RuntimeException('Before-image not saved.'); }
foreach ($receipt['terms'] as $row) {
    $result = wp_update_term($row['id'], $row['taxonomy'], ['name' => $row['after']]);
    if (is_wp_error($result)) { throw new RuntimeException('Term update failed.'); }
}
$receipt['complete'] = true;
update_option($key, $receipt, false);
$verify();
