<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_touchtable_review_page_url(array $args = []): string
{
    $base_url = admin_url('edit.php?post_type=' . ISS_REGISTER_POST_TYPE . '&page=' . ISS_REGISTER_TOUCHTABLE_REVIEW_PAGE_SLUG);

    if (!$args) {
        return $base_url;
    }

    return add_query_arg($args, $base_url);
}

function iss_register_touchtable_review_kind_labels(): array
{
    return [
        'detail_page' => 'Detailseiten',
        'map_page' => 'Karten',
        'landing_page' => 'Startseiten',
        'media' => 'Medien',
        'all' => 'Alles',
    ];
}

function iss_register_touchtable_review_status_labels(): array
{
    return [
        'needs_review' => 'Offen',
        'new' => 'Neu',
        'changed' => 'Geaendert',
        'linked' => 'Verknuepft',
        'ignored' => 'Ignoriert',
        'all' => 'Alle',
    ];
}

function iss_register_get_reviewable_places(): array
{
    $posts = get_posts([
        'post_type' => ISS_REGISTER_POST_TYPE,
        'post_status' => ['publish', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'suppress_filters' => true,
    ]);

    $items = [];

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $items[] = [
            'id' => (int) $post->ID,
            'title' => (string) $post->post_title,
        ];
    }

    return $items;
}

function iss_register_render_place_select_options(array $places, int $selected_place_id = 0): void
{
    echo '<option value="">Ort waehlen</option>';

    foreach ($places as $place) {
        $place_id = (int) ($place['id'] ?? 0);
        $title = (string) ($place['title'] ?? '');

        if ($place_id <= 0 || $title === '') {
            continue;
        }

        echo '<option value="' . esc_attr((string) $place_id) . '" ' . selected($selected_place_id, $place_id, false) . '>' . esc_html($title) . '</option>';
    }
}

function iss_register_render_linked_place_label(int $place_id): string
{
    if ($place_id <= 0) {
        return '&mdash;';
    }

    $title = get_the_title($place_id);
    if ($title === '') {
        return '&mdash;';
    }

    $edit_link = get_edit_post_link($place_id);
    if (!$edit_link) {
        return esc_html($title);
    }

    return '<a href="' . esc_url($edit_link) . '">' . esc_html($title) . '</a>';
}

function iss_register_render_touchtable_review_notice(): void
{
    $notice = sanitize_key((string) wp_unslash($_GET['iss_register_notice'] ?? ''));
    if ($notice === '') {
        return;
    }

    $messages = [
        'linked' => ['updated', 'Quellstand wurde mit einem Register-Ort verknuepft.'],
        'created' => ['updated', 'Neuer Register-Ort wurde aus dem Quellstand angelegt und direkt verknuepft.'],
        'ignored' => ['updated', 'Quellstand wurde ignoriert.'],
        'reset' => ['updated', 'Quellstand wurde auf offen zurueckgesetzt.'],
        'error' => ['error', sanitize_text_field((string) wp_unslash($_GET['iss_register_message'] ?? 'Aktion konnte nicht ausgefuehrt werden.'))],
    ];

    if (!isset($messages[$notice])) {
        return;
    }

    [$class, $message] = $messages[$notice];
    echo '<div class="notice notice-' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
}

function iss_register_handle_touchtable_review_action(): void
{
    $capability = function_exists('iss_core_capability') ? iss_core_capability('register') : 'edit_posts';
    if (function_exists('iss_require_cap')) {
        iss_require_cap($capability);
    } elseif (!current_user_can($capability)) {
        wp_die('Insufficient permissions.', 403);
    }

    check_admin_referer('iss_register_touchtable_review_action', 'iss_register_touchtable_review_nonce');

    $snapshot_post_id = absint(wp_unslash($_POST['snapshot_post_id'] ?? 0));
    $action_name = sanitize_key((string) wp_unslash($_POST['iss_register_touchtable_review_subaction'] ?? ''));
    $place_id = absint(wp_unslash($_POST['place_id'] ?? 0));

    $redirect_args = [
        'source_kind' => sanitize_key((string) wp_unslash($_POST['source_kind_filter'] ?? 'detail_page')),
        'review_status' => sanitize_key((string) wp_unslash($_POST['review_status_filter'] ?? 'needs_review')),
        'paged' => max(1, absint(wp_unslash($_POST['paged_filter'] ?? 1))),
    ];

    $linked_register_place_id = absint(wp_unslash($_POST['linked_register_place_id_filter'] ?? 0));
    if ($linked_register_place_id > 0) {
        $redirect_args['linked_register_place_id'] = $linked_register_place_id;
    }

    $snapshot = iss_register_get_source_snapshot($snapshot_post_id);
    if (!$snapshot) {
        $redirect_args['iss_register_notice'] = 'error';
        $redirect_args['iss_register_message'] = 'Quellstand nicht gefunden.';
        wp_safe_redirect(iss_register_touchtable_review_page_url($redirect_args));
        exit;
    }

    switch ($action_name) {
        case 'accept_candidate':
            $place_id = (int) ($snapshot['candidate_register_place_id'] ?? 0);
            if ($place_id <= 0 || !iss_register_link_source_snapshot_to_place($snapshot_post_id, $place_id)) {
                $redirect_args['iss_register_notice'] = 'error';
                $redirect_args['iss_register_message'] = 'Automatischer Match konnte nicht uebernommen werden.';
            } else {
                $redirect_args['iss_register_notice'] = 'linked';
            }
            break;

        case 'link_existing':
            if ($place_id <= 0 || !iss_register_link_source_snapshot_to_place($snapshot_post_id, $place_id)) {
                $redirect_args['iss_register_notice'] = 'error';
                $redirect_args['iss_register_message'] = 'Bitte einen gueltigen Register-Ort waehlen.';
            } else {
                $redirect_args['iss_register_notice'] = 'linked';
            }
            break;

        case 'create_place':
            $created_post_id = iss_register_create_register_place_from_source_snapshot($snapshot_post_id);
            if (is_wp_error($created_post_id) || !iss_register_link_source_snapshot_to_place($snapshot_post_id, (int) $created_post_id)) {
                $redirect_args['iss_register_notice'] = 'error';
                $redirect_args['iss_register_message'] = is_wp_error($created_post_id)
                    ? $created_post_id->get_error_message()
                    : 'Register-Ort konnte nicht angelegt werden.';
            } else {
                $redirect_args['iss_register_notice'] = 'created';
            }
            break;

        case 'ignore':
            if (!iss_register_ignore_source_snapshot($snapshot_post_id)) {
                $redirect_args['iss_register_notice'] = 'error';
                $redirect_args['iss_register_message'] = 'Quellstand konnte nicht ignoriert werden.';
            } else {
                $redirect_args['iss_register_notice'] = 'ignored';
            }
            break;

        case 'reset':
            if (!iss_register_reset_source_snapshot_review($snapshot_post_id)) {
                $redirect_args['iss_register_notice'] = 'error';
                $redirect_args['iss_register_message'] = 'Quellstand konnte nicht zurueckgesetzt werden.';
            } else {
                $redirect_args['iss_register_notice'] = 'reset';
            }
            break;

        default:
            $redirect_args['iss_register_notice'] = 'error';
            $redirect_args['iss_register_message'] = 'Unbekannte Aktion.';
            break;
    }

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('register_touchtable_review_' . $action_name, [
            'capability' => $capability,
            'object_ids' => array_values(array_filter([$snapshot_post_id, $place_id])),
            'result' => (($redirect_args['iss_register_notice'] ?? '') === 'error') ? 'failed' : 'completed',
        ]);
    }

    wp_safe_redirect(iss_register_touchtable_review_page_url($redirect_args));
    exit;
}

add_action('admin_post_iss_register_touchtable_review', 'iss_register_handle_touchtable_review_action');

function iss_register_render_touchtable_review_filters(array $filters): void
{
    $kind_labels = iss_register_touchtable_review_kind_labels();
    $status_labels = iss_register_touchtable_review_status_labels();

    echo '<form method="get" style="margin:1rem 0;">';
    echo '<input type="hidden" name="post_type" value="' . esc_attr(ISS_REGISTER_POST_TYPE) . '">';
    echo '<input type="hidden" name="page" value="' . esc_attr(ISS_REGISTER_TOUCHTABLE_REVIEW_PAGE_SLUG) . '">';
    echo '<label for="iss-register-source-kind" style="margin-right:0.5rem;">Typ</label>';
    echo '<select id="iss-register-source-kind" name="source_kind">';
    foreach ($kind_labels as $value => $label) {
        echo '<option value="' . esc_attr($value) . '" ' . selected($filters['source_kind'], $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select> ';
    echo '<label for="iss-register-review-status" style="margin:0 0.5rem 0 1rem;">Status</label>';
    echo '<select id="iss-register-review-status" name="review_status">';
    foreach ($status_labels as $value => $label) {
        echo '<option value="' . esc_attr($value) . '" ' . selected($filters['review_status'], $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select> ';

    if ($filters['linked_register_place_id'] > 0) {
        echo '<input type="hidden" name="linked_register_place_id" value="' . esc_attr((string) $filters['linked_register_place_id']) . '">';
        echo '<span style="margin-left:1rem;">Ort-Filter: <strong>' . wp_kses_post(iss_register_render_linked_place_label($filters['linked_register_place_id'])) . '</strong></span> ';
        echo '<a href="' . esc_url(iss_register_touchtable_review_page_url([
            'source_kind' => $filters['source_kind'],
            'review_status' => $filters['review_status'],
        ])) . '">Filter aufheben</a> ';
    }

    submit_button('Filtern', 'secondary', '', false);
    echo '</form>';
}

function iss_register_render_touchtable_review_actions(array $snapshot, array $places, array $filters): void
{
    $snapshot_post_id = (int) ($snapshot['post_id'] ?? 0);
    $source_kind = (string) ($snapshot['source_kind'] ?? '');
    $linked_place_id = (int) ($snapshot['matched_register_place_id'] ?? 0);
    $candidate_place_id = (int) ($snapshot['candidate_register_place_id'] ?? 0);
    $selected_place_id = $linked_place_id > 0 ? $linked_place_id : $candidate_place_id;

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="iss_register_touchtable_review">';
    echo '<input type="hidden" name="snapshot_post_id" value="' . esc_attr((string) $snapshot_post_id) . '">';
    echo '<input type="hidden" name="source_kind_filter" value="' . esc_attr($filters['source_kind']) . '">';
    echo '<input type="hidden" name="review_status_filter" value="' . esc_attr($filters['review_status']) . '">';
    echo '<input type="hidden" name="paged_filter" value="' . esc_attr((string) $filters['paged']) . '">';
    echo '<input type="hidden" name="linked_register_place_id_filter" value="' . esc_attr((string) $filters['linked_register_place_id']) . '">';
    wp_nonce_field('iss_register_touchtable_review_action', 'iss_register_touchtable_review_nonce');

    echo '<p><select name="place_id" style="width:100%;">';
    iss_register_render_place_select_options($places, $selected_place_id);
    echo '</select></p>';

    if ($candidate_place_id > 0 && $candidate_place_id !== $linked_place_id) {
        echo '<p><button type="submit" class="button button-secondary" name="iss_register_touchtable_review_subaction" value="accept_candidate">Auto-Match uebernehmen</button></p>';
    }

    echo '<p><button type="submit" class="button button-secondary" name="iss_register_touchtable_review_subaction" value="link_existing">Mit Ort verknuepfen</button></p>';

    if ($source_kind === 'detail_page' && $linked_place_id <= 0) {
        echo '<p><button type="submit" class="button button-secondary" name="iss_register_touchtable_review_subaction" value="create_place">Neuen Ort anlegen</button></p>';
    }

    echo '<p><button type="submit" class="button button-secondary" name="iss_register_touchtable_review_subaction" value="ignore">Ignorieren</button></p>';

    if ($linked_place_id > 0 || ($snapshot['review_status'] ?? 'new') !== 'new') {
        echo '<p><button type="submit" class="button button-link-delete" name="iss_register_touchtable_review_subaction" value="reset">Zuruecksetzen</button></p>';
    }

    echo '</form>';
}

function iss_register_render_touchtable_review_content_cell(array $snapshot): void
{
    $source_kind = (string) ($snapshot['source_kind'] ?? '');
    $preview_text = trim((string) ($snapshot['preview_text'] ?? ''));
    $preview_length = (int) ($snapshot['preview_length'] ?? 0);
    $content_length = (int) ($snapshot['content_length'] ?? 0);
    $excerpt_length = (int) ($snapshot['excerpt_length'] ?? 0);
    $hotspot_count = (int) ($snapshot['hotspot_count'] ?? 0);

    if ($preview_text !== '') {
        echo '<strong>Text erkannt</strong><br>';
        echo '<span>' . esc_html((string) $preview_length) . ' Zeichen';
        if ($content_length > 0 && $excerpt_length > 0) {
            echo ' · Inhalt ' . esc_html((string) $content_length) . ' · Kurztext ' . esc_html((string) $excerpt_length);
        }
        echo '</span><br>';
        echo '<span>' . esc_html($preview_text) . '</span>';
        return;
    }

    if ($source_kind === 'map_page' && $hotspot_count > 0) {
        echo '<strong>Kartenkontext</strong><br>';
        echo '<span>' . esc_html((string) $hotspot_count) . ' Hotspots, kein freier Fliesstext</span>';
        return;
    }

    echo '<strong>Kein Text erkannt</strong><br>';
    echo '<span>Diese Quelle liefert aktuell keinen nutzbaren Textinhalt.</span>';
}

function iss_register_render_touchtable_review_table(array $items, array $places, array $filters): void
{
    if (!$items) {
        echo '<p>Keine Quellstaende fuer den aktuellen Filter.</p>';
        return;
    }

    $status_labels = iss_register_source_review_statuses();

    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>Quelle</th><th>Kontext</th><th>Inhalt</th><th>Kandidat</th><th>Verknuepft</th><th>Status</th><th>Aktionen</th>';
    echo '</tr></thead><tbody>';

    foreach ($items as $snapshot) {
        $title = (string) ($snapshot['title'] ?? '');
        $source_url = (string) ($snapshot['source_url'] ?? '');
        $extracted = is_array($snapshot['extracted_payload'] ?? null) ? $snapshot['extracted_payload'] : [];
        $root_title = trim((string) ($extracted['root_page_title'] ?? ''));
        $candidate_place_id = (int) ($snapshot['candidate_register_place_id'] ?? 0);
        $linked_place_id = (int) ($snapshot['matched_register_place_id'] ?? 0);
        $review_status = iss_register_normalize_source_review_status((string) ($snapshot['review_status'] ?? 'new'));

        echo '<tr>';
        echo '<td>';
        echo '<strong>' . esc_html($title) . '</strong><br>';
        echo '<span>' . esc_html((string) ($snapshot['source_kind'] ?? '')) . '</span>';
        if ($source_url !== '') {
            echo '<br><a href="' . esc_url($source_url) . '" target="_blank" rel="noreferrer">Quelle oeffnen</a>';
        }
        echo '</td>';

        echo '<td>';
        if ($root_title !== '') {
            echo '<strong>' . esc_html($root_title) . '</strong><br>';
        }
        echo 'ID: ' . esc_html((string) ($snapshot['source_id'] ?? 0));
        if (!empty($snapshot['source_modified'])) {
            echo '<br>Quelle geaendert: ' . esc_html((string) $snapshot['source_modified']);
        }
        echo '</td>';

        echo '<td>';
        iss_register_render_touchtable_review_content_cell($snapshot);
        echo '</td>';

        echo '<td>' . wp_kses_post(iss_register_render_linked_place_label($candidate_place_id)) . '</td>';
        echo '<td>' . wp_kses_post(iss_register_render_linked_place_label($linked_place_id)) . '</td>';
        echo '<td>' . esc_html($status_labels[$review_status] ?? $review_status) . '</td>';
        echo '<td>';
        iss_register_render_touchtable_review_actions($snapshot, $places, $filters);
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

function iss_register_render_touchtable_review_pagination(array $result, array $filters): void
{
    if (($result['pages'] ?? 1) <= 1) {
        return;
    }

    $links = paginate_links([
        'base' => add_query_arg(
            [
                'post_type' => ISS_REGISTER_POST_TYPE,
                'page' => ISS_REGISTER_TOUCHTABLE_REVIEW_PAGE_SLUG,
                'source_kind' => $filters['source_kind'],
                'review_status' => $filters['review_status'],
                'linked_register_place_id' => $filters['linked_register_place_id'],
                'paged' => '%#%',
            ],
            admin_url('edit.php')
        ),
        'format' => '',
        'current' => max(1, (int) ($result['page'] ?? 1)),
        'total' => max(1, (int) ($result['pages'] ?? 1)),
        'type' => 'list',
    ]);

    if (!$links) {
        return;
    }

    echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post($links) . '</div></div>';
}

function iss_register_render_touchtable_review_page(): void
{
    $capability = function_exists('iss_core_capability') ? iss_core_capability('register') : 'edit_posts';
    if (function_exists('iss_require_cap')) {
        iss_require_cap($capability);
    } elseif (!current_user_can($capability)) {
        wp_die('Insufficient permissions.', 403);
    }

    $filters = [
        'source_kind' => sanitize_key((string) wp_unslash($_GET['source_kind'] ?? 'detail_page')),
        'review_status' => sanitize_key((string) wp_unslash($_GET['review_status'] ?? 'needs_review')),
        'linked_register_place_id' => absint(wp_unslash($_GET['linked_register_place_id'] ?? 0)),
        'paged' => max(1, absint(wp_unslash($_GET['paged'] ?? 1))),
    ];

    $result = iss_register_get_source_snapshots_for_review([
        'source_kind' => $filters['source_kind'],
        'review_status' => $filters['review_status'],
        'linked_register_place_id' => $filters['linked_register_place_id'],
        'paged' => $filters['paged'],
        'per_page' => 25,
    ]);

    $places = iss_register_get_reviewable_places();

    echo '<div class="wrap">';
    echo '<h1>Touchtable Review</h1>';
    echo '<p>Prueft lokale Touchtable-Quellstaende, verknuepft sie mit <code>register_place</code> oder legt daraus neue Entwuerfe an. Oeffentliche Ausgabe bleibt theme-owned und nutzt nur lokale Daten.</p>';
    echo '<p>Quellen mit erkanntem Text stehen oben, leere Detailseiten werden im Review nach unten gedrueckt.</p>';
    iss_register_render_touchtable_review_notice();
    iss_register_render_touchtable_review_filters($filters);
    echo '<p><strong>' . esc_html((string) ($result['total'] ?? 0)) . '</strong> Quellstaende im aktuellen Filter.</p>';
    iss_register_render_touchtable_review_table($result['items'] ?? [], $places, $filters);
    iss_register_render_touchtable_review_pagination($result, $filters);
    echo '</div>';
}

add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=' . ISS_REGISTER_POST_TYPE,
        'Touchtable Review',
        'Touchtable Review',
        function_exists('iss_core_capability') ? iss_core_capability('register') : 'edit_posts',
        ISS_REGISTER_TOUCHTABLE_REVIEW_PAGE_SLUG,
        'iss_register_render_touchtable_review_page'
    );
});

function iss_register_render_touchtable_source_meta_box(WP_Post $post): void
{
    $items = iss_register_get_source_snapshots_for_place((int) $post->ID, 12);

    if (!$items) {
        echo '<p>Kein Touchtable-Quellstand mit diesem Ort verknuepft.</p>';
        echo '<p><a class="button button-secondary" href="' . esc_url(iss_register_touchtable_review_page_url()) . '">Touchtable Review oeffnen</a></p>';
        return;
    }

    echo '<ul style="margin:0;">';
    $status_labels = iss_register_source_review_statuses();
    foreach ($items as $item) {
        $source_url = (string) ($item['source_url'] ?? '');
        $review_status = iss_register_normalize_source_review_status((string) ($item['review_status'] ?? 'new'));
        echo '<li style="margin-bottom:0.75rem;">';
        echo '<strong>' . esc_html((string) ($item['title'] ?? '')) . '</strong><br>';
        echo '<span>' . esc_html((string) ($item['source_kind'] ?? '')) . ' · ' . esc_html($status_labels[$review_status] ?? $review_status) . '</span>';
        if ($source_url !== '') {
            echo '<br><a href="' . esc_url($source_url) . '" target="_blank" rel="noreferrer">Quelle oeffnen</a>';
        }
        echo '</li>';
    }
    echo '</ul>';

    echo '<p><a class="button button-secondary" href="' . esc_url(iss_register_touchtable_review_page_url([
        'review_status' => 'all',
        'source_kind' => 'all',
        'linked_register_place_id' => (int) $post->ID,
    ])) . '">Alle verknuepften Quellstaende ansehen</a></p>';
}

add_action('add_meta_boxes', function () {
    add_meta_box(
        'iss-register-touchtable-source',
        'Touchtable Quelle',
        'iss_register_render_touchtable_source_meta_box',
        ISS_REGISTER_POST_TYPE,
        'side',
        'default'
    );
});
