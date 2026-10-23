<?php
/** wp eval-file FILE --use-include. Temporary options; no mail, subscriber writes or external requests. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Connection-local shadow options isolate verification receipts/settings.
global $wpdb;
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($label); } };
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !wp_using_ext_object_cache(), 'Fixture requires disabled cron and external cache');
add_filter('pre_wp_mail', static function () { throw new RuntimeException('Fixture must not send mail.'); });
add_filter('pre_http_request', static function () { return new WP_Error('fixture_offline', 'No external calls in privacy fixture.'); });

foreach (['https://www.youtube.com/embed/test', 'https://www.youtube-nocookie.com/embed/test'] as $src) {
    $html = do_blocks('<!-- wp:html --><iframe src="' . $src . '" title="Fixture"></iframe><!-- /wp:html -->');
    $tag = new WP_HTML_Tag_Processor($html); $tag->next_tag('iframe');
    $expect($tag->get_attribute('src') === null && $tag->get_attribute('data-iss-consent-src') === $src, 'Native raw embed blocked before browser parses it');
    $expect($tag->get_attribute('hidden') === true && str_contains($html, 'Video abspielen'), 'Blocked embed has visible consent alternative');
    $expect(iss_consent_block_embeds($html) === $html, 'Nested block filtering does not duplicate controls');
}
$expect(!iss_consent_youtube_url('https://youtube.com.attacker.invalid/embed/test'), 'Provider host matching is exact');
$expect(iss_newsletter_private_cookie_headers(['Set-Cookie: newsletter=secret', 'Set-Cookie: tnpe=campaign', 'Set-Cookie: wordpress_logged_in=retain', 'Content-Type: text/html']) === ['Set-Cookie: wordpress_logged_in=retain', 'Content-Type: text/html'], 'Suppress only newsletter identity cookies; preserve authentication');
$url = 'https://example.invalid/path;segment?x=1&y=2'; $key = 'fixture-only-key';
$signature = md5('0;0;' . $url . ';' . $key);
$expect(iss_newsletter_untracked_url(base64_encode('0;0;' . $url . ';;' . $signature), $key) === $url, 'Signed legacy link retains semicolons/query without subscriber reads');
$expect(iss_newsletter_untracked_url(base64_encode('0;0;' . $url . ';;wrong'), $key) === '', 'Forged external redirect rejected');
$unsafe = 'javascript:alert(1)';
$expect(iss_newsletter_untracked_url(base64_encode('0;0;' . $unsafe . ';;' . md5('0;0;' . $unsafe . ';' . $key)), $key) === '', 'Non-HTTP signed redirect rejected');
$expect(has_action('wp_loaded', [NewsletterStatistics::instance(), 'hook_wp_loaded']) === false, 'Legacy collection entry point replaced');
$expect(has_action('wp_loaded', 'iss_newsletter_handle_legacy_tracking') !== false, 'Signed compatibility handler installed');
$subscription = new TNP_Subscription(); $subscription->optin = 'single';
$subscription = apply_filters('newsletter_subscription', $subscription, null);
$expect($subscription->optin === 'double', 'Single-opt-in shortcode override cannot bypass confirmation');
$email = new TNP_Email(); $email->id = 0; $email->subject = 'Fixture'; $email->track = 1; $email->message = '<html><body><a href="https://example.invalid/">Example</a></body></html>';
$user = new TNP_User(); $user->id = 0; $user->email = 'fixture@example.invalid';
for ($i = 1; $i <= 20; ++$i) { $user->{'profile_' . $i} = ''; }
$message = NewsletterEngine::instance()->build_message($email, $user);
$expect($email->track === 0 && !str_contains($message->body, 'nltr=') && !str_contains($message->body, 'noti='), 'Real engine builds an untracked message even from a tracking-enabled draft');

$table = $wpdb->options;
$rows = $wpdb->get_results("SELECT * FROM `$table` ORDER BY option_id", ARRAY_A);
$schema = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N)[1], 1);
$created = false;
try {
    $expect($wpdb->query($schema) !== false, 'Create isolated option table'); $created = true;
    foreach ($rows as $row) { if ($wpdb->insert($table, $row) !== 1) { throw new RuntimeException('Cannot shadow options.'); } }
    wp_cache_flush();
    $browser_row = static function (): array { return array_values(array_filter(iss_consent_checks(), static fn(array $row): bool => $row['name'] === 'Browserprüfung'))[0]; };
    delete_option('iss_consent_browser_check');
    $expect($browser_row()['status'] === 'warning', 'No browser receipt is not a pass');
    update_option('iss_consent_browser_check', ['fingerprint' => iss_consent_fingerprint(), 'checked_at' => time()]);
    $expect($browser_row()['status'] === 'ok', 'Matching recent browser receipt accepted');
    update_option('iss_consent_updated_at', 'fixture-update');
    $expect($browser_row()['status'] === 'warning', 'Update invalidates previous browser acceptance');
    update_option('iss_consent_browser_check', ['fingerprint' => iss_consent_fingerprint(), 'checked_at' => time() - 31 * DAY_IN_SECONDS]);
    $expect($browser_row()['status'] === 'warning', 'Monthly review becomes due automatically');
    $bad_report = ['config' => ['metadata' => ['issConsentFingerprint' => iss_consent_fingerprint(), 'baseURL' => home_url()]], 'stats' => ['startTime' => gmdate('c'), 'expected' => 5, 'skipped' => 1]];
    $rejected = false;
    try { iss_consent_record_browser($bad_report); } catch (RuntimeException $error) { $rejected = true; }
    $expect($rejected, 'Skipped/incomplete reports cannot mark browser acceptance');
    $before = (array) get_option('newsletter_main'); $before['fixture_extra'] = 'keep'; $before['track'] = 1;
    update_option('newsletter_main', $before);
    $expect(in_array('warning', array_column(iss_newsletter_privacy_checks(), 'status'), true), 'Newsletter setting drift visible');
    iss_newsletter_apply_privacy_defaults();
    $expect(get_option('newsletter_main')['fixture_extra'] === 'keep' && !in_array('warning', array_column(iss_newsletter_privacy_checks(), 'status'), true), 'Settings repair preserves unrelated configuration');
    $health = apply_filters('site_status_tests', []);
    $expect(isset($health['direct']['iss_consent']), 'Native Site Health check installed');
} finally {
    if ($created) { $wpdb->query("DROP TEMPORARY TABLE `$table`"); }
    wp_cache_flush();
}
$expect($wpdb->get_results("SELECT * FROM `$table` ORDER BY option_id", ARRAY_A) === $rows, 'Real settings/verification receipts unchanged by fixture');
WP_CLI::success("Privacy: $checks checks passed; no mail or subscriber changes.");
