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

/** Classic edit-form cleanup compares post fields only, overlooking revisioned JSON. */
add_filter('pre_delete_post', static function ($delete, WP_Post $revision) {
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
}, 10, 2);

function iss_editorial_saved_token(int $post_id, string $format_slug): string
{
    $post = get_post($post_id);
    $state = ['modified' => $post ? $post->post_modified_gmt : '', 'title' => $post ? $post->post_title : '', 'excerpt' => $post ? $post->post_excerpt : ''];
    $keys = [iss_editorial_get_document_meta_key($format_slug), iss_editorial_get_enabled_meta_key($format_slug), iss_editorial_get_skin_meta_key($format_slug)];
    if ($post) {
        $keys = array_unique(array_merge($keys, wp_post_revision_meta_keys($post->post_type)));
    }
    foreach ($keys as $key) {
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

foreach (['added_post_meta', 'updated_post_meta'] as $lock_hook) {
    add_action($lock_hook, static function ($meta_id, int $post_id, string $key, $value): void {
        if ($key === '_edit_lock' && is_string($value)) {
            iss_editorial_track_lock_session($post_id, $value);
        }
    }, 10, 4);
}

// Two sessions of the same user can renew the same timestamp within one second,
// so a successful heartbeat must record ownership even when core's meta did not change.
add_filter('heartbeat_received', static function (array $response, array $data): array {
    if (!empty($response['wp-refresh-post-lock']['new_lock'])) {
        iss_editorial_track_lock_session(absint($data['wp-refresh-post-lock']['post_id'] ?? 0), (string) $response['wp-refresh-post-lock']['new_lock']);
    }
    return $response;
}, 11, 2);

/** Logout releases only this session's native locks, even if other logins remain. */
add_action('wp_logout', static function (int $user_id): void {
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
});

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

add_action('load-post.php', static function (): void {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Native edit-screen entry; only an inactive session's transient lock can be removed.
    if (($_GET['action'] ?? '') === 'edit') {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Permission and session liveness are checked by the helper.
        iss_editorial_release_inactive_lock(absint($_GET['post'] ?? 0));
    }
});

/** Do not let an old tab acquire locks as the newly signed-in account. */
add_action('wp_ajax_heartbeat', static function (): void {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Account mismatch returns read-only state; lock cleanup below requires the native nonce.
    $editor_user = absint($_POST['data']['iss-editorial-user'] ?? 0);
    if (!$editor_user) {
        return;
    }
    $nonce_valid = wp_verify_nonce(sanitize_text_field(wp_unslash((string) ($_POST['_nonce'] ?? ''))), 'heartbeat-nonce');
    if ($editor_user !== get_current_user_id() || !$nonce_valid) {
        wp_send_json(['iss-editorial-session-changed' => true]);
    }
    iss_editorial_release_inactive_lock(absint($_POST['data']['wp-refresh-post-lock']['post_id'] ?? 0));
}, 0);

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
    // Recovery must preserve unfinished form entries. Canonical saves validate separately.
    $document = map_deep($document, static function ($value) {
        return is_string($value) ? wp_kses_post($value) : $value;
    });
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
    update_metadata('post', $revision_id, '_iss_editorial_draft_base', iss_editorial_saved_token($post_id, $format_slug));
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
        return new WP_Error('editorial_conflict', __('Inzwischen wurde eine neue Fassung gespeichert. Bitte diese Seite neu öffnen und Ihren Entwurf wiederherstellen.', 'iss-editorial'));
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
            return new WP_Error('editorial_draft_conflict', __('Der Entwurf wurde in einem anderen Fenster geändert. Bitte diese Seite neu öffnen.', 'iss-editorial'));
        }
    }
    return true;
}

/** Reject the entire submitted update before WordPress or owner hooks change it. */
function iss_editorial_validate_post_update(array $data, array $postarr): array
{
    if (($data['post_type'] ?? '') === 'revision' || empty($_POST['iss_editorial_nonce'])) {
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
    $payload = wp_unslash($_POST['iss_editorial'][$format['slug']]);
    $result = current_user_can('edit_post', $post_id)
        ? iss_editorial_check_edit_version($post_id, $format['slug'], (string) ($payload['base'] ?? ''), (string) ($payload['draft_token'] ?? ''))
        : new WP_Error('editorial_permission', __('Keine Berechtigung.', 'iss-editorial'));
    if (!is_wp_error($result)) {
        $result = iss_editorial_validate_document((string) ($payload['document'] ?? ''), $format['slug']);
    }
    if (is_wp_error($result)) {
        wp_die(esc_html($result->get_error_message()), esc_html__('Inhalt nicht gespeichert', 'iss-editorial'), ['response' => 409, 'back_link' => true]);
    }
    // Capture the pre-update document too, including the first edit after installing this support.
    wp_save_post_revision($post_id);
    return $data;
}
add_filter('wp_insert_post_data', 'iss_editorial_validate_post_update', 99, 2);

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
        wp_cache_delete($revision_id, 'post_meta');
        if (update_meta_cache('post', [$revision_id]) === false || $wpdb->last_error !== '') {
            return false;
        }
        $values = [];
        foreach (wp_post_revision_meta_keys(get_post_type($post_id)) as $key) {
            $values[$key] = get_post_meta($revision_id, $key);
        }
        wp_restore_post_revision_meta($post_id, $revision_id);
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
add_filter('the_preview', static function ($post) {
    $format = $post instanceof WP_Post ? iss_editorial_get_format_for_post($post) : [];
    if ($format && iss_editorial_should_prefer_preview_autosave($post->ID, $format['slug'])) {
        $draft = iss_editorial_get_draft($post->ID, $format['slug']);
        if ($draft) {
            $post = clone $post;
            $post->post_title = $draft['title'];
            $post->post_excerpt = $draft['excerpt'];
        }
    }
    return $post;
}, 20);

/** Show section changes in the existing WordPress revision comparison. */
add_filter('wp_get_revision_ui_diff', static function (array $fields, WP_Post $from, WP_Post $to): array {
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
    $diff = wp_text_diff($describe($from), $describe($to));
    if ($diff) {
        $fields[] = ['id' => 'iss-editorial-document', 'name' => __('Inhaltsabschnitte', 'iss-editorial'), 'diff' => $diff];
    }
    return $fields;
}, 10, 3);
