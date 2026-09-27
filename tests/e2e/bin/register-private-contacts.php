<?php
/** Private-contact boundaries exercised without leaving fixture mutations. */
if (!defined('WP_CLI') || !WP_CLI) {
    exit(1);
}
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Transactional fixture changes, rolled back.
global $wpdb;
$count = 0;
$expect = static function (bool $condition, string $message) use (&$count): void {
    if (!$condition) {
        throw new RuntimeException(esc_html($message));
    }
    ++$count;
};
$post = get_page_by_path('stiftung-reinbeckhallen', OBJECT, ISS_REGISTER_POST_TYPE);
$id = (int) $post->ID;
$original = get_post_meta($id);
$user = get_current_user_id();
$error = null;
$wpdb->query('START TRANSACTION');
try {
    $contacts = [['name' => 'Private Contact Fixture', 'email' => 'private-fixture@example.invalid', 'phone' => '+49 000 12345', 'role' => 'Fixture role', 'source' => 'Internal fixture']];
    $expect(iss_register_sanitize_contacts(array_merge($contacts, [[]], $contacts)) === $contacts, 'Blank rows disappear and duplicate contacts are deduplicated.');
    update_post_meta($id, '_iss_register_contacts', $contacts);
    update_post_meta($id, 'research_note', 'Private Research Fixture');
    foreach (['Contact: private-fixture@example.invalid', 'Kontakt: Another Person', 'Kontakt Another Person', 'Private Contact Fixture', '+49 000 12345', '0171-1234567'] as $text) {
        $expect(is_wp_error(iss_register_validate_public_import_text($id, [$text])), 'Import rejects private contact prose.');
    }
    $expect(iss_register_validate_public_import_text($id, ['Aktiver Kunstbetrieb. 22 Studios geplant.']) === true, 'Substantial public status remains importable.');
    $schema = get_registered_meta_keys('post', ISS_REGISTER_POST_TYPE);
    $expect($schema['_iss_register_contacts']['show_in_rest'] === false && $schema['research_note']['show_in_rest'] === false, 'Neither internal field is registered for public REST access.');
    wp_set_current_user(0);
    foreach (['/wp/v2/register_place/' . $id, '/iss-register/v1/places/13', '/iss-register/v1/atlas-detail/' . $id, '/iss-register/v1/atlas-bootstrap'] as $route) {
        $response = rest_do_request(new WP_REST_Request('GET', $route));
        $json = (string) wp_json_encode($response->get_data());
        $expect($response->get_status() === 200, 'Public route remains usable.');
        $expect(!str_contains($json, 'Private Contact Fixture') && !str_contains($json, 'private-fixture@') && !str_contains($json, 'Private Research Fixture') && !str_contains($json, '_iss_register_contacts'), 'Anonymous route omits internal contacts and research.');
    }
    $request = new WP_REST_Request('GET', '/wp/v2/register_place/' . $id);
    $request->set_param('context', 'edit');
    $expect(rest_do_request($request)->get_status() >= 400, 'Anonymous edit context is denied.');
    ob_start();
    iss_register_render_fields_table($post, ['_iss_register_contacts', 'research_note']);
    $html = (string) ob_get_clean();
    $expect(!str_contains($html, 'Private Contact Fixture') && !str_contains($html, 'Private Research Fixture'), 'Unauthorized field rendering reveals no private values.');
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) $admins[0]);
    ob_start();
    iss_register_render_fields_table($post, ['_iss_register_contacts']);
    $html = (string) ob_get_clean();
    $expect(str_contains($html, 'Private Contact Fixture') && str_contains($html, '[email]') && str_contains($html, '[phone]') && str_contains($html, '[source]'), 'Authorized editor gets distinct structured fields.');
} catch (Throwable $caught) {
    $error = $caught;
} finally {
    $wpdb->query('ROLLBACK');
    clean_post_cache($id);
    wp_set_current_user($user);
    iss_register_clear_places_cache();
}
$restored = get_post_meta($id);
// An open editor can refresh its heartbeat lock outside this transaction.
unset($restored['_edit_lock'], $original['_edit_lock']);
$expect($restored === $original, 'Fixture metadata restored.');
if ($error) {
    WP_CLI::error($error->getMessage());
}
WP_CLI::success($count . ' private-contact checks passed; fixture rolled back.');
