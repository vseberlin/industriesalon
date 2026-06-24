<?php
/**
 * Plugin Name: ISS Core
 * Description: Shared infrastructure conventions for first-party Industriesalon plugins.
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ISS_CORE_VERSION', '0.1.0');
define('ISS_CORE_FILE', __FILE__);
define('ISS_CORE_PATH', plugin_dir_path(__FILE__));
define('ISS_CORE_CAPS_VERSION', '2026-06-24-operations-caps-1');
define('ISS_CORE_CAPS_OPTION', 'iss_caps_version');
define('ISS_CORE_OPERATIONS_MENU_SLUG', 'iss-operations');

function iss_core_default_capability_definitions(): array
{
    return [
        'iss_access_operations' => ['label' => 'Access Operations menu', 'owner' => 'iss-core'],
        'iss_manage_steuerung' => ['label' => 'Manage central site controls', 'owner' => 'industriesalon-steuerung'],
        'iss_manage_hinweise' => ['label' => 'Manage notices', 'owner' => 'industriesalon-notices'],
        'iss_manage_register' => ['label' => 'Manage register places and tools', 'owner' => 'industriesalon-schoeneweide-register'],
        'iss_manage_archive' => ['label' => 'Manage archive curation surfaces', 'owner' => 'iss-archive'],
        'iss_import_archive' => ['label' => 'Run archive import jobs', 'owner' => 'iss-archive'],
        'iss_create_sets' => ['label' => 'Create Sets and attach incoming items', 'owner' => 'iss-content'],
        'iss_edit_sets' => ['label' => 'Organize Sets and edit metadata', 'owner' => 'iss-content'],
        'iss_review_sets' => ['label' => 'Review, reject, restore, and annotate Set items', 'owner' => 'iss-content'],
        'iss_promote_media' => ['label' => 'Promote approved media into public refs', 'owner' => 'iss-content'],
        'iss_delete_sets' => ['label' => 'Delete Sets or Set items', 'owner' => 'iss-content'],
        'iss_cleanup_sets' => ['label' => 'Run Set decay and quarantine cleanup', 'owner' => 'iss-content'],
        'iss_manage_rueckblicke' => ['label' => 'Manage Rueckblick content', 'owner' => 'iss-content'],
        'iss_manage_publications' => ['label' => 'Manage Publications', 'owner' => 'iss-publications'],
        'iss_manage_newsletter_content' => ['label' => 'Manage Newsletter content and drafts', 'owner' => 'iss-newsletter'],
        'iss_send_newsletter' => ['label' => 'Send or schedule public Newsletter dispatches', 'owner' => 'iss-newsletter'],
        'iss_sync_external_sources' => ['label' => 'Run external synchronization jobs', 'owner' => 'iss-core'],
        'iss_repair_data' => ['label' => 'Run repair and consistency tools', 'owner' => 'iss-core'],
        'iss_cleanup_data' => ['label' => 'Run destructive or semi-destructive cleanup tools', 'owner' => 'iss-core'],
        'iss_run_diagnostics' => ['label' => 'Run read-only diagnostics', 'owner' => 'iss-core'],
        'iss_manage_requests' => ['label' => 'Manage operational requests', 'owner' => 'iss-commerce-lite'],

        'edit_rueckblick' => ['label' => 'Edit one Rueckblick', 'owner' => 'iss-content'],
        'read_rueckblick' => ['label' => 'Read one Rueckblick', 'owner' => 'iss-content'],
        'delete_rueckblick' => ['label' => 'Delete one Rueckblick', 'owner' => 'iss-content'],
        'edit_rueckblicke' => ['label' => 'Edit Rueckblicke', 'owner' => 'iss-content'],
        'edit_others_rueckblicke' => ['label' => 'Edit other Rueckblicke', 'owner' => 'iss-content'],
        'publish_rueckblicke' => ['label' => 'Publish Rueckblicke', 'owner' => 'iss-content'],
        'read_private_rueckblicke' => ['label' => 'Read private Rueckblicke', 'owner' => 'iss-content'],
        'delete_rueckblicke' => ['label' => 'Delete Rueckblicke', 'owner' => 'iss-content'],
        'delete_private_rueckblicke' => ['label' => 'Delete private Rueckblicke', 'owner' => 'iss-content'],
        'delete_published_rueckblicke' => ['label' => 'Delete published Rueckblicke', 'owner' => 'iss-content'],
        'delete_others_rueckblicke' => ['label' => 'Delete other Rueckblicke', 'owner' => 'iss-content'],
        'edit_private_rueckblicke' => ['label' => 'Edit private Rueckblicke', 'owner' => 'iss-content'],
        'edit_published_rueckblicke' => ['label' => 'Edit published Rueckblicke', 'owner' => 'iss-content'],
        'create_rueckblicke' => ['label' => 'Create Rueckblicke', 'owner' => 'iss-content'],

        'edit_publication' => ['label' => 'Edit one Publication', 'owner' => 'iss-publications'],
        'read_publication' => ['label' => 'Read one Publication', 'owner' => 'iss-publications'],
        'delete_publication' => ['label' => 'Delete one Publication', 'owner' => 'iss-publications'],
        'edit_publications' => ['label' => 'Edit Publications', 'owner' => 'iss-publications'],
        'edit_others_publications' => ['label' => 'Edit other Publications', 'owner' => 'iss-publications'],
        'publish_publications' => ['label' => 'Publish Publications', 'owner' => 'iss-publications'],
        'read_private_publications' => ['label' => 'Read private Publications', 'owner' => 'iss-publications'],
        'delete_publications' => ['label' => 'Delete Publications', 'owner' => 'iss-publications'],
        'delete_private_publications' => ['label' => 'Delete private Publications', 'owner' => 'iss-publications'],
        'delete_published_publications' => ['label' => 'Delete published Publications', 'owner' => 'iss-publications'],
        'delete_others_publications' => ['label' => 'Delete other Publications', 'owner' => 'iss-publications'],
        'edit_private_publications' => ['label' => 'Edit private Publications', 'owner' => 'iss-publications'],
        'edit_published_publications' => ['label' => 'Edit published Publications', 'owner' => 'iss-publications'],
        'create_publications' => ['label' => 'Create Publications', 'owner' => 'iss-publications'],

        'edit_register_place' => ['label' => 'Edit one Register place', 'owner' => 'industriesalon-schoeneweide-register'],
        'read_register_place' => ['label' => 'Read one Register place', 'owner' => 'industriesalon-schoeneweide-register'],
        'delete_register_place' => ['label' => 'Delete one Register place', 'owner' => 'industriesalon-schoeneweide-register'],
        'edit_register_places' => ['label' => 'Edit Register places', 'owner' => 'industriesalon-schoeneweide-register'],
        'edit_others_register_places' => ['label' => 'Edit other Register places', 'owner' => 'industriesalon-schoeneweide-register'],
        'publish_register_places' => ['label' => 'Publish Register places', 'owner' => 'industriesalon-schoeneweide-register'],
        'read_private_register_places' => ['label' => 'Read private Register places', 'owner' => 'industriesalon-schoeneweide-register'],
        'delete_register_places' => ['label' => 'Delete Register places', 'owner' => 'industriesalon-schoeneweide-register'],
        'delete_private_register_places' => ['label' => 'Delete private Register places', 'owner' => 'industriesalon-schoeneweide-register'],
        'delete_published_register_places' => ['label' => 'Delete published Register places', 'owner' => 'industriesalon-schoeneweide-register'],
        'delete_others_register_places' => ['label' => 'Delete other Register places', 'owner' => 'industriesalon-schoeneweide-register'],
        'edit_private_register_places' => ['label' => 'Edit private Register places', 'owner' => 'industriesalon-schoeneweide-register'],
        'edit_published_register_places' => ['label' => 'Edit published Register places', 'owner' => 'industriesalon-schoeneweide-register'],
        'create_register_places' => ['label' => 'Create Register places', 'owner' => 'industriesalon-schoeneweide-register'],

        'edit_iss_notice' => ['label' => 'Edit one notice', 'owner' => 'industriesalon-notices'],
        'read_iss_notice' => ['label' => 'Read one notice', 'owner' => 'industriesalon-notices'],
        'delete_iss_notice' => ['label' => 'Delete one notice', 'owner' => 'industriesalon-notices'],
        'edit_iss_notices' => ['label' => 'Edit notices', 'owner' => 'industriesalon-notices'],
        'edit_others_iss_notices' => ['label' => 'Edit other notices', 'owner' => 'industriesalon-notices'],
        'publish_iss_notices' => ['label' => 'Publish notices', 'owner' => 'industriesalon-notices'],
        'read_private_iss_notices' => ['label' => 'Read private notices', 'owner' => 'industriesalon-notices'],
        'delete_iss_notices' => ['label' => 'Delete notices', 'owner' => 'industriesalon-notices'],
        'delete_private_iss_notices' => ['label' => 'Delete private notices', 'owner' => 'industriesalon-notices'],
        'delete_published_iss_notices' => ['label' => 'Delete published notices', 'owner' => 'industriesalon-notices'],
        'delete_others_iss_notices' => ['label' => 'Delete other notices', 'owner' => 'industriesalon-notices'],
        'edit_private_iss_notices' => ['label' => 'Edit private notices', 'owner' => 'industriesalon-notices'],
        'edit_published_iss_notices' => ['label' => 'Edit published notices', 'owner' => 'industriesalon-notices'],
        'create_iss_notices' => ['label' => 'Create notices', 'owner' => 'industriesalon-notices'],

        'edit_archive_item' => ['label' => 'Edit one archive item', 'owner' => 'iss-archive'],
        'read_archive_item' => ['label' => 'Read one archive item', 'owner' => 'iss-archive'],
        'delete_archive_item' => ['label' => 'Delete one archive item', 'owner' => 'iss-archive'],
        'edit_archive_items' => ['label' => 'Edit archive items', 'owner' => 'iss-archive'],
        'edit_others_archive_items' => ['label' => 'Edit other archive items', 'owner' => 'iss-archive'],
        'publish_archive_items' => ['label' => 'Publish archive items', 'owner' => 'iss-archive'],
        'read_private_archive_items' => ['label' => 'Read private archive items', 'owner' => 'iss-archive'],
        'delete_archive_items' => ['label' => 'Delete archive items', 'owner' => 'iss-archive'],
        'delete_private_archive_items' => ['label' => 'Delete private archive items', 'owner' => 'iss-archive'],
        'delete_published_archive_items' => ['label' => 'Delete published archive items', 'owner' => 'iss-archive'],
        'delete_others_archive_items' => ['label' => 'Delete other archive items', 'owner' => 'iss-archive'],
        'edit_private_archive_items' => ['label' => 'Edit private archive items', 'owner' => 'iss-archive'],
        'edit_published_archive_items' => ['label' => 'Edit published archive items', 'owner' => 'iss-archive'],
        'create_archive_items' => ['label' => 'Create archive items', 'owner' => 'iss-archive'],
    ];
}

function iss_core_declared_capabilities(): array
{
    $capabilities = apply_filters('iss_register_caps', iss_core_default_capability_definitions());
    if (!is_array($capabilities)) {
        $capabilities = [];
    }

    $normalized = [];
    foreach ($capabilities as $capability => $definition) {
        $capability = sanitize_key((string) $capability);
        if ($capability === '') {
            continue;
        }
        $definition = is_array($definition) ? $definition : [];
        $normalized[$capability] = array_merge([
            'label' => $capability,
            'owner' => 'unknown',
        ], $definition);
    }

    ksort($normalized);
    return $normalized;
}

function iss_core_all_capability_slugs(): array
{
    return array_keys(iss_core_declared_capabilities());
}

function iss_core_post_type_capabilities(string $singular, string $plural, string $create_capability = ''): array
{
    $singular = sanitize_key($singular);
    $plural = sanitize_key($plural);
    $create_capability = sanitize_key($create_capability);
    if ($create_capability === '') {
        $create_capability = 'create_' . $plural;
    }

    return [
        'edit_post' => 'edit_' . $singular,
        'read_post' => 'read_' . $singular,
        'delete_post' => 'delete_' . $singular,
        'edit_posts' => 'edit_' . $plural,
        'edit_others_posts' => 'edit_others_' . $plural,
        'publish_posts' => 'publish_' . $plural,
        'read_private_posts' => 'read_private_' . $plural,
        'delete_posts' => 'delete_' . $plural,
        'delete_private_posts' => 'delete_private_' . $plural,
        'delete_published_posts' => 'delete_published_' . $plural,
        'delete_others_posts' => 'delete_others_' . $plural,
        'edit_private_posts' => 'edit_private_' . $plural,
        'edit_published_posts' => 'edit_published_' . $plural,
        'create_posts' => $create_capability,
    ];
}

function iss_core_operation_capability_map(): array
{
    return [
        'manage' => 'iss_access_operations',
        'edit_content' => 'edit_posts',
        'sync' => 'iss_sync_external_sources',
        'debug' => 'iss_run_diagnostics',
        'repair' => 'iss_repair_data',
        'cleanup' => 'iss_cleanup_data',
        'requests' => 'iss_manage_requests',
        'steuerung' => 'iss_manage_steuerung',
        'notices' => 'iss_manage_hinweise',
        'register' => 'iss_manage_register',
        'archive' => 'iss_manage_archive',
        'archive_import' => 'iss_import_archive',
        'sets_create' => 'iss_create_sets',
        'sets_edit' => 'iss_edit_sets',
        'sets_review' => 'iss_review_sets',
        'sets_promote' => 'iss_promote_media',
        'sets_delete' => 'iss_delete_sets',
        'sets_cleanup' => 'iss_cleanup_sets',
        'rueckblicke' => 'iss_manage_rueckblicke',
        'publications' => 'iss_manage_publications',
        'newsletter_content' => 'iss_manage_newsletter_content',
        'newsletter_send' => 'iss_send_newsletter',
    ];
}

function iss_core_capability(string $area = 'manage'): string
{
    $area = sanitize_key($area);
    $capabilities = iss_core_operation_capability_map();

    return $capabilities[$area] ?? 'iss_access_operations';
}

function iss_require_cap(string $capability, $object_id = null): void
{
    $capability = sanitize_key($capability);
    $allowed = $object_id === null
        ? current_user_can($capability)
        : current_user_can($capability, $object_id);

    if ($allowed) {
        return;
    }

    iss_core_audit_log('capability_denied', [
        'capability' => $capability,
        'object_ids' => $object_id === null ? [] : [(int) $object_id],
        'result' => 'denied',
    ]);

    wp_die(esc_html__('Keine Berechtigung.', 'iss-core'), 403);
}

function iss_cap_check(string $capability, $object_id = null): callable
{
    return static function () use ($capability, $object_id): bool {
        $capability = sanitize_key($capability);

        return $object_id === null
            ? current_user_can($capability)
            : current_user_can($capability, $object_id);
    };
}

function iss_core_audit_log(string $action, array $context = []): void
{
    $action = sanitize_key($action);
    if ($action === '') {
        $action = 'unknown';
    }

    $upload = wp_upload_dir(null, false);
    $base_dir = is_array($upload) && empty($upload['error']) ? (string) ($upload['basedir'] ?? '') : '';
    if ($base_dir === '') {
        iss_core_debug_log('Audit log unavailable', ['action' => $action]);
        return;
    }

    $dir = trailingslashit($base_dir) . 'iss-audit';
    if (!wp_mkdir_p($dir)) {
        iss_core_debug_log('Audit log directory unavailable', ['dir' => $dir, 'action' => $action]);
        return;
    }

    $file = trailingslashit($dir) . 'operations.log';
    if (is_file($file) && filesize($file) !== false && filesize($file) > 5242880) {
        @rename($file, trailingslashit($dir) . 'operations-' . gmdate('Ymd-His') . '.log'); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- Best-effort local audit rotation.
    }

    $entry = [
        'timestamp' => gmdate('c'),
        'user_id' => get_current_user_id(),
        'capability' => isset($context['capability']) ? sanitize_key((string) $context['capability']) : '',
        'action' => $action,
        'object_ids' => array_values(array_filter(array_map('intval', (array) ($context['object_ids'] ?? [])))),
        'job_id' => isset($context['job_id']) ? sanitize_text_field((string) $context['job_id']) : '',
        'result' => isset($context['result']) ? sanitize_key((string) $context['result']) : 'completed',
    ];

    $line = wp_json_encode(array_merge($entry, ['context' => $context]), JSON_UNESCAPED_SLASHES) . "\n";
    if (file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Structured local audit log.
        iss_core_debug_log('Audit log write failed', ['file' => $file, 'action' => $action]);
    }
}

function iss_core_operations_role_grants(): array
{
    $archive_item_caps = [
        'edit_archive_item',
        'read_archive_item',
        'delete_archive_item',
        'edit_archive_items',
        'edit_others_archive_items',
        'publish_archive_items',
        'read_private_archive_items',
        'delete_archive_items',
        'delete_private_archive_items',
        'delete_published_archive_items',
        'delete_others_archive_items',
        'edit_private_archive_items',
        'edit_published_archive_items',
        'create_archive_items',
    ];
    $publication_caps = [
        'edit_publication',
        'read_publication',
        'delete_publication',
        'edit_publications',
        'edit_others_publications',
        'publish_publications',
        'read_private_publications',
        'delete_publications',
        'delete_private_publications',
        'delete_published_publications',
        'delete_others_publications',
        'edit_private_publications',
        'edit_published_publications',
        'create_publications',
    ];
    $register_caps = [
        'edit_register_place',
        'read_register_place',
        'delete_register_place',
        'edit_register_places',
        'edit_others_register_places',
        'publish_register_places',
        'read_private_register_places',
        'delete_register_places',
        'delete_private_register_places',
        'delete_published_register_places',
        'delete_others_register_places',
        'edit_private_register_places',
        'edit_published_register_places',
        'create_register_places',
    ];
    $rueckblick_caps = [
        'edit_rueckblick',
        'read_rueckblick',
        'delete_rueckblick',
        'edit_rueckblicke',
        'edit_others_rueckblicke',
        'publish_rueckblicke',
        'read_private_rueckblicke',
        'delete_rueckblicke',
        'delete_private_rueckblicke',
        'delete_published_rueckblicke',
        'delete_others_rueckblicke',
        'edit_private_rueckblicke',
        'edit_published_rueckblicke',
        'create_rueckblicke',
    ];
    $notice_caps = [
        'edit_iss_notice',
        'read_iss_notice',
        'delete_iss_notice',
        'edit_iss_notices',
        'edit_others_iss_notices',
        'publish_iss_notices',
        'read_private_iss_notices',
        'delete_iss_notices',
        'delete_private_iss_notices',
        'delete_published_iss_notices',
        'delete_others_iss_notices',
        'edit_private_iss_notices',
        'edit_published_iss_notices',
        'create_iss_notices',
    ];

    $all_declared = iss_core_all_capability_slugs();
    $content_caps = array_merge($archive_item_caps, $publication_caps, $register_caps, $rueckblick_caps, $notice_caps);
    $set_caps = ['iss_create_sets', 'iss_edit_sets', 'iss_review_sets', 'iss_promote_media', 'iss_delete_sets', 'iss_cleanup_sets'];
    $technical_caps = ['iss_sync_external_sources', 'iss_repair_data', 'iss_cleanup_data', 'iss_run_diagnostics', 'iss_import_archive'];

    return [
        'administrator' => $all_declared,
        'editor' => array_merge([
            'read',
            'iss_access_operations',
            'iss_manage_steuerung',
            'iss_manage_hinweise',
            'iss_manage_register',
            'iss_manage_archive',
            'iss_manage_rueckblicke',
            'iss_manage_publications',
            'iss_manage_newsletter_content',
            'iss_manage_requests',
            'iss_create_sets',
            'iss_edit_sets',
            'iss_review_sets',
            'iss_promote_media',
        ], $content_caps),
        'iss_operations_manager' => array_merge([
            'read',
            'upload_files',
            'iss_access_operations',
            'iss_manage_steuerung',
            'iss_manage_hinweise',
            'iss_manage_register',
            'iss_manage_archive',
            'iss_import_archive',
            'iss_manage_rueckblicke',
            'iss_manage_publications',
            'iss_manage_newsletter_content',
            'iss_manage_requests',
        ], $set_caps, $content_caps, ['iss_sync_external_sources', 'iss_run_diagnostics']),
        'iss_curator_editor' => array_merge([
            'read',
            'upload_files',
            'iss_access_operations',
            'iss_manage_hinweise',
            'iss_manage_register',
            'iss_manage_archive',
            'iss_manage_rueckblicke',
            'iss_manage_publications',
            'iss_create_sets',
            'iss_edit_sets',
            'iss_review_sets',
        ], $content_caps),
        'iss_reviewer' => [
            'read',
            'iss_access_operations',
            'iss_review_sets',
            'iss_run_diagnostics',
        ],
        'iss_intake_helper' => [
            'read',
            'upload_files',
            'iss_access_operations',
            'iss_create_sets',
            'iss_edit_sets',
        ],
        'iss_technical_maintainer' => array_merge([
            'read',
            'iss_access_operations',
            'iss_manage_requests',
        ], $technical_caps),
    ];
}

function iss_core_project_role_labels(): array
{
    return [
        'iss_operations_manager' => 'ISS Operations Manager',
        'iss_curator_editor' => 'ISS Curator Editor',
        'iss_reviewer' => 'ISS Reviewer',
        'iss_intake_helper' => 'ISS Intake Helper',
        'iss_technical_maintainer' => 'ISS Technical Maintainer',
    ];
}

function iss_core_apply_capability_migration(bool $dry_run = false): array
{
    $declared = iss_core_declared_capabilities();
    $declared_lookup = array_fill_keys(array_keys($declared), true);
    $role_grants = iss_core_operations_role_grants();
    $role_labels = iss_core_project_role_labels();
    $changes = [];
    $unknown_grants = [];

    foreach ($role_grants as $role_name => $caps) {
        $role = get_role($role_name);
        if (!$role instanceof WP_Role && isset($role_labels[$role_name])) {
            if (!$dry_run) {
                add_role($role_name, $role_labels[$role_name], ['read' => true]);
            }
            $changes[] = ['role' => $role_name, 'change' => 'create_role'];
            $role = $dry_run ? null : get_role($role_name);
        }
        if (!$role instanceof WP_Role && !$dry_run) {
            continue;
        }

        foreach (array_values(array_unique(array_map('sanitize_key', $caps))) as $capability) {
            if ($capability === '' || in_array($capability, ['read', 'upload_files'], true)) {
                $known = true;
            } else {
                $known = isset($declared_lookup[$capability]);
            }
            if (!$known) {
                $unknown_grants[] = ['role' => $role_name, 'capability' => $capability];
                continue;
            }
            if ($dry_run) {
                if ($role instanceof WP_Role && $role->has_cap($capability)) {
                    continue;
                }
                $changes[] = ['role' => $role_name, 'capability' => $capability, 'change' => 'would_add_cap'];
                continue;
            }
            if ($role instanceof WP_Role && !$role->has_cap($capability)) {
                $role->add_cap($capability);
                $changes[] = ['role' => $role_name, 'capability' => $capability, 'change' => 'add_cap'];
            }
        }
    }

    if (!$dry_run) {
        update_option(ISS_CORE_CAPS_OPTION, ISS_CORE_CAPS_VERSION, false);
        iss_core_audit_log('capability_role_migration', [
            'capability' => 'iss_access_operations',
            'result' => 'completed',
            'changes' => $changes,
            'unknown_grants' => $unknown_grants,
            'version' => ISS_CORE_CAPS_VERSION,
        ]);
    }

    return [
        'version' => ISS_CORE_CAPS_VERSION,
        'dry_run' => $dry_run,
        'declared_count' => count($declared),
        'changes' => $changes,
        'unknown_grants' => $unknown_grants,
    ];
}

function iss_core_maybe_run_capability_migration(): void
{
    if ((string) get_option(ISS_CORE_CAPS_OPTION, '') === ISS_CORE_CAPS_VERSION) {
        return;
    }

    iss_core_apply_capability_migration(false);
}
add_action('plugins_loaded', 'iss_core_maybe_run_capability_migration', 99);

function iss_core_register_operations_menu(): void
{
    add_menu_page(
        __('Operations', 'iss-core'),
        __('Operations', 'iss-core'),
        iss_core_capability('manage'),
        ISS_CORE_OPERATIONS_MENU_SLUG,
        'iss_core_render_operations_page',
        'dashicons-admin-tools',
        27
    );
}
add_action('admin_menu', 'iss_core_register_operations_menu', 5);

function iss_core_render_operations_page(): void
{
    iss_require_cap(iss_core_capability('manage'));

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Operations', 'iss-core') . '</h1>';
    echo '<p>' . esc_html__('Zentrale Einstiegsseite fuer operative Admin-Oberflaechen.', 'iss-core') . '</p>';
    echo '</div>';
}

function iss_core_migrate_domain_plugin_basenames(): void
{
    $active = get_option('active_plugins', []);
    if (!is_array($active) || empty($active)) {
        return;
    }

    $current_lookup = array_fill_keys(array_map('strval', $active), true);
    $replacements = [
        'iss-content-model/iss-content-model.php' => 'iss-content/iss-content.php',
        'iss-payments-lite/iss-payments-lite.php' => 'iss-commerce-lite/iss-commerce-lite.php',
        'iss-wf-import/iss-wf-import.php' => 'iss-archive/iss-archive.php',
    ];
    $retired = [
        'iss-fuehrungen/iss-fuehrungen.php' => true,
        'iss-programm/iss-programm.php' => true,
        'saas-api/saas-api.php' => true,
    ];

    $next = [];
    $saw_retired_domain_plugin = false;
    foreach ($active as $plugin) {
        $plugin = (string) $plugin;
        if (isset($retired[$plugin])) {
            $saw_retired_domain_plugin = true;
            continue;
        }
        if (isset($replacements[$plugin])) {
            $plugin = $replacements[$plugin];
            $saw_retired_domain_plugin = true;
        }
        if ($plugin !== '' && !in_array($plugin, $next, true)) {
            $next[] = $plugin;
        }
    }

    if ($saw_retired_domain_plugin) {
        foreach (array_values($replacements) as $plugin) {
            if (!in_array($plugin, $next, true)) {
                $next[] = $plugin;
            }
        }
        if (!in_array('iss-frontend/iss-frontend.php', $next, true)) {
            $next[] = 'iss-frontend/iss-frontend.php';
        }
    }

    if (in_array('iss-content/iss-content.php', $next, true) && !in_array('iss-editorial/iss-editorial.php', $next, true)) {
        $plugin_root = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : trailingslashit(WP_CONTENT_DIR) . 'plugins';
        if (is_readable(trailingslashit($plugin_root) . 'iss-editorial/iss-editorial.php')) {
            $next[] = 'iss-editorial/iss-editorial.php';
        }
    }

    if ($next === $active) {
        return;
    }

    update_option('active_plugins', array_values($next));

    $plugin_root = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : trailingslashit(WP_CONTENT_DIR) . 'plugins';
    foreach ($next as $plugin) {
        if (isset($current_lookup[$plugin])) {
            continue;
        }
        $path = trailingslashit($plugin_root) . $plugin;
        if (is_readable($path)) {
            require_once $path;
        }
    }
}
iss_core_migrate_domain_plugin_basenames();

function iss_core_schema_option_name(string $plugin_slug): string
{
    $plugin_slug = sanitize_key($plugin_slug);
    return $plugin_slug !== '' ? 'iss_' . str_replace('-', '_', $plugin_slug) . '_schema_version' : '';
}

function iss_core_admin_group_label(string $group): string
{
    $group = sanitize_key($group);

    $labels = [
        'content' => __('Inhalte', 'iss-core'),
        'data' => __('Daten', 'iss-core'),
        'sync' => __('Synchronisierung', 'iss-core'),
        'debug' => __('Diagnose', 'iss-core'),
    ];

    return $labels[$group] ?? ucfirst($group);
}

function iss_core_debug_log(string $message, array $context = []): void
{
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }

    $line = '[iss-core] ' . $message;
    if (!empty($context)) {
        $line .= ' ' . wp_json_encode($context);
    }

    error_log($line); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}

function iss_core_drift_result(string $status, string $message, array $data = []): array
{
    $status = sanitize_key($status);
    if (!in_array($status, ['ok', 'warning', 'error'], true)) {
        $status = 'warning';
    }

    return [
        'status' => $status,
        'message' => $message,
        'data' => $data,
    ];
}

function iss_core_capability_diagnostic_report(): array
{
    $declared = iss_core_declared_capabilities();
    $role_grants = iss_core_operations_role_grants();
    $roles = wp_roles();
    $unknown_role_grants = [];
    $missing_role_caps = [];
    $db_project_caps = [];

    foreach ($role_grants as $role_name => $caps) {
        $role = get_role($role_name);
        if (!$role instanceof WP_Role) {
            $missing_role_caps[] = ['role' => $role_name, 'capability' => '*role_missing*'];
            continue;
        }

        foreach (array_values(array_unique(array_map('sanitize_key', $caps))) as $capability) {
            if ($capability === '' || in_array($capability, ['read', 'upload_files'], true)) {
                continue;
            }
            if (!isset($declared[$capability])) {
                $unknown_role_grants[] = ['role' => $role_name, 'capability' => $capability];
                continue;
            }
            if (!$role->has_cap($capability)) {
                $missing_role_caps[] = ['role' => $role_name, 'capability' => $capability];
            }
        }
    }

    foreach ((array) $roles->roles as $role_name => $role_data) {
        $caps = isset($role_data['capabilities']) && is_array($role_data['capabilities']) ? $role_data['capabilities'] : [];
        foreach ($caps as $capability => $enabled) {
            $capability = sanitize_key((string) $capability);
            if (!$enabled || strpos($capability, 'iss_') !== 0) {
                continue;
            }
            $db_project_caps[] = [
                'role' => (string) $role_name,
                'capability' => $capability,
                'declared' => isset($declared[$capability]),
            ];
        }
    }

    return [
        'version_option' => (string) get_option(ISS_CORE_CAPS_OPTION, ''),
        'target_version' => ISS_CORE_CAPS_VERSION,
        'declared_capabilities' => $declared,
        'unknown_role_grants' => $unknown_role_grants,
        'missing_role_caps' => $missing_role_caps,
        'db_project_caps' => $db_project_caps,
    ];
}

if (defined('WP_CLI') && WP_CLI) {
    final class ISS_Core_Capabilities_CLI_Command
    {
        /**
         * Show declared project capabilities and current DB role state.
         */
        public function report(array $args, array $assoc_args): void
        {
            unset($args);
            $format = isset($assoc_args['format']) ? sanitize_key((string) $assoc_args['format']) : 'table';
            $report = iss_core_capability_diagnostic_report();

            if ($format === 'json') {
                \WP_CLI::log((string) wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return;
            }

            \WP_CLI::log('Capability version: ' . $report['version_option'] . ' target=' . $report['target_version']);
            \WP_CLI::log('Declared capabilities: ' . count($report['declared_capabilities']));
            \WP_CLI::log('Unknown role grants: ' . count($report['unknown_role_grants']));
            \WP_CLI::log('Missing role caps: ' . count($report['missing_role_caps']));
            \WP_CLI::log('Project caps in DB: ' . count($report['db_project_caps']));

            if (!empty($report['missing_role_caps'])) {
                \WP_CLI::warning('Missing role capabilities detected. Run `wp iss-core caps migrate`.');
            }
            if (!empty($report['unknown_role_grants'])) {
                \WP_CLI::warning('Unknown role grants detected in code.');
            }
        }

        /**
         * Apply the version-gated project capability migration.
         */
        public function migrate(array $args, array $assoc_args): void
        {
            unset($args);
            $dry_run = !empty($assoc_args['dry-run']);
            $result = iss_core_apply_capability_migration($dry_run);
            \WP_CLI::log((string) wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            if ($dry_run) {
                \WP_CLI::success('Capability migration dry run complete.');
                return;
            }

            \WP_CLI::success('Capability migration complete.');
        }
    }

    \WP_CLI::add_command('iss-core caps', 'ISS_Core_Capabilities_CLI_Command');
}
