<?php

if (!defined('ABSPATH')) {
    exit;
}

if (defined('WP_CLI') && WP_CLI) {
class ISS_WF_Import_CLI_Command
{
        /**
         * Sync WF-Museum archive posts into the local mirror CPT.
         *
         * ## OPTIONS
         *
         * [--remote_id=<id>]
         * : Import or refresh a single remote post.
         *
         * [--page=<page>]
         * : Start on a specific remote page. Default: 1.
         *
         * [--per_page=<count>]
         * : Remote page size, max 100. Default: 100.
         *
         * [--limit=<count>]
         * : Stop after processing this many remote posts.
         *
         * [--media=<mode>]
         * : Media handling: all, featured, or none. Default: all.
         *
         * [--force]
         * : Reimport even when the remote hash is unchanged.
         */
        public function sync(array $args, array $assoc_args): void
        {
            $media = sanitize_key((string) ($assoc_args['media'] ?? 'all'));
            if (!in_array($media, ['all', 'featured', 'none'], true)) {
                $media = 'all';
            }

            $options = [
                'per_page' => absint($assoc_args['per_page'] ?? 100),
                'page' => absint($assoc_args['page'] ?? 1),
                'limit' => absint($assoc_args['limit'] ?? 0),
                'remote_id' => absint($assoc_args['remote_id'] ?? 0),
                'force' => !empty($assoc_args['force']),
                'media' => $media,
            ];

            $stats = iss_wf_import_sync($options);

            foreach ((array) ($stats['errors'] ?? []) as $error) {
                WP_CLI::warning((string) $error);
            }

            WP_CLI::success(sprintf(
                'WF sync finished. processed=%d imported=%d updated=%d skipped=%d failed=%d total_remote=%d',
                (int) ($stats['processed'] ?? 0),
                (int) ($stats['imported'] ?? 0),
                (int) ($stats['updated'] ?? 0),
                (int) ($stats['skipped'] ?? 0),
                (int) ($stats['failed'] ?? 0),
                (int) ($stats['total_remote'] ?? 0)
            ));
        }

        /**
         * Generate reviewable place suggestions for mirrored archive posts.
         *
         * ## OPTIONS
         *
         * [--post_id=<id>]
         * : Refresh suggestions for a single local archive record.
         *
         * [--post_type=<type>]
         * : Target post type. Supported: archivbeitrag, archivobjekt. Default: archivbeitrag.
         *
         * [--limit=<count>]
         * : Stop after refreshing this many posts.
         *
         * [--force]
         * : Recalculate even when suggestions already exist.
         */
        public function suggest(array $args, array $assoc_args): void
        {
            $stats = iss_wf_import_generate_suggestions([
                'post_id' => absint($assoc_args['post_id'] ?? 0),
                'post_type' => sanitize_key((string) ($assoc_args['post_type'] ?? ISS_WF_IMPORT_POST_TYPE)),
                'limit' => absint($assoc_args['limit'] ?? 0),
                'force' => !empty($assoc_args['force']),
            ]);

            foreach ((array) ($stats['errors'] ?? []) as $error) {
                WP_CLI::warning((string) $error);
            }

            WP_CLI::success(sprintf(
                'WF suggestions finished. processed=%d with_suggestions=%d without_suggestions=%d skipped=%d',
                (int) ($stats['processed'] ?? 0),
                (int) ($stats['with_suggestions'] ?? 0),
                (int) ($stats['without_suggestions'] ?? 0),
                (int) ($stats['skipped'] ?? 0)
            ));
        }

        /**
         * Sync a single museum-digital object into the local archive object CPT.
         *
         * ## OPTIONS
         *
         * [--object_id=<id>]
         * : museum-digital object id.
         *
         * [--source_url=<url>]
         * : museum-digital object URL. Used when object_id is omitted.
         *
         * [--media=<mode>]
         * : Media handling: all, featured, or none. Default: all.
         *
         * [--force]
         * : Reimport even when the remote hash is unchanged.
         */
        public function sync_md_object(array $args, array $assoc_args): void
        {
            $object_id = absint($assoc_args['object_id'] ?? 0);
            if ($object_id <= 0 && !empty($assoc_args['source_url'])) {
                $object_id = iss_wf_import_extract_md_object_id_from_url((string) $assoc_args['source_url']);
            }

            if ($object_id <= 0) {
                WP_CLI::error('Provide --object_id or a museum-digital --source_url.');
            }

            $media = sanitize_key((string) ($assoc_args['media'] ?? 'all'));
            if (!in_array($media, ['all', 'featured', 'none'], true)) {
                $media = 'all';
            }

            $result = iss_wf_import_upsert_md_object($object_id, [
                'force' => !empty($assoc_args['force']),
                'media' => $media,
            ]);

            if (($result['status'] ?? '') === 'failed') {
                WP_CLI::error((string) ($result['message'] ?? 'museum-digital object sync failed.'));
            }

            WP_CLI::success(sprintf(
                'museum-digital object sync finished. object_id=%d local_post_id=%d status=%s',
                $object_id,
                (int) ($result['post_id'] ?? 0),
                (string) ($result['status'] ?? 'unknown')
            ));
        }

        /**
         * Sync a museum-digital collection into the local archive collection CPT.
         *
         * ## OPTIONS
         *
         * [--collection_id=<id>]
         * : museum-digital collection id.
         *
         * [--source_url=<url>]
         * : museum-digital collection URL. Used when collection_id is omitted.
         *
         * [--limit=<count>]
         * : Stop after importing this many collection objects.
         *
         * [--media=<mode>]
         * : Media handling: all, featured, or none. Default: all.
         *
         * [--skip-objects]
         * : Mirror only the collection record, not the contained objects.
         *
         * [--force]
         * : Reimport even when the remote hash is unchanged.
         */
        public function sync_md_collection(array $args, array $assoc_args): void
        {
            $collection_id = absint($assoc_args['collection_id'] ?? 0);
            if ($collection_id <= 0 && !empty($assoc_args['source_url'])) {
                $collection_id = iss_wf_import_extract_md_collection_id_from_url((string) $assoc_args['source_url']);
            }

            if ($collection_id <= 0) {
                WP_CLI::error('Provide --collection_id or a museum-digital --source_url.');
            }

            $media = sanitize_key((string) ($assoc_args['media'] ?? 'all'));
            if (!in_array($media, ['all', 'featured', 'none'], true)) {
                $media = 'all';
            }

            $result = iss_wf_import_upsert_md_collection($collection_id, [
                'limit' => absint($assoc_args['limit'] ?? 0),
                'media' => $media,
                'skip_objects' => !empty($assoc_args['skip-objects']),
                'force' => !empty($assoc_args['force']),
            ]);

            if (($result['status'] ?? '') === 'failed') {
                WP_CLI::error((string) ($result['message'] ?? 'museum-digital collection sync failed.'));
            }

            $object_stats = (array) ($result['object_stats'] ?? []);
            foreach ((array) ($object_stats['errors'] ?? []) as $error) {
                WP_CLI::warning((string) $error);
            }

            WP_CLI::success(sprintf(
                'museum-digital collection sync finished. collection_id=%d local_post_id=%d status=%s object_processed=%d imported=%d updated=%d skipped=%d failed=%d',
                $collection_id,
                (int) ($result['post_id'] ?? 0),
                (string) ($result['status'] ?? 'unknown'),
                (int) ($object_stats['processed'] ?? 0),
                (int) ($object_stats['imported'] ?? 0),
                (int) ($object_stats['updated'] ?? 0),
                (int) ($object_stats['skipped'] ?? 0),
                (int) ($object_stats['failed'] ?? 0)
            ));
        }

        /**
         * Mirror a curated WF collection or album page into the local archive collection CPT.
         *
         * ## OPTIONS
         *
         * --source_url=<url>
         * : WF page URL to mirror.
         *
         * [--limit=<count>]
         * : Stop after importing this many contained objects.
         *
         * [--media=<mode>]
         * : Media handling: all, featured, or none. Default: all.
         *
         * [--follow-children]
         * : Also mirror child collection links below the current page.
         *
         * [--force]
         * : Reimport even when the local hash is unchanged.
         */
        public function sync_wf_collection(array $args, array $assoc_args): void
        {
            $url = esc_url_raw((string) ($assoc_args['source_url'] ?? ''));
            if ($url === '') {
                WP_CLI::error('Provide --source_url for the WF collection page.');
            }

            $media = sanitize_key((string) ($assoc_args['media'] ?? 'all'));
            if (!in_array($media, ['all', 'featured', 'none'], true)) {
                $media = 'all';
            }

            $result = iss_wf_import_upsert_wf_collection($url, [
                'limit' => absint($assoc_args['limit'] ?? 0),
                'media' => $media,
                'follow_children' => !empty($assoc_args['follow-children']),
                'force' => !empty($assoc_args['force']),
            ]);

            if (($result['status'] ?? '') === 'failed') {
                WP_CLI::error((string) ($result['message'] ?? 'WF collection sync failed.'));
            }

            $object_stats = (array) ($result['object_stats'] ?? []);
            foreach ((array) ($object_stats['errors'] ?? []) as $error) {
                WP_CLI::warning((string) $error);
            }

            WP_CLI::success(sprintf(
                'WF collection sync finished. local_post_id=%d status=%s object_processed=%d imported=%d updated=%d skipped=%d failed=%d child_collections=%d',
                (int) ($result['post_id'] ?? 0),
                (string) ($result['status'] ?? 'unknown'),
                (int) ($object_stats['processed'] ?? 0),
                (int) ($object_stats['imported'] ?? 0),
                (int) ($object_stats['updated'] ?? 0),
                (int) ($object_stats['skipped'] ?? 0),
                (int) ($object_stats['failed'] ?? 0),
                count((array) ($result['child_stats'] ?? []))
            ));
        }

        /**
         * Repair empty museum-digital archive object stubs by re-syncing them from source.
         *
         * ## OPTIONS
         *
         * [--post_id=<id>]
         * : Repair a single local archivobjekt post.
         *
         * [--limit=<count>]
         * : Stop after repairing this many stubs.
         *
         * [--media=<mode>]
         * : Media handling: all, featured, or none. Default: featured.
         *
         * [--force]
         * : Force reimport even if the source hash matches.
         */
        public function repair_md_stubs(array $args, array $assoc_args): void
        {
            $media = sanitize_key((string) ($assoc_args['media'] ?? 'featured'));
            if (!in_array($media, ['all', 'featured', 'none'], true)) {
                $media = 'featured';
            }

            $post_id = absint($assoc_args['post_id'] ?? 0);
            $limit = max(0, absint($assoc_args['limit'] ?? 0));
            $force = !empty($assoc_args['force']);

            $query_args = [
                'post_type' => ISS_WF_IMPORT_OBJECT_POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
                'suppress_filters' => true,
                'meta_query' => [
                    [
                        'key' => ISS_WF_IMPORT_SOURCE_KIND_META,
                        'value' => 'museum_digital_object',
                        'compare' => '=',
                    ],
                ],
            ];

            if ($post_id > 0) {
                $query_args['post__in'] = [$post_id];
                unset($query_args['posts_per_page']);
            }

            $post_ids = get_posts($query_args);
            $stats = [
                'processed' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'errors' => [],
            ];

            foreach ($post_ids as $candidate_id) {
                $candidate_id = (int) $candidate_id;
                $content = trim(wp_strip_all_tags((string) get_post_field('post_content', $candidate_id)));
                $excerpt = trim((string) get_post_field('post_excerpt', $candidate_id));
                $thumb_id = (int) get_post_thumbnail_id($candidate_id);
                $images = get_post_meta($candidate_id, ISS_WF_IMPORT_OBJECT_IMAGE_SOURCE_META, true);
                $has_images = is_array($images) && count(array_filter($images, static function ($image): bool {
                    return is_array($image) && (
                        !empty($image['source_url']) ||
                        !empty($image['preview_url']) ||
                        !empty($image['attachment_id']) ||
                        !empty($image['preview_attachment_id'])
                    );
                })) > 0;

                $is_stub = $content === '' && $excerpt === '' && $thumb_id <= 0 && !$has_images;
                if (!$is_stub && $post_id <= 0) {
                    continue;
                }

                $object_id = absint(get_post_meta($candidate_id, ISS_WF_IMPORT_SOURCE_EXTERNAL_ID_META, true));
                if ($object_id <= 0) {
                    $stats['failed']++;
                    $stats['errors'][] = sprintf('Post %d: missing museum-digital source id.', $candidate_id);
                    continue;
                }

                $result = iss_wf_import_upsert_md_object($object_id, [
                    'force' => $force || $is_stub,
                    'media' => $media,
                ]);

                $stats['processed']++;

                if (($result['status'] ?? '') === 'failed') {
                    $stats['failed']++;
                    $stats['errors'][] = sprintf('Object %d / post %d: %s', $object_id, $candidate_id, (string) ($result['message'] ?? 'Sync failed.'));
                    continue;
                }

                if (($result['status'] ?? '') === 'updated' || ($result['status'] ?? '') === 'imported') {
                    $stats['updated']++;
                } else {
                    $stats['skipped']++;
                }

                if ($limit > 0 && $stats['processed'] >= $limit) {
                    break;
                }
            }

            foreach ($stats['errors'] as $error) {
                WP_CLI::warning($error);
            }

            WP_CLI::success(sprintf(
                'museum-digital stub repair finished. processed=%d updated=%d skipped=%d failed=%d',
                $stats['processed'],
                $stats['updated'],
                $stats['skipped'],
                $stats['failed']
            ));
        }
}

    WP_CLI::add_command('iss-wf-import', 'ISS_WF_Import_CLI_Command');
}
