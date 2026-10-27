<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Read-only release gate for stored canonical JSON, including disabled and trashed posts. */
WP_CLI::add_command('iss-editorial validate-all', static function (array $args, array $options): void {
    global $wpdb;
    $output = $options['format'] ?? 'table';
    if (!in_array($output, ['table', 'json'], true)) { WP_CLI::error('Use --format=table or --format=json.'); }
    $report = ['checked' => 0, 'invalid' => 0, 'formats' => [], 'errors' => []];
    $include_drafts = isset($options['include-drafts']);
    $editor_view = isset($options['editor-view']);
    if ($include_drafts) { $report['drafts'] = ['checked' => 0, 'invalid' => 0, 'stale_base' => 0, 'missing_base' => 0]; }
    foreach (iss_editorial_get_registered_formats() as $slug => $format) {
        $cursor = 0;
        $report['formats'][$slug] = 0;
        $types = array_values(array_diff($format['post_types'], ['revision']));
        if (!$types) { continue; }
        $placeholders = implode(',', array_fill(0, count($types), '%s'));
        do {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Raw audit in bounded batches; the merged parameter array matches generated placeholders.
            $rows = $wpdb->get_results($wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Only native table names and generated placeholders are interpolated.
                "SELECT m.meta_id,m.post_id,m.meta_value,p.post_type,p.post_status FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE m.meta_key=%s AND p.post_type IN ($placeholders) AND m.meta_id>%d ORDER BY m.meta_id LIMIT 100",
                array_merge([iss_editorial_get_document_meta_key($slug)], $types, [$cursor])
            ), ARRAY_A);
            if ($wpdb->last_error !== '') { WP_CLI::error('Could not read stored editorial documents.'); }
            foreach ($rows as $row) {
                $cursor = (int) $row['meta_id'];
                ++$report['checked']; ++$report['formats'][$slug];
                $validated = iss_editorial_validate_document($row['meta_value'], $slug);
                if ($editor_view && !is_wp_error($validated)) {
                    $copy = apply_filters('iss_editorial_editor_document', iss_editorial_decode_document($row['meta_value']), (int) $row['post_id'], $slug);
                    $validated = iss_editorial_validate_document(iss_editorial_hydrate_document_previews($copy), $slug);
                }
                if (is_wp_error($validated)) {
                    ++$report['invalid'];
                    $report['errors'][] = [
                        'post_id' => (int) $row['post_id'], 'meta_id' => $cursor, 'format' => $slug,
                        'post_type' => $row['post_type'], 'status' => $row['post_status'],
                        'code' => $validated->get_error_code(), 'message' => $validated->get_error_message(),
                    ];
                }
            }
        } while (count($rows) === 100);
        if ($include_drafts) {
            $cursor = 0;
            do {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Read-only, keyset-batched private recovery audit; no draft content is printed.
                $rows = $wpdb->get_results($wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Native table names and generated placeholders only.
                    "SELECT m.meta_id,m.post_id,m.meta_value,p.post_parent,parent.post_type FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id JOIN {$wpdb->posts} parent ON parent.ID=p.post_parent WHERE m.meta_key=%s AND p.post_type='revision' AND parent.post_type IN ($placeholders) AND m.meta_id>%d ORDER BY m.meta_id LIMIT 100",
                    array_merge([iss_editorial_get_document_meta_key($slug)], $types, [$cursor])
                ), ARRAY_A);
                if ($wpdb->last_error !== '') { WP_CLI::error('Could not read private editorial drafts.'); }
                foreach ($rows as $row) {
                    $cursor = (int) $row['meta_id'];
                    if (!wp_is_post_autosave((int) $row['post_id'])) { continue; }
                    ++$report['drafts']['checked'];
                    $validated = iss_editorial_validate_document($row['meta_value'], $slug);
                    if ($editor_view && !is_wp_error($validated)) {
                        $copy = apply_filters('iss_editorial_editor_document', iss_editorial_decode_document($row['meta_value']), (int) $row['post_parent'], $slug);
                        $validated = iss_editorial_validate_document(iss_editorial_hydrate_document_previews($copy), $slug);
                    }
                    if (is_wp_error($validated)) {
                        ++$report['invalid']; ++$report['drafts']['invalid'];
                        $report['errors'][] = ['post_id' => (int) $row['post_id'], 'meta_id' => $cursor, 'format' => $slug, 'post_type' => $row['post_type'], 'status' => 'private-autosave', 'code' => $validated->get_error_code(), 'message' => $validated->get_error_message()];
                    }
                    $base = (string) get_metadata_raw('post', (int) $row['post_id'], '_iss_editorial_draft_base', true);
                    if ($base === '') { ++$report['drafts']['missing_base']; }
                    elseif ($base !== iss_editorial_saved_token((int) $row['post_parent'], $slug)) { ++$report['drafts']['stale_base']; }
                }
            } while (count($rows) === 100);
        }
    }
    if ($output === 'json') {
        WP_CLI::log((string) wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } else {
        WP_CLI::log(sprintf('Checked %d canonical documents; %d invalid%s. No content changed.', $report['checked'], $report['invalid'], $include_drafts ? ' across canonical documents and private autosaves' : '. Revisions and autosaves excluded'));
        if ($include_drafts) {
            WP_CLI::log(sprintf('Private autosaves: %d checked, %d invalid, %d stale bases, %d missing bases. Base differences require comparison, not automatic deletion.', ...array_values($report['drafts'])));
        }
        if ($report['errors']) {
            WP_CLI::log("post_id\tmeta_id\tformat\tstatus\tcode\tmessage");
            foreach ($report['errors'] as $row) {
                unset($row['post_type']);
                WP_CLI::log(implode("\t", $row));
            }
        }
    }
    if ($report['invalid']) { WP_CLI::halt(1); }
});

/** Check format contributions without changing documents, options or templates. */
WP_CLI::add_command('iss-editorial registry-check', static function (array $args, array $options): void {
    $errors = iss_editorial_registry_errors();
    $rows = [];
    foreach (iss_editorial_get_registered_formats() as $slug => $format) {
        $rows[] = [
            'format' => $slug,
            'sections' => count($format['sections']),
            'versions' => implode(',', $format['supported_versions']),
            'workspace' => !empty($format['editor']['workspace']) ? 'yes' : 'no',
            'preview' => !empty($format['editor']['preview']) ? 'yes' : 'no',
        ];
    }
    if (($options['format'] ?? '') === 'json') {
        WP_CLI::log((string) wp_json_encode(['formats' => $rows, 'errors' => $errors], JSON_PRETTY_PRINT));
    } else {
        WP_CLI::log("format\tsections\tversions\tworkspace\tpreview");
        foreach ($rows as $row) { WP_CLI::log(implode("\t", $row)); }
    }
    if ($errors) { WP_CLI::error(implode("\n", $errors)); }
    if (($options['format'] ?? '') !== 'json') { WP_CLI::success('Editorial registry is consistent. No content changed.'); }
});

/** Check canonical documents, including unchanged documents, before closing a sync. */
WP_CLI::add_command('iss-editorial media-check', static function (): void {
    $references = [];
    $documents = 0;
    $walk = static function ($value, int $owner) use (&$walk, &$references): void {
        if (!is_array($value)) {
            return;
        }
        if (isset($value['source'], $value['id']) && in_array($value['source'], ['wp-media', 'wordpress'], true)) {
            $references[absint($value['id'])][$owner] = true;
        }
        foreach ($value as $child) {
            $walk($child, $owner);
        }
    };
    foreach (iss_editorial_get_registered_formats() as $slug => $format) {
        $posts = get_posts([
            'post_type' => $format['post_types'],
            'post_status' => 'any',
            'numberposts' => -1,
            'meta_key' => iss_editorial_get_document_meta_key($slug), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Explicit CLI audit of every stored document.
        ]);
        foreach ($posts as $post) {
            $document = iss_editorial_get_document($post->ID, $slug, false);
            if ($document) {
                ++$documents;
                $walk($document, $post->ID);
            }
        }
    }
    $errors = [];
    foreach ($references as $id => $owners) {
        $reason = '';
        if (get_post_type($id) !== 'attachment') {
            $reason = 'missing attachment record';
        } elseif (!get_attached_file($id) || !is_file(get_attached_file($id))) {
            $reason = 'missing current attachment file';
        } elseif (wp_attachment_is_image($id) && !wp_get_attachment_image($id, 'medium_large')) {
            $reason = 'image cannot render';
        }
        if ($reason !== '') {
            $errors[] = sprintf('%d: %s (content %s)', $id, $reason, implode(', ', array_keys($owners)));
        }
    }
    WP_CLI::log(sprintf('Checked %d canonical documents and %d unique WordPress media references.', $documents, count($references)));
    if ($errors) {
        WP_CLI::error(implode("\n", $errors));
    }
    WP_CLI::success('Attachment records and current files exist. No content changed. Check transferred variants against the uploads manifest.');
});
