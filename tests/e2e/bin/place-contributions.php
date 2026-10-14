<?php
/** Isolated native-comment tables and process-local Place fixture; no mail or public submissions. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
global $wpdb;
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($label); }
    ++$checks;
};
$id = (int) $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts}") + 1000000;
$comment_start = (int) $wpdb->get_var("SELECT MAX(comment_ID) FROM {$wpdb->comments}") + 1000000;
$expect(!wp_using_ext_object_cache() && !get_post($id), 'Process-local fixture is isolated');
$fixture = new WP_Post((object) ['ID' => $id, 'post_type' => 'register_place', 'post_status' => 'publish', 'post_title' => 'Contribution fixture', 'post_name' => 'contribution-fixture', 'post_content' => '', 'post_excerpt' => '', 'post_author' => 0, 'comment_status' => 'open']);
wp_cache_set($id, $fixture, 'posts');
$set_open = static function (bool $open, string $generation = 'one') use ($id): void {
    wp_cache_set($id, ['_iss_upload_open' => [$open ? '1' : '0'], '_iss_upload_generation' => [$generation]], 'post_meta');
};
$before = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments}");
$original_comments = $wpdb->comments;
$original_commentmeta = $wpdb->commentmeta;
$temporary_comments = $wpdb->prefix . 'iss_fixture_comments';
$temporary_commentmeta = $wpdb->prefix . 'iss_fixture_commentmeta';
$expect($wpdb->query("CREATE TEMPORARY TABLE $temporary_comments LIKE $original_comments") !== false, 'Isolated comment table created before any writes');
$expect($wpdb->query("CREATE TEMPORARY TABLE $temporary_commentmeta LIKE $original_commentmeta") !== false, 'Isolated comment metadata table created before any writes');
$wpdb->comments = $temporary_comments;
$wpdb->commentmeta = $temporary_commentmeta;
$expect($wpdb->query("ALTER TABLE {$wpdb->comments} AUTO_INCREMENT = $comment_start") !== false, 'Isolated comment identities allocated');
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
add_filter('notify_moderator', '__return_false');
add_filter('notify_post_author', '__return_false');
$directory = sys_get_temp_dir() . '/place-receipts-' . wp_generate_uuid4();
mkdir($directory, 0700);
try {
    $set_open(false);
    $input = ['feedback_nonce' => wp_create_nonce('iss_feedback_' . $id), 'feedback_name' => "Gast O'Neil", 'feedback_text' => "Eine Erinnerung aus der Zeit.\nZweite Zeile.", 'feedback_email' => 'private@example.invalid', 'feedback_consent' => '1', 'subject' => '1945–1990 · Historischer Betrieb'];
    $expect(iss_content_upload_url($id, $input['subject']) === '', 'Closed intake has no topic link');
    $expect(is_wp_error(iss_content_submit_feedback($id, '', $input)), 'Closed Place rejects text');
    $set_open(true);
    $key = iss_content_upload_key($id);
    $url = iss_content_upload_url($id, $input['subject']);
    parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $args);
    $expect($args['subject'] === $input['subject'] && (int) $args['context'] === $id, 'Topic URL keeps the signed Place identity');
    parse_str((string) wp_parse_url(iss_content_upload_url($id, 'Fotos & Erinnerungen? #1945 + 1990'), PHP_URL_QUERY), $special_args);
    $expect($special_args['subject'] === 'Fotos & Erinnerungen? #1945 + 1990', 'Topic punctuation cannot split the query or fragment');
    $expect(is_wp_error(iss_content_submit_feedback($id, 'wrong', $input)), 'Topic cannot bypass signature');
    foreach (['feedback_nonce' => '', 'feedback_name' => '', 'feedback_text' => '', 'feedback_consent' => '', 'feedback_email' => 'invalid', 'feedback_website' => 'spam', 'subject' => ['bad']] as $field => $value) {
        $expect(is_wp_error(iss_content_submit_feedback($id, $key, array_replace($input, [$field => $value]))), 'Invalid submission rejected: ' . $field);
    }
    $comment = iss_content_submit_feedback($id, $key, $input);
    wp_cache_set($id, $fixture, 'posts'); $set_open(true);
    $expect(is_int($comment) && $comment >= $comment_start, 'Place text accepted without a file into isolated table');
    $expect(get_comment($comment)->comment_approved === '0', 'New Place memories require moderation');
    $expect(get_comment_meta($comment, '_iss_feedback_subject', true) === $input['subject'], 'Topic survives native moderation storage');
    $expect(industriesalon_render_feedback($id, false) === '', 'No pending-memory heading or wrapper');
    $expect(is_wp_error(iss_content_submit_feedback($id, $key, $input)), 'Duplicate text rejected');
    wp_set_comment_status($comment, 'approve');
    wp_cache_set($id, $fixture, 'posts'); $set_open(true);
    $html = industriesalon_render_feedback($id, false);
    $expect(str_contains($html, 'Erinnerungen an diesen Ort') && str_contains($html, 'Eine Erinnerung'), 'Approved Place memory appears');
    $expect(!str_contains($html, 'private@example.invalid'), 'Email stays private');
    $set_open(false);
    $expect(str_contains(industriesalon_render_feedback($id, false), 'Eine Erinnerung'), 'Closing intake retains approved memories');
    $expect(industriesalon_render_place_epoch_contribution('', $id, ['title' => 'Phase', 'start_year' => 1945]) === '', 'Closed epoch invitations vanish');
    $set_open(true, 'two');
    $expect(is_wp_error(iss_content_submit_feedback($id, $key, $input)), 'Changed generation invalidates old links');
    $epoch = ['title' => 'Phase', 'body' => '<p>Actual narrative.</p>', 'start_year' => 1945, 'end_year' => 1990, 'contribution_question' => 'Wissen Sie, was hier ab 1945 geschah?'];
    $gesture = ['type' => 'dynamic_slot', 'slot_key' => 'place-epoch-contribution', 'treatment' => 'slot.place-epoch-contribution', 'title' => $epoch['contribution_question']];
    $expect(str_contains(industriesalon_render_editorial_place_document($id, ['sections' => [array_merge($epoch, ['type' => 'epoche']), $gesture]]), 'Wissen Sie, was hier ab 1945 geschah?'), 'Chosen JSON gesture invites date-specific contributions');
    $expect(!str_contains(industriesalon_render_editorial_place_epoch($epoch, $id), 'research-button'), 'An old authored epoch field does not implicitly add a gesture');
    $expect(industriesalon_render_place_epoch_contribution('', $id, $epoch + ['milestone' => ['title' => 'Event']]) === '', 'Imported milestones do not multiply invitations');
    $no_question = $epoch; unset($no_question['contribution_question']);
    $contexts = [];
    foreach ([
        ['start_year' => 1945, 'end_year' => 1990, 'question' => 'Wissen Sie, was hier zwischen 1945 und 1990 geschah?'],
        ['start_year' => 1964, 'end_year' => 1964, 'question' => 'Wissen Sie, was hier im Jahr 1964 geschah?'],
        ['start_year' => 1990, 'end_year' => null, 'question' => 'Wissen Sie, was hier ab 1990 geschah?'],
        ['start_year' => 2024, 'end_year' => null, 'is_current' => true, 'question' => 'Wissen Sie, was hier seit 2024 geschieht?'],
        ['start_year' => null, 'end_year' => 1945, 'question' => 'Wissen Sie, was hier bis 1945 geschah?'],
        ['start_year' => null, 'end_year' => null, 'question' => 'Wissen Sie mehr über diese Epoche?'],
    ] as $case) {
        $phase = array_replace($no_question, $case);
        $link = industriesalon_render_place_epoch_contribution('', $id, $phase, true);
        $expect(str_contains($link, $case['question']), 'Automatic question follows actual phase dates: ' . $case['question']);
        $tags = new WP_HTML_Tag_Processor($link);
        $expect($tags->next_tag('A'), 'Automatic phase invitation is a usable link');
        parse_str((string) wp_parse_url($tags->get_attribute('href'), PHP_URL_QUERY), $topic_args);
        $expect((int) $topic_args['context'] === $id && $topic_args['key'] === iss_content_upload_key($id), 'Every phase keeps the same signed Place intake');
        $expect(str_contains($topic_args['subject'], 'Phase') && str_contains($topic_args['subject'], $case['question']), 'Phase topic identifies title and question');
        $contexts[] = ['context' => $topic_args['context'], 'key' => $topic_args['key']];
    }
    $expect(count(array_unique($contexts, SORT_REGULAR)) === 1, 'Epoch topics never split the Place destination or its Set');
    $changed = $no_question; $changed['end_year'] = 2000;
    $expect(str_contains(industriesalon_render_place_epoch_contribution('', $id, $changed, true), 'zwischen 1945 und 2000'), 'Editing phase years updates the generated invitation');
    $expect(!array_key_exists('contribution_question', $no_question), 'Automatic rendering does not author or mutate a stored override');
    $fixture->post_status = 'draft'; wp_cache_set($id, $fixture, 'posts');
    $expect(is_wp_error(iss_content_submit_feedback($id, iss_content_upload_key($id), $input)) && industriesalon_render_feedback($id, false) === '', 'Draft Place feedback stays private');
    $fixture->post_status = 'publish'; $fixture->post_password = 'protected'; wp_cache_set($id, $fixture, 'posts');
    $expect(is_wp_error(iss_content_submit_feedback($id, iss_content_upload_key($id), $input)), 'Password-protected Place rejects anonymous feedback');
    $fixture->post_password = ''; $fixture->post_type = 'veranstaltung'; wp_cache_set($id, $fixture, 'posts');
    $expect(str_contains(industriesalon_render_event_feedback($id), 'Stimmen zur Veranstaltung'), 'Event wrapper retains event labels');
    $expect(iss_content_contribution_subject(['invalid']) === '' && mb_strlen(iss_content_contribution_subject(str_repeat('ä', 250))) === 240, 'Topic hint is scalar and bounded');

    $manifest = $directory . '/upload-manifest.csv';
    $old_receipt = ['event_slug' => 'register_place__fixture', 'participant_id' => 'Guest', 'original_name' => 'old,photo.jpg', 'stored_name' => 'old.jpg', 'extension' => 'jpg', 'size_bytes' => '100', 'sha256' => 'hash', 'remote_ip' => '', 'user_agent' => '', 'token_suffix' => '', 'uploaded_at' => '2026-10-14', 'attribution' => "O'Neil", 'license' => 'all-rights-reserved', 'consent' => '1', 'uploader_email' => 'private@example.invalid'];
    $fp = fopen($manifest, 'w'); fputcsv($fp, array_keys($old_receipt)); fputcsv($fp, array_values($old_receipt)); fclose($fp);
    $before_csv = file_get_contents($manifest);
    $new_receipt = array_replace($old_receipt, ['stored_name' => 'new.jpg', 'contribution_subject' => $input['subject']]);
    $expect(iss_content_editorial_sets_event_drop_append_receipt($manifest, $new_receipt), 'Topic receipt appended through existing manifest owner');
    $rows = iss_content_editorial_sets_event_drop_manifest_rows($manifest);
    $expect($rows['new.jpg']['contribution_subject'] === $input['subject'], 'File intake parser retains epoch topic');
    unset($rows['old.jpg']['contribution_subject']);
    $expect($rows['old.jpg'] === $old_receipt && file_get_contents($manifest . '.before-subject') === $before_csv, 'Header extension preserves old values and before-image');
    $expect(iss_content_editorial_sets_event_drop_append_receipt($manifest, array_replace($new_receipt, ['stored_name' => 'second.jpg'])) && count(iss_content_editorial_sets_event_drop_manifest_rows($manifest)) === 3, 'Later receipts append without another header migration');
    WP_CLI::success("Place contributions: $checks checks passed.");
} finally {
    $wpdb->query("DROP TEMPORARY TABLE {$wpdb->commentmeta}");
    $wpdb->query("DROP TEMPORARY TABLE {$wpdb->comments}");
    $wpdb->comments = $original_comments;
    $wpdb->commentmeta = $original_commentmeta;
    $expect($before === $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments}"), 'Published comments unchanged');
    foreach (glob($directory . '/*') as $path) { unlink($path); }
    rmdir($directory);
    wp_cache_delete($id, 'posts'); wp_cache_delete($id, 'post_meta');
    remove_filter('notify_moderator', '__return_false'); remove_filter('notify_post_author', '__return_false');
}
