<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_graph_get_register_post_type(): string
{
    return defined('ISS_REGISTER_POST_TYPE') ? ISS_REGISTER_POST_TYPE : 'register_place';
}

function iss_graph_get_register_place_name_type_options(): array
{
    $options = function_exists('iss_graph_get_entity_name_type_options')
        ? iss_graph_get_entity_name_type_options()
        : [
            'alternative' => 'Alternativer Name',
            'alias' => 'Alias',
            'historical' => 'Historischer Name',
            'official' => 'Offizieller Name',
        ];

    return array_merge(
        array_intersect_key($options, array_flip(['historical', 'alternative', 'official', 'abbreviation', 'alias', 'source_label', 'transliteration', 'typo'])),
        ['short' => 'Kurzname']
    );
}

function iss_graph_get_register_place_organization_relation_options(): array
{
    return [
        'owner' => 'Eigentuemer',
        'operator' => 'Betreiber',
        'developer' => 'Entwickler',
        'tenant' => 'Nutzer',
        'related' => 'Weiterer Bezug',
    ];
}

function iss_graph_get_register_place_person_relation_options(): array
{
    return [
        'architect' => 'Architekt',
        'founder' => 'Gruender',
        'worker' => 'Arbeiter / Beschaeftigte',
        'resident' => 'Bewohner / Anwohner',
        'related' => 'Weiterer Bezug',
    ];
}

function iss_graph_get_register_meta_value(int $post_id, string $meta_key, string $default = ''): string
{
    $value = get_post_meta($post_id, $meta_key, true);
    if (!is_scalar($value)) {
        return $default;
    }

    $value = trim((string) $value);

    return $value !== '' ? $value : $default;
}

function iss_graph_maybe_backfill_register_places(): void
{
    if (!post_type_exists(iss_graph_get_register_post_type())) {
        return;
    }

    $stored = (string) get_option(ISS_GRAPH_REGISTER_BACKFILL_OPTION, '');
    if ($stored === ISS_GRAPH_REGISTER_BACKFILL_VERSION) {
        return;
    }

    iss_graph_backfill_register_places();
    update_option(ISS_GRAPH_REGISTER_BACKFILL_OPTION, ISS_GRAPH_REGISTER_BACKFILL_VERSION, false);
}

function iss_graph_backfill_register_places(): int
{
    $post_type = iss_graph_get_register_post_type();
    if (!post_type_exists($post_type)) {
        return 0;
    }

    $post_ids = get_posts([
        'post_type' => $post_type,
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
        'suppress_filters' => true,
    ]);

    $count = 0;
    foreach ($post_ids as $post_id) {
        if (iss_graph_sync_register_place_entity((int) $post_id)) {
            $count++;
        }
    }

    return $count;
}

function iss_graph_sync_register_place_entity(int $post_id): ?array
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== iss_graph_get_register_post_type()) {
        return null;
    }

    $service = iss_graph_get_service();
    $is_public = $post->post_status === 'publish' && iss_graph_get_register_meta_value($post_id, 'place_visibility', 'public') === 'public';
    $entity = $service->upsert_entity([
        'entity_kind' => 'place',
        'post_id' => $post_id,
        'source_system' => 'register_post',
        'source_id' => (string) $post_id,
        'canonical_slug' => (string) $post->post_name,
        'display_title' => get_the_title($post),
        'summary' => (string) get_post_field('post_excerpt', $post_id),
        'status' => sanitize_key((string) $post->post_status),
        'is_public' => $is_public,
        'search_visibility' => $is_public ? 'public' : 'hidden',
    ]);

    if (!$entity) {
        return null;
    }

    $service->replace_entity_names_for_source((int) $entity['id'], 'register_title', [[
        'name' => get_the_title($post) ?: ('Ort ' . $post_id),
        'name_type' => 'primary',
        'is_primary' => true,
        'position' => 0,
    ]], 'post:' . $post_id);

    $identifier_rows = [];
    $register_id = iss_graph_get_register_meta_value($post_id, 'register_id', '');
    if ($register_id !== '') {
        $identifier_rows[] = [
            'namespace' => 'register_id',
            'value' => $register_id,
            'label' => 'Register ID',
            'trust_level' => 'trusted_auto_link',
            'confidence' => 100,
            'is_primary' => true,
            'status' => 'accepted',
        ];
    }
    iss_graph_sync_wp_post_identifiers((int) $entity['id'], $post, 'register_post', $identifier_rows);

    if (function_exists('iss_graph_sync_entity_alias_backfill')) {
        iss_graph_sync_entity_alias_backfill((int) $entity['id']);
    }

    $organization_rows = [];
    foreach (['owner', 'operator', 'developer', 'tenant'] as $index => $relation_type) {
        $name = iss_graph_get_register_meta_value($post_id, $relation_type, '');
        if ($name === '') {
            continue;
        }

        $organization = iss_graph_resolve_or_create_named_entity('organization', $name, [
            'source_system' => 'manual_slug',
            'search_visibility' => 'hidden',
            'is_public' => false,
        ], [
            'source_ref' => 'post:' . $post_id,
        ]);

        if (!$organization) {
            continue;
        }

        $organization_rows[] = [
            'to_entity_id' => (int) $organization['id'],
            'relation_type' => $relation_type,
            'relation_label' => (string) (iss_graph_get_register_place_organization_relation_options()[$relation_type] ?? $relation_type),
            'position' => $index,
            'is_public' => true,
        ];
    }

    $service->replace_entity_relations_for_source(
        (int) $entity['id'],
        'organization',
        'register_meta',
        $organization_rows,
        'post:' . $post_id
    );

    return $entity;
}

function iss_graph_enrich_register_place_entity(array $place, WP_Post $post): array
{
    $place['graph_entity_id'] = 0;
    $place['names'] = [];
    $place['organizations'] = [];
    $place['people'] = [];

    $entity = iss_graph_get_service()->find_entity_by_post('place', (int) $post->ID);
    if (!$entity) {
        $entity = iss_graph_sync_register_place_entity((int) $post->ID);
    }

    if (!$entity) {
        return $place;
    }

    $entity_id = (int) ($entity['id'] ?? 0);
    if ($entity_id <= 0) {
        return $place;
    }

    $service = iss_graph_get_service();
    $place['graph_entity_id'] = $entity_id;
    $place['names'] = $service->get_names_for_entity($entity_id);
    $place['organizations'] = array_map('iss_graph_map_relation_row_for_contract', $service->get_relations_for_entity($entity_id, 'organization', [
        'public_only' => true,
    ]));
    $place['people'] = array_map('iss_graph_map_relation_row_for_contract', $service->get_relations_for_entity($entity_id, 'person', [
        'public_only' => true,
    ]));

    return $place;
}

function iss_graph_map_name_row_for_editor(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'name' => (string) ($row['name'] ?? ''),
        'name_type' => (string) ($row['name_type'] ?? ''),
        'valid_from_year' => $row['valid_from_year'] ?? null,
        'valid_to_year' => $row['valid_to_year'] ?? null,
        'position' => (int) ($row['position'] ?? 0),
    ];
}

function iss_graph_map_relation_row_for_editor(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'name' => (string) ($row['name'] ?? ''),
        'relation_type' => (string) ($row['relation_type'] ?? ''),
        'valid_from_year' => $row['valid_from_year'] ?? null,
        'valid_to_year' => $row['valid_to_year'] ?? null,
        'is_public' => !empty($row['is_public']),
        'position' => (int) ($row['position'] ?? 0),
    ];
}

function iss_graph_get_public_profile_permalink(int $profile_post_id): string
{
    if ($profile_post_id <= 0) {
        return '';
    }

    $post = get_post($profile_post_id);
    if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
        return '';
    }

    return (string) get_permalink($post);
}

function iss_graph_map_relation_row_for_contract(array $row): array
{
    $profile_post_id = (int) ($row['profile_post_id'] ?? 0);

    return [
        'id' => (int) ($row['id'] ?? 0),
        'entity_id' => (int) ($row['entity_id'] ?? 0),
        'name' => (string) ($row['name'] ?? ''),
        'entity_kind' => (string) ($row['entity_kind'] ?? ''),
        'post_id' => (int) ($row['post_id'] ?? 0),
        'profile_post_id' => $profile_post_id,
        'slug' => (string) ($row['slug'] ?? ''),
        'permalink' => (string) ($row['permalink'] ?? ''),
        'profile_permalink' => iss_graph_get_public_profile_permalink($profile_post_id),
        'relation_family' => (string) ($row['relation_family'] ?? ''),
        'relation_type' => (string) ($row['relation_type'] ?? ''),
        'relation_role' => (string) ($row['relation_role'] ?? ''),
        'relation_label' => (string) ($row['relation_label'] ?? ''),
        'note' => (string) ($row['note'] ?? ''),
        'weight' => (int) ($row['weight'] ?? 0),
        'position' => (int) ($row['position'] ?? 0),
        'is_primary' => !empty($row['is_primary']),
        'valid_from_year' => isset($row['valid_from_year']) && $row['valid_from_year'] !== null ? (int) $row['valid_from_year'] : null,
        'valid_to_year' => isset($row['valid_to_year']) && $row['valid_to_year'] !== null ? (int) $row['valid_to_year'] : null,
        'is_public' => !empty($row['is_public']),
    ];
}

function iss_graph_render_register_place_meta_box(WP_Post $post): void
{
    wp_nonce_field('iss_graph_save_register_place_graph', 'iss_graph_register_place_graph_nonce');

    $service = iss_graph_get_service();
    $entity = $service->find_entity_by_post('place', (int) $post->ID);
    $entity_id = (int) ($entity['id'] ?? 0);

    $names = $entity_id > 0 ? array_map('iss_graph_map_name_row_for_editor', $service->get_names_for_entity($entity_id, [
        'source_system' => 'register_admin',
    ])) : [];
    $organizations = $entity_id > 0 ? array_map('iss_graph_map_relation_row_for_editor', $service->get_relations_for_entity($entity_id, 'organization', [
        'source_system' => 'register_admin',
    ])) : [];
    $people = $entity_id > 0 ? array_map('iss_graph_map_relation_row_for_editor', $service->get_relations_for_entity($entity_id, 'person', [
        'source_system' => 'register_admin',
    ])) : [];

    $name_rows = wp_json_encode($names, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $organization_rows = wp_json_encode($organizations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $person_rows = wp_json_encode($people, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!is_string($name_rows) || $name_rows === '') {
        $name_rows = '[]';
    }
    if (!is_string($organization_rows) || $organization_rows === '') {
        $organization_rows = '[]';
    }
    if (!is_string($person_rows) || $person_rows === '') {
        $person_rows = '[]';
    }

    echo '<p>Der Hauptname des Ortes wird automatisch aus dem Beitragstitel synchronisiert. Die Felder Eigentuemer, Betreiber, Entwickler und Nutzer bleiben bestehen und werden parallel in den Graph gespiegelt.</p>';
    echo '<p class="description">Hier gepflegte Zusatznamen, Personen und Organisationen werden in gemeinsamen Graph-Tabellen gespeichert und im Register-Detailvertrag verfuegbar gemacht.</p>';

    echo '<div class="iss-graph-editor"';
    echo ' data-name-types="' . esc_attr(wp_json_encode(iss_graph_get_register_place_name_type_options(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '"';
    echo ' data-organization-types="' . esc_attr(wp_json_encode(iss_graph_get_register_place_organization_relation_options(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '"';
    echo ' data-person-types="' . esc_attr(wp_json_encode(iss_graph_get_register_place_person_relation_options(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '">';

    echo '<section class="iss-graph-editor__group" data-group="names">';
    echo '<div class="iss-graph-editor__header"><h4>Weitere Namen</h4><p class="description">Historische oder alternative Ortsnamen.</p></div>';
    echo '<div class="iss-graph-editor__rows"></div>';
    echo '<p><button type="button" class="button button-secondary iss-graph-editor__add">Namen hinzufuegen</button></p>';
    echo '<textarea id="iss-graph-register-name-rows" name="iss_graph_register_name_rows" rows="6" style="display:none;">' . esc_textarea($name_rows) . '</textarea>';
    echo '</section>';

    echo '<section class="iss-graph-editor__group" data-group="organizations">';
    echo '<div class="iss-graph-editor__header"><h4>Organisationen</h4><p class="description">Zusaetzliche institutionelle Akteure und historische Bezuge.</p></div>';
    echo '<div class="iss-graph-editor__rows"></div>';
    echo '<p><button type="button" class="button button-secondary iss-graph-editor__add">Organisation hinzufuegen</button></p>';
    echo '<textarea id="iss-graph-register-organization-rows" name="iss_graph_register_organization_rows" rows="6" style="display:none;">' . esc_textarea($organization_rows) . '</textarea>';
    echo '</section>';

    echo '<section class="iss-graph-editor__group" data-group="people">';
    echo '<div class="iss-graph-editor__header"><h4>Personen</h4><p class="description">Relevante Personen mit direktem Ortsbezug.</p></div>';
    echo '<div class="iss-graph-editor__rows"></div>';
    echo '<p><button type="button" class="button button-secondary iss-graph-editor__add">Person hinzufuegen</button></p>';
    echo '<textarea id="iss-graph-register-person-rows" name="iss_graph_register_person_rows" rows="6" style="display:none;">' . esc_textarea($person_rows) . '</textarea>';
    echo '</section>';

    echo '</div>';
}

function iss_graph_decode_posted_rows(string $field_name): array
{
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Callers verify the owning metabox nonce before decoding graph editor rows.
    $raw = $_POST[$field_name] ?? '[]';
    if (!is_string($raw)) {
        return [];
    }

    $decoded = json_decode(wp_unslash($raw), true);

    return is_array($decoded) ? $decoded : [];
}

function iss_graph_sanitize_register_name_rows(array $rows): array
{
    if (function_exists('iss_graph_sanitize_entity_name_rows')) {
        return iss_graph_sanitize_entity_name_rows($rows, iss_graph_get_register_place_name_type_options());
    }

    return [];
}

function iss_graph_sanitize_register_relation_rows(array $rows, string $target_kind): array
{
    $service = iss_graph_get_service();
    $options = $target_kind === 'organization'
        ? iss_graph_get_register_place_organization_relation_options()
        : iss_graph_get_register_place_person_relation_options();
    $allowed_types = array_fill_keys(array_keys($options), true);
    $sanitized = [];

    foreach (array_values($rows) as $index => $row) {
        if (!is_array($row)) {
            continue;
        }

        $name = sanitize_text_field((string) ($row['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $relation_type = sanitize_key((string) ($row['relation_type'] ?? 'related'));
        if (!isset($allowed_types[$relation_type])) {
            $relation_type = 'related';
        }

        $target_entity = iss_graph_resolve_or_create_named_entity($target_kind, $name, [
            'source_system' => 'manual_slug',
            'search_visibility' => 'hidden',
            'is_public' => false,
        ], [
            'source_ref' => 'register_relation_editor',
        ]);

        if (!$target_entity) {
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

function iss_graph_save_register_place_graph(int $post_id, WP_Post $post): void
{
    if ($post->post_type !== iss_graph_get_register_post_type()) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $entity = iss_graph_sync_register_place_entity($post_id);
    $entity_id = (int) ($entity['id'] ?? 0);

    if (
        $entity_id > 0
        && isset($_POST['iss_graph_register_place_graph_nonce'])
        && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['iss_graph_register_place_graph_nonce'])), 'iss_graph_save_register_place_graph')
    ) {
        $service = iss_graph_get_service();
        $source_ref = 'post:' . $post_id;
        $service->replace_entity_names_for_source(
            $entity_id,
            'register_admin',
            iss_graph_sanitize_register_name_rows(iss_graph_decode_posted_rows('iss_graph_register_name_rows')),
            $source_ref
        );
        $service->replace_entity_relations_for_source(
            $entity_id,
            'organization',
            'register_admin',
            iss_graph_sanitize_register_relation_rows(iss_graph_decode_posted_rows('iss_graph_register_organization_rows'), 'organization'),
            $source_ref
        );
        $service->replace_entity_relations_for_source(
            $entity_id,
            'person',
            'register_admin',
            iss_graph_sanitize_register_relation_rows(iss_graph_decode_posted_rows('iss_graph_register_person_rows'), 'person'),
            $source_ref
        );
    }

    if (function_exists('iss_register_clear_places_cache')) {
        iss_register_clear_places_cache();
    }
}

function iss_graph_delete_register_place_graph(int $post_id): void
{
    if (get_post_type($post_id) !== iss_graph_get_register_post_type()) {
        return;
    }

    iss_graph_get_service()->delete_entity_by_post('place', $post_id);

    if (function_exists('iss_register_clear_places_cache')) {
        iss_register_clear_places_cache();
    }
}

function iss_graph_admin_enqueue_register_place_assets(string $hook): void
{
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== iss_graph_get_register_post_type()) {
        return;
    }

    wp_enqueue_script(
        'iss-graph-register-place-admin',
        ISS_GRAPH_URL . 'assets/js/register-place-graph-admin.js',
        [],
        ISS_GRAPH_VERSION,
        true
    );
    wp_enqueue_style(
        'iss-graph-register-place-admin',
        ISS_GRAPH_URL . 'assets/css/register-place-graph-admin.css',
        [],
        ISS_GRAPH_VERSION
    );
}

add_action('add_meta_boxes', function (): void {
    if (!post_type_exists(iss_graph_get_register_post_type())) {
        return;
    }

    add_meta_box(
        'iss-graph-register-place',
        __('Namen und Akteure', 'iss-graph'),
        'iss_graph_render_register_place_meta_box',
        iss_graph_get_register_post_type(),
        'normal',
        'default'
    );
});

add_action('save_post', 'iss_graph_save_register_place_graph', 50, 2);
add_action('before_delete_post', 'iss_graph_delete_register_place_graph', 5);
add_action('admin_enqueue_scripts', 'iss_graph_admin_enqueue_register_place_assets');
