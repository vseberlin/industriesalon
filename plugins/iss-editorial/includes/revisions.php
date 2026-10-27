<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Editorial recovery uses WordPress revisions, including one autosave per author. */
function iss_editorial_register_revision_meta(): void
{
    foreach (iss_editorial_get_registered_formats() as $slug => $format) {
        $keys = array_filter([
            iss_editorial_get_document_meta_key($slug),
            empty($format['always_enabled']) ? iss_editorial_get_enabled_meta_key($slug) : '',
            iss_editorial_get_skin_meta_key($slug),
        ]);
        foreach ($format['post_types'] as $post_type) {
            add_post_type_support($post_type, 'revisions');
            foreach ($keys as $key) {
                // Keep any owning plugin's sanitization and permission contract.
                $existing = get_registered_meta_keys('post', $post_type)[$key] ?? [];
                register_post_meta($post_type, $key, array_merge([
                    'type' => 'string',
                    'single' => true,
                    'show_in_rest' => false,
                ], $existing, ['revisions_enabled' => true]));
            }
        }
    }
}
add_action('init', 'iss_editorial_register_revision_meta', 110);

/** Limit only records with stored editorial JSON, including disabled/legacy documents. */
function iss_editorial_has_revision_document(WP_Post $post): bool
{
    $format = iss_editorial_get_format_for_post($post);
    return $format && metadata_exists('post', $post->ID, iss_editorial_get_document_meta_key($format['slug']));
}

function iss_editorial_revision_limit(int $limit, WP_Post $post): int {
    if (!iss_editorial_has_revision_document($post)) { return $limit; }
    return $limit < 0 ? 30 : min(30, $limit);
}
add_filter('wp_revisions_to_keep', 'iss_editorial_revision_limit', 20, 2);

// Core owns pruning on revision creation. Private recovery drafts do not consume its allowance.
function iss_editorial_retained_revisions(array $revisions, int $post_id): array {
    $post = get_post($post_id);
    return $post && iss_editorial_has_revision_document($post)
        ? array_filter($revisions, static fn(WP_Post $revision): bool => !wp_is_post_autosave($revision))
        : $revisions;
}
add_filter('wp_save_post_revision_revisions_before_deletion', 'iss_editorial_retained_revisions', 20, 2);

// A native restore updates the parent (and may prune its source) before copying revisioned meta.
// Keep only an in-request copy for that existing restore boundary; core still owns deletion.
function iss_editorial_capture_pruned_revision_meta($delete, WP_Post $revision) {
    if ($delete !== null || $revision->post_type !== 'revision' || wp_is_post_autosave($revision)
        || !doing_action('wp_after_insert_post')) { return $delete; }
    $parent = get_post($revision->post_parent);
    if (!$parent || !iss_editorial_has_revision_document($parent)) { return $delete; }
    global $wpdb;
    wp_cache_delete($revision->ID, 'post_meta');
    if (update_meta_cache('post', [$revision->ID]) === false || $wpdb->last_error !== '') { return false; }
    $values = [];
    foreach (wp_post_revision_meta_keys($parent->post_type) as $key) {
        $values[$key] = get_post_meta($revision->ID, $key);
    }
    $GLOBALS['iss_editorial_pruned_revision_meta'][$revision->ID] = ['parent' => $parent->ID, 'values' => $values];
    return $delete;
}
add_filter('pre_delete_post', 'iss_editorial_capture_pruned_revision_meta', 10, 2);

/** Use native revision policy and verify its metadata copy before replacing a document. */
function iss_editorial_capture_revision(int $post_id): bool
{
    $post = get_post($post_id);
    if (!$post || $post->post_type === 'revision') {
        return false;
    }
    if ($post->post_status === 'auto-draft' || !wp_revisions_enabled($post)) {
        return true;
    }
    $meta = [];
    foreach (wp_post_revision_meta_keys($post->post_type) as $key) {
        // Registered defaults are not stored rows and core does not copy them.
        $meta[$key] = get_metadata_raw('post', $post_id, $key, false);
    }
    $verified_revision = 0;
    // Core prunes before wp_save_post_revision returns, even if insertion/copying failed.
    $verify = static function (int $revision_id, int $parent_id) use ($post_id, $meta, &$verified_revision): void {
        if ($parent_id !== $post_id || wp_is_post_autosave($revision_id)) { return; }
        $verified_revision = 0;
        foreach ($meta as $key => $values) {
            if (get_metadata_raw('post', $revision_id, $key, false) !== $values) { return; }
        }
        $verified_revision = $revision_id;
    };
    $protect_history = static function (array $revisions, int $parent_id) use ($post_id, &$verified_revision): array {
        return $parent_id === $post_id && !$verified_revision ? [] : $revisions;
    };
    add_action('_wp_put_post_revision', $verify, PHP_INT_MAX, 2);
    add_filter('wp_save_post_revision_revisions_before_deletion', $protect_history, PHP_INT_MAX, 2);
    try {
        $revision_id = wp_save_post_revision($post_id);
    } finally {
        remove_action('_wp_put_post_revision', $verify, PHP_INT_MAX);
        remove_filter('wp_save_post_revision_revisions_before_deletion', $protect_history, PHP_INT_MAX);
    }
    // Core returns null when the latest revision already represents this state.
    if ($revision_id === null) {
        return true;
    }
    if (is_wp_error($revision_id) || !$revision_id) {
        return false;
    }
    $verify($revision_id, $post_id);
    if ($verified_revision !== $revision_id) {
        wp_delete_post_revision($revision_id);
        return false;
    }
    return true;
}

/** Classic edit-form cleanup compares post fields only, overlooking revisioned JSON. */
function iss_editorial_protect_private_recovery($delete, WP_Post $revision) {
    if ($delete !== null || !is_admin() || wp_doing_ajax()
        || ($GLOBALS['pagenow'] ?? '') !== 'post.php'
        || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET'
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only edit-screen detection, not a submitted mutation.
        || ($_GET['action'] ?? '') !== 'edit') {
        return $delete;
    }
    $parent_id = wp_is_post_autosave($revision);
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Limit protection to the post being displayed by the native edit screen.
    if (!$parent_id || $parent_id !== absint($_GET['post'] ?? 0)
        || !get_metadata_raw('post', $revision->ID, '_iss_editorial_draft_base', true)) {
        return $delete;
    }
    // Our recovery UI owns discarding these drafts; AJAX discard and normal saves still delete them.
    $format = iss_editorial_get_format_for_post($parent_id);
    return $format && get_metadata_raw('post', $revision->ID, iss_editorial_get_document_meta_key($format['slug']), true)
        ? false : $delete;
}
add_filter('pre_delete_post', 'iss_editorial_protect_private_recovery', 10, 2);

function iss_editorial_saved_token(int $post_id, string $format_slug): string
{
    $post = get_post($post_id);
    $format = iss_editorial_get_format($format_slug);
    $state = ['title' => $post ? $post->post_title : '', 'excerpt' => $post ? $post->post_excerpt : ''];
    // Protect editor inputs, not unrelated post timestamps or other owners' revision metadata.
    $keys = array_merge([
        iss_editorial_get_document_meta_key($format_slug),
        empty($format['always_enabled']) ? iss_editorial_get_enabled_meta_key($format_slug) : '',
        iss_editorial_get_skin_meta_key($format_slug),
    ], $format['version_meta_keys'] ?? []);
    foreach (array_unique($keys) as $key) {
        if ($key !== '') {
            $state[$key] = get_metadata_raw('post', $post_id, $key, true);
        }
    }
    return hash('sha256', iss_editorial_encode_document($state));
}

function iss_editorial_get_draft(int $post_id, string $format_slug): array
{
    if (!get_current_user_id() || !current_user_can('edit_post', $post_id)) {
        return [];
    }
    $revision = wp_get_post_autosave($post_id, get_current_user_id());
    if (!$revision) {
        return [];
    }
    $document = get_metadata_raw('post', $revision->ID, iss_editorial_get_document_meta_key($format_slug), true);
    if (!is_string($document) || $document === '') {
        return [];
    }
    $enabled = !empty(iss_editorial_get_format($format_slug)['always_enabled'])
        || get_metadata_raw('post', $revision->ID, iss_editorial_get_enabled_meta_key($format_slug), true) === '1';
    return [
        'id' => $revision->ID,
        'document' => iss_editorial_decode_document($document),
        'enabled' => $enabled,
        'token' => hash('sha256', $document . '|' . $revision->post_title . '|' . $revision->post_excerpt . '|' . ($enabled ? '1' : '0') . '|' . (string) get_metadata_raw('post', $revision->ID, '_iss_editorial_draft_base', true)),
        'base' => (string) get_metadata_raw('post', $revision->ID, '_iss_editorial_draft_base', true),
        'modified' => $revision->post_modified,
        'title' => $revision->post_title,
        'excerpt' => $revision->post_excerpt,
    ];
}

/** Associate the native lock with its browser session without storing a login token. */
function iss_editorial_track_lock_session(int $post_id, string $lock): void
{
    $token = wp_get_session_token();
    $post = get_post($post_id);
    if ($token === '' || !$post || !iss_editorial_uses_canvas($post)
        || absint(explode(':', $lock)[1] ?? 0) !== get_current_user_id()
        || get_post_meta($post_id, '_edit_lock', true) !== $lock) {
        return;
    }
    update_post_meta($post_id, '_iss_editorial_lock_session', ['lock' => $lock, 'session' => hash('sha256', $token)]);
}

function iss_editorial_track_lock_meta($meta_id, int $post_id, string $key, $value): void
{
    if ($key === '_edit_lock' && is_string($value)) {
        iss_editorial_track_lock_session($post_id, $value);
    }
}
add_action('added_post_meta', 'iss_editorial_track_lock_meta', 10, 4);
add_action('updated_post_meta', 'iss_editorial_track_lock_meta', 10, 4);

// Two sessions of the same user can renew the same timestamp within one second,
// so a successful heartbeat must record ownership even when core's meta did not change.
function iss_editorial_track_heartbeat_lock(array $response, array $data): array {
    if (!empty($response['wp-refresh-post-lock']['new_lock'])) {
        iss_editorial_track_lock_session(absint($data['wp-refresh-post-lock']['post_id'] ?? 0), (string) $response['wp-refresh-post-lock']['new_lock']);
    }
    return $response;
}
add_filter('heartbeat_received', 'iss_editorial_track_heartbeat_lock', 11, 2);

/** Logout releases only this session's native locks, even if other logins remain. */
function iss_editorial_release_session_locks(int $user_id): void {
    // Core has invalidated the session, but the request cookie still identifies it.
    $token = wp_get_session_token();
    if ($token === '') { return; }
    $session = hash('sha256', $token);
    $post_types = [];
    foreach (iss_editorial_get_registered_formats() as $format) {
        $post_types = array_merge($post_types, $format['post_types']);
    }
    $post_ids = get_posts([
        'post_type' => array_unique($post_types), 'post_status' => array_keys(get_post_stati()),
        'numberposts' => -1, 'fields' => 'ids',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Logout-only lookup, narrowed by the indexed lock-session meta key; never a public query.
        'meta_query' => [['key' => '_iss_editorial_lock_session', 'value' => '"' . $session . '"', 'compare' => 'LIKE']],
    ]);
    foreach ($post_ids as $post_id) {
        $owner = get_post_meta($post_id, '_iss_editorial_lock_session', true);
        if (!is_array($owner) || ($owner['session'] ?? '') !== $session
            || absint(explode(':', (string) ($owner['lock'] ?? ''))[1] ?? 0) !== $user_id) {
            continue;
        }
        // Do not remove a lock renewed or taken over after the session's last write.
        if (delete_post_meta($post_id, '_edit_lock', $owner['lock'])) {
            delete_post_meta($post_id, '_iss_editorial_lock_session', $owner);
        }
    }
}
add_action('wp_logout', 'iss_editorial_release_session_locks');

/** Legacy locks without session ownership still expire through core or final logout. */
function iss_editorial_release_inactive_lock(int $post_id): void
{
    $post = get_post($post_id);
    if (!$post || !current_user_can('edit_post', $post_id) || !iss_editorial_uses_canvas($post)) {
        return;
    }
    $lock = (string) get_post_meta($post_id, '_edit_lock', true);
    $owner = absint(explode(':', $lock)[1] ?? 0);
    if ($owner && $owner !== get_current_user_id() && !WP_Session_Tokens::get_instance($owner)->get_all()) {
        // Compare the exact value so a concurrent heartbeat/takeover keeps its newer lock.
        delete_post_meta($post_id, '_edit_lock', $lock);
    }
}

function iss_editorial_release_edit_screen_lock(): void {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Native edit-screen entry; only an inactive session's transient lock can be removed.
    if (($_GET['action'] ?? '') === 'edit') {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Permission and session liveness are checked by the helper.
        iss_editorial_release_inactive_lock(absint($_GET['post'] ?? 0));
    }
}
add_action('load-post.php', 'iss_editorial_release_edit_screen_lock');

/** Do not let an old tab acquire locks as the newly signed-in account. */
function iss_editorial_check_heartbeat_session(): void {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Account mismatch returns read-only state; lock cleanup below requires the native nonce.
    $editor_user = absint($_POST['data']['iss-editorial-user'] ?? 0);
    if (!$editor_user) {
        return;
    }
    $nonce_valid = wp_verify_nonce(sanitize_text_field(wp_unslash((string) ($_POST['_nonce'] ?? ''))), 'heartbeat-nonce');
    if ($editor_user !== get_current_user_id()) {
        wp_send_json(['iss-editorial-session-changed' => true]);
    }
    if (!$nonce_valid) { wp_send_json(['iss-editorial-session-expired' => true]); }
    iss_editorial_release_inactive_lock(absint($_POST['data']['wp-refresh-post-lock']['post_id'] ?? 0));
}
add_action('wp_ajax_heartbeat', 'iss_editorial_check_heartbeat_session', 0);

add_filter('heartbeat_received', static function (array $response, array $data): array {
    $post_id = absint($data['wp-refresh-post-lock']['post_id'] ?? 0);
    if (absint($data['iss-editorial-user'] ?? 0) === get_current_user_id() && $post_id && current_user_can('edit_post', $post_id)) {
        $response['iss-editorial-nonces'] = [
            'preview' => wp_create_nonce('iss_editorial_preview_document'),
            'save' => wp_create_nonce('iss_editorial_save_document'),
            'rest' => wp_create_nonce('wp_rest'),
            'choices' => wp_create_nonce('iss_relations_station_objects'),
            'previewUrl' => iss_editorial_get_preview_url($post_id, (string) (iss_editorial_get_format_for_post($post_id)['slug'] ?? '')),
        ];
    }
    return $response;
}, 12, 2);

/** Recovery preserves unfinished fields and opaque trash; canonical validation is separate. */
function iss_editorial_recovery_document(array $document): array
{
    $document = iss_editorial_strip_document_preview_cache($document);
    foreach ($document as $key => $value) {
        if ($key === 'deleted_sections') { continue; }
        $document[$key] = map_deep($value, static function ($field) {
            return is_string($field) ? wp_kses_post($field) : $field;
        });
    }
    return $document;
}

/**
 * @return array|WP_Error
 */
function iss_editorial_save_draft(int $post_id, string $format_slug, array $document, bool $enabled, array $post_fields = [])
{
    $post = get_post($post_id);
    if (!$post || !current_user_can('edit_post', $post_id)) {
        return new WP_Error('editorial_permission', __('Keine Berechtigung.', 'iss-editorial'));
    }
    if (!iss_editorial_supports_version(iss_editorial_get_format($format_slug), $document['schema_version'] ?? null) || !isset($document['sections']) || !is_array($document['sections']) || !array_is_list($document['sections'])) {
        return new WP_Error('editorial_invalid_schema', __('Der Entwurf konnte nicht gelesen werden.', 'iss-editorial'));
    }
    $trash_permission = iss_editorial_check_trash_removal($post_id, $format_slug, $document);
    if (is_wp_error($trash_permission)) { return $trash_permission; }
    $document = iss_editorial_recovery_document($document);
    $meta = [iss_editorial_get_document_meta_key($format_slug) => iss_editorial_encode_document($document)];
    $format = iss_editorial_get_format($format_slug);
    if (empty($format['always_enabled'])) {
        $meta[iss_editorial_get_enabled_meta_key($format_slug)] = $enabled ? '1' : '0';
    }
    $skin_key = iss_editorial_get_skin_meta_key($format_slug);
    if ($skin_key !== '') {
        $meta[$skin_key] = (string) ($document['skin'] ?? '');
    }
    $meta = (array) apply_filters('iss_editorial_draft_meta', $meta, $document, $format);
    $controller = new WP_REST_Autosaves_Controller($post->post_type);
    $post_data = $post->to_array();
    if (isset($post_fields['title'])) {
        $post_data['post_title'] = sanitize_text_field((string) $post_fields['title']);
    }
    if (isset($post_fields['excerpt'])) {
        $post_data['post_excerpt'] = wp_kses_post((string) $post_fields['excerpt']);
    }
    $post_data['post_modified'] = current_time('mysql');
    $post_data['post_modified_gmt'] = current_time('mysql', true);
    $revision_id = $controller->create_post_autosave($post_data, $meta);
    if (is_wp_error($revision_id) || !$revision_id) {
        return is_wp_error($revision_id) ? $revision_id : new WP_Error('editorial_autosave_failed', __('Der Entwurf konnte nicht gespeichert werden.', 'iss-editorial'));
    }
    // Core can return an older autosave unchanged when the new values equal the parent.
    // Explicitly reverting every edit must also replace that older recovery draft.
    $revision = get_post($revision_id);
    if ($revision && ($revision->post_title !== $post_data['post_title'] || $revision->post_excerpt !== $post_data['post_excerpt'])) {
        wp_update_post(wp_slash(['ID' => $revision_id, 'post_title' => $post_data['post_title'], 'post_excerpt' => $post_data['post_excerpt']]));
    }
    foreach ($meta as $key => $value) {
        update_metadata('post', $revision_id, $key, wp_slash($value));
    }
    // update_metadata is intentional: update_post_meta redirects revisions to their parent.
    $base = iss_editorial_saved_token($post_id, $format_slug);
    update_metadata('post', $revision_id, '_iss_editorial_draft_base', $base);
    $saved_post = get_post($revision_id);
    if (!$saved_post || $saved_post->post_title !== $post_data['post_title'] || $saved_post->post_excerpt !== $post_data['post_excerpt']
        || get_metadata_raw('post', $revision_id, '_iss_editorial_draft_base', true) !== $base) {
        return new WP_Error('editorial_autosave_failed', __('Titel, Zusammenfassung oder Entwurfsstand konnten nicht vollständig gespeichert werden. Bitte erneut prüfen.', 'iss-editorial'));
    }
    foreach ($meta as $key => $value) {
        if (get_metadata_raw('post', $revision_id, $key, true) !== $value) {
            return new WP_Error('editorial_autosave_failed', __('Der Entwurf konnte nicht vollständig gespeichert werden. Bitte erneut versuchen.', 'iss-editorial'));
        }
    }
    return iss_editorial_get_draft($post_id, $format_slug);
}

/**
 * @return true|WP_Error
 */
function iss_editorial_check_edit_version(int $post_id, string $format_slug, string $base, ?string $draft_token = null)
{
    if (!hash_equals(iss_editorial_saved_token($post_id, $format_slug), $base)) {
        return new WP_Error('editorial_conflict', __('Inzwischen wurde eine neue Fassung gespeichert. Bitte die Fassungen vergleichen. Laden Sie Ihre Änderungen vor einem erneuten Öffnen herunter.', 'iss-editorial'));
    }
    if (!function_exists('wp_check_post_lock')) {
        require_once ABSPATH . 'wp-admin/includes/post.php';
    }
    iss_editorial_release_inactive_lock($post_id);
    if (wp_check_post_lock($post_id)) {
        return new WP_Error('editorial_locked', __('Dieser Inhalt wird gerade von einer anderen Person bearbeitet. Ihr Entwurf bleibt in diesem Fenster erhalten.', 'iss-editorial'));
    }
    if ($draft_token !== null) {
        $draft = iss_editorial_get_draft($post_id, $format_slug);
        if (!hash_equals((string) ($draft['token'] ?? ''), $draft_token)) {
            return new WP_Error('editorial_draft_conflict', __('Der Entwurf wurde in einem anderen Fenster geändert. Bitte die Fassungen vergleichen. Ihre Eingaben bleiben in diesem Fenster erhalten.', 'iss-editorial'));
        }
    }
    return true;
}

/** Reject the entire submitted update before WordPress or owner hooks change it. */
function iss_editorial_validate_post_update(array $data, array $postarr): array
{
    if (($data['post_type'] ?? '') === 'revision' || empty($_POST['iss_editorial_nonce'])
        || absint($postarr['ID'] ?? 0) !== absint($_POST['post_ID'] ?? 0)) {
        return $data;
    }
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['iss_editorial_nonce'])), 'iss_editorial_save_document')) {
        wp_die(esc_html__('Die Sitzung ist abgelaufen. Bitte den Inhalt neu öffnen.', 'iss-editorial'), '', ['response' => 403, 'back_link' => true]);
    }
    $post_id = absint($postarr['ID'] ?? 0);
    $format = iss_editorial_get_format_for_post($post_id);
    if (!$format || !isset($_POST['iss_editorial'][$format['slug']])) {
        return $data;
    }
    if (!iss_core_acquire_post_write_lock($post_id)) {
        wp_die(esc_html__('Eine Speicherung läuft noch. Ihre bisherige Fassung bleibt erhalten. Bitte kurz warten und erneut versuchen.', 'iss-editorial'), '', ['response' => 409, 'back_link' => true]);
    }
    $payload = wp_unslash($_POST['iss_editorial'][$format['slug']]);
    $result = current_user_can('edit_post', $post_id)
        ? iss_editorial_check_edit_version($post_id, $format['slug'], (string) ($payload['base'] ?? ''), (string) ($payload['draft_token'] ?? ''))
        : new WP_Error('editorial_permission', __('Keine Berechtigung.', 'iss-editorial'));
    if (!is_wp_error($result)) {
        $result = iss_editorial_validate_document((string) ($payload['document'] ?? ''), $format['slug']);
    }
    if (!is_wp_error($result)) {
        $result = iss_editorial_check_trash_removal($post_id, $format['slug'], iss_editorial_decode_document($payload['document'] ?? ''));
    }
    if (is_wp_error($result)) {
        iss_core_release_post_write_locks($post_id);
        wp_die(esc_html($result->get_error_message()), esc_html__('Inhalt nicht gespeichert', 'iss-editorial'), ['response' => 409, 'back_link' => true]);
    }
    // Capture the pre-update document too, including the first edit after installing this support.
    if (!iss_editorial_capture_revision($post_id)) {
        iss_core_release_post_write_locks($post_id);
        wp_die(esc_html__('Die bisherige Fassung konnte nicht gesichert werden. Bitte erneut versuchen.', 'iss-editorial'), esc_html__('Inhalt nicht gespeichert', 'iss-editorial'), ['response' => 500, 'back_link' => true]);
    }
    $GLOBALS['iss_editorial_revision_before_update'][$post_id] = true;
    $GLOBALS['iss_editorial_native_write_lock'][$post_id] = true;
    return $data;
}

add_action('wp_after_insert_post', static function (int $post_id): void {
    if (!doing_action('save_post') && !empty($GLOBALS['iss_editorial_native_write_lock'][$post_id])) {
        iss_core_release_post_write_locks($post_id);
        unset($GLOBALS['iss_editorial_native_write_lock'][$post_id]);
    }
}, PHP_INT_MAX);
add_filter('wp_insert_post_data', 'iss_editorial_validate_post_update', 99, 2);

// The form's pre-update snapshot covers only this native update, not a later API write.
function iss_editorial_clear_revision_capture(int $post_id): void {
    unset($GLOBALS['iss_editorial_revision_before_update'][$post_id]);
}
add_action('wp_after_insert_post', 'iss_editorial_clear_revision_capture', PHP_INT_MAX);

/** Keep core's metadata copy inside the same owner boundary as ordinary saves. */
function iss_editorial_restore_revision_meta(int $post_id, int $revision_id): void
{
    $format = iss_editorial_get_format_for_post($post_id);
    if (!$format) {
        wp_restore_post_revision_meta($post_id, $revision_id);
        return;
    }
    $saved = iss_editorial_persist_document($post_id, $format['slug'], static function () use ($post_id, $revision_id): bool {
        global $wpdb;
        $pruned = $GLOBALS['iss_editorial_pruned_revision_meta'][$revision_id] ?? [];
        $source = null;
        if (($pruned['parent'] ?? 0) === $post_id && !get_post($revision_id)) {
            $values = $pruned['values'];
            // Supply the captured source to core's copy routine only for this exact revision.
            $source = static function ($value, int $object_id, string $key, bool $single) use ($revision_id, $values) {
                if ($object_id !== $revision_id || !array_key_exists($key, $values)) { return $value; }
                return $single ? ($values[$key][0] ?? '') : $values[$key];
            };
            add_filter('get_post_metadata', $source, 10, 4);
        } else {
            wp_cache_delete($revision_id, 'post_meta');
            if (update_meta_cache('post', [$revision_id]) === false || $wpdb->last_error !== '') { return false; }
            $values = [];
            foreach (wp_post_revision_meta_keys(get_post_type($post_id)) as $key) {
                $values[$key] = get_post_meta($revision_id, $key);
            }
        }
        try {
            wp_restore_post_revision_meta($post_id, $revision_id);
        } finally {
            if ($source) { remove_filter('get_post_metadata', $source, 10); }
            unset($GLOBALS['iss_editorial_pruned_revision_meta'][$revision_id]);
        }
        wp_cache_delete($post_id, 'post_meta');
        foreach ($values as $key => $value) {
            if (get_post_meta($post_id, $key) !== $value) {
                return false;
            }
        }
        return true;
    });
    if (!$saved) {
        wp_die(esc_html__('Die Abschnitte dieser Revision konnten nicht wiederhergestellt werden. Bitte die Seite neu öffnen und erneut versuchen.', 'iss-editorial'), esc_html__('Revision nicht wiederhergestellt', 'iss-editorial'), ['response' => 500, 'back_link' => true]);
    }
}
remove_action('wp_restore_post_revision', 'wp_restore_post_revision_meta');
add_action('wp_restore_post_revision', 'iss_editorial_restore_revision_meta', 10, 2);

/** The shared preview URL uses the current author's title and summary too. */
function iss_editorial_preview_post_fields($post) {
    $format = $post instanceof WP_Post ? iss_editorial_get_format_for_post($post) : [];
    if ($format && iss_editorial_should_prefer_preview_autosave($post->ID, $format['slug'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preview auth above; snapshot hash is checked at template_redirect.
        if (!empty($_GET['iss_editorial_embed']) && str_starts_with((string) ($_GET['iss_editorial_snapshot'] ?? ''), 'saved:')) {
            return get_post($post->ID);
        }
        $draft = iss_editorial_get_draft($post->ID, $format['slug']);
        if ($draft) {
            $post = clone $post;
            $post->post_title = $draft['title'];
            $post->post_excerpt = $draft['excerpt'];
        }
    }
    return $post;
}
add_filter('the_preview', 'iss_editorial_preview_post_fields', 20);

/** Show section changes in the existing WordPress revision comparison. */
function iss_editorial_revision_ui_diff(array $fields, WP_Post|false $from, WP_Post $to): array {
    $parent_id = $to->post_parent ?: $to->ID;
    $format = iss_editorial_get_format_for_post($parent_id);
    if (!$format) {
        return $fields;
    }
    $describe = static function (WP_Post $revision) use ($format): string {
        $raw = get_metadata_raw('post', $revision->ID, iss_editorial_get_document_meta_key($format['slug']), true);
        $document = iss_editorial_decode_document($raw);
        $labels = ['sections' => 'Abschnitte', 'deleted_sections' => 'Papierkorb', 'skin' => 'Darstellung', 'title' => 'Titel', 'body' => 'Text', 'kicker' => 'Vorspann', 'quote' => 'Zitat', 'attribution' => 'Quelle', 'media_refs' => 'Medien', 'object_refs' => 'Archivobjekte', 'links' => 'Links', 'label' => 'Beschriftung', 'items' => 'Einträge', 'facts' => 'Fakten', 'value' => 'Wert'];
        $lines = [];
        $walk = static function ($value, string $path = '') use (&$walk, &$lines, $labels, $format): void {
            if (is_array($value)) {
                foreach ($value as $key => $child) {
                    $label = is_int($key) ? (string) ($key + 1) : ($labels[$key] ?? str_replace('_', ' ', $key));
                    if ($key === 'type' && is_string($child)) {
                        $child = $format['sections'][$child]['label'] ?? $child;
                        $label = 'Abschnitt';
                    }
                    $walk($child, ltrim($path . ' · ' . $label, ' ·'));
                }
            } elseif ($value !== '' && $value !== null) {
                $lines[] = $path . ': ' . (string) $value;
            }
        };
        $walk($document);
        if (empty($format['always_enabled'])) {
            $lines[] = 'Abschnittsfassung aktiv: ' . (get_metadata_raw('post', $revision->ID, iss_editorial_get_enabled_meta_key($format['slug']), true) === '1' ? 'Ja' : 'Nein');
        }
        return implode("\n", $lines);
    };
    $diff = wp_text_diff($from ? $describe($from) : '', $describe($to));
    if ($diff) {
        $fields[] = ['id' => 'iss-editorial-document', 'name' => __('Inhaltsabschnitte', 'iss-editorial'), 'diff' => $diff];
    }
    return $fields;
}
add_filter('wp_get_revision_ui_diff', 'iss_editorial_revision_ui_diff', 10, 3);
