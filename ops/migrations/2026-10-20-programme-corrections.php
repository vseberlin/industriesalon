<?php
/**
 * Complete the bounded programme audit through editorial and occurrence owners.
 * wp eval-file FILE dry-run|apply|verify PRIVATE_JSON --use-include --user=admin
 * After public-copy, tour-facts and reduced-tickets. No external requests/mail.
 * Rollback: compare later edits, restore fields/documents/meta via their APIs;
 * restore series/slot/occurrence before-images via the occurrence service.
 * Receipt is private and non-autoload. No schema or uploads changes.
 */
if (!defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('WP-CLI required.'); }
$mode = $args[0] ?? 'dry-run';
$path = $args[1] ?? '';
$expect = static function (bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException(esc_html($message)); }
};
$expect(in_array($mode, ['dry-run', 'apply', 'verify'], true) && is_readable($path), 'Provide mode and private input.');
$expect(current_user_can('manage_options'), 'Administrator required.');
$input = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$expect(($input['home_url'] ?? '') === home_url(), 'Input belongs to another site.');
$scope = ['licht-und-transparenz', 'licht-und-transparenz-gruppenbuchung', 'judische-unternehmen-im-historischen-berlin-bustour', 'kiez-tour-wilhelminenhofstrase-sa-2-5-26-14-uhr'];
$expect(array_column($input['records'], 'slug') === $scope, 'Unexpected content scope.');
$expect($input['ignored_series'] === ['tour:elektropolis-individuell', 'tour:angebot-ist-raus-elektropolis-licht-und-transparenz']
    && array_keys($input['ignored_slots']) === ['public:73836755', 'public:72767289', 'public:73058861']
    && array_keys($input['appointment_titles']) === ['public:74269614'], 'Unexpected calendar scope.');
$owner = iss_occurrences_get_service();
$receipt_key = 'iss_backup_20261020_programme_corrections';
$receipt = get_option($receipt_key);
$draft_hash = static function (int $id): string {
    $values = array_filter(get_post_meta($id), static fn(string $key): bool => str_contains($key, 'draft') || str_contains($key, 'autosave'), ARRAY_FILTER_USE_KEY);
    foreach (wp_get_post_revisions($id, ['posts_per_page' => -1]) as $revision) {
        if (wp_is_post_autosave($revision->ID)) { $values[$revision->ID] = [$revision->to_array(), get_post_meta($revision->ID)]; }
    }
    return hash('sha256', wp_json_encode($values));
};
$verify = static function () use (&$receipt, $input, $expect, $owner, $draft_hash): void {
    $expect(is_array($receipt) && !empty($receipt['complete']), 'Missing or partial receipt.');
    foreach ($receipt['records'] as $row) {
        $id = $row['id'];
        $expect(iss_editorial_get_document($id, 'fuehrung', false) === $row['after_document'], 'Document differs: ' . $row['slug']);
        foreach ($row['after_fields'] as $field => $value) { $expect(get_post_field($field, $id, 'raw') === $value, 'Post field differs.'); }
        foreach ($row['after_meta'] as $field => $value) { $expect(get_post_meta($id, $field, true) === $value, 'Metadata differs: ' . $row['slug'] . '/' . $field); }
        $expect($draft_hash($id) === $row['draft_hash'], 'Private draft changed.');
    }
    foreach ($input['ignored_series'] as $series_key) {
        $expect(($owner->get_series_by_key($series_key)['review_state'] ?? '') === 'ignored', 'Series review differs.');
    }
    foreach ($input['ignored_slots'] as $external_id => $title) {
        $slot = $owner->get_supersaas_slot_by_external($external_id);
        $expect(($slot['review_state'] ?? '') === 'ignored' && !$owner->get_occurrence_by_external('supersaas', $external_id), 'Individual/internal slot still public.');
    }
    foreach ($input['appointment_titles'] as $external_id => $title) {
        $occurrence = $owner->get_occurrence_by_external('supersaas', $external_id);
        $expect(($occurrence['title'] ?? '') === $title && $owner->format_timeline_row($occurrence)['title'] === $title, 'Special appointment label differs.');
    }
    echo "Verified four tour documents, separate public/group offers, reviewed private appointments and special title; drafts preserved.\n";
};
if ($mode === 'verify' || is_array($receipt)) { $verify(); return; }
$receipt = ['complete' => false, 'input_sha256' => hash_file('sha256', $path), 'records' => [], 'series' => [], 'slots' => [], 'occurrences' => []];
$allowed_meta = ['booking_mode','offer_catalog_groups','allow_on_demand_with_calendar','booking_price_cents','booking_reduced_price_cents','price_note','booking_note','inquiry_label','duration','meeting_point'];
foreach ($input['records'] as $row) {
    $post = get_page_by_path($row['slug'], OBJECT, 'fuehrung');
    $expect($post instanceof WP_Post && $post->post_status === 'publish' && $post->post_title === $row['title'], 'Post identity differs: ' . $row['slug']);
    $row['id'] = $post->ID;
    $expect(iss_editorial_get_document($post->ID, 'fuehrung', false) === $row['before_document'], 'Document changed: ' . $row['slug']);
    $validated = iss_editorial_validate_document($row['after_document'], 'fuehrung');
    $expect(!is_wp_error($validated), 'Invalid document.');
    $row['after_document'] = $validated;
    $expect(!array_diff(array_keys($row['after_fields']), ['post_title','post_excerpt'])
        && array_keys($row['before_fields']) === array_keys($row['after_fields']), 'Unexpected post fields.');
    $expect(!array_diff(array_keys($row['after_meta']), $allowed_meta)
        && array_keys($row['before_meta']) === array_keys($row['after_meta']), 'Unexpected metadata.');
    foreach ($row['before_fields'] as $field => $value) { $expect(get_post_field($field, $post->ID, 'raw') === $value, 'Post field changed.'); }
    foreach ($row['before_meta'] as $field => $value) { $expect(get_post_meta($post->ID, $field, true) === $value, 'Metadata changed: ' . $field); }
    $row['before_post'] = $post->to_array();
    $row['all_meta_before'] = get_post_meta($post->ID);
    $row['draft_hash'] = $draft_hash($post->ID);
    $receipt['records'][] = $row;
}
foreach ($input['ignored_series'] as $series_key) {
    $series = $owner->get_series_by_key($series_key);
    $expect($series && $series['review_state'] === 'mapped', 'Series changed.');
    $receipt['series'][$series_key] = $series;
}
foreach ($input['ignored_slots'] + $input['appointment_titles'] as $external_id => $title) {
    $slot = $owner->get_supersaas_slot_by_external($external_id);
    $occurrence = $owner->get_occurrence_by_external('supersaas', $external_id);
    $expect($slot && $occurrence && $slot['raw_title'] === $title, 'Slot identity changed: ' . $external_id);
    $receipt['slots'][$external_id] = $slot;
    $receipt['occurrences'][$external_id] = $occurrence;
}
if ($mode === 'dry-run') { echo "Ready: four tours, two ignored series, three ignored appointments and one special title.\n"; return; }
$expect(add_option($receipt_key, $receipt, '', false) && get_option($receipt_key) === $receipt, 'Before-image not saved.');
foreach ($receipt['records'] as $row) {
    $expect(iss_editorial_save_document($row['id'], 'fuehrung', $row['after_document'], false), 'Document save failed.');
    foreach ($row['after_meta'] as $field => $value) { update_post_meta($row['id'], $field, $value); }
    $result = wp_update_post(wp_slash(['ID' => $row['id']] + $row['after_fields']), true);
    $expect(!is_wp_error($result), 'Post save failed.');
}
foreach ($input['ignored_series'] as $series_key) { $expect($owner->set_series_review_state($series_key, 'ignored'), 'Series review failed.'); }
foreach ($input['ignored_slots'] as $external_id => $title) {
    $expect($owner->set_supersaas_slot_review_state((int) $receipt['slots'][$external_id]['id'], 'ignored'), 'Slot review failed.');
    $expect($owner->delete_occurrence_by_external('supersaas', $external_id) === 1, 'Projection removal failed.');
}
foreach ($input['appointment_titles'] as $external_id => $title) {
    $row = $receipt['occurrences'][$external_id];
    $row['title'] = $title;
    // A title correction must not rename the broader series.
    $row['supersaas_title'] = $owner->get_series_by_key($row['series_key'])['supersaas_title'];
    $expect($owner->upsert_occurrence($row) > 0, 'Appointment title write failed.');
}
do_action('iss_occurrences_changed', ['origin' => 'supersaas', 'audit_corrections' => true]);
$receipt['complete'] = true;
update_option($receipt_key, $receipt, false);
$expect(get_option($receipt_key) === $receipt, 'Receipt write failed.');
$verify();
