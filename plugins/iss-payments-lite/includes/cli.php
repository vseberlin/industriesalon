<?php

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- CLI verifies the plugin-owned request table.

if (defined('WP_CLI') && WP_CLI) {
    final class ISS_Payments_Lite_CLI_Command
    {
        public function verify(array $args, array $assoc_args): void
        {
            global $wpdb;

            iss_payments_lite_maybe_install_schema();

            $errors = [];
            $table = iss_payments_lite_requests_table_name();
            if (!iss_payments_lite_table_exists()) {
                $errors[] = 'Payments-lite request table is missing.';
            } else {
                foreach (['id', 'request_kind', 'status', 'duplicate_hash', 'notified_at', 'notification_error', 'created_at'] as $column) {
                    $found = (string) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
                    if ($found === '') {
                        $errors[] = sprintf('Payments-lite request table is missing column %s.', $column);
                    }
                }
            }

            $schema_version = (string) get_option(ISS_PAYMENTS_LITE_SCHEMA_OPTION, '');
            if ($schema_version !== ISS_PAYMENTS_LITE_SCHEMA_VERSION) {
                $errors[] = sprintf('Payments-lite schema version mismatch stored=%s expected=%s.', $schema_version, ISS_PAYMENTS_LITE_SCHEMA_VERSION);
            }

            $legacy_options = $wpdb->get_col(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name IN ('is_tours_booking_requests', 'iss_publication_order_requests') ORDER BY option_name ASC"
            );
            if (is_array($legacy_options) && !empty($legacy_options)) {
                $errors[] = sprintf('Legacy request option(s) remain: %s.', implode(', ', $legacy_options));
            }

            if (!empty($errors)) {
                \WP_CLI::error_multi_line($errors);
                \WP_CLI::error('ISS payments-lite verification failed.');
            }

            $settings = iss_payments_lite_get_settings();
            if (empty($settings['require_nonce'])) {
                \WP_CLI::warning('Public write endpoints are configured without REST nonce requirement.');
            }
            if (empty($settings['notifications_enabled'])) {
                \WP_CLI::warning('Request email notifications are disabled.');
            } elseif (!is_email((string) $settings['notification_email'])) {
                \WP_CLI::warning('Request email notifications are enabled without a valid recipient.');
            }

            $counts = [];
            if (iss_payments_lite_table_exists()) {
                $rows = $wpdb->get_results("SELECT status, COUNT(*) AS row_count FROM {$table} GROUP BY status ORDER BY status ASC", ARRAY_A);
                foreach (is_array($rows) ? $rows : [] as $row) {
                    $counts[] = sprintf('%s=%d', (string) ($row['status'] ?? ''), (int) ($row['row_count'] ?? 0));
                }
            }

            \WP_CLI::log('request_status_counts=' . ($counts ? implode(' ', $counts) : 'none'));
            \WP_CLI::success('ISS payments-lite verification passed.');
        }
    }

    \WP_CLI::add_command('iss-payments-lite', 'ISS_Payments_Lite_CLI_Command');
}
