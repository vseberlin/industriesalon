<?php

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Admin screen operates on the plugin-owned request table.

function iss_payments_lite_admin_capability(): string
{
    return function_exists('iss_core_capability') ? iss_core_capability('requests') : 'manage_options';
}

function iss_payments_lite_status_labels(): array
{
    return [
        'new' => __('Neu', 'iss-payments-lite'),
        'in_progress' => __('In Bearbeitung', 'iss-payments-lite'),
        'confirmed' => __('Bestätigt', 'iss-payments-lite'),
        'completed' => __('Erledigt', 'iss-payments-lite'),
        'cancelled' => __('Storniert', 'iss-payments-lite'),
        'spam' => __('Spam', 'iss-payments-lite'),
    ];
}

function iss_payments_lite_kind_labels(): array
{
    return [
        'booking' => __('Buchung', 'iss-payments-lite'),
        'inquiry' => __('Terminanfrage', 'iss-payments-lite'),
        'order' => __('Bestellung', 'iss-payments-lite'),
    ];
}

function iss_payments_lite_admin_add_menu(): void
{
    add_submenu_page(
        defined('ISS_CORE_OPERATIONS_MENU_SLUG') ? ISS_CORE_OPERATIONS_MENU_SLUG : 'tools.php',
        __('ISS Anfragen', 'iss-payments-lite'),
        __('ISS Anfragen', 'iss-payments-lite'),
        iss_payments_lite_admin_capability(),
        'iss-payments-lite-requests',
        'iss_payments_lite_render_requests_page'
    );
}
add_action('admin_menu', 'iss_payments_lite_admin_add_menu');

function iss_payments_lite_admin_get_param(string $key): string
{
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin filters.
    return isset($_GET[$key]) ? sanitize_text_field(wp_unslash((string) $_GET[$key])) : '';
}

function iss_payments_lite_admin_current_filters(): array
{
    return [
        'status' => sanitize_key(iss_payments_lite_admin_get_param('request_status')),
        'kind' => sanitize_key(iss_payments_lite_admin_get_param('request_kind')),
        'search' => iss_payments_lite_admin_get_param('s'),
    ];
}

function iss_payments_lite_admin_build_where(array $filters, array &$values): string
{
    global $wpdb;

    $where = ['1=1'];
    $statuses = array_keys(iss_payments_lite_status_labels());
    $kinds = array_keys(iss_payments_lite_kind_labels());

    if (!empty($filters['status']) && in_array((string) $filters['status'], $statuses, true)) {
        $where[] = 'status = %s';
        $values[] = (string) $filters['status'];
    }

    if (!empty($filters['kind']) && in_array((string) $filters['kind'], $kinds, true)) {
        $where[] = 'request_kind = %s';
        $values[] = (string) $filters['kind'];
    }

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(customer_name LIKE %s OR customer_email LIKE %s OR item_label LIKE %s OR tag LIKE %s OR slot_id LIKE %s)';
        array_push($values, $like, $like, $like, $like, $like);
    }

    return implode(' AND ', $where);
}

function iss_payments_lite_admin_query_requests(array $filters, int $limit = 50, int $offset = 0): array
{
    global $wpdb;

    if (!iss_payments_lite_table_exists()) {
        return ['rows' => [], 'total' => 0];
    }

    $values = [];
    $where_sql = iss_payments_lite_admin_build_where($filters, $values);
    $table = iss_payments_lite_requests_table_name();

    $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic where is built from fixed fragments above; no prepare is needed when it contains no placeholders.
    $total = (int) $wpdb->get_var($values ? $wpdb->prepare($count_sql, $values) : $count_sql);

    $query_values = $values;
    $query_values[] = max(1, $limit);
    $query_values[] = max(0, $offset);
    $rows = $wpdb->get_results(
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared -- Placeholder count is built with the dynamic where fragment and values array above.
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
            $query_values
        ),
        ARRAY_A
    );

    return [
        'rows' => is_array($rows) ? $rows : [],
        'total' => $total,
    ];
}

function iss_payments_lite_admin_handle_actions(): void
{
    if (!is_admin()) {
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page routing check only.
    if (!isset($_GET['page']) || (string) wp_unslash($_GET['page']) !== 'iss-payments-lite-requests') {
        return;
    }

    if (function_exists('iss_require_cap')) {
        iss_require_cap(iss_payments_lite_admin_capability());
    } elseif (!current_user_can(iss_payments_lite_admin_capability())) {
        wp_die(esc_html__('Keine Berechtigung.', 'iss-payments-lite'), 403);
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Export has its own nonce below.
    if (!empty($_GET['iss_payments_lite_export'])) {
        check_admin_referer('iss_payments_lite_export');
        if (function_exists('iss_core_audit_log')) {
            iss_core_audit_log('requests_export', [
                'capability' => iss_payments_lite_admin_capability(),
                'result' => 'completed',
            ]);
        }
        iss_payments_lite_admin_export_csv();
    }

    if (empty($_POST['iss_payments_lite_action'])) {
        return;
    }

    $action = sanitize_key(wp_unslash((string) $_POST['iss_payments_lite_action']));
    if ($action === 'update_settings') {
        check_admin_referer('iss_payments_lite_settings');
        iss_payments_lite_update_settings([
            'notifications_enabled' => !empty($_POST['notifications_enabled']),
            'notification_email' => isset($_POST['notification_email']) ? sanitize_email(wp_unslash((string) $_POST['notification_email'])) : '',
            'require_nonce' => !empty($_POST['require_nonce']),
            'min_submit_seconds' => isset($_POST['min_submit_seconds']) ? (int) $_POST['min_submit_seconds'] : 3,
            'max_body_bytes' => isset($_POST['max_body_bytes']) ? (int) $_POST['max_body_bytes'] : 8192,
        ]);
        if (function_exists('iss_core_audit_log')) {
            iss_core_audit_log('requests_settings_update', [
                'capability' => iss_payments_lite_admin_capability(),
                'result' => 'completed',
            ]);
        }
        wp_safe_redirect(add_query_arg(['page' => 'iss-payments-lite-requests', 'updated' => 'settings'], admin_url(defined('ISS_CORE_OPERATIONS_MENU_SLUG') ? 'admin.php' : 'tools.php')));
        exit;
    }

    if ($action === 'update_status') {
        check_admin_referer('iss_payments_lite_status');
        iss_payments_lite_admin_update_status();
        wp_safe_redirect(add_query_arg(['page' => 'iss-payments-lite-requests', 'updated' => 'status'], admin_url(defined('ISS_CORE_OPERATIONS_MENU_SLUG') ? 'admin.php' : 'tools.php')));
        exit;
    }
}
add_action('admin_init', 'iss_payments_lite_admin_handle_actions');

function iss_payments_lite_admin_update_status(): void
{
    global $wpdb;

    if (!iss_payments_lite_table_exists()) {
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by iss_payments_lite_admin_handle_actions().
    $status = isset($_POST['request_new_status']) ? sanitize_key(wp_unslash((string) $_POST['request_new_status'])) : '';
    if (!isset(iss_payments_lite_status_labels()[$status])) {
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by iss_payments_lite_admin_handle_actions().
    $ids_raw = isset($_POST['request_ids']) && is_array($_POST['request_ids']) ? $_POST['request_ids'] : [];
    $ids = array_values(array_unique(array_filter(array_map('absint', $ids_raw))));
    if (empty($ids)) {
        return;
    }

    $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
    $table = iss_payments_lite_requests_table_name();
    $values = array_merge([$status, current_time('mysql')], $ids);
    $wpdb->query(
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared -- ID placeholders and values are built together above.
        $wpdb->prepare(
            "UPDATE {$table} SET status = %s, updated_at = %s WHERE id IN ({$placeholders})",
            $values
        )
    );

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('requests_status_update', [
            'capability' => iss_payments_lite_admin_capability(),
            'object_ids' => $ids,
            'result' => 'completed',
            'status' => $status,
        ]);
    }
}

function iss_payments_lite_admin_export_csv(): void
{
    $filters = iss_payments_lite_admin_current_filters();
    $result = iss_payments_lite_admin_query_requests($filters, 5000, 0);

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=iss-payments-lite-requests-' . gmdate('Ymd-His') . '.csv');

    $out = fopen('php://output', 'w'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV export stream.
    if (!$out) {
        exit;
    }

    fputcsv($out, ['id', 'request_kind', 'status', 'created_at', 'customer_name', 'customer_email', 'item_label', 'quantity', 'amount_cents', 'payment_method', 'slot_id', 'tag', 'source_post_id', 'source_post_type', 'ip_address', 'notified_at', 'notification_error']);
    foreach ($result['rows'] as $row) {
        fputcsv($out, [
            (int) ($row['id'] ?? 0),
            (string) ($row['request_kind'] ?? ''),
            (string) ($row['status'] ?? ''),
            (string) ($row['created_at'] ?? ''),
            (string) ($row['customer_name'] ?? ''),
            (string) ($row['customer_email'] ?? ''),
            (string) ($row['item_label'] ?? ''),
            (int) ($row['quantity'] ?? 0),
            (int) ($row['amount_cents'] ?? 0),
            (string) ($row['payment_method'] ?? ''),
            (string) ($row['slot_id'] ?? ''),
            (string) ($row['tag'] ?? ''),
            (int) ($row['source_post_id'] ?? 0),
            (string) ($row['source_post_type'] ?? ''),
            (string) ($row['ip_address'] ?? ''),
            (string) ($row['notified_at'] ?? ''),
            (string) ($row['notification_error'] ?? ''),
        ]);
    }

    fclose($out); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CSV export stream.
    exit;
}

function iss_payments_lite_render_requests_page(): void
{
    if (function_exists('iss_require_cap')) {
        iss_require_cap(iss_payments_lite_admin_capability());
    } elseif (!current_user_can(iss_payments_lite_admin_capability())) {
        wp_die(esc_html__('Keine Berechtigung.', 'iss-payments-lite'), 403);
    }

    iss_payments_lite_maybe_install_schema();

    $filters = iss_payments_lite_admin_current_filters();
    $paged = max(1, (int) iss_payments_lite_admin_get_param('paged'));
    $per_page = 50;
    $result = iss_payments_lite_admin_query_requests($filters, $per_page, ($paged - 1) * $per_page);
    $rows = $result['rows'];
    $total = (int) $result['total'];
    $settings = iss_payments_lite_get_settings();
    $status_labels = iss_payments_lite_status_labels();
    $kind_labels = iss_payments_lite_kind_labels();
    $export_url = wp_nonce_url(
        add_query_arg(array_filter([
            'page' => 'iss-payments-lite-requests',
            'iss_payments_lite_export' => '1',
            'request_status' => $filters['status'] ?: null,
            'request_kind' => $filters['kind'] ?: null,
            's' => $filters['search'] ?: null,
        ], static fn($value): bool => $value !== null), admin_url(defined('ISS_CORE_OPERATIONS_MENU_SLUG') ? 'admin.php' : 'tools.php')),
        'iss_payments_lite_export'
    );

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('ISS Anfragen', 'iss-payments-lite'); ?></h1>

        <?php if (iss_payments_lite_admin_get_param('updated') !== '') : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Gespeichert.', 'iss-payments-lite'); ?></p></div>
        <?php endif; ?>

        <h2><?php esc_html_e('Einstellungen', 'iss-payments-lite'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url((defined('ISS_CORE_OPERATIONS_MENU_SLUG') ? 'admin.php' : 'tools.php') . '?page=iss-payments-lite-requests')); ?>">
            <?php wp_nonce_field('iss_payments_lite_settings'); ?>
            <input type="hidden" name="iss_payments_lite_action" value="update_settings">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Benachrichtigungen', 'iss-payments-lite'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="notifications_enabled" value="1" <?php checked(!empty($settings['notifications_enabled'])); ?>>
                            <?php esc_html_e('Neue Anfragen per E-Mail melden', 'iss-payments-lite'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="iss-payments-lite-email"><?php esc_html_e('Empfänger', 'iss-payments-lite'); ?></label></th>
                    <td><input id="iss-payments-lite-email" class="regular-text" type="email" name="notification_email" value="<?php echo esc_attr((string) $settings['notification_email']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Öffentliche Schreibendpunkte', 'iss-payments-lite'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="require_nonce" value="1" <?php checked(!empty($settings['require_nonce'])); ?>>
                            <?php esc_html_e('REST-Nonce verlangen', 'iss-payments-lite'); ?>
                        </label>
                        <p>
                            <label><?php esc_html_e('Mindestzeit bis Absenden', 'iss-payments-lite'); ?>
                                <input type="number" min="0" step="1" name="min_submit_seconds" value="<?php echo esc_attr((string) $settings['min_submit_seconds']); ?>" class="small-text"> s
                            </label>
                        </p>
                        <p>
                            <label><?php esc_html_e('Maximale JSON-Größe', 'iss-payments-lite'); ?>
                                <input type="number" min="1024" step="512" name="max_body_bytes" value="<?php echo esc_attr((string) $settings['max_body_bytes']); ?>" class="small-text"> bytes
                            </label>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Einstellungen speichern', 'iss-payments-lite')); ?>
        </form>

        <hr>
        <h2><?php esc_html_e('Anfragen', 'iss-payments-lite'); ?></h2>
        <form method="get" action="<?php echo esc_url(admin_url('tools.php')); ?>">
            <input type="hidden" name="page" value="iss-payments-lite-requests">
            <select name="request_status">
                <option value=""><?php esc_html_e('Alle Status', 'iss-payments-lite'); ?></option>
                <?php foreach ($status_labels as $status => $label) : ?>
                    <option value="<?php echo esc_attr($status); ?>" <?php selected($filters['status'], $status); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="request_kind">
                <option value=""><?php esc_html_e('Alle Arten', 'iss-payments-lite'); ?></option>
                <?php foreach ($kind_labels as $kind => $label) : ?>
                    <option value="<?php echo esc_attr($kind); ?>" <?php selected($filters['kind'], $kind); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="search" name="s" value="<?php echo esc_attr((string) $filters['search']); ?>" placeholder="<?php esc_attr_e('Suchen', 'iss-payments-lite'); ?>">
            <?php submit_button(__('Filtern', 'iss-payments-lite'), 'secondary', '', false); ?>
            <a class="button" href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('CSV exportieren', 'iss-payments-lite'); ?></a>
        </form>

        <p><?php echo esc_html(sprintf(_n('%d Anfrage', '%d Anfragen', $total, 'iss-payments-lite'), $total)); ?></p>

        <form method="post" action="<?php echo esc_url(admin_url('tools.php?page=iss-payments-lite-requests')); ?>">
            <?php wp_nonce_field('iss_payments_lite_status'); ?>
            <input type="hidden" name="iss_payments_lite_action" value="update_status">
            <div class="tablenav top">
                <select name="request_new_status">
                    <?php foreach ($status_labels as $status => $label) : ?>
                        <option value="<?php echo esc_attr($status); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button(__('Status setzen', 'iss-payments-lite'), 'secondary', '', false); ?>
            </div>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th scope="col" class="check-column"></th>
                        <th><?php esc_html_e('ID', 'iss-payments-lite'); ?></th>
                        <th><?php esc_html_e('Art', 'iss-payments-lite'); ?></th>
                        <th><?php esc_html_e('Status', 'iss-payments-lite'); ?></th>
                        <th><?php esc_html_e('Datum', 'iss-payments-lite'); ?></th>
                        <th><?php esc_html_e('Name', 'iss-payments-lite'); ?></th>
                        <th><?php esc_html_e('E-Mail', 'iss-payments-lite'); ?></th>
                        <th><?php esc_html_e('Titel', 'iss-payments-lite'); ?></th>
                        <th><?php esc_html_e('Anzahl', 'iss-payments-lite'); ?></th>
                        <th><?php esc_html_e('Benachrichtigung', 'iss-payments-lite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)) : ?>
                        <tr><td colspan="10"><?php esc_html_e('Keine Anfragen gefunden.', 'iss-payments-lite'); ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <th scope="row" class="check-column"><input type="checkbox" name="request_ids[]" value="<?php echo esc_attr((string) (int) ($row['id'] ?? 0)); ?>"></th>
                            <td><?php echo esc_html((string) (int) ($row['id'] ?? 0)); ?></td>
                            <td><?php echo esc_html($kind_labels[(string) ($row['request_kind'] ?? '')] ?? (string) ($row['request_kind'] ?? '')); ?></td>
                            <td><?php echo esc_html($status_labels[(string) ($row['status'] ?? '')] ?? (string) ($row['status'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) ($row['created_at'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) ($row['customer_name'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) ($row['customer_email'] ?? '')); ?></td>
                            <td>
                                <?php echo esc_html((string) ($row['item_label'] ?? '')); ?>
                                <details>
                                    <summary><?php esc_html_e('Details', 'iss-payments-lite'); ?></summary>
                                    <pre style="white-space: pre-wrap;"><?php echo esc_html((string) ($row['payload'] ?? '')); ?></pre>
                                </details>
                            </td>
                            <td><?php echo esc_html((string) (int) ($row['quantity'] ?? 0)); ?></td>
                            <td>
                                <?php
                                $notified_at = (string) ($row['notified_at'] ?? '');
                                $notification_error = (string) ($row['notification_error'] ?? '');
                                if ($notified_at !== '') {
                                    echo esc_html($notified_at);
                                } elseif ($notification_error !== '') {
                                    echo esc_html($notification_error);
                                } else {
                                    esc_html_e('Nicht gesendet', 'iss-payments-lite');
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    </div>
    <?php
}
