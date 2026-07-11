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

            if (!function_exists('iss_fuehrung_get_catalog_posts')) {
                $errors[] = 'Tour catalog query helper is unavailable.';
            } else {
                $catalog_posts = iss_fuehrung_get_catalog_posts();
                $catalog_ids = [];
                foreach ((array) $catalog_posts as $post) {
                    if ($post instanceof WP_Post) {
                        $catalog_ids[] = (int) $post->ID;
                    }
                }
                $catalog_ids = array_values(array_unique(array_filter($catalog_ids)));

                $published_ids = get_posts([
                    'post_type' => ISS_FUEHRUNGEN_POST_TYPE,
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                    'no_found_rows' => true,
                ]);
                $published_ids = array_values(array_unique(array_filter(array_map('intval', (array) $published_ids))));

                $missing_catalog_ids = array_diff($published_ids, $catalog_ids);
                if (!empty($missing_catalog_ids)) {
                    $errors[] = sprintf('Published Führung posts missing from offer catalog query: %s.', implode(', ', array_slice($missing_catalog_ids, 0, $limit)));
                }

                $known_group_keys = function_exists('iss_fuehrung_get_offer_catalog_groups')
                    ? array_keys(iss_fuehrung_get_offer_catalog_groups())
                    : [];
                foreach ($catalog_ids as $post_id) {
                    $contract = function_exists('iss_graph_get_contract_payload_for_post')
                        ? iss_graph_get_contract_payload_for_post($post_id)
                        : [];
                    if (sanitize_key((string) ($contract['kind'] ?? '')) !== 'offer'
                        || sanitize_key((string) ($contract['subtype'] ?? '')) !== 'tour'
                        || sanitize_key((string) ($contract['storage_kind'] ?? '')) !== ISS_FUEHRUNGEN_POST_TYPE
                    ) {
                        $errors[] = sprintf(
                            'Führung #%d has invalid offer contract kind=%s subtype=%s storage=%s.',
                            $post_id,
                            (string) ($contract['kind'] ?? ''),
                            (string) ($contract['subtype'] ?? ''),
                            (string) ($contract['storage_kind'] ?? '')
                        );
                    }

                    $group_keys = function_exists('iss_fuehrung_get_offer_catalog_group_keys')
                        ? iss_fuehrung_get_offer_catalog_group_keys($post_id)
                        : [];
                    $group_keys = array_values(array_filter(array_map('sanitize_key', (array) $group_keys)));
                    if (empty($group_keys)) {
                        $errors[] = sprintf('Führung #%d has no offer catalog group.', $post_id);
                    }
                    foreach ($group_keys as $group_key) {
                        if (!in_array($group_key, $known_group_keys, true)) {
                            $errors[] = sprintf('Führung #%d has unknown offer catalog group %s.', $post_id, $group_key);
                        }
                    }
                }

                if (!empty($catalog_posts) && function_exists('iss_fuehrung_group_offer_catalog_posts')) {
                    $grouped_posts = iss_fuehrung_group_offer_catalog_posts((array) $catalog_posts);
                    $has_available_group = false;
                    foreach ($known_group_keys as $group_key) {
                        if (!empty($grouped_posts[$group_key])) {
                            $has_available_group = true;
                            break;
                        }
                    }
                    if (!$has_available_group) {
                        $errors[] = 'Tour offer catalog has no renderable group with posts.';
                    }
                }

                if (!function_exists('industriesalon_render_fuehrungen_offers_slot')) {
                    $errors[] = 'The active theme does not expose the Führungen landing offer slot.';
                }
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

    WP_CLI::add_command('iss-content tours-drift-check', ['ISS_Fuehrungen_CLI_Command', 'drift_check']);
    WP_CLI::add_command('iss-fuehrungen drift-check', ['ISS_Fuehrungen_CLI_Command', 'drift_check']);
}
