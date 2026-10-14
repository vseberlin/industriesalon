<?php
/** One explicitly authored KAOS 93 research invitation; no bulk Place changes.
 * dry-run|apply|verify|rollback. Requires the v4 epoch owner schema/code.
 * Uses the epoch owner API and retains the previous phase rows in a private option.
 * No new media or uploads artifact is needed for this field.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')) { exit(1); }
$mode = $args[0] ?? 'dry-run';
if (!in_array($mode, ['dry-run', 'apply', 'verify', 'rollback'], true)) { WP_CLI::error('Use dry-run, apply, verify or rollback.'); }
$post = get_page_by_path('kaos-93-kunst-und-gewerbehof-genossenschaft-i-gr', OBJECT, 'register_place');
if (!$post || $post->post_status !== 'publish' || iss_editorial_document_is_enabled($post->ID, 'place')
    || ISS_REGISTER_EPOCH_SCHEMA_VERSION !== '2026-10-14-v4') { WP_CLI::error('Reviewed legacy Place or matching schema/code is missing.'); }
$service = iss_register_get_epoch_service();
$rows = $service->get_epochs_for_place($post->ID);
$matches = array_keys(array_filter($rows, static fn(array $row): bool => $row['phase_name'] === 'Industrieareal in DDR-Zeit' && $row['start_year'] === 1945 && $row['end_year'] === 1990));
if (count($rows) !== 4 || count($matches) !== 1) { WP_CLI::error('Reviewed four-phase baseline differs.'); }
$index = $matches[0];
$question = 'Wissen Sie, was hier zwischen 1945 und 1990 geschah?';
$backup_key = 'iss_backup_20261014_place_research_question';
$backup = get_option($backup_key);
if ($mode === 'rollback') {
    if (!is_array($backup) || (int) $backup['post_id'] !== $post->ID || $rows[$index]['contribution_question'] !== $question) { WP_CLI::error('Preview or before-image differs; no overwrite.'); }
    // Restore only the authored field, preserving any later changes to other phase facts.
    $rows[$index]['contribution_question'] = (string) ($backup['question'] ?? '');
} elseif ($rows[$index]['contribution_question'] === $question) {
    WP_CLI::success('One KAOS 93 research invitation already verified.'); return;
} elseif ($mode === 'verify') { WP_CLI::error('Research invitation is not installed.');
} elseif ($rows[$index]['contribution_question'] !== '') { WP_CLI::error('An authored question already exists; no overwrite.');
} elseif ($mode === 'dry-run') { WP_CLI::success('One authored invitation will be added to the existing DDR phase.'); return;
} else {
    if (!is_array($backup) && !add_option($backup_key, ['post_id' => $post->ID, 'epochs' => $rows, 'question' => ''], '', false)) { WP_CLI::error('Could not save the targeted before-image.'); }
    $rows[$index]['contribution_question'] = $question;
}
$result = $service->save_epochs_for_place($post->ID, $rows, ['reason' => 'Reviewed Place dossier research invitation']);
if (is_wp_error($result)) { WP_CLI::error($result->get_error_message()); }
iss_register_clear_places_cache();
$saved = $service->get_epochs_for_place($post->ID);
if (($saved[$index]['contribution_question'] ?? '') !== $rows[$index]['contribution_question']) { WP_CLI::error('Research question did not survive owner readback.'); }
WP_CLI::success($mode === 'rollback' ? 'Restored the previous research question.' : 'Added one explicit KAOS 93 research question; other phase content preserved.');
