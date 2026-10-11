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

            require_once dirname(__DIR__, 2) . '/iss-core/schema.php';
            $errors = [];
            $table = iss_payments_lite_requests_table_name();
            try {
                iss_core_verify_schema(iss_payments_lite_schema_statements());
            } catch (Throwable $error) {
                $errors[] = $error->getMessage();
            }

            $schema_version = (string) get_option(ISS_PAYMENTS_LITE_SCHEMA_OPTION, '');
            if ($schema_version !== ISS_PAYMENTS_LITE_SCHEMA_VERSION) {
                $errors[] = sprintf('Payments-lite schema version mismatch stored=%s expected=%s.', $schema_version, ISS_PAYMENTS_LITE_SCHEMA_VERSION);
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
            \WP_CLI::success('ISS commerce-lite verification passed.');
        }
    }

    \WP_CLI::add_command('iss-commerce-lite', 'ISS_Payments_Lite_CLI_Command');
}
