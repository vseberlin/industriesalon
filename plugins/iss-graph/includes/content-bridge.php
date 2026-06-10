<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_graph_get_content_relation_post_types(): array
{
    $post_types = (array) apply_filters('iss_graph_content_relation_post_types', [
        'video',
        'publication',
        'ausstellung',
        'fuehrung',
        'projekt',
        'veranstaltung',
        'post',
    ]);

    return array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'post_type_exists')));
}

function iss_graph_is_content_relation_post_type(string $post_type): bool
{
    return in_array(sanitize_key($post_type), iss_graph_get_content_relation_post_types(), true);
}

function iss_graph_add_content_relation_post_types(array $post_types): array
{
    $post_types = array_values(array_filter(array_map('sanitize_key', $post_types)));

    foreach (iss_graph_get_content_relation_post_types() as $post_type) {
        $post_types[] = $post_type;
    }

    return array_values(array_unique(array_filter($post_types)));
}
add_filter('iss_relations_candidate_post_types', 'iss_graph_add_content_relation_post_types');

function iss_graph_maybe_backfill_public_content_entities(): void
{
    $post_types = iss_graph_get_content_relation_post_types();
    if (!$post_types) {
        return;
    }

    $stored = (string) get_option(ISS_GRAPH_CONTENT_BACKFILL_OPTION, '');
    if ($stored === ISS_GRAPH_CONTENT_BACKFILL_VERSION) {
        return;
    }

    iss_graph_backfill_public_content_entities();
    update_option(ISS_GRAPH_CONTENT_BACKFILL_OPTION, ISS_GRAPH_CONTENT_BACKFILL_VERSION, false);
}

function iss_graph_backfill_public_content_entities(): int
{
    $post_types = iss_graph_get_content_relation_post_types();
    if (!$post_types) {
        return 0;
    }

    $post_ids = get_posts([
        'post_type' => $post_types,
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
        'suppress_filters' => true,
    ]);

    $count = 0;
    foreach ($post_ids as $post_id) {
        if (iss_graph_sync_public_content_entity((int) $post_id)) {
            $count++;
        }
    }

    return $count;
}

function iss_graph_get_content_organization_relation_options(): array
{
    return [
        'subject' => 'Thema / Institution',
        'organizer' => 'Veranstalter / Gastgeber',
        'publisher' => 'Herausgeber / Verlag',
        'partner' => 'Projektpartner',
        'related' => 'Weiterer Bezug',
    ];
}

function iss_graph_get_content_person_relation_options(): array
{
    return [
        'subject' => 'Thema / Person',
        'author' => 'Autor / Autorin',
        'participant' => 'Mitwirkende / Beteiligte',
        'interviewee' => 'Interviewpartner',
        'related' => 'Weiterer Bezug',
    ];
}

function iss_graph_get_content_relation_options(string $target_kind): array
{
    return $target_kind === 'organization'
        ? iss_graph_get_content_organization_relation_options()
        : iss_graph_get_content_person_relation_options();
}

function iss_graph_get_content_profile_choices(string $entity_kind): array
{
    if (!function_exists('iss_graph_get_profile_entity_choices_grouped')) {
        return [];
    }

    $entity_kind = sanitize_key($entity_kind);
    $grouped = iss_graph_get_profile_entity_choices_grouped();
    $choices = is_array($grouped[$entity_kind] ?? null) ? $grouped[$entity_kind] : [];

    return array_values(array_filter($choices, static function ($row): bool {
        return is_array($row)
            && (int) ($row['id'] ?? 0) > 0
            && (int) ($row['profile_post_id'] ?? 0) > 0;
    }));
}

function iss_graph_get_content_person_profile_choices(): array
{
    return iss_graph_get_content_profile_choices('person');
}

function iss_graph_get_content_organization_profile_choices(): array
{
    return iss_graph_get_content_profile_choices('organization');
}

function iss_graph_sync_public_content_entity(int $post_id): ?array
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || !iss_graph_is_content_relation_post_type((string) $post->post_type)) {
        return null;
    }

    $service = iss_graph_get_service();
    $entity_kind = sanitize_key((string) $post->post_type);
    $existing = $service->find_entity_by_post($entity_kind, (int) $post->ID);

    if (in_array($post->post_status, ['auto-draft', 'trash'], true)) {
        if ($existing) {
            $service->delete_entity_by_post($entity_kind, (int) $post->ID);
        }

        return null;
    }

    $entity = $service->upsert_entity([
        'entity_kind' => $entity_kind,
        'post_id' => (int) $post->ID,
        'source_system' => 'wp_post',
        'source_id' => (string) $post->ID,
        'canonical_slug' => $post->post_type . '-' . (int) $post->ID . '-' . (trim((string) $post->post_name) !== '' ? (string) $post->post_name : sanitize_title((string) get_the_title($post))),
        'display_title' => get_the_title($post),
        'summary' => (string) get_post_field('post_excerpt', $post->ID),
        'status' => sanitize_key((string) $post->post_status),
        'is_public' => $post->post_status === 'publish',
        'search_visibility' => 'hidden',
    ]);

    if (!$entity || empty($entity['id'])) {
        return null;
    }

    $service->replace_entity_names_for_source((int) $entity['id'], 'iss_graph_content_post_title', [[
        'name' => get_the_title($post) ?: ($post->post_type . ' ' . (int) $post->ID),
        'name_type' => 'primary',
        'is_primary' => true,
        'position' => 0,
    ]], 'post:' . (int) $post->ID);

    iss_graph_sync_wp_post_identifiers((int) $entity['id'], $post, 'wp_post');

    if (function_exists('iss_graph_sync_entity_alias_backfill')) {
        iss_graph_sync_entity_alias_backfill((int) $entity['id']);
    }

    return $service->get_entity_by_id((int) $entity['id']);
}

function iss_graph_render_public_content_relations_meta_box(WP_Post $post): void
{
    wp_nonce_field('iss_graph_save_content_relations', 'iss_graph_content_relations_nonce');

    $service = iss_graph_get_service();
    $entity = $service->find_entity_by_post(sanitize_key((string) $post->post_type), (int) $post->ID);
    $entity_id = (int) ($entity['id'] ?? 0);

    $people = $entity_id > 0 ? array_map('iss_graph_map_relation_row_for_editor', $service->get_relations_for_entity($entity_id, 'person', [
        'source_system' => 'content_admin',
    ])) : [];
    $organizations = $entity_id > 0 ? array_map('iss_graph_map_relation_row_for_editor', $service->get_relations_for_entity($entity_id, 'organization', [
        'source_system' => 'content_admin',
    ])) : [];

    $person_rows = wp_json_encode($people, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $organization_rows = wp_json_encode($organizations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $person_choices = wp_json_encode(iss_graph_get_content_person_profile_choices(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $organization_choices = wp_json_encode(iss_graph_get_content_organization_profile_choices(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!is_string($person_rows) || $person_rows === '') {
        $person_rows = '[]';
    }
    if (!is_string($organization_rows) || $organization_rows === '') {
        $organization_rows = '[]';
    }
    if (!is_string($person_choices) || $person_choices === '') {
        $person_choices = '[]';
    }
    if (!is_string($organization_choices) || $organization_choices === '') {
        $organization_choices = '[]';
    }

    echo '<p>' . esc_html__('Hier legst du fest, welche Personen und Organisationen zu diesem Beitrag gehoeren. Waehle vorhandene Profile, damit sie auf der Seite erscheinen koennen.', 'iss-graph') . '</p>';
    echo '<div class="iss-graph-editor iss-graph-editor--content-relations">';

    echo '<section class="iss-graph-editor__group" data-group="organizations" data-input-id="iss-graph-content-organization-rows" data-relation-types="' . esc_attr(wp_json_encode(iss_graph_get_content_organization_relation_options(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '" data-entity-choices="' . esc_attr($organization_choices) . '" data-row-title="Organisation">';
    echo '<div class="iss-graph-editor__tabs" aria-hidden="true"><span class="iss-graph-editor__tab is-active">' . esc_html__('Organisationen', 'iss-graph') . '</span></div>';
    echo '<div class="iss-graph-editor__header"><h4>' . esc_html__('Organisationen auswählen', 'iss-graph') . '</h4><p class="description">' . esc_html__('Institutionen, Partner, Veranstalter, Herausgeber oder andere Organisationen, die für diesen Beitrag wichtig sind.', 'iss-graph') . '</p></div>';
    echo '<div class="iss-graph-editor__rows"></div>';
    echo '<p><button type="button" class="button button-secondary iss-graph-editor__add">Organisation hinzufuegen</button></p>';
    echo '<textarea id="iss-graph-content-organization-rows" name="iss_graph_content_organization_rows" rows="6" style="display:none;">' . esc_textarea($organization_rows) . '</textarea>';
    echo '</section>';

    echo '<section class="iss-graph-editor__group" data-group="people" data-input-id="iss-graph-content-person-rows" data-relation-types="' . esc_attr(wp_json_encode(iss_graph_get_content_person_relation_options(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '" data-entity-choices="' . esc_attr($person_choices) . '" data-row-title="Person">';
    echo '<div class="iss-graph-editor__tabs" aria-hidden="true"><span class="iss-graph-editor__tab is-active">' . esc_html__('Personen', 'iss-graph') . '</span></div>';
    echo '<div class="iss-graph-editor__header"><h4>' . esc_html__('Personen auswählen', 'iss-graph') . '</h4><p class="description">' . esc_html__('Autor:innen, Mitwirkende, Interviewpartner oder andere Personen, die für diesen Beitrag wichtig sind.', 'iss-graph') . '</p></div>';
    echo '<div class="iss-graph-editor__rows"></div>';
    echo '<p><button type="button" class="button button-secondary iss-graph-editor__add">Person hinzufuegen</button></p>';
    echo '<textarea id="iss-graph-content-person-rows" name="iss_graph_content_person_rows" rows="6" style="display:none;">' . esc_textarea($person_rows) . '</textarea>';
    echo '</section>';

    echo '</div>';
}

function iss_graph_sanitize_content_relation_rows(array $rows, string $target_kind): array
{
    $service = iss_graph_get_service();
    $options = iss_graph_get_content_relation_options($target_kind);
    $allowed_types = array_fill_keys(array_keys($options), true);
    $sanitized = [];

    foreach (array_values($rows) as $index => $row) {
        if (!is_array($row)) {
            continue;
        }

        $relation_type = sanitize_key((string) ($row['relation_type'] ?? 'related'));
        if (!isset($allowed_types[$relation_type])) {
            $relation_type = 'related';
        }

        $entity_id = absint($row['entity_id'] ?? 0);
        $target_entity = $entity_id > 0 ? $service->get_entity_by_id($entity_id) : null;

        if (!$target_entity || (string) ($target_entity['entity_kind'] ?? '') !== $target_kind) {
            continue;
        }

        if (in_array($target_kind, ['organization', 'person'], true) && (int) ($target_entity['profile_post_id'] ?? 0) <= 0) {
            continue;
        }

        $sanitized[] = [
            'to_entity_id' => (int) ($target_entity['id'] ?? 0),
            'relation_type' => $relation_type,
            'relation_label' => (string) ($options[$relation_type] ?? $relation_type),
            'valid_from_year' => $service->normalize_year($row['valid_from_year'] ?? null),
            'valid_to_year' => $service->normalize_year($row['valid_to_year'] ?? null),
            'is_public' => !empty($row['is_public']),
            'position' => $index,
        ];
    }

    return $sanitized;
}

function iss_graph_save_public_content_relations(int $post_id, WP_Post $post): void
{
    if (!iss_graph_is_content_relation_post_type((string) $post->post_type)) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (
        !isset($_POST['iss_graph_content_relations_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['iss_graph_content_relations_nonce'])), 'iss_graph_save_content_relations')
    ) {
        return;
    }

    $service = iss_graph_get_service();
    $entity = $service->find_entity_by_post(sanitize_key((string) $post->post_type), $post_id);
    $person_rows = iss_graph_sanitize_content_relation_rows(iss_graph_decode_posted_rows('iss_graph_content_person_rows'), 'person');
    $organization_rows = iss_graph_sanitize_content_relation_rows(iss_graph_decode_posted_rows('iss_graph_content_organization_rows'), 'organization');

    if ((!$entity || empty($entity['id'])) && ($person_rows || $organization_rows)) {
        $entity = iss_graph_sync_public_content_entity($post_id);
    }

    $entity_id = (int) ($entity['id'] ?? 0);
    if ($entity_id > 0) {
        $source_ref = 'post:' . $post_id;
        $service->replace_entity_relations_for_source(
            $entity_id,
            'person',
            'content_admin',
            $person_rows,
            $source_ref
        );
        $service->replace_entity_relations_for_source(
            $entity_id,
            'organization',
            'content_admin',
            $organization_rows,
            $source_ref
        );
    }

    if (function_exists('iss_relations_sync_post_read_models')) {
        iss_relations_sync_post_read_models($post_id);
    } elseif (function_exists('iss_relations_sync_post_graph')) {
        iss_relations_sync_post_graph($post_id);
    }

    if (function_exists('iss_graph_sync_public_search_post')) {
        iss_graph_sync_public_search_post($post_id);
    }
}

function iss_graph_refresh_public_content_entity_on_save(int $post_id, WP_Post $post): void
{
    if (!iss_graph_is_content_relation_post_type((string) $post->post_type)) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    iss_graph_sync_public_content_entity($post_id);
}
add_action('save_post', 'iss_graph_refresh_public_content_entity_on_save', 35, 2);

function iss_graph_delete_public_content_entity_on_before_delete(int $post_id): void
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || !iss_graph_is_content_relation_post_type((string) $post->post_type)) {
        return;
    }

    iss_graph_get_service()->delete_entity_by_post(sanitize_key((string) $post->post_type), $post_id);
}
add_action('before_delete_post', 'iss_graph_delete_public_content_entity_on_before_delete', 5);

function iss_graph_get_content_entity_relations(int $post_id, string $target_kind, array $args = []): array
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || !iss_graph_is_content_relation_post_type((string) $post->post_type)) {
        return [];
    }

    $target_kind = sanitize_key($target_kind);
    if (!in_array($target_kind, ['organization', 'person'], true)) {
        return [];
    }

    $service = iss_graph_get_service();
    $entity = $service->find_entity_by_post(sanitize_key((string) $post->post_type), $post_id);
    if (!$entity) {
        $entity = iss_graph_sync_public_content_entity($post_id);
    }

    if (!$entity || empty($entity['id'])) {
        return [];
    }

    $limit = isset($args['limit']) ? max(1, min(50, (int) $args['limit'])) : 12;

    return $service->get_relations_for_entity((int) $entity['id'], $target_kind, [
        'public_only' => true,
        'limit' => $limit,
    ]);
}

function iss_graph_get_entity_relation_public_permalink(array $row): string
{
    $profile_post_id = (int) ($row['profile_post_id'] ?? 0);
    if ($profile_post_id > 0 && function_exists('iss_graph_get_public_profile_permalink')) {
        $profile_permalink = iss_graph_get_public_profile_permalink($profile_post_id);
        if ($profile_permalink !== '') {
            return $profile_permalink;
        }
    }

    $post_id = (int) ($row['post_id'] ?? 0);
    if ($post_id > 0 && get_post_status($post_id) === 'publish') {
        return (string) get_permalink($post_id);
    }

    return '';
}

function iss_graph_register_content_relation_blocks(): void
{
    if (!function_exists('register_block_type')) {
        return;
    }

    $editor_script = 'iss-graph-entity-relations-editor';
    $editor_asset = ISS_GRAPH_PATH . 'assets/js/entity-relations-block.js';
    if (file_exists($editor_asset)) {
        wp_register_script(
            $editor_script,
            ISS_GRAPH_URL . 'assets/js/entity-relations-block.js',
            ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-server-side-render'],
            (string) filemtime($editor_asset),
            true
        );
    }

    register_block_type('iss-graph/entity-relations', [
        'api_version' => 3,
        'title' => __('Entity Relations', 'iss-graph'),
        'category' => 'widgets',
        'icon' => 'groups',
        'description' => __('Renders direct people or organization relations for the current public content entry.', 'iss-graph'),
        'attributes' => [
            'entityKind' => [
                'type' => 'string',
                'default' => 'person',
            ],
            'kicker' => [
                'type' => 'string',
                'default' => '',
            ],
            'title' => [
                'type' => 'string',
                'default' => '',
            ],
            'intro' => [
                'type' => 'string',
                'default' => '',
            ],
            'limit' => [
                'type' => 'number',
                'default' => 8,
            ],
        ],
        'supports' => [
            'html' => false,
        ],
        'editor_script' => wp_script_is($editor_script, 'registered') ? $editor_script : null,
        'render_callback' => 'iss_graph_render_entity_relations_block',
    ]);
}
add_action('init', 'iss_graph_register_content_relation_blocks', 35);

function iss_graph_render_entity_relations_block(array $attributes = [], string $content = '', $block = null): string
{
    unset($content, $block);

    $post = get_post();
    if (!$post instanceof WP_Post) {
        return '';
    }

    $target_kind = sanitize_key((string) ($attributes['entityKind'] ?? 'person'));
    if (!in_array($target_kind, ['organization', 'person'], true)) {
        return '';
    }

    $rows = iss_graph_get_content_entity_relations((int) $post->ID, $target_kind, [
        'limit' => (int) ($attributes['limit'] ?? 8),
    ]);
    if (!$rows) {
        return '';
    }

    $default_title = $target_kind === 'organization'
        ? __('Organisationen im Zusammenhang', 'iss-graph')
        : __('Personen im Zusammenhang', 'iss-graph');
    $kicker = trim(sanitize_text_field((string) ($attributes['kicker'] ?? __('Akteure', 'iss-graph'))));
    $title = trim(sanitize_text_field((string) ($attributes['title'] ?? $default_title)));
    $intro = trim(sanitize_text_field((string) ($attributes['intro'] ?? '')));

    $out = '<section class="section section--plain iss-entity-relations iss-entity-relations--' . esc_attr($target_kind) . '">';
    $out .= '<div class="iss-container">';
    $out .= '<div class="iss-info-panel">';
    $out .= '<div class="wp-block-columns iss-info-panel__grid">';
    $out .= '<div class="wp-block-column iss-info-panel__title-col">';
    $out .= '<div class="iss-heading iss-heading--uncaged iss-info-panel__heading">';
    if ($kicker !== '') {
        $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html($kicker) . '</p>';
    }
    if ($title !== '') {
        $out .= '<h2 class="iss-heading__title iss-info-panel__title">' . esc_html($title) . '</h2>';
    }
    if ($intro !== '') {
        $out .= '<p class="iss-heading__text iss-info-panel__intro">' . esc_html($intro) . '</p>';
    }
    $out .= '</div>';
    $out .= '</div>';
    $out .= '<div class="wp-block-column iss-info-panel__rows-col">';
    $out .= '<div class="iss-info-panel__rows">';

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $link = iss_graph_get_entity_relation_public_permalink($row);
        $relation_label = trim((string) ($row['relation_label'] ?? ''));
        if ($relation_label === '') {
            $relation_options = iss_graph_get_content_relation_options($target_kind);
            $relation_type = sanitize_key((string) ($row['relation_type'] ?? 'related'));
            $relation_label = (string) ($relation_options[$relation_type] ?? __('Bezug', 'iss-graph'));
        }

        $years = iss_graph_format_fact_year_range(
            $row['valid_from_year'] ?? null,
            $row['valid_to_year'] ?? null,
            __('seit', 'iss-graph'),
            __('bis', 'iss-graph')
        );

        $name_html = $link !== ''
            ? '<a href="' . esc_url($link) . '">' . esc_html($name) . '</a>'
            : esc_html($name);

        if ($years !== '') {
            $name_html .= ' <span>(' . esc_html($years) . ')</span>';
        }

        $out .= '<div class="iss-info-row"><div class="iss-info-row__main"><p class="iss-info-row__text"><strong>' . esc_html($relation_label) . '</strong><br>' . $name_html . '</p></div></div>';
    }

    $out .= '</div>';
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</section>';

    return $out;
}

function iss_graph_add_public_content_relation_meta_boxes(): void
{
    foreach (iss_graph_get_content_relation_post_types() as $post_type) {
        add_meta_box(
            'iss-graph-public-content-relations',
            __('Personen und Organisationen', 'iss-graph'),
            'iss_graph_render_public_content_relations_meta_box',
            $post_type,
            'normal',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'iss_graph_add_public_content_relation_meta_boxes');

function iss_graph_admin_enqueue_content_relation_assets(string $hook): void
{
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || !iss_graph_is_content_relation_post_type((string) $screen->post_type)) {
        return;
    }

    wp_enqueue_script(
        'iss-graph-content-relations-admin',
        ISS_GRAPH_URL . 'assets/js/register-place-graph-admin.js',
        [],
        ISS_GRAPH_VERSION,
        true
    );
    wp_enqueue_style(
        'iss-graph-content-relations-admin',
        ISS_GRAPH_URL . 'assets/css/register-place-graph-admin.css',
        [],
        ISS_GRAPH_VERSION
    );
}
add_action('admin_enqueue_scripts', 'iss_graph_admin_enqueue_content_relation_assets');
add_action('save_post', 'iss_graph_save_public_content_relations', 52, 2);
