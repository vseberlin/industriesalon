<?php
/**
 * Short editorial summaries for the eight source-identified programme records.
 * wp eval-file FILE check|apply|verify /PRIVATE/receipt.json --use-include
 * Local and staging only. Full DB backup required before apply; exclusive receipt
 * retains the previous excerpts. Roll back only these excerpts using wp_update_post
 * after checking for subsequent edits. No dates, original body or media change.
 */
$mode = $args[0] ?? 'check'; $path = $args[1] ?? '';
if (!in_array(wp_parse_url(home_url(), PHP_URL_HOST), ['192.168.2.31', 'staging.industriesalon.info'], true) || !in_array($mode, ['check', 'apply', 'verify'], true)) { WP_CLI::error('Unsupported target/mode.'); }
$copy = [
    12060 => 'Sebastian Zett und Daniel Lindenblatt erzählen mit Schauspiel, Gesang und Gitarre von der Geschichte des Telefons.',
    12763 => 'Anne Rabe liest aus „Die Möglichkeit von Glück“ und spricht mit dem Publikum über Herkunft, Familie und Gegenwart. Moderation: Klaus Burmeister.',
    11915 => 'Entdecken Sie Berliner Industriekultur bei Ausstellungen, Führungen und Veranstaltungen an verschiedenen Orten der Stadt.',
    11969 => 'Führungen und offene Türen laden dazu ein, die Industriegeschichte Schöneweides und den Industriesalon zu entdecken.',
    12680 => 'Besuchen Sie die Sonderausstellung „Pioniere der Transformation“ im Industriesalon Schöneweide.',
    11895 => '24 Sehrohre entlang der Wilhelminenhofstraße zeigen Erfindungen, Menschen und Produkte hinter den historischen Industriegebäuden. Ein Entdeckerspiel lädt zum Mitmachen ein.',
    12744 => 'Lernen Sie sechs jüdische Familien kennen, die in Schöneweide lebten und arbeiteten – mit Geschichten, historischen Stadtplänen und Erinnerungen aus der Nachbarschaft.',
    11696 => 'Entdecken Sie die Geschichte der NAG und ihrer Nutzfahrzeuge aus Schöneweide. Der Eintritt zur Ausstellung ist frei.',
];
$rows = [];
foreach ($copy as $source => $text) {
    $ids = get_posts(['post_type' => ['veranstaltung', 'ausstellung'], 'post_status' => 'publish', 'meta_key' => 'source_post_id', 'meta_value' => (string) $source, 'fields' => 'ids']);
    if (count($ids) !== 1) { WP_CLI::error('Source identity ambiguous: ' . $source); }
    $rows[] = ['id' => $ids[0], 'source' => $source, 'before' => get_post_field('post_excerpt', $ids[0], 'raw'), 'after' => $text];
}
if ($mode === 'verify') {
    foreach ($rows as $row) { if ($row['before'] !== $row['after']) { WP_CLI::error('Summary mismatch.'); } }
    WP_CLI::success('Eight editorial summaries verified.'); return;
}
if ($mode === 'check') { WP_CLI::success('Eight unique sources ready.'); return; }
if (!$path || file_exists($path)) { WP_CLI::error('New private receipt required.'); }
$f = fopen($path, 'x'); if (!$f) { WP_CLI::error('Cannot create before-image.'); }
fwrite($f, wp_json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); fclose($f);
foreach ($rows as $row) {
    $result = wp_update_post(wp_slash(['ID' => $row['id'], 'post_excerpt' => $row['after']]), true);
    if (is_wp_error($result)) { WP_CLI::error($result->get_error_message()); }
    iss_occurrences_sync_source($row['id']);
}
iss_timeline_rest_bump_cache_version();
WP_CLI::success('Eight editorial summaries saved.');
