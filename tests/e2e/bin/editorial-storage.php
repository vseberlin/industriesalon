<?php
/** Run with wp eval-file and a read-only mount of this file. Own fixtures are removed. */
if (!defined('WP_CLI') || !WP_CLI) {
    exit(1);
}

require_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

global $wpdb;

$checks = 0;
$assert = static function ($condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
};
$fixtures = [];
$fixture_sets = [];
$other_user = 0;
$original_user = get_current_user_id();
$original_post = $_POST;
$admin = get_users(['role' => 'administrator', 'number' => 1])[0];
wp_set_current_user($admin->ID);
$snapshot = static function (): string {
    global $wpdb;
    // An open browser renews its edit lock through heartbeat without changing editorial data.
    return hash('sha256', serialize($wpdb->get_results("SELECT p.ID,p.post_title,p.post_excerpt,p.post_content,m.meta_key,m.meta_value FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key <> '_edit_lock' WHERE p.post_type <> 'revision' ORDER BY p.ID,m.meta_id", ARRAY_A)));
};
$before = $snapshot();
$die_handler = static function () {
    return static function ($message): void {
        throw new RuntimeException('Rejected: ' . wp_strip_all_tags((string) $message));
    };
};
add_filter('wp_die_handler', $die_handler, PHP_INT_MAX);

try {
    $stored_count = 0;
    foreach (iss_editorial_get_registered_formats() as $slug => $format) {
        foreach (get_posts(['post_type' => $format['post_types'], 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => iss_editorial_get_document_meta_key($slug)]) as $post) {
            $raw = get_metadata_raw('post', $post->ID, iss_editorial_get_document_meta_key($slug), true);
            if (!$raw) {
                continue;
            }
            $valid = iss_editorial_validate_document($raw, $slug);
            $assert(!is_wp_error($valid), "Stored $slug document $post->ID: " . (is_wp_error($valid) ? $valid->get_error_message() : ''));
            ++$stored_count;
        }
    }
    foreach (['projekt', 'veranstaltung'] as $post_type) {
        $id = wp_insert_post(['post_type' => $post_type, 'post_status' => 'auto-draft', 'post_title' => 'Editorial integration fixture', 'post_author' => $admin->ID], true);
        $assert(!is_wp_error($id), 'Create fixture');
        $fixtures[] = $id;
        $assert(iss_editorial_uses_canvas(get_post($id)), "New $post_type uses shared canvas");
        wp_update_post(['ID' => $id, 'post_status' => 'draft']);
        foreach (iss_content_editorial_sets_service()->get_links_for_context($post_type, $id) as $link) { $fixture_sets[] = (int) $link['set_id']; }
        $format = iss_editorial_get_format_for_post($id)['slug'];
        $document = iss_editorial_get_empty_document($format);
        if ($format === 'veranstaltung') {
            $document['entity_key'] = 'event.general';
            update_post_meta($id, '_iss_entity_key', 'event.general');
            $assert(iss_editorial_get_document_meta_key($format) === '_iss_content_json', 'Events keep their existing storage key');
        } else {
            $assert(!iss_editorial_uses_canvas(get_post($id)), 'Existing disabled JSON keeps legacy editor');
        }
        $document['sections'] = [['type' => 'kapitel', 'title' => 'Original', 'body' => '<p>Quoted "text" and C:\\source</p>']];
        $assert(iss_editorial_save_document($id, $format, $document), 'Save canonical document');
        iss_editorial_set_document_enabled($id, $format, true);
        $document = iss_editorial_get_document($id, $format);
        $base = iss_editorial_saved_token($id, $format);
        $key = iss_editorial_get_document_meta_key($format);
        $canonical = get_metadata_raw('post', $id, $key, true);
        $baseline_revision = wp_save_post_revision($id);
        $assert((bool) $baseline_revision, 'Create baseline revision');
        $assert(get_metadata_raw('post', $baseline_revision, $key, true) === $canonical, 'Native revisions contain JSON');

        $draft_doc = $document;
        $draft_doc['sections'][0]['title'] = 'First draft';
        if ($format === 'veranstaltung') {
            $draft_doc['entity_key'] = 'event.festival';
        }
        $draft = iss_editorial_save_draft($id, $format, $draft_doc, true, ['title' => 'Draft title', 'excerpt' => 'Draft summary']);
        $assert(!is_wp_error($draft) && $draft['document']['sections'][0]['title'] === 'First draft', 'Native autosave contains document');
        $assert($draft['title'] === 'Draft title' && $draft['excerpt'] === 'Draft summary', 'Autosave includes identity');
        $preview_args = [];
        parse_str((string) wp_parse_url(iss_editorial_get_preview_url($id, $format), PHP_URL_QUERY), $preview_args);
        $old_get = $_GET;
        $_GET = $preview_args;
        $preview = apply_filters('the_preview', get_post($id));
        $assert($preview->post_title === 'Draft title' && $preview->post_excerpt === 'Draft summary', 'Shared preview uses own draft title and excerpt');
        if ($format === 'veranstaltung') {
            $assert(get_post_meta($id, '_iss_entity_key', true) === 'event.festival', 'Preview structure follows own document draft');
        }
        $_GET = $old_get;
        $assert(get_metadata_raw('post', $id, $key, true) === $canonical && iss_editorial_saved_token($id, $format) === $base, 'Autosave never changes canonical content');
        $assert(is_wp_error(iss_editorial_check_edit_version($id, $format, $base, '')), 'Another tab with old draft token is rejected');
        $assert(iss_editorial_check_edit_version($id, $format, $base, $draft['token']) === true, 'Current draft token accepted');
        $draft_doc['sections'][0]['title'] = 'Second draft';
        $second = iss_editorial_save_draft($id, $format, $draft_doc, true);
        $assert(!is_wp_error($second) && $second['id'] === $draft['id'] && $second['token'] !== $draft['token'], 'Same author updates one autosave');

        $partial = $document;
        $partial['sections'][] = ['type' => 'material', 'links' => [['label' => 'Unfinished link', 'url' => '']]];
        $assert(is_wp_error(iss_editorial_validate_document($partial, $format)), 'Incomplete links cannot be published');
        $partial_draft = iss_editorial_save_draft($id, $format, $partial, true);
        $assert(!is_wp_error($partial_draft) && $partial_draft['document']['sections'][1]['links'][0]['label'] === 'Unfinished link', 'Recovery preserves incomplete link entries');

        // A complete undo must replace an older draft even when it equals the parent again.
        $reverted = iss_editorial_save_draft($id, $format, $document, true);
        $assert(!is_wp_error($reverted) && $reverted['document'] === $document, 'Undo back to canonical also resets recovery');

        if (!$other_user) {
            $other_user = wp_insert_user(['user_login' => 'editorial-test-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => 'editor']);
            $assert(!is_wp_error($other_user), 'Create second editor fixture');
        }
        wp_set_current_user($other_user);
        $assert(iss_editorial_get_draft($id, $format) === [], 'Other editors do not receive first author draft');
        $other = iss_editorial_save_draft($id, $format, $draft_doc, true);
        $assert(!is_wp_error($other) && $other['id'] !== $draft['id'], 'Different authors have separate recovery');
        wp_set_current_user(0);
        $assert(iss_editorial_get_document($id, $format, true) === $document, 'Visitors never read private autosaves');
        wp_set_current_user($admin->ID);

        $invalid = $document;
        $invalid['sections'][] = ['type' => 'unknown_future_gesture', 'body' => 'Must survive'];
        $assert(is_wp_error(iss_editorial_validate_document($invalid, $format)), 'Unknown section is rejected');
        $assert(!iss_editorial_save_document($id, $format, '{broken'), 'Malformed JSON cannot wipe canonical document');
        $invalid_version = $document;
        $invalid_version['schema_version'] = 999;
        $assert(is_wp_error(iss_editorial_validate_document($invalid_version, $format)), 'Unsupported schema is rejected');

        $_POST = wp_slash(['iss_editorial_nonce' => wp_create_nonce('iss_editorial_save_document'), 'iss_editorial' => [$format => ['document' => iss_editorial_encode_document($invalid), 'base' => $base, 'draft_token' => $reverted['token'], 'enabled' => '1']]]);
        if ($format === 'projekt') {
            $_POST['iss_content_model_meta_nonce'] = wp_create_nonce('iss_content_model_save_meta');
            $_POST['iss_content_model'] = ['menu_order' => '37'];
        }
        $rejected = false;
        try {
            wp_update_post(['ID' => $id, 'post_title' => 'Must not be written']);
        } catch (RuntimeException $error) {
            $rejected = str_starts_with($error->getMessage(), 'Rejected:');
        }
        $assert($rejected && get_post($id)->post_title === 'Editorial integration fixture' && get_metadata_raw('post', $id, $key, true) === $canonical, 'Validation rejects the whole update before owner saves');
        $assert((int) get_post($id)->menu_order === 0, 'Rejected update leaves the project order unchanged too');

        $_POST['iss_editorial'][$format]['document'] = wp_slash(iss_editorial_encode_document($draft_doc));
        wp_update_post(['ID' => $id, 'post_title' => 'Saved next version']);
        $assert(iss_editorial_get_document($id, $format)['sections'][0]['title'] === 'Second draft', 'WordPress Update saves JSON');
        if ($format === 'projekt') {
            $assert((int) get_post($id)->menu_order === 37, 'Project order and JSON save in one update without a false conflict');
        }
        require_once ABSPATH . 'wp-admin/includes/revision.php';
        $diff = wp_get_revision_ui_diff($id, $baseline_revision, $id);
        $assert(in_array('iss-editorial-document', array_column($diff, 'id'), true), 'Section changes appear in native revision comparison');
        $assert(iss_editorial_get_draft($id, $format) === [], 'Successful Update clears own recovery');
        $assert(is_wp_error(iss_editorial_check_edit_version($id, $format, $base)), 'Older saved base rejected');
        $_POST = [];
        if ($format === 'veranstaltung') {
            update_post_meta($id, '_iss_entity_key', 'event.festival');
        }
        wp_restore_post_revision($baseline_revision);
        $assert(get_metadata_raw('post', $id, $key, true) === $canonical, 'Native revision restore restores exact JSON');
        $assert(iss_editorial_document_is_enabled($id, $format), 'Native revision restores enabled authority');
        if ($format === 'veranstaltung') {
            $assert(get_post_meta($id, '_iss_entity_key', true) === 'event.general', 'Restoring event JSON restores its structure too');
        }

        // Exercise the real authenticated admin page and AJAX route using only the fixture editor.
        $expires = time() + HOUR_IN_SECONDS;
        $token = WP_Session_Tokens::get_instance($other_user)->create($expires);
        $cookies = AUTH_COOKIE . '=' . wp_generate_auth_cookie($other_user, $expires, 'auth', $token) . '; ' . LOGGED_IN_COOKIE . '=' . wp_generate_auth_cookie($other_user, $expires, 'logged_in', $token);
        $headers = ['Cookie' => $cookies];
        $response = wp_remote_get(admin_url('post.php?post=' . $id . '&action=edit'), ['headers' => $headers, 'timeout' => 30]);
        $assert(!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200, 'Authenticated editor returns HTTP 200');
        $html = wp_remote_retrieve_body($response);
        $assert(str_contains($html, 'iss-editorial-root') && str_contains($html, 'id="editor-js"') && !str_contains($html, 'id="autosave-js"'), 'Real admin loads shared canvas and WordPress text editing with a single autosave owner');
        $assert(strpos($html, 'class="iss-editorial-recovery"') < strpos($html, 'class="iss-editorial-root"'), 'Draft choice appears before the section canvas, not below a long disabled document');
        $assert(!str_contains($html, 'id="iss-content-model-veranstaltung-content-js"'), 'Old event script is not loaded with shared editor');
        preg_match('/var issEditorialAdmin = (.*?);\s*\n/', $html, $matches);
        $settings = json_decode($matches[1] ?? '', true);
        $assert(is_array($settings) && empty($settings['validationError']), 'Actual localized editor configuration is valid');
        $response = wp_remote_post($settings['ajaxUrl'], ['headers' => $headers, 'timeout' => 30, 'body' => [
            'action' => 'iss_editorial_save_preview_document', 'nonce' => $settings['previewNonce'], 'post_id' => $id, 'format' => $format,
            'document' => iss_editorial_encode_document($draft_doc), 'base' => $settings['baseToken'], 'draft_token' => $settings['draftToken'],
            'title' => 'HTTP draft title', 'excerpt' => 'HTTP draft summary', 'enabled' => '1',
        ]]);
        $saved = json_decode(wp_remote_retrieve_body($response), true);
        $assert(wp_remote_retrieve_response_code($response) === 200 && !empty($saved['success']), 'Real AJAX request saves a private draft');
        $assert(get_metadata_raw('post', $id, $key, true) === $canonical, 'AJAX autosave leaves canonical JSON unchanged');
        $preview_response = wp_remote_get($saved['data']['previewUrl'], ['headers' => $headers, 'timeout' => 30]);
        $assert(wp_remote_retrieve_response_code($preview_response) === 200 && str_contains(wp_remote_retrieve_body($preview_response), 'HTTP draft title'), 'Real preview displays the saved draft title');
        $assert(!str_contains(wp_remote_retrieve_body($preview_response), 'iss-editorial-preview-frame-js') && !str_contains(wp_remote_retrieve_body($preview_response), 'iss-editorial-preview-frame-css'), 'Separate-window preview does not load the embedding bridge or selection outline');
        $embedded_url = add_query_arg(['iss_editorial_embed' => '1', 'iss_editorial_snapshot' => $saved['data']['draftToken']], $saved['data']['previewUrl']);
        $embedded = wp_remote_get($embedded_url, ['headers' => $headers, 'timeout' => 30]);
        $assert(wp_remote_retrieve_response_code($embedded) === 200 && str_contains(wp_remote_retrieve_body($embedded), 'iss-editorial-preview-frame-js') && str_contains(wp_remote_retrieve_body($embedded), 'iss-editorial-preview-frame-css'), 'Matching authenticated snapshot loads the preview bridge and section selection styles');
        $outdated = wp_remote_get(add_query_arg('iss_editorial_snapshot', str_repeat('0', 64), $embedded_url), ['headers' => $headers, 'timeout' => 30]);
        $assert(wp_remote_retrieve_response_code($outdated) === 409, 'Outdated iframe snapshot is rejected');
        $anonymous = wp_remote_get($embedded_url, ['timeout' => 30]);
        $assert(wp_remote_retrieve_response_code($anonymous) !== 200 || !str_contains(wp_remote_retrieve_body($anonymous), 'iss-editorial-preview-frame-js'), 'Anonymous request cannot render the private embedded draft');
        $partial_response = wp_remote_post($settings['ajaxUrl'], ['headers' => $headers, 'timeout' => 30, 'body' => [
            'action' => 'iss_editorial_save_preview_document', 'nonce' => $settings['previewNonce'], 'post_id' => $id, 'format' => $format,
            'document' => iss_editorial_encode_document($partial), 'base' => $settings['baseToken'], 'draft_token' => $saved['data']['draftToken'], 'enabled' => '1',
        ]]);
        $partial_result = json_decode(wp_remote_retrieve_body($partial_response), true);
        $assert(wp_remote_retrieve_response_code($partial_response) === 200 && !empty($partial_result['success']) && !empty($partial_result['data']['validationMessage']), 'AJAX secures unfinished work and reports what blocks publication');
        // Make the meta-only autosave newer: classic edit-form cleanup otherwise ignores JSON changes.
        $autosave_id = (int) $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_parent=%d AND post_author=%d AND post_type='revision' AND post_name LIKE %s", $id, $other_user, $wpdb->esc_like($id . '-autosave') . '%'));
        $wpdb->update($wpdb->posts, ['post_modified_gmt' => gmdate('Y-m-d H:i:s', time() + 2)], ['ID' => $autosave_id]);
        $reload = wp_remote_get(admin_url('post.php?post=' . $id . '&action=edit'), ['headers' => $headers, 'timeout' => 30]);
        preg_match('/var issEditorialAdmin = (.*?);\s*\n/', wp_remote_retrieve_body($reload), $reload_matches);
        $reload_settings = json_decode($reload_matches[1] ?? '', true);
        $assert(wp_remote_retrieve_response_code($reload) === 200 && !empty($reload_settings['recovery']), 'Reload retains recovery when only section metadata changed');
        $assert($reload_settings['recovery']['document'] === $partial, 'Reload preserves unfinished section entries exactly');
        $discard_response = wp_remote_post($settings['ajaxUrl'], ['headers' => $headers, 'timeout' => 30, 'body' => [
            'action' => 'iss_editorial_save_preview_document', 'nonce' => $settings['previewNonce'], 'post_id' => $id, 'format' => $format,
            'base' => $reload_settings['baseToken'], 'draft_token' => $reload_settings['draftToken'], 'intent' => 'discard',
        ]]);
        $discard_result = json_decode(wp_remote_retrieve_body($discard_response), true);
        $assert(!empty($discard_result['success']) && !$wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID=%d", $autosave_id)), 'Explicit recovery discard still deletes its autosave');
        WP_Session_Tokens::get_instance($other_user)->destroy($token);
    }
    wp_set_current_user($admin->ID);
    $rich_doc = iss_editorial_get_empty_document('landing');
    $rich_doc['schema_version'] = 2;
    $rich = '<p><a href="/archive/"><strong><span class="iss-ink-123abc">Colour</span></strong></a> <span class="iss-mark-ffeeaa">mark</span></p>';
    $rich_doc['sections'] = [
        ['type' => 'fliesstext', 'title' => 'Rich text fixture', 'body' => $rich, 'treatment' => 'text.standard'],
        ['type' => 'text_bild_reihe', 'body' => '', 'items' => [['label' => 'Row', 'text' => '<a href="/archive/"><em>Visit</em></a><br>Literal &lt;b&gt;text&lt;/b&gt;']], 'treatment' => 'text-bild-reihe.compact'],
        ['type' => 'gateway', 'body' => '', 'items' => [['label' => 'Card', 'url' => '/archive/', 'text' => '<span class="iss-ink-e81d25">Red</span>']], 'treatment' => 'gateway.cards'],
    ];
    $v2 = iss_editorial_validate_document($rich_doc, 'landing');
    $assert(!is_wp_error($v2), 'Landing v2 validates rich prose and inline descriptions');
    $assert($v2['sections'][0]['body'] === $rich, 'Colour, highlight, emphasis and link survive server normalization');
    $rendered = industriesalon_editorial_landing_render_document($v2);
    $assert(str_contains($rendered, $rich), 'Theme renders v2 body formatting');
    $assert(str_contains($rendered, '<a href="/archive/"><em>Visit</em></a>'), 'Non-linked row renders its inline prose link');
    $assert(str_contains($rendered, 'Literal &lt;b&gt;text&lt;/b&gt;'), 'Literal legacy markup stays literal after explicit upgrade');
    $assert(str_contains($rendered, '<span class="iss-ink-e81d25">Red</span>'), 'Card text renders its colour span');
    $legacy = $rich_doc;
    $legacy['schema_version'] = 1;
    $legacy['sections'][1]['items'][0]['text'] = 'Literal <b>text</b>';
    $legacy_html = industriesalon_editorial_landing_render_document($legacy);
    $assert(str_contains($legacy_html, 'Literal &lt;b&gt;text&lt;/b&gt;'), 'v1 theme continues to escape plain item descriptions');
    $bad = $rich_doc;
    $bad['sections'][2]['items'][0]['text'] = '<a href="/nested/">nested</a>';
    $assert(is_wp_error(iss_editorial_validate_document($bad, 'landing')), 'Card text cannot contain a nested link');
    foreach (['<span class="iss-ink-red">bad</span>', '<span class="iss-ink-123abc evil">bad</span>', '<p style="color:red">old</p>', '<a href="javascript:alert(1)">bad</a>', '<iframe src="/">bad</iframe>'] as $markup) {
        $bad['sections'][0]['body'] = $markup;
        $bad['sections'][2] = $rich_doc['sections'][2];
        $assert(is_wp_error(iss_editorial_validate_document($bad, 'landing')), 'Unsupported markup is reported before save: ' . $markup);
    }
    $assert(iss_editorial_sanitize_rich_text('<a href="javascript:alert(1)">bad</a>', 'block') === '<a>bad</a>', 'Unsafe protocol does not reach public output');
    $new_tab = new WP_HTML_Tag_Processor(iss_editorial_sanitize_rich_text('<a href="/" target="_blank">new tab</a>', 'inline'));
    $new_tab->next_tag('a');
    $assert($new_tab->get_attribute('target') === '_blank' && $new_tab->get_attribute('rel') === 'noopener noreferrer', 'New-tab links receive safe rel attributes');
    $assert(!iss_editorial_supports_version(iss_editorial_get_format('projekt'), 2), 'Other formats do not opt into v2');
    $assert(!iss_editorial_supports_version(iss_editorial_get_format('landing'), '2'), 'Schema version remains a strict integer');
    $id = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Editorial v2 fixture', 'post_author' => $admin->ID]);
    $fixtures[] = $id;
    $fixture_format = static function (array $formats) use ($id): array {
        $original = $formats['landing']['post_eligibility_callback'];
        $formats['landing']['post_eligibility_callback'] = static fn($post) => (int) $post->ID === $id || $original($post);
        return $formats;
    };
    add_filter('iss_editorial_formats', $fixture_format, 99);
    $assert(iss_editorial_save_document($id, 'landing', $v2), 'Save v2 canonical fixture');
    $key = iss_editorial_get_document_meta_key('landing');
    $canonical_v2 = get_metadata_raw('post', $id, $key, true);
    $revision_v2 = wp_save_post_revision($id);
    $changed_v2 = $v2;
    $changed_v2['sections'][0]['body'] = str_replace('123abc', 'abcdef', $rich);
    $draft_v2 = iss_editorial_save_draft($id, 'landing', $changed_v2, true);
    $assert(!is_wp_error($draft_v2) && $draft_v2['document']['sections'][0]['body'] === $changed_v2['sections'][0]['body'], 'Native draft recovery preserves v2 colours exactly');
    $assert(get_metadata_raw('post', $id, $key, true) === $canonical_v2, 'Private v2 draft leaves canonical unchanged');
    $assert(iss_editorial_save_document($id, 'landing', $changed_v2), 'Save changed v2 fixture');
    wp_restore_post_revision($revision_v2);
    $assert(get_metadata_raw('post', $id, $key, true) === $canonical_v2, 'Native revision restore retains v2 marks and inline text');
    remove_filter('iss_editorial_formats', $fixture_format, 99);
    WP_CLI::log("PASS: $checks checks, including $stored_count existing documents; temporary records only.");
} finally {
    $_POST = [];
    remove_filter('wp_die_handler', $die_handler, PHP_INT_MAX);
    wp_set_current_user($admin->ID);
    foreach ($fixtures as $id) {
        wp_delete_post($id, true);
    }
    foreach ($fixture_sets as $set_id) {
        $service = iss_content_editorial_sets_service();
        $assert($service->delete_set_if_safe($set_id), 'Remove own empty test Set');
        $wpdb->delete($service->get_audit_table_name(), ['set_id' => $set_id], ['%d']);
    }
    if (is_int($other_user) && $other_user > 0) {
        wp_delete_user($other_user);
    }
    $_POST = $original_post;
    wp_set_current_user($original_user);
    $assert($snapshot() === $before, 'Existing post content and metadata are unchanged after fixture cleanup');
    WP_CLI::log('PASS: existing post content and metadata unchanged; fixtures removed.');
}
