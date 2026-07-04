<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_relations_get_station_object_choice(int $post_id): ?array
{
    if ($post_id <= 0) {
        return null;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== 'archivobjekt') {
        return null;
    }

    return [
        'id' => (int) $post->ID,
        'title' => get_the_title($post),
    ];
}

function iss_relations_get_station_story_post_types(): array
{
    $types = [
        'archivbeitrag',
        'post',
        'publication',
        'veranstaltung',
        'ausstellung',
        'projekt',
        'page',
    ];

    return array_values(array_filter($types, 'post_type_exists'));
}

function iss_relations_get_station_story_choice(int $post_id): ?array
{
    if ($post_id <= 0) {
        return null;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post || !in_array($post->post_type, iss_relations_get_station_story_post_types(), true)) {
        return null;
    }

    return [
        'id' => (int) $post->ID,
        'title' => get_the_title($post),
    ];
}

function iss_relations_get_station_object_choices_for_place(int $place_id): array
{
    if (
        $place_id <= 0
        || !post_type_exists('archivobjekt')
    ) {
        return [];
    }

    if (function_exists('iss_wf_import_get_relation_service')) {
        $post_ids = iss_wf_import_get_relation_service()->get_object_post_ids_for_local_place($place_id);
        if ($post_ids) {
            $posts = get_posts([
                'post_type' => 'archivobjekt',
                'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
                'suppress_filters' => true,
                'post__in' => array_map('intval', $post_ids),
            ]);

            $choices = [];
            foreach ($posts as $post) {
                if (!$post instanceof WP_Post) {
                    continue;
                }

                $choices[] = [
                    'id' => (int) $post->ID,
                    'title' => get_the_title($post),
                ];
            }

            if ($choices) {
                return $choices;
            }
        }
    }

    if (
        !defined('ISS_RELATIONS_TAXONOMY')
        || !taxonomy_exists(ISS_RELATIONS_TAXONOMY)
        || !function_exists('iss_relations_get_place_term_id')
    ) {
        return [];
    }

    $term_id = iss_relations_get_place_term_id($place_id);
    if ($term_id <= 0) {
        return [];
    }

    $posts = get_posts([
        'post_type' => 'archivobjekt',
        'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'suppress_filters' => true,
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Station object choices use the relation taxonomy index scoped to one selected place.
        'tax_query' => [
            [
                'taxonomy' => ISS_RELATIONS_TAXONOMY,
                'field' => 'term_id',
                'terms' => [$term_id],
            ],
        ],
    ]);

    $choices = [];

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $choices[] = [
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
        ];
    }

    return $choices;
}

function iss_relations_get_station_story_choices_for_place(int $place_id): array
{
    $post_types = iss_relations_get_station_story_post_types();

    if (
        $place_id <= 0
        || !$post_types
        || !defined('ISS_RELATIONS_TAXONOMY')
        || !taxonomy_exists(ISS_RELATIONS_TAXONOMY)
        || !function_exists('iss_relations_get_place_term_id')
    ) {
        return [];
    }

    $term_id = iss_relations_get_place_term_id($place_id);
    if ($term_id <= 0) {
        return [];
    }

    $posts = get_posts([
        'post_type' => $post_types,
        'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'suppress_filters' => true,
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Station story choices use the relation taxonomy index scoped to one selected place.
        'tax_query' => [
            [
                'taxonomy' => ISS_RELATIONS_TAXONOMY,
                'field' => 'term_id',
                'terms' => [$term_id],
            ],
        ],
    ]);

    $choices = [];

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $choices[] = [
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
        ];
    }

    return $choices;
}

function iss_relations_get_place_choices(): array
{
    static $choices = null;

    if (is_array($choices)) {
        return $choices;
    }

    $choices = [];
    $place_type = iss_relations_get_place_post_type();

    if (!post_type_exists($place_type)) {
        return $choices;
    }

    $posts = get_posts([
        'post_type' => $place_type,
        'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'suppress_filters' => true,
    ]);

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $choices[] = [
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
        ];
    }

    return $choices;
}

function iss_relations_get_route_station_editor_config(int $post_id): array
{
    $post_type = (string) get_post_type($post_id);
    if ($post_id <= 0 || !iss_relations_supports_route_fields($post_type)) {
        return [
            'enabled' => false,
        ];
    }

    $stored = get_post_meta($post_id, ISS_RELATIONS_META_KEY, true);
    $canonical = iss_relations_normalize_relations(is_array($stored) ? $stored : [], $post_id);
    $draft = function_exists('iss_relations_get_route_draft') ? iss_relations_get_route_draft($post_id) : [];

    return [
        'enabled' => true,
        'postId' => $post_id,
        'locked' => !$draft,
        'relations' => $draft ? (array) ($draft['relations'] ?? []) : $canonical,
        'canonical' => $canonical,
        'draft' => $draft,
        'trash' => $draft ? (array) ($draft['trash'] ?? []) : [],
        'previewArgs' => function_exists('iss_relations_get_route_draft_preview_args')
            ? iss_relations_get_route_draft_preview_args($post_id)
            : [],
        'places' => iss_relations_get_place_choices(),
        'restRoot' => esc_url_raw(rest_url('iss-relations/v1')),
        'nonce' => wp_create_nonce('wp_rest'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'choiceNonce' => wp_create_nonce('iss_relations_station_objects'),
        'strings' => [
            'heading' => __('Route / Stationen', 'iss-relations'),
            'description' => __('Die veröffentlichte Route ist gesperrt. Änderungen passieren zuerst im Routen-Entwurf.', 'iss-relations'),
            'lockedTitle' => __('Route veröffentlicht und gesperrt', 'iss-relations'),
            'lockedDescription' => __('Diese Stationen sind öffentlich wirksam und mit der Atlas-Karte synchronisiert. Zum Bearbeiten muss die Route entsperrt werden.', 'iss-relations'),
            'draftTitle' => __('Routen-Entwurf aktiv', 'iss-relations'),
            'draftDescription' => __('Stationen, Reihenfolge und gelöschte Stationen sind nur im Entwurf geändert. Die öffentliche Route bleibt unverändert, bis Sie die Route veröffentlichen und sperren.', 'iss-relations'),
            'unlock' => __('Route entsperren', 'iss-relations'),
            'saveDraft' => __('Entwurf speichern', 'iss-relations'),
            'publish' => __('Route veröffentlichen & sperren', 'iss-relations'),
            'discard' => __('Entwurf verwerfen', 'iss-relations'),
            'restoreStation' => __('Wiederherstellen', 'iss-relations'),
            'trashHeading' => __('Gelöschte Stationen im Entwurf', 'iss-relations'),
            'trashDescription' => __('Diese Stationen bleiben wiederherstellbar, solange der Entwurf nicht veröffentlicht wird.', 'iss-relations'),
            'empty' => __('Noch keine Stationen. Eine Station verbindet die Führung mit einem Ort.', 'iss-relations'),
            'addStation' => __('Station hinzufügen', 'iss-relations'),
            'placePlaceholder' => __('Ort wählen', 'iss-relations'),
            'changed' => __('Routen-Entwurf geändert.', 'iss-relations'),
            'saving' => __('Routen-Entwurf wird gespeichert ...', 'iss-relations'),
            'saved' => __('Routen-Entwurf gespeichert.', 'iss-relations'),
            'published' => __('Route veröffentlicht, gesperrt und mit der Karte synchronisiert.', 'iss-relations'),
            'discarded' => __('Routen-Entwurf verworfen.', 'iss-relations'),
            'unlocked' => __('Routen-Entwurf aktiv. Normales WordPress-Aktualisieren veröffentlicht diese Änderungen nicht.', 'iss-relations'),
            'saveError' => __('Route konnte nicht gespeichert werden.', 'iss-relations'),
            'publishConfirm' => __('Route veröffentlichen und gelöschte Stationen aus der öffentlichen Route entfernen?', 'iss-relations'),
            'sourceHeading' => __('Optionale Quellen', 'iss-relations'),
            'sourceDescription' => __('Objekt und Beitrag sind Quellen für spätere Ausgaben. Die aktuelle Route und Atlas-Karte nutzen diese Felder nicht.', 'iss-relations'),
            'objectPlaceholder' => __('Objekt wählen', 'iss-relations'),
            'objectLoading' => __('Objekte werden geladen ...', 'iss-relations'),
            'objectNone' => __('Keine verknüpften Objekte für diesen Ort', 'iss-relations'),
            'objectError' => __('Objekte konnten nicht geladen werden', 'iss-relations'),
            'storyPlaceholder' => __('Beitrag wählen', 'iss-relations'),
            'storyLoading' => __('Beiträge werden geladen ...', 'iss-relations'),
            'storyNone' => __('Keine verknüpften Beiträge für diesen Ort', 'iss-relations'),
            'storyError' => __('Beiträge konnten nicht geladen werden', 'iss-relations'),
        ],
    ];
}

function iss_relations_get_route_station_relation_field_keys(): array
{
    return [
        'place_id',
        'role',
        'weight',
        'label',
        'route_title',
        'route_teaser',
        'station_object_id',
        'station_story_id',
    ];
}

function iss_relations_render_route_station_hidden_fields(array $relations): void
{
    echo '<div class="iss-editorial-route-fields" hidden aria-hidden="true">';

    foreach (array_values($relations) as $index => $relation) {
        foreach (iss_relations_get_route_station_relation_field_keys() as $key) {
            $value = $relation[$key] ?? '';
            echo '<input type="hidden" name="iss_relations[' . esc_attr((string) $index) . '][' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '">';
        }
    }

    echo '</div>';
}

function iss_relations_add_meta_boxes(): void
{
    foreach (iss_relations_get_supported_post_types() as $post_type) {
        add_meta_box(
            'iss-relations-places',
            __('Verknüpfte Orte', 'iss-relations'),
            'iss_relations_render_meta_box',
            $post_type,
            'normal',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'iss_relations_add_meta_boxes');

function iss_relations_render_row(array $relation, array $places, int $index, string $post_type = ''): string
{
    $roles = iss_relations_get_role_options();
    $place_id = (int) ($relation['place_id'] ?? 0);
    $role = (string) ($relation['role'] ?? 'related');
    $weight = (int) ($relation['weight'] ?? 0);
    $label = (string) ($relation['label'] ?? '');
    $route_title = (string) ($relation['route_title'] ?? '');
    $route_teaser = (string) ($relation['route_teaser'] ?? '');
    $station_object_id = (int) ($relation['station_object_id'] ?? 0);
    $station_story_id = (int) ($relation['station_story_id'] ?? 0);
    $supports_route_fields = iss_relations_supports_route_fields($post_type);
    $station_object_choices = $supports_route_fields ? iss_relations_get_station_object_choices_for_place($place_id) : [];
    $selected_station_object = $supports_route_fields ? iss_relations_get_station_object_choice($station_object_id) : null;
    $station_story_choices = $supports_route_fields ? iss_relations_get_station_story_choices_for_place($place_id) : [];
    $selected_station_story = $supports_route_fields ? iss_relations_get_station_story_choice($station_story_id) : null;

    if (
        $supports_route_fields
        && $selected_station_object
        && !in_array($station_object_id, array_column($station_object_choices, 'id'), true)
    ) {
        array_unshift($station_object_choices, $selected_station_object);
    }

    if (
        $supports_route_fields
        && $selected_station_story
        && !in_array($station_story_id, array_column($station_story_choices, 'id'), true)
    ) {
        array_unshift($station_story_choices, $selected_station_story);
    }

    ob_start();
    ?>
    <tr data-iss-relations-row>
        <td>
            <select class="widefat" name="iss_relations[<?php echo esc_attr((string) $index); ?>][place_id]">
                <option value=""><?php esc_html_e('Ort wählen', 'iss-relations'); ?></option>
                <?php foreach ($places as $place) : ?>
                    <option value="<?php echo esc_attr((string) $place['id']); ?>" <?php selected($place_id, (int) $place['id']); ?>>
                        <?php echo esc_html((string) $place['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <select class="widefat" name="iss_relations[<?php echo esc_attr((string) $index); ?>][role]">
                <?php foreach ($roles as $value => $label_text) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($role, $value); ?>>
                        <?php echo esc_html($label_text); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input class="small-text" type="number" name="iss_relations[<?php echo esc_attr((string) $index); ?>][weight]" value="<?php echo esc_attr((string) $weight); ?>">
        </td>
        <td>
            <input class="widefat" type="text" name="iss_relations[<?php echo esc_attr((string) $index); ?>][label]" value="<?php echo esc_attr($label); ?>" placeholder="<?php esc_attr_e('Optionales Anzeige-Label', 'iss-relations'); ?>">
            <?php if ($supports_route_fields) : ?>
                <div style="margin-top:8px">
                    <input class="widefat" type="text" name="iss_relations[<?php echo esc_attr((string) $index); ?>][route_title]" value="<?php echo esc_attr($route_title); ?>" placeholder="<?php esc_attr_e('Stations-Titel im Routenblock', 'iss-relations'); ?>">
                </div>
                <div style="margin-top:8px">
                    <textarea class="widefat" rows="3" name="iss_relations[<?php echo esc_attr((string) $index); ?>][route_teaser]" placeholder="<?php esc_attr_e('Kurzer Stations-Teaser', 'iss-relations'); ?>"><?php echo esc_textarea($route_teaser); ?></textarea>
                </div>
                <div style="margin-top:8px; display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                    <select
                        class="widefat"
                        name="iss_relations[<?php echo esc_attr((string) $index); ?>][station_object_id]"
                        data-iss-relations-station-object
                        data-selected-object-id="<?php echo esc_attr($station_object_id > 0 ? (string) $station_object_id : ''); ?>"
                    >
                        <option value=""><?php esc_html_e('Objekt wählen', 'iss-relations'); ?></option>
                        <?php foreach ($station_object_choices as $object_choice) : ?>
                            <option value="<?php echo esc_attr((string) $object_choice['id']); ?>" <?php selected($station_object_id, (int) $object_choice['id']); ?>>
                                <?php echo esc_html((string) $object_choice['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select
                        class="widefat"
                        name="iss_relations[<?php echo esc_attr((string) $index); ?>][station_story_id]"
                        data-iss-relations-station-story
                        data-selected-story-id="<?php echo esc_attr($station_story_id > 0 ? (string) $station_story_id : ''); ?>"
                    >
                        <option value=""><?php esc_html_e('Beitrag wählen', 'iss-relations'); ?></option>
                        <?php foreach ($station_story_choices as $story_choice) : ?>
                            <option value="<?php echo esc_attr((string) $story_choice['id']); ?>" <?php selected($station_story_id, (int) $story_choice['id']); ?>>
                                <?php echo esc_html((string) $story_choice['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p class="description" style="margin-top:8px;">
                    <?php esc_html_e('Nach Ortswahl werden nur verknüpfte Objekte und Beiträge für diese Station angeboten.', 'iss-relations'); ?>
                </p>
                <p class="description">
                    <?php esc_html_e('Beim Speichern wird ausgewählter Inhalt bei Bedarf automatisch auch mit diesem Ort verknüpft.', 'iss-relations'); ?>
                </p>
            <?php endif; ?>
        </td>
        <td>
            <button type="button" class="button-link" data-iss-relations-move="up"><?php esc_html_e('Hoch', 'iss-relations'); ?></button>
            <span aria-hidden="true">|</span>
            <button type="button" class="button-link" data-iss-relations-move="down"><?php esc_html_e('Runter', 'iss-relations'); ?></button>
            <span aria-hidden="true">|</span>
            <button type="button" class="button-link-delete" data-iss-relations-remove><?php esc_html_e('Entfernen', 'iss-relations'); ?></button>
        </td>
    </tr>
    <?php

    return trim((string) ob_get_clean());
}

function iss_relations_render_meta_box(WP_Post $post): void
{
    wp_nonce_field('iss_relations_save_meta', 'iss_relations_meta_nonce');

    $places = iss_relations_get_place_choices();
    $relations = function_exists('iss_relations_get_stored_post_relations')
        ? iss_relations_get_stored_post_relations((int) $post->ID)
        : iss_relations_get_post_relations((int) $post->ID);
    $supports_route_fields = iss_relations_supports_route_fields($post->post_type);

    echo '<div data-iss-relations-box>';

    if (!$places) {
        echo '<p>' . esc_html__('Noch keine Orte verfügbar. Beziehungen werden aktiv, sobald register_place-Einträge vorhanden sind.', 'iss-relations') . '</p>';
        echo '</div>';
        return;
    }

    echo '<p>' . esc_html__('Verknüpft diesen Inhalt mit einem oder mehreren Orten. Die Auswahl bleibt in Meta gespeichert und wird zusätzlich in eine versteckte Query-Taxonomie gespiegelt.', 'iss-relations') . '</p>';
    if ($supports_route_fields) {
        echo '<p>' . esc_html__('Für Führungen bilden Einträge mit Rolle "Station" die Route. Gewicht = Reihenfolge. Titel und Teaser können pro Station für den Tour-Routenblock überschrieben werden.', 'iss-relations') . '</p>';
    }
    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Ort', 'iss-relations') . '</th>';
    echo '<th>' . esc_html__('Rolle', 'iss-relations') . '</th>';
    echo '<th>' . esc_html__('Gewicht', 'iss-relations') . '</th>';
    echo '<th>' . esc_html__('Label', 'iss-relations') . '</th>';
    echo '<th>' . esc_html__('Aktionen', 'iss-relations') . '</th>';
    echo '</tr></thead>';
    echo '<tbody data-iss-relations-rows>';

    if ($relations) {
        foreach ($relations as $index => $relation) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Row renderer escapes every field before returning markup.
            echo iss_relations_render_row($relation, $places, (int) $index, $post->post_type);
        }
    }

    echo '</tbody>';
    echo '</table>';

    echo '<p><button type="button" class="button" data-iss-relations-add>' . esc_html__('Ort hinzufügen', 'iss-relations') . '</button></p>';
    echo '<template data-iss-relations-template>'
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Row renderer escapes every field before returning markup.
        . iss_relations_render_row([
        'place_id' => 0,
        'role' => $supports_route_fields ? 'stop' : 'related',
        'weight' => 0,
        'label' => '',
        'route_title' => '',
        'route_teaser' => '',
        'station_object_id' => 0,
        'station_story_id' => 0,
    ], $places, 9999, $post->post_type) . '</template>';
    echo '</div>';
}

function iss_relations_save_meta_box(int $post_id, WP_Post $post): void
{
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (!iss_relations_is_supported_post_type($post->post_type)) {
        return;
    }

    $nonce = $_POST['iss_relations_meta_nonce'] ?? '';
    if (!is_string($nonce) || !wp_verify_nonce($nonce, 'iss_relations_save_meta')) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $raw_relations = $_POST['iss_relations'] ?? [];
    iss_relations_save_post_relations($post_id, is_array($raw_relations) ? wp_unslash($raw_relations) : []);
}
add_action('save_post', 'iss_relations_save_meta_box', 10, 2);

function iss_relations_enqueue_admin_assets(string $hook_suffix): void
{
    if (!in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || !iss_relations_is_supported_post_type((string) $screen->post_type)) {
        return;
    }

    $script_path = ISS_RELATIONS_PATH . 'assets/admin-relations.js';
    if (!file_exists($script_path)) {
        return;
    }

    wp_enqueue_script(
        'iss-relations-admin',
        ISS_RELATIONS_URL . 'assets/admin-relations.js',
        [],
        (string) filemtime($script_path),
        true
    );

    wp_localize_script('iss-relations-admin', 'issRelationsAdmin', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('iss_relations_station_objects'),
        'strings' => [
            'objectPlaceholder' => __('Objekt wählen', 'iss-relations'),
            'objectLoading' => __('Objekte werden geladen …', 'iss-relations'),
            'objectNone' => __('Keine verknüpften Objekte für diesen Ort', 'iss-relations'),
            'objectError' => __('Objekte konnten nicht geladen werden', 'iss-relations'),
            'storyPlaceholder' => __('Beitrag wählen', 'iss-relations'),
            'storyLoading' => __('Beiträge werden geladen …', 'iss-relations'),
            'storyNone' => __('Keine verknüpften Beiträge für diesen Ort', 'iss-relations'),
            'storyError' => __('Beiträge konnten nicht geladen werden', 'iss-relations'),
        ],
    ]);

    if (iss_relations_supports_route_fields((string) $screen->post_type)) {
        $route_script_path = ISS_RELATIONS_PATH . 'assets/route-station-editor.js';
        $route_style_path = ISS_RELATIONS_PATH . 'assets/route-station-editor.css';

        if (file_exists($route_style_path)) {
            wp_enqueue_style(
                'iss-relations-route-stations',
                ISS_RELATIONS_URL . 'assets/route-station-editor.css',
                [],
                (string) filemtime($route_style_path)
            );
        }

        if (file_exists($route_script_path)) {
            wp_register_script(
                'iss-relations-route-stations',
                ISS_RELATIONS_URL . 'assets/route-station-editor.js',
                [],
                (string) filemtime($route_script_path),
                true
            );
            wp_enqueue_script('iss-relations-route-stations');
        }
    }
}
add_action('admin_enqueue_scripts', 'iss_relations_enqueue_admin_assets');

function iss_relations_ajax_station_objects(): void
{
    check_ajax_referer('iss_relations_station_objects', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('Keine Berechtigung.', 'iss-relations')], 403);
    }

    $place_id = isset($_GET['place_id']) ? absint($_GET['place_id']) : 0;
    $selected_id = isset($_GET['selected_id']) ? absint($_GET['selected_id']) : 0;
    $choices = iss_relations_get_station_object_choices_for_place($place_id);

    if ($selected_id > 0) {
        $selected_choice = iss_relations_get_station_object_choice($selected_id);
        if (
            $selected_choice
            && !in_array($selected_id, array_column($choices, 'id'), true)
        ) {
            array_unshift($choices, $selected_choice);
        }
    }

    wp_send_json_success([
        'objects' => $choices,
    ]);
}
add_action('wp_ajax_iss_relations_station_objects', 'iss_relations_ajax_station_objects');

function iss_relations_ajax_station_stories(): void
{
    check_ajax_referer('iss_relations_station_objects', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('Keine Berechtigung.', 'iss-relations')], 403);
    }

    $place_id = isset($_GET['place_id']) ? absint($_GET['place_id']) : 0;
    $selected_id = isset($_GET['selected_id']) ? absint($_GET['selected_id']) : 0;
    $choices = iss_relations_get_station_story_choices_for_place($place_id);

    if ($selected_id > 0) {
        $selected_choice = iss_relations_get_station_story_choice($selected_id);
        if (
            $selected_choice
            && !in_array($selected_id, array_column($choices, 'id'), true)
        ) {
            array_unshift($choices, $selected_choice);
        }
    }

    wp_send_json_success([
        'stories' => $choices,
    ]);
}
add_action('wp_ajax_iss_relations_station_stories', 'iss_relations_ajax_station_stories');
