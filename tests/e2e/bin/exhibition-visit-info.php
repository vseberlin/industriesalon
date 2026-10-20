<?php
/** Read-only visitor regressions with process-local metadata/options/term fixtures. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$expect = static function (bool $ok, string $message) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException(esc_html($message)); }
};
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !wp_using_ext_object_cache(), 'Cron disabled; fixture caches are process-local.');
$post = get_page_by_path('ausstellung-judisches-leben-und-arbeiten-in-schoneweide', OBJECT, 'ausstellung');
$expect($post instanceof WP_Post, 'Existing exhibition available for read-only fixtures.');
$id = (int) $post->ID;
$before = [get_post($id)->to_array(), get_post_meta($id), wp_get_object_terms($id, 'ausstellung_typ', ['fields' => 'ids'])];
$control = Industriesalon_Steuerung::instance();
$settings = $control->get_all_data();
$before_settings = $settings;
$expect(($settings['prices']['note'] ?? '') === 'Eintritt frei.', 'Confirmed museum admission is stored centrally.');
$expect(str_contains($control->render_visit_info(['show_admission' => true]), 'Eintritt frei.'), 'Confirmed free admission reaches the shared visitor block.');
$today = '2026-10-20';
$meta = ['iss_start_date' => '2026-10-09', 'iss_end_date' => '2026-11-07', 'iss_location' => $settings['general']['site_name']];
$date_filter = static function ($date, $format) use (&$today) { return $format === 'Y-m-d' ? $today : $date; };
$meta_filter = static function ($value, $object_id, $key, $single) use ($id, &$meta) {
    return (int) $object_id === $id && array_key_exists($key, $meta) ? ($single ? $meta[$key] : [$meta[$key]]) : $value;
};
$option_filters = [];
foreach (['general', 'accessibility', 'prices'] as $group) {
    $option_filters[$group] = static function () use (&$settings, $group) { return $settings[$group]; };
    add_filter('pre_option_iss_control_' . $group, $option_filters[$group]);
}
add_filter('wp_date', $date_filter, 99, 2);
add_filter('get_post_metadata', $meta_filter, 99, 4);
$terms = [];
foreach (['sonderausstellung', 'digitaleausstellungen', 'dauerausstellung'] as $slug) {
    $term = get_term_by('slug', $slug, 'ausstellung_typ');
    $expect($term instanceof WP_Term, 'Fixture type exists: ' . $slug);
    $terms[$slug] = $term->term_id;
}
$fixture_type = 'sonderausstellung';
$type = static function (string $slug) use ($id, $terms, &$fixture_type): void {
    $fixture_type = $slug;
    wp_cache_set($id, [$terms[$slug]], 'ausstellung_typ_relationships');
};
$term_filter = static function ($found, $ids, $taxonomies, $args) use ($id, &$fixture_type) {
    return in_array($id, $ids, true) && $taxonomies === ['ausstellung_typ'] && ($args['fields'] ?? '') === 'slugs'
        ? [$fixture_type] : $found;
};
add_filter('get_object_terms', $term_filter, 99, 4);
$rows = static fn(): array => iss_content_model_get_meta_rows_for_post($id, ['show_places' => false]);
$has_visit = static fn(): bool => in_array('Museumsöffnungszeiten', array_column($rows(), 'label'), true);
try {
    $type('sonderausstellung');
    $expect($has_visit(), 'Current museum exhibition includes central visit facts.');
    foreach (['2026-10-09', '2026-11-07'] as $today) {
        $expect($has_visit(), 'First and last exhibition days are inclusive.');
    }
    $today = '2026-10-08';
    $expect(!$has_visit(), 'Future exhibition makes no current opening promise.');
    $today = '2026-11-08';
    $expect(!$has_visit() && in_array('Beendet', array_column($rows(), 'value'), true), 'Ended exhibition keeps its history without current visit facts.');
    $today = '2026-10-20';
    $meta['iss_location'] = 'Wilhelminenhofstraße, Berlin';
    $expect(!$has_visit() && in_array($meta['iss_location'], array_column($rows(), 'value'), true), 'External venue is retained without museum conditions.');
    $meta['iss_location'] = '';
    $expect(!$has_visit(), 'Thematic museum relation alone is not a visitor venue.');
    $meta['iss_location'] = $settings['general']['site_name'];
    $meta['iss_end_date'] = '';
    $expect(!$has_visit(), 'Open-ended historical special exhibition makes no current visit promise.');
    $meta['iss_start_date'] = '2026-02-31';
    $meta['iss_end_date'] = '2026-11-07';
    $expect(!$has_visit(), 'Invalid dates cannot activate a visit promise.');
    $meta['iss_start_date'] = '2026-10-09';
    $type('digitaleausstellungen');
    $expect(!$has_visit() && !in_array('Ausstellungsort', array_column($rows(), 'label'), true), 'Digital exhibition does not inherit physical venue conditions.');
    $type('dauerausstellung');
    $meta['iss_start_date'] = $meta['iss_end_date'] = '';
    $expect($has_visit(), 'Confirmed museum venue works for an undated permanent exhibition.');
    $settings['general']['street'] = 'Fixturestraße 42';
    $settings['accessibility'] = ['note' => 'Fixture: Zugang über den Hof.'];
    $settings['prices'] = ['note' => 'Fixture: Eintritt frei.'];
    $values = implode(' ', array_column($rows(), 'value'));
    $expect(str_contains($values, 'Fixturestraße 42') && str_contains($values, 'Zugang über den Hof') && str_contains($values, 'Eintritt frei'), 'Settings changes flow directly to exhibition facts.');
    $visit = $control->render_visit_info(['show_admission' => true, 'show_office_hours' => false]);
    $expect(str_contains($visit, 'Fixturestraße 42') && str_contains($visit, 'Eintritt frei') && !str_contains($visit, 'Büro'), 'Overview shares the same facts and separates museum from office hours.');
    $settings['prices'] = [];
    $expect(!in_array('Eintritt', array_column($rows(), 'label'), true), 'Missing admission facts are omitted, never interpreted as free.');
    $settings['prices'] = ['adult' => '5 €', 'reduced' => '3 €', 'tour' => '14 €', 'rental' => '100 €'];
    $admission = array_column($rows(), 'value', 'label')['Eintritt'];
    $expect(str_contains($admission, '5 €') && str_contains($admission, '3 €') && !str_contains($admission, '14 €') && !str_contains($admission, '100 €'), 'Admission stays separate from tour and room-hire rates.');
    $settings['prices'] = ['note' => '<script>fixture</script>'];
    $expect(!str_contains($control->render_visit_info(['show_admission' => true]), '<script>'), 'Admission is escaped by the shared visit renderer.');
    $registered = get_registered_meta_keys('post', 'ausstellung');
    $expect(isset($registered['iss_location']) && $registered['iss_location']['show_in_rest'], 'Existing venue metadata is editor/REST-visible.');
    foreach (['page-ausstellungen', 'single-ausstellung'] as $slug) {
        $template = get_block_file_template('industriesalon//' . $slug, 'wp_template');
        $parsed = parse_blocks($template->content);
        $expect(parse_blocks(serialize_blocks($parsed)) === $parsed, 'Template block roundtrip: ' . $slug);
    }
} finally {
    remove_filter('wp_date', $date_filter, 99);
    remove_filter('get_post_metadata', $meta_filter, 99);
    remove_filter('get_object_terms', $term_filter, 99);
    foreach ($option_filters as $group => $callback) {
        remove_filter('pre_option_iss_control_' . $group, $callback);
    }
    wp_cache_delete($id, 'ausstellung_typ_relationships');
}
$after = [get_post($id)->to_array(), get_post_meta($id), wp_get_object_terms($id, 'ausstellung_typ', ['fields' => 'ids'])];
$expect($before === $after && $control->get_all_data() === $before_settings, 'Real exhibition and settings were preserved.');
WP_CLI::success("$checks exhibition visitor checks passed; no content writes or messages.");
