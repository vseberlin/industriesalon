<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_editorial_sets_supported_post_types(): array
{
    $post_types = [
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
        'rueckblick',
        ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE,
        ISS_CONTENT_MODEL_PROJEKT_POST_TYPE,
        'publication',
        'page',
    ];

    return array_values(array_filter(array_map('sanitize_key', $post_types), 'post_type_exists'));
}

function iss_content_editorial_sets_admin_url(array $args = []): string
{
    return add_query_arg(array_merge(['page' => 'iss-editorial-sets'], $args), admin_url('admin.php'));
}

function iss_content_editorial_sets_register_admin_page(): void
{
    add_submenu_page(
        defined('ISS_CORE_OPERATIONS_MENU_SLUG') ? ISS_CORE_OPERATIONS_MENU_SLUG : 'tools.php',
        __('Intake Workbench', 'iss-content-model'),
        __('Sets', 'iss-content-model'),
        ISS_CONTENT_EDITORIAL_SETS_CAPABILITY,
        'iss-editorial-sets',
        'iss_content_editorial_sets_render_admin_page'
    );
}
add_action('admin_menu', 'iss_content_editorial_sets_register_admin_page');

function iss_content_editorial_sets_render_admin_page(): void
{
    if (function_exists('iss_require_cap')) {
        iss_require_cap(ISS_CONTENT_EDITORIAL_SETS_CAPABILITY);
    } elseif (!current_user_can(ISS_CONTENT_EDITORIAL_SETS_CAPABILITY)) {
        wp_die(esc_html__('Keine Berechtigung.', 'iss-content-model'), 403);
    }

    echo '<div class="wrap iss-editorial-sets-page">';
    echo '<h1>' . esc_html__('Intake Workbench', 'iss-content-model') . '</h1>';
    echo '<div id="iss-editorial-sets-workbench" class="iss-editorial-sets-workbench"></div>';
    echo '</div>';
}

function iss_content_editorial_sets_enqueue_admin_assets(string $hook): void
{
    if (strpos($hook, 'iss-editorial-sets') === false) {
        return;
    }

    wp_enqueue_media();

    $style_path = ISS_CONTENT_MODEL_PATH . 'assets/admin-editorial-sets.css';
    if (file_exists($style_path)) {
        wp_enqueue_style(
            'iss-content-editorial-sets',
            plugins_url('../assets/admin-editorial-sets.css', __FILE__),
            [],
            (string) filemtime($style_path)
        );
    }

    $script_path = ISS_CONTENT_MODEL_PATH . 'assets/admin-editorial-sets.js';
    if (file_exists($script_path)) {
        wp_enqueue_script(
            'iss-content-editorial-sets',
            plugins_url('../assets/admin-editorial-sets.js', __FILE__),
            ['wp-api-fetch'],
            (string) filemtime($script_path),
            true
        );
        wp_localize_script(
            'iss-content-editorial-sets',
            'issContentEditorialSets',
            [
                'restRoot' => esc_url_raw(rest_url(iss_content_editorial_sets_rest_namespace())),
                'nonce' => wp_create_nonce('wp_rest'),
                'contextType' => sanitize_key((string) ($_GET['context_type'] ?? '')), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin filter.
                'contextId' => absint($_GET['context_id'] ?? 0), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin filter.
                'roles' => iss_content_editorial_sets_service()->get_allowed_set_roles(),
                'statuses' => iss_content_editorial_sets_service()->get_allowed_item_statuses(),
                'setStatuses' => iss_content_editorial_sets_service()->get_allowed_set_statuses(),
                'strings' => [
                    'error' => __('Die Anfrage konnte nicht abgeschlossen werden.', 'iss-content-model'),
                    'newSet' => __('Neues Set', 'iss-content-model'),
                    'setName' => __('Set-Name', 'iss-content-model'),
                    'addMedia' => __('Medien hinzufuegen', 'iss-content-model'),
                    'addToSet' => __('Zum Set hinzufuegen', 'iss-content-model'),
                    'selectAll' => __('Alle auswaehlen', 'iss-content-model'),
                    'approve' => __('Freigeben', 'iss-content-model'),
                    'reject' => __('Ablehnen', 'iss-content-model'),
                    'review' => __('Pruefen', 'iss-content-model'),
                    'retain' => __('Behalten', 'iss-content-model'),
                    'stale' => __('Als abgelaufen markieren', 'iss-content-model'),
                    'restore' => __('Wiederherstellen', 'iss-content-model'),
                    'archiveCandidate' => __('Archivkandidat', 'iss-content-model'),
                    'move' => __('Verschieben', 'iss-content-model'),
                    'promote' => __('Veroeffentlichen', 'iss-content-model'),
                    'uncategorized' => __('Ohne Set', 'iss-content-model'),
                    'attachHere' => __('Hier anhaengen', 'iss-content-model'),
                    'allStatuses' => __('Alle Status', 'iss-content-model'),
                    'noItems' => __('Keine Eintraege in dieser Ansicht.', 'iss-content-model'),
                    'noSelection' => __('Keine Eintraege ausgewaehlt.', 'iss-content-model'),
                    'moveToSet' => __('In Set-ID verschieben', 'iss-content-model'),
                    'invalidJson' => __('Rechte und Herkunft muessen gueltiges JSON sein.', 'iss-content-model'),
                    'promotionTargetMissing' => __('Freigegebene Eintraege aus einem angehaengten Set auswaehlen.', 'iss-content-model'),
                    'promotionComplete' => __('Veroeffentlichung abgeschlossen.', 'iss-content-model'),
                    'loading' => __('Laedt...', 'iss-content-model'),
                    'close' => __('Schliessen', 'iss-content-model'),
                    'item' => __('Eintrag', 'iss-content-model'),
                    'untitled' => __('Ohne Titel', 'iss-content-model'),
                    'status' => __('Status', 'iss-content-model'),
                    'source' => __('Quelle', 'iss-content-model'),
                    'origin' => __('Herkunft', 'iss-content-model'),
                    'storageState' => __('Ablage', 'iss-content-model'),
                    'uploaded' => __('Hochgeladen', 'iss-content-model'),
                    'filename' => __('Dateiname', 'iss-content-model'),
                    'mime' => __('MIME', 'iss-content-model'),
                    'decay' => __('Loeschen ab', 'iss-content-model'),
                    'label' => __('Beschriftung', 'iss-content-model'),
                    'notes' => __('Notizen', 'iss-content-model'),
                    'rightsJson' => __('Rechte / Einwilligung JSON', 'iss-content-model'),
                    'provenanceJson' => __('Herkunft JSON', 'iss-content-model'),
                    'saveItem' => __('Eintrag speichern', 'iss-content-model'),
                    'statusLabels' => [
                        'pending' => __('Neu', 'iss-content-model'),
                        'reviewing' => __('In Pruefung', 'iss-content-model'),
                        'approved' => __('Freigegeben', 'iss-content-model'),
                        'rejected' => __('Abgelehnt', 'iss-content-model'),
                        'stale' => __('Abgelaufen', 'iss-content-model'),
                        'promoted' => __('Veroeffentlicht', 'iss-content-model'),
                        'retained' => __('Behalten', 'iss-content-model'),
                    ],
                    'kindLabels' => [
                        'external_upload' => __('Roh-Upload', 'iss-content-model'),
                        'wp_media' => __('Mediathek', 'iss-content-model'),
                        'archive_object' => __('Archivobjekt', 'iss-content-model'),
                    ],
                    'storageLabels' => [
                        'incoming' => __('Eingang', 'iss-content-model'),
                        'accepted' => __('Angenommen', 'iss-content-model'),
                        'rejected' => __('Quarantaene', 'iss-content-model'),
                        'imported' => __('Importiert', 'iss-content-model'),
                    ],
                ],
            ]
        );
    }
}
add_action('admin_enqueue_scripts', 'iss_content_editorial_sets_enqueue_admin_assets');

function iss_content_editorial_sets_resolve_external_upload_path(array $item): string
{
    if (function_exists('iss_content_editorial_sets_event_drop_resolve_item_path')) {
        return iss_content_editorial_sets_event_drop_resolve_item_path($item);
    }

    if ((string) ($item['kind'] ?? '') !== 'external_upload' || (string) ($item['source'] ?? '') !== 'event-drop') {
        return '';
    }

    $provenance = json_decode((string) ($item['provenance_json'] ?? ''), true);
    $provenance = is_array($provenance) ? $provenance : [];
    $stored_name = sanitize_file_name((string) ($provenance['stored_name'] ?? $item['source_id'] ?? ''));
    if ($stored_name === '') {
        return '';
    }

    $root = function_exists('iss_content_editorial_sets_event_drop_storage_root')
        ? iss_content_editorial_sets_event_drop_storage_root()
        : '/event-drop-storage';
    $root = realpath(rtrim($root, '/'));
    if (!is_string($root) || $root === '') {
        return '';
    }

    $candidate = (string) ($provenance['path'] ?? '');
    $paths = $candidate !== '' ? [$candidate] : [];
    foreach (['incoming', 'accepted', 'rejected'] as $state) {
        $paths[] = $root . '/' . $state . '/' . $stored_name;
    }

    foreach (array_unique($paths) as $path) {
        $real = realpath($path);
        if (is_string($real) && strpos($real, $root . '/') === 0 && is_file($real) && is_readable($real)) {
            return $real;
        }
    }

    return '';
}

function iss_content_editorial_sets_stream_file_preview(): void
{
    $item_id = absint($_GET['item_id'] ?? 0); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified below.
    if ($item_id <= 0 || !check_ajax_referer('iss_editorial_set_file_' . $item_id, false, false)) {
        wp_die('', 403);
    }

    if (!iss_content_editorial_sets_current_user_can_any(['iss_edit_sets', 'iss_review_sets'])) {
        wp_die('', 403);
    }

    $item = iss_content_editorial_sets_service()->get_item($item_id);
    $path = $item ? iss_content_editorial_sets_resolve_external_upload_path($item) : '';
    if ($path === '') {
        wp_die('', 404);
    }

    $filetype = wp_check_filetype($path);
    $mime = (string) ($filetype['type'] ?? '');
    if (strpos($mime, 'image/') !== 0) {
        wp_die('', 415);
    }

    nocache_headers();
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: inline; filename="' . basename($path) . '"');
    readfile($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming private local intake file to authenticated admin.
    exit;
}
add_action('wp_ajax_iss_editorial_set_file_preview', 'iss_content_editorial_sets_stream_file_preview');

function iss_content_editorial_sets_add_context_metaboxes(): void
{
    foreach (iss_content_editorial_sets_supported_post_types() as $post_type) {
        add_meta_box(
            'iss-content-editorial-sets',
            __('Sets', 'iss-content-model'),
            'iss_content_editorial_sets_render_context_metabox',
            $post_type,
            'side',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'iss_content_editorial_sets_add_context_metaboxes', 40);

function iss_content_editorial_sets_render_context_metabox(WP_Post $post): void
{
    $service = iss_content_editorial_sets_service();
    $context_type = (string) $post->post_type;
    $links = $service->get_links_for_context($context_type, (int) $post->ID);
    $workbench_url = iss_content_editorial_sets_admin_url([
        'context_type' => $context_type,
        'context_id' => (int) $post->ID,
    ]);

    echo '<div class="iss-editorial-sets-context">';
    echo '<p>' . esc_html__('Private Arbeitssets fuer Rohmaterial, Review und Promotion.', 'iss-content-model') . '</p>';
    if (!$links) {
        echo '<p class="description">' . esc_html__('Noch keine Sets angehaengt.', 'iss-content-model') . '</p>';
    } else {
        echo '<ul>';
        foreach ($links as $link) {
            $url = iss_content_editorial_sets_admin_url([
                'context_type' => $context_type,
                'context_id' => (int) $post->ID,
                'set_id' => (int) ($link['set_id'] ?? 0),
            ]);
            echo '<li><a href="' . esc_url($url) . '">' . esc_html((string) ($link['title'] ?? '')) . '</a>';
            echo ' <span class="description">' . esc_html((string) ($link['link_role'] ?? '')) . ' &middot; ' . esc_html((string) ($link['item_count'] ?? '0')) . '</span></li>';
        }
        echo '</ul>';
    }
    echo '<p><a class="button" href="' . esc_url($workbench_url) . '">' . esc_html__('Workbench oeffnen', 'iss-content-model') . '</a></p>';
    echo '</div>';
}
