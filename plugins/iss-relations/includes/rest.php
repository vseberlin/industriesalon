<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_relations_rest_can_edit_post_relations(WP_REST_Request $request): bool
{
    $post_id = absint($request->get_param('post_id'));

    return $post_id > 0
        && current_user_can('edit_post', $post_id)
        && iss_relations_is_supported_post_type((string) get_post_type($post_id));
}

function iss_relations_rest_get_post_relations(WP_REST_Request $request)
{
    $post_id = absint($request->get_param('post_id'));
    $stored = get_post_meta($post_id, ISS_RELATIONS_META_KEY, true);

    return rest_ensure_response([
        'ok' => true,
        'relations' => iss_relations_normalize_relations(is_array($stored) ? $stored : [], $post_id),
    ]);
}

function iss_relations_rest_save_post_relations(WP_REST_Request $request)
{
    $post_id = absint($request->get_param('post_id'));
    if (iss_relations_supports_route_drafts($post_id)) {
        return new WP_Error(
            'iss_relations_route_locked',
            __('Routenstationen werden nur über den Route-Entwurf veröffentlicht.', 'iss-relations'),
            ['status' => 409]
        );
    }

    $params = $request->get_json_params();
    $relations = is_array($params['relations'] ?? null) ? $params['relations'] : [];

    return rest_ensure_response([
        'ok' => true,
        'relations' => iss_relations_save_post_relations($post_id, $relations),
    ]);
}

function iss_relations_rest_can_edit_route_draft(WP_REST_Request $request): bool
{
    $post_id = absint($request->get_param('post_id'));

    return $post_id > 0
        && current_user_can('edit_post', $post_id)
        && iss_relations_supports_route_drafts($post_id);
}

function iss_relations_rest_route_draft_payload(int $post_id): array
{
    $draft = iss_relations_get_route_draft($post_id);
    $canonical = iss_relations_get_stored_post_relations($post_id);

    return [
        'ok' => true,
        'locked' => !$draft,
        'canonical' => $canonical,
        'canonicalHash' => iss_relations_route_relation_hash($canonical),
        'draftToken' => iss_relations_route_draft_token($post_id),
        'draft' => $draft,
        'relations' => $draft ? (array) ($draft['relations'] ?? []) : $canonical,
        'trash' => $draft ? (array) ($draft['trash'] ?? []) : [],
        'previewArgs' => iss_relations_get_route_draft_preview_args($post_id),
    ];
}

function iss_relations_rest_get_route_draft(WP_REST_Request $request)
{
    return rest_ensure_response(iss_relations_rest_route_draft_payload(absint($request->get_param('post_id'))));
}

function iss_relations_rest_save_route_draft(WP_REST_Request $request)
{
    $post_id = absint($request->get_param('post_id'));
    $params = $request->get_json_params();
    $action = sanitize_key((string) ($params['action'] ?? 'save'));

    if (!in_array($action, ['unlock', 'discard', 'publish', 'save'], true)) {
        return new WP_Error('iss_relations_route_action', __('Unbekannte Routenaktion.', 'iss-relations'), ['status' => 400]);
    }
    if ($action === 'save') {
        $relations = $params['relations'] ?? null;
        $trash = $params['trash'] ?? null;
        if (!is_array($relations) || !is_array($trash) || !array_is_list($relations) || !array_is_list($trash)
            || count($relations) !== count(iss_relations_normalize_relations($relations, $post_id))
            || count($trash) !== count(iss_relations_route_station_rows(iss_relations_normalize_relations($trash, $post_id)))) {
            return new WP_Error('iss_relations_route_incomplete', __('Die Stationen sind unvollständig oder enthalten doppelte Einträge. Bitte die Route prüfen. Es wurde nichts gespeichert.', 'iss-relations'), ['status' => 422]);
        }
    }
    if (!iss_core_acquire_post_write_lock($post_id)) {
        return new WP_Error('iss_relations_route_busy', __('Eine Speicherung läuft noch. Bitte kurz warten und erneut versuchen.', 'iss-relations'), ['status' => 409]);
    }
    if (!function_exists('wp_check_post_lock')) { require_once ABSPATH . 'wp-admin/includes/post.php'; }
    if (wp_check_post_lock($post_id)) {
        return new WP_Error('iss_relations_route_locked', __('Eine andere Person bearbeitet diesen Inhalt. Ihre Änderungen bleiben in diesem Fenster.', 'iss-relations'), ['status' => 409]);
    }
    $canonical_hash = iss_relations_route_relation_hash(iss_relations_get_stored_post_relations($post_id));
    if (!hash_equals($canonical_hash, (string) ($params['baseHash'] ?? ''))) {
        return new WP_Error('iss_relations_route_conflict', __('Die veröffentlichte Route wurde geändert. Bitte Änderungen herunterladen und die Fassungen vergleichen.', 'iss-relations'), ['status' => 409]);
    }
    if (!hash_equals(iss_relations_route_draft_token($post_id), (string) ($params['draftToken'] ?? ''))) {
        // Lost response: acknowledge only an exact convergent save, not newer local input.
        $draft = iss_relations_get_route_draft($post_id);
        $same = $action === 'save' && $draft
            && $draft['relations'] === iss_relations_normalize_relations((array) ($params['relations'] ?? []), $post_id)
            && $draft['trash'] === iss_relations_route_station_rows(iss_relations_normalize_relations((array) ($params['trash'] ?? []), $post_id));
        $unlocked = $action === 'unlock' && $draft && !$draft['trash'] && $draft['base_hash'] === $canonical_hash
            && $draft['relations'] === iss_relations_get_stored_post_relations($post_id);
        if ($same || $unlocked || ($action === 'discard' && !$draft)) { return rest_ensure_response(iss_relations_rest_route_draft_payload($post_id)); }
        return new WP_Error('iss_relations_route_draft_conflict', __('Der Routen-Entwurf wurde in einem anderen Fenster geändert. Bitte Änderungen herunterladen und die Fassungen vergleichen.', 'iss-relations'), ['status' => 409]);
    }
    if ($action === 'publish' && !current_user_can(get_post_type_object(get_post_type($post_id))->cap->publish_posts)) {
        return new WP_Error('iss_relations_route_permission', __('Keine Berechtigung zum Veröffentlichen der Route.', 'iss-relations'), ['status' => 403]);
    }

    if ($action === 'unlock') {
        iss_relations_create_route_draft($post_id);
        if (!iss_relations_get_route_draft($post_id)) { return new WP_Error('iss_relations_route_write', __('Der Routen-Entwurf konnte nicht angelegt werden.', 'iss-relations'), ['status' => 500]); }
        return rest_ensure_response(iss_relations_rest_route_draft_payload($post_id));
    }

    if ($action === 'discard') {
        iss_relations_discard_route_draft($post_id);
        if (iss_relations_get_route_draft($post_id)) { return new WP_Error('iss_relations_route_write', __('Der Routen-Entwurf konnte nicht verworfen werden.', 'iss-relations'), ['status' => 500]); }
        return rest_ensure_response(iss_relations_rest_route_draft_payload($post_id));
    }

    if ($action === 'publish') {
        $draft = iss_relations_get_route_draft($post_id);
        if (!$draft) {
            return new WP_Error(
                'iss_relations_route_no_draft',
                __('Es gibt keinen Routen-Entwurf zum Veröffentlichen.', 'iss-relations'),
                ['status' => 409]
            );
        }

        try { iss_relations_publish_route_draft($post_id); }
        catch (Throwable $error) {
            return new WP_Error('iss_relations_route_publish', __('Die Route wurde nicht vollständig veröffentlicht. Bitte die gespeicherten Stationen und den Entwurf prüfen; nicht ungeprüft wiederholen.', 'iss-relations'), ['status' => 500]);
        }
        if (iss_relations_get_route_draft($post_id) || iss_relations_get_stored_post_relations($post_id) !== $draft['relations']) {
            return new WP_Error('iss_relations_route_publish', __('Die Veröffentlichung konnte nicht bestätigt werden. Bitte die Route prüfen.', 'iss-relations'), ['status' => 500]);
        }
        return rest_ensure_response(iss_relations_rest_route_draft_payload($post_id));
    }

    $relations = is_array($params['relations'] ?? null) ? $params['relations'] : [];
    $trash = is_array($params['trash'] ?? null) ? $params['trash'] : [];
    $base_hash = sanitize_text_field((string) ($params['baseHash'] ?? ''));
    iss_relations_save_route_draft($post_id, $relations, $trash, $base_hash);
    $stored = iss_relations_get_route_draft($post_id);
    if (!$stored || $stored['relations'] !== iss_relations_normalize_relations($relations, $post_id)
        || $stored['trash'] !== iss_relations_route_station_rows(iss_relations_normalize_relations($trash, $post_id))) {
        return new WP_Error('iss_relations_route_write', __('Der Routen-Entwurf wurde nicht vollständig gespeichert. Bitte erneut prüfen.', 'iss-relations'), ['status' => 500]);
    }

    return rest_ensure_response(iss_relations_rest_route_draft_payload($post_id));
}

function iss_relations_register_relation_rest_routes(): void
{
    register_rest_route('iss-relations/v1', '/posts/(?P<post_id>\d+)/places', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'iss_relations_rest_get_post_relations',
            'permission_callback' => 'iss_relations_rest_can_edit_post_relations',
        ],
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'iss_relations_rest_save_post_relations',
            'permission_callback' => 'iss_relations_rest_can_edit_post_relations',
            'args' => [
                'relations' => [
                    'required' => true,
                    'type' => 'array',
                ],
            ],
        ],
    ]);

    register_rest_route('iss-relations/v1', '/posts/(?P<post_id>\d+)/route-draft', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'iss_relations_rest_get_route_draft',
            'permission_callback' => 'iss_relations_rest_can_edit_route_draft',
        ],
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'iss_relations_rest_save_route_draft',
            'permission_callback' => 'iss_relations_rest_can_edit_route_draft',
        ],
    ]);
}
add_action('rest_api_init', 'iss_relations_register_relation_rest_routes');
