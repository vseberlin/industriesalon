<?php
/**
 * Explicit opening copy for the source-identified Anne Rabe event; no title parsing.
 * wp eval-file FILE check|apply|verify /PRIVATE/new-receipt.json --use-include
 * Deploy matching iss-content/editorial/theme code first. No new media required.
 * Apply exclusively creates a targeted before-image before writing through the
 * shared editor API. Rollback: compare current document to receipt.after, then
 * save receipt.before through iss_editorial_save_document(id, 'veranstaltung', ...).
 * Do not roll back over subsequent editorial changes. Post title/body stay intact.
 */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$mode = $args[0] ?? 'check';
$path = $args[1] ?? '';
if (!in_array($mode, ['check', 'apply', 'verify'], true)) { WP_CLI::error('Unknown mode.'); }
$ids = get_posts(['post_type' => 'veranstaltung', 'post_status' => 'publish', 'meta_key' => 'source_post_id', 'meta_value' => '12763', 'fields' => 'ids']);
if (count($ids) !== 1) { WP_CLI::error('Expected one Anne Rabe source record.'); }
$id = (int) $ids[0];
$before = iss_content_model_veranstaltung_content_document($id);
if (!$before || !str_contains(get_the_title($id), 'Anne Rabe')) { WP_CLI::error('Unexpected source identity/document.'); }
$fields = ['hero_kicker' => 'Zukunft im Gespräch · Lesung & Gespräch', 'hero_title' => 'Anne Rabe', 'hero_subtitle' => 'Die Möglichkeit von Glück', 'hero_image_fit' => 'contain'];
$after = iss_editorial_validate_document(array_merge($before, $fields), 'veranstaltung');
if (is_wp_error($after) || array_intersect_key($after, $fields) != $fields) { WP_CLI::error('Deploy the matching editorial contract first.'); }
if ($mode === 'verify') {
    if (array_intersect_key($before, $fields) != $fields) { WP_CLI::error('Opening copy differs.'); }
    WP_CLI::success('Opening copy verified.'); return;
}
foreach ($fields as $key => $value) {
    if (!empty($before[$key]) && $before[$key] !== $value) { WP_CLI::error('Preserve existing editorial choice: ' . $key); }
}
if ($mode === 'check') { WP_CLI::success('One source-identified event ready.'); return; }
if ($before === $after) { WP_CLI::success('Already applied.'); return; }
if (!$path || file_exists($path)) { WP_CLI::error('A new private before-image path is required.'); }
$receipt = wp_json_encode(['id' => $id, 'source_post_id' => 12763, 'before' => $before, 'after' => $after], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$f = fopen($path, 'x');
if (!$f) { WP_CLI::error('Cannot create before-image.'); }
$written = fwrite($f, $receipt); fclose($f);
if ($written !== strlen($receipt) || file_get_contents($path) !== $receipt) { WP_CLI::error('Before-image verification failed.'); }
if (!iss_editorial_save_document($id, 'veranstaltung', $after) || iss_content_model_veranstaltung_content_document($id) !== $after) { WP_CLI::error('Save/readback failed; retain receipt.'); }
WP_CLI::success('Anne Rabe opening saved; original document retained in private receipt.');
