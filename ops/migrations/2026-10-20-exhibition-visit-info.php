<?php
/**
 * Retire the equivalent exhibition template override and apply reviewed admission facts.
 * wp eval-file FILE dry-run|apply|verify PRIVATE_JSON --use-include --user=admin
 * Private input: home_url, template_hash, prices_before, prices_after, source_note.
 * Prices may stay unchanged while confirmation is pending. Never infer free admission.
 * Rollback after checking later edits: restore the recorded template post_name/status
 * with wp_update_post. Restore prices via update_option(before_prices_raw), or
 * delete_option when that value is null (previously absent). No posts are deleted.
 */
if (!defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('WP-CLI required.'); }
$mode = $args[0] ?? 'dry-run';
$path = $args[1] ?? '';
$expect = static function (bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException(esc_html($message)); }
};
$expect(in_array($mode, ['dry-run', 'apply', 'verify'], true) && is_readable($path), 'Mode and private input required.');
$expect(current_user_can('manage_options'), 'Administrator required.');
$input = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$expect(($input['home_url'] ?? '') === home_url() && !empty($input['source_note']), 'Input environment/source differs.');
$owner = Industriesalon_Steuerung::instance();
$prices_key = $owner->get_option_keys()['prices'];
$expect(is_array($input['prices_before']) && is_array($input['prices_after'])
    && $owner->sanitize_prices($input['prices_after']) === $input['prices_after'], 'Invalid reviewed prices.');
$receipt_key = 'iss_backup_20261020_exhibition_visit_info';
$receipt = get_option($receipt_key);
$slug = 'single-ausstellung';
$retired_slug = 'single-ausstellung-pre-visitor-20261020';
$verify = static function () use (&$receipt, $expect, $input, $owner, $slug, $retired_slug, $path): void {
    $expect(is_array($receipt) && !empty($receipt['complete'])
        && $receipt['input_sha256'] === hash_file('sha256', $path), 'Receipt missing, incomplete or for another input.');
    $active = get_block_template('industriesalon//' . $slug, 'wp_template');
    $retired = get_post($receipt['before_template']['ID']);
    $expect($active && $active->source === 'theme' && $retired instanceof WP_Post
        && $retired->post_name === $retired_slug && $retired->post_status === 'draft'
        && $retired->post_content === $receipt['before_template']['post_content'], 'Template authority/preservation differs.');
    $expect($owner->get_all_data()['prices'] === $input['prices_after'], 'Reviewed prices differ.');
    echo "Verified exhibition template authority and central admission settings.\n";
};
if ($mode === 'verify' || is_array($receipt)) { $verify(); return; }
$template = get_block_template('industriesalon//' . $slug, 'wp_template');
$disk = get_block_file_template('industriesalon//' . $slug, 'wp_template');
$expect($template && $template->source === 'custom' && $template->wp_id > 0 && $disk
    && hash('sha256', $template->content) === $input['template_hash'], 'Inspected template override differs.');
$normalize = static function (array $blocks) use (&$normalize): array {
    foreach ($blocks as &$block) {
        if ($block['blockName'] === 'core/template-part') { unset($block['attrs']['theme']); }
        if ($block['blockName'] === 'iss/content-meta') { $block['attrs'] = []; }
        $block['innerBlocks'] = $normalize($block['innerBlocks']);
    }
    return $blocks;
};
$expect($normalize(parse_blocks(rtrim($template->content))) === $normalize(parse_blocks(rtrim($disk->content))), 'Override has unique composition; preserve/review before retiring.');
$expect(!get_page_by_path($retired_slug, OBJECT, 'wp_template'), 'Retired template slug already exists.');
$expect($owner->get_all_data()['prices'] === $input['prices_before'], 'Admission settings changed since inspection.');
if ($mode === 'dry-run') { echo "Ready: preserve one equivalent template override; sync reviewed central prices only. No SQL or uploads.\n"; return; }
$receipt = [
    'complete' => false, 'before_template' => get_post($template->wp_id)->to_array(),
    'before_template_meta' => get_post_meta($template->wp_id),
    'before_prices' => $input['prices_before'], 'after_prices' => $input['prices_after'],
    'before_prices_raw' => get_option($prices_key, null),
    'source_note' => $input['source_note'], 'input_sha256' => hash_file('sha256', $path),
];
$expect(add_option($receipt_key, $receipt, '', false) && get_option($receipt_key) === $receipt, 'Before-image not saved.');
$result = wp_update_post(['ID' => $template->wp_id, 'post_name' => $retired_slug, 'post_status' => 'draft'], true);
$expect(!is_wp_error($result) && $result === $template->wp_id, 'Template retirement failed.');
if ($input['prices_before'] !== $input['prices_after']) {
    update_option($prices_key, $input['prices_after']);
}
$receipt['complete'] = true;
update_option($receipt_key, $receipt, false);
$expect(get_option($receipt_key) === $receipt, 'Receipt write failed.');
$verify();
