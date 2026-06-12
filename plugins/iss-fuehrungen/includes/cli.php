<?php

if (!defined('ABSPATH')) {
    exit;
}

if (defined('WP_CLI') && WP_CLI) {
    final class ISS_Fuehrungen_CLI_Command
    {
        public function drift_check(array $args, array $assoc_args): void
        {
            global $wpdb;

            $errors = [];
            $limit = isset($assoc_args['limit']) ? max(1, (int) $assoc_args['limit']) : 25;
            $retired_template_slugs = [
                'single-tour',
                'single-tour-on-demand',
            ];

            foreach ($retired_template_slugs as $template_slug) {
                $retired_template = function_exists('get_block_template')
                    ? get_block_template(get_stylesheet() . '//' . $template_slug, 'wp_template')
                    : null;
                if ($retired_template instanceof WP_Block_Template && !empty($retired_template->source)) {
                    $errors[] = sprintf('Retired Führung block template remains: %s source=%s.', $template_slug, (string) $retired_template->source);
                }
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP-CLI drift check must inspect current stored template assignments.
            $stale_template_posts = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID WHERE p.post_type = %s AND p.post_status = %s AND pm.meta_key = %s AND pm.meta_value IN (%s, %s)",
                    ISS_FUEHRUNGEN_POST_TYPE,
                    'publish',
                    '_wp_page_template',
                    'single-tour',
                    'single-tour-on-demand'
                )
            );
            if ($stale_template_posts > 0) {
                $errors[] = sprintf('Published Führung posts still use retired custom template meta: %d.', $stale_template_posts);
            }

            if (!empty($errors)) {
                WP_CLI::error_multi_line(array_slice($errors, 0, $limit));
                if (count($errors) > $limit) {
                    WP_CLI::warning(sprintf('%d additional drift issue(s) hidden by --limit=%d.', count($errors) - $limit, $limit));
                }
                WP_CLI::error(sprintf('ISS Führungen drift check failed with %d issue(s).', count($errors)));
            }

            WP_CLI::success('ISS Führungen drift check passed.');
        }
    }

    WP_CLI::add_command('iss-fuehrungen drift-check', ['ISS_Fuehrungen_CLI_Command', 'drift_check']);
}
