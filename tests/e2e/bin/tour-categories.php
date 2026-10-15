<?php
/** Category stability, contextual actions and native editor save regression. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$expect = static function (bool $ok, string $message) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException($message); }
};
$definitions = iss_fuehrung_get_offer_catalog_group_definitions();
$expect(array_keys($definitions) === ['oeffentlich', 'gruppen'], 'Only two category choices');
$tour = get_page_by_path('elektropolis-tour', OBJECT, 'fuehrung');
$expect($tour instanceof WP_Post, 'Existing dual-offer source');
$id = (int) $tour->ID;
$groups = ['oeffentlich', 'gruppen'];
$badge = 'Sonderführung';
$override = static function ($value, $post_id, $key) use ($id, &$groups, &$badge) {
    if ((int) $post_id !== $id) { return $value; }
    if ($key === 'offer_catalog_groups') { return [$groups]; }
    if ($key === 'tour_badge') { return $badge; }
    if ($key === 'inquiry_label') { return 'Gruppenführung anfragen'; }
    return $value;
};
$future = static fn(): string => '2090-01-01 00:00:00';
$writes = [];
$observe = static function ($sql) use (&$writes) {
    if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP)\b/i', $sql)) { $writes[] = $sql; }
    return $sql;
};
add_filter('get_post_metadata', $override, 10, 3);
add_filter('query', $observe);
try {
    $expect(iss_fuehrung_get_offer_catalog_group_keys($id) === $groups, 'Dual assignment uses explicit categories');
    $group_card = industriesalon_render_fuehrungen_offer_card($id, 'gruppen');
    $public_card = industriesalon_render_fuehrungen_offer_card($id, 'oeffentlich');
    $expect(str_contains($group_card, 'Gruppenführung anfragen'), 'Group CTA names group inquiry');
    $expect(str_contains($group_card, '&quot;intent&quot;:&quot;inquiry&quot;'), 'Group CTA opens existing inquiry flow');
    $expect(!str_contains($group_card, 'Nächster Termin'), 'Public dates never replace group inquiry state');
    $expect(!str_contains($group_card, 'supersaas.de'), 'Group CTA does not send visitors to public booking');
    $expect(str_contains($group_card, 'href="' . esc_url(get_permalink($id)) . '"'), 'Group action has navigable fallback');
    $expect(str_contains($public_card, 'Termine ansehen') && str_contains($public_card, '#naechste-termine'), 'Public action goes to source dates');
    $next = iss_fuehrung_get_next_event($id);
    $availability = iss_fuehrung_get_availability_label($next['availability_state'] ?? '');
    $expect($availability === '' || str_contains($public_card, $availability), 'Public card preserves current availability alongside the date');
    $expect(!str_contains($public_card, '&quot;intent&quot;:&quot;inquiry&quot;'), 'Public card does not open group inquiry');
    $expect(str_contains($public_card, 'Sonderführung') && str_contains($group_card, 'Sonderführung'), 'Special designation works in both categories');
    $expect(count(iss_fuehrung_group_offer_catalog_posts([$tour])) === 2, 'Special label does not create a category');

    add_filter('iss_occurrences_query_now', $future);
    $expect(iss_fuehrung_get_next_event($id) === null, 'Future clock removes scheduled dates');
    $expect(iss_fuehrung_get_offer_catalog_group_keys($id) === $groups, 'Categories survive last public date');
    $empty_public = industriesalon_render_fuehrungen_offer_card($id, 'oeffentlich');
    $expect(str_contains($empty_public, 'Aktuell keine öffentlichen Termine'), 'Public empty-date state is accurate');
    $expect(str_contains($empty_public, 'Details ansehen'), 'Public offer retains a details action between dates');
    $expect(!str_contains($empty_public, 'Gruppenführung anfragen'), 'Empty public schedule does not change category action');
    remove_filter('iss_occurrences_query_now', $future);

    $groups = [];
    $expect(iss_fuehrung_get_offer_catalog_group_keys($id) === [], 'Explicit exclusion does not infer from dates or words');
    $groups = ['individuell', 'familie-kinder', 'sonderfuehrung'];
    $expect(iss_fuehrung_get_offer_catalog_group_keys($id) === [], 'Retired and special labels cannot become filter groups');
    $groups = ['gruppen'];
    $badge = 'Familien und Kinder, individuell buchbar';
    $expect(iss_fuehrung_get_offer_catalog_group_keys($id) === ['gruppen'], 'Audience or wording cannot change explicit classification');
    $expect(!$writes, 'Category and card reads never mutate storage');
} finally {
    remove_filter('iss_occurrences_query_now', $future);
    remove_filter('get_post_metadata', $override, 10);
    remove_filter('query', $observe);
}

// Save only a private disposable tour through the real native metabox callback.
$previous_user = get_current_user_id();
$previous_post = $_POST;
$admin_ids = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
$expect(!empty($admin_ids), 'Local administrator available for scoped editor test');
wp_set_current_user((int) $admin_ids[0]);
$fixture = wp_insert_post(['post_type' => 'fuehrung', 'post_status' => 'draft', 'post_title' => 'ISS category regression ' . wp_generate_uuid4()], true);
$expect(!is_wp_error($fixture), 'Private disposable fixture created');
try {
    foreach ([['oeffentlich', 'gruppen'], ['gruppen'], []] as $selection) {
        $_POST = [
            'iss_fuehrung_meta_nonce' => wp_create_nonce('iss_fuehrung_save_meta'),
            'iss_fuehrung' => ['offer_catalog_groups' => $selection, 'tour_badge' => 'Sonderführung', 'booking_mode' => 'hybrid'],
        ];
        do_action('save_post_fuehrung', $fixture);
        clean_post_cache($fixture);
        $expect(metadata_exists('post', $fixture, 'offer_catalog_groups'), 'Empty selection remains a deliberate saved choice');
        $expect(iss_fuehrung_get_offer_catalog_group_keys($fixture) === $selection, 'Native editor selection survives reload');
        $expect(get_post_meta($fixture, 'tour_badge', true) === 'Sonderführung', 'Optional designation survives reload');
        ob_start();
        iss_fuehrungen_render_meta_box(get_post($fixture));
        $html = (string) ob_get_clean();
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        $choices = $xpath->query('//input[@name="iss_fuehrung[offer_catalog_groups][]"]');
        $expect($choices->length === 2, 'Editor exposes exactly two category checkboxes');
        $selected = [];
        foreach ($choices as $choice) {
            if ($choice->hasAttribute('checked')) { $selected[] = $choice->getAttribute('value'); }
        }
        $expect($selected === $selection, 'Editor displays saved membership');
        $expect(str_contains($html, 'z. B. Sonderführung'), 'Editor explains the optional special label');
        $expect(!str_contains($html, 'automatisch zuzuordnen'), 'Editor no longer promises keyword inference');
    }
    $_POST = ['iss_fuehrung' => ['offer_catalog_groups' => ['oeffentlich']]];
    do_action('save_post_fuehrung', $fixture);
    $expect(iss_fuehrung_get_offer_catalog_group_keys($fixture) === [], 'Missing nonce cannot change categories');
} finally {
    $_POST = $previous_post;
    wp_delete_post((int) $fixture, true);
    wp_set_current_user($previous_user);
}
$expect(get_post($fixture) === null, 'Disposable fixture removed');
WP_CLI::success("Tour categories: $checks checks passed.");
