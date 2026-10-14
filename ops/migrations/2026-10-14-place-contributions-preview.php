<?php
/** Explicit KAOS 93 preview intake choice. No other Place is opened by deployment.
 * wp eval-file ... dry-run|apply|verify|rollback
 * Uses the contribution owner API; rollback closes intake and revokes preview links.
 * No media/upload artifact is needed for this setting.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')) { exit(1); }
$mode = $args[0] ?? 'dry-run';
if (!in_array($mode, ['dry-run', 'apply', 'verify', 'rollback'], true)) { WP_CLI::error('Use dry-run, apply, verify or rollback.'); }
$post = get_page_by_path('kaos-93-kunst-und-gewerbehof-genossenschaft-i-gr', OBJECT, 'register_place');
if (!$post || $post->post_status !== 'publish' || !function_exists('iss_content_submit_feedback')) { WP_CLI::error('Reviewed published Place or matching contribution code is missing.'); }
$backup_key = 'iss_backup_20261014_place_contributions_preview';
$backup = get_option($backup_key);
if ($mode === 'rollback') {
    if (!is_array($backup) || (int) $backup['post_id'] !== $post->ID) { WP_CLI::error('Targeted backup is missing.'); }
    iss_content_set_upload_open($post->ID, (bool) $backup['was_open']);
    WP_CLI::success('Restored the previous intake choice through its owner API.');
    return;
}
if (iss_content_upload_is_open($post->ID)) { WP_CLI::success('KAOS 93 contribution preview is open and verified.'); return; }
if ($mode === 'verify') { WP_CLI::error('KAOS 93 contribution preview is closed.'); }
if ($mode === 'dry-run') { WP_CLI::success('One explicit KAOS 93 intake choice will open text and file contributions.'); return; }
if (!is_array($backup) && !add_option($backup_key, ['post_id' => $post->ID, 'was_open' => false, 'open_meta' => get_post_meta($post->ID, '_iss_upload_open', true)], '', false)) {
    WP_CLI::error('Could not save the targeted intake before-image.');
}
iss_content_set_upload_open($post->ID, true);
if (!iss_content_upload_is_open($post->ID)) { WP_CLI::error('Intake did not open; targeted backup retained.'); }
WP_CLI::success('Opened only KAOS 93 contributions; text requires moderation and files remain private.');
