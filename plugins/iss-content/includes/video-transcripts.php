<?php

if (!defined('ABSPATH')) {
    exit;
}

const ISS_CONTENT_MODEL_VIDEO_TRANSCRIPT_META_KEY = '_iss_video_transcript_json';

function iss_content_model_register_video_transcript_meta(): void
{
    register_post_meta(
        ISS_CONTENT_MODEL_VIDEO_POST_TYPE,
        ISS_CONTENT_MODEL_VIDEO_TRANSCRIPT_META_KEY,
        [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => false,
            'auth_callback' => static function (): bool {
                return current_user_can('edit_posts');
            },
        ]
    );
}
add_action('init', 'iss_content_model_register_video_transcript_meta', 20);

function iss_content_model_empty_video_transcript_document(): array
{
    return [
        'schema_version' => 1,
        'source' => 'manual',
        'segments' => [],
    ];
}

function iss_content_model_video_transcript_review_states(): array
{
    return [
        'raw' => __('Rohfassung', 'iss-content-model'),
        'needs_review' => __('Zu prüfen', 'iss-content-model'),
        'reviewed' => __('Geprüft', 'iss-content-model'),
    ];
}

function iss_content_model_normalize_video_transcript_timecode(string $timecode): string
{
    $timecode = trim($timecode);
    $timecode = trim($timecode, "[] \t\n\r\0\x0B");

    if (!preg_match('/^(?:(\d{1,2}):)?(\d{2}):(\d{2})$/', $timecode, $matches)) {
        return '';
    }

    $hours = isset($matches[1]) && $matches[1] !== '' ? (int) $matches[1] : 0;
    $minutes = (int) $matches[2];
    $seconds = (int) $matches[3];

    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
    }

    return sprintf('%02d:%02d', $minutes, $seconds);
}

function iss_content_model_sanitize_video_transcript_segment($segment): array
{
    $segment = is_array($segment) ? $segment : [];
    $timecode = iss_content_model_normalize_video_transcript_timecode((string) ($segment['timecode'] ?? ''));
    $seconds = $timecode !== '' ? iss_content_model_parse_video_timecode($timecode) : -1;
    $review_state = sanitize_key((string) ($segment['review_state'] ?? 'needs_review'));
    if (!array_key_exists($review_state, iss_content_model_video_transcript_review_states())) {
        $review_state = 'needs_review';
    }

    return [
        'timecode' => $timecode,
        'seconds' => max(0, $seconds),
        'speaker' => sanitize_text_field((string) ($segment['speaker'] ?? '')),
        'text' => sanitize_textarea_field((string) ($segment['text'] ?? '')),
        'source' => sanitize_key((string) ($segment['source'] ?? 'manual')),
        'review_state' => $review_state,
    ];
}

function iss_content_model_sanitize_video_transcript_document($document): array
{
    if (is_string($document)) {
        $decoded = json_decode(wp_unslash($document), true);
        $document = is_array($decoded) ? $decoded : [];
    }

    $document = is_array($document) ? $document : [];
    $sanitized = iss_content_model_empty_video_transcript_document();
    $sanitized['source'] = sanitize_key((string) ($document['source'] ?? 'manual')) ?: 'manual';

    foreach ((array) ($document['segments'] ?? []) as $segment) {
        $segment = iss_content_model_sanitize_video_transcript_segment($segment);
        if ($segment['timecode'] === '' && $segment['text'] === '') {
            continue;
        }
        $sanitized['segments'][] = $segment;
    }

    return $sanitized;
}

function iss_content_model_video_transcript_document_has_segments(array $document): bool
{
    foreach ((array) ($document['segments'] ?? []) as $segment) {
        if (trim((string) ($segment['text'] ?? '')) !== '') {
            return true;
        }
    }

    return false;
}

function iss_content_model_parse_video_transcript_text(string $text, string $source = 'post_content'): array
{
    $text = html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES, get_bloginfo('charset'));
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if ($text === '') {
        return iss_content_model_empty_video_transcript_document();
    }

    $parts = preg_split('/(\[?\d{1,2}:\d{2}(?::\d{2})?\]?)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $document = [
        'schema_version' => 1,
        'source' => $source,
        'segments' => [],
    ];
    $current_timecode = '';

    foreach ($parts ?: [] as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }

        $timecode = iss_content_model_normalize_video_transcript_timecode($part);
        if ($timecode !== '') {
            $current_timecode = $timecode;
            continue;
        }

        if ($current_timecode === '') {
            continue;
        }

        $document['segments'][] = [
            'timecode' => $current_timecode,
            'seconds' => max(0, iss_content_model_parse_video_timecode($current_timecode)),
            'speaker' => '',
            'text' => $part,
            'source' => $source,
            'review_state' => 'needs_review',
        ];
        $current_timecode = '';
    }

    return iss_content_model_sanitize_video_transcript_document($document);
}

function iss_content_model_parse_video_transcript_post_content(int $post_id): array
{
    $content_html = apply_filters('the_content', (string) get_post_field('post_content', $post_id));
    return iss_content_model_parse_video_transcript_text($content_html, 'post_content');
}

function iss_content_model_get_video_transcript_document(int $post_id, bool $fallback_to_post_content = true): array
{
    $stored = get_post_meta($post_id, ISS_CONTENT_MODEL_VIDEO_TRANSCRIPT_META_KEY, true);
    if (is_string($stored) && trim($stored) !== '') {
        $document = iss_content_model_sanitize_video_transcript_document($stored);
        if (iss_content_model_video_transcript_document_has_segments($document)) {
            return $document;
        }
    }

    return $fallback_to_post_content
        ? iss_content_model_parse_video_transcript_post_content($post_id)
        : iss_content_model_empty_video_transcript_document();
}

function iss_content_model_render_video_transcript_document_html(array $document): string
{
    $segments = (array) ($document['segments'] ?? []);
    if (!$segments) {
        return '';
    }

    ob_start();
    foreach ($segments as $segment) {
        $timecode = iss_content_model_normalize_video_transcript_timecode((string) ($segment['timecode'] ?? ''));
        $text = trim((string) ($segment['text'] ?? ''));
        if ($timecode === '' || $text === '') {
            continue;
        }

        $seconds = max(0, iss_content_model_parse_video_timecode($timecode));
        $id = iss_content_model_video_timecode_id($timecode);
        ?>
        <p id="<?php echo esc_attr($id); ?>" class="iss-video-transcript__segment">
            <a class="iss-video-timecode" href="#<?php echo esc_attr($id); ?>" data-video-seek="<?php echo esc_attr((string) $seconds); ?>">
                <span class="screen-reader-text"><?php esc_html_e('Videozeit', 'iss-content-model'); ?> </span><?php echo esc_html($timecode); ?>
            </a>
            <?php if ((string) ($segment['speaker'] ?? '') !== '') : ?>
                <strong class="iss-video-transcript__speaker"><?php echo esc_html((string) $segment['speaker']); ?></strong>
            <?php endif; ?>
            <?php echo esc_html($text); ?>
        </p>
        <?php
    }

    return trim((string) ob_get_clean());
}

function iss_content_model_get_video_transcript_json_editor_document(int $post_id): array
{
    $document = iss_content_model_get_video_transcript_document($post_id, true);
    if (!iss_content_model_video_transcript_document_has_segments($document)) {
        return iss_content_model_empty_video_transcript_document();
    }

    return $document;
}

function iss_content_model_render_video_transcript_json_box(WP_Post $post): void
{
    wp_nonce_field('iss_content_model_save_video_transcript_json', 'iss_content_model_video_transcript_nonce');
    $document = iss_content_model_get_video_transcript_json_editor_document((int) $post->ID);
    $encoded = (string) wp_json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $states = iss_content_model_video_transcript_review_states();

    echo '<div class="iss-video-transcript-editor" data-document="' . esc_attr($encoded) . '" data-review-states="' . esc_attr((string) wp_json_encode($states, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '">';
    echo '<input type="hidden" class="iss-video-transcript-editor__field" name="iss_video_transcript_json" value="' . esc_attr($encoded) . '">';
    echo '<div class="iss-video-transcript-editor__root"></div>';
    echo '</div>';
}

function iss_content_model_save_video_transcript_json(int $post_id, WP_Post $post): void
{
    if ($post->post_type !== ISS_CONTENT_MODEL_VIDEO_POST_TYPE || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    $nonce = isset($_POST['iss_content_model_video_transcript_nonce'])
        ? sanitize_text_field((string) wp_unslash($_POST['iss_content_model_video_transcript_nonce']))
        : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'iss_content_model_save_video_transcript_json')) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $raw = isset($_POST['iss_video_transcript_json']) ? (string) wp_unslash($_POST['iss_video_transcript_json']) : '';
    $document = iss_content_model_sanitize_video_transcript_document($raw);
    if (!iss_content_model_video_transcript_document_has_segments($document)) {
        delete_post_meta($post_id, ISS_CONTENT_MODEL_VIDEO_TRANSCRIPT_META_KEY);
        return;
    }

    update_post_meta($post_id, ISS_CONTENT_MODEL_VIDEO_TRANSCRIPT_META_KEY, wp_slash((string) wp_json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
}
add_action('save_post_' . ISS_CONTENT_MODEL_VIDEO_POST_TYPE, 'iss_content_model_save_video_transcript_json', 30, 2);

function iss_content_model_enqueue_video_transcript_admin_assets(string $hook): void
{
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== ISS_CONTENT_MODEL_VIDEO_POST_TYPE) {
        return;
    }

    $style_path = ISS_CONTENT_MODEL_PATH . 'assets/admin-video-transcript.css';
    if (file_exists($style_path)) {
        wp_enqueue_style(
            'iss-content-model-video-transcript-admin',
            plugins_url('../assets/admin-video-transcript.css', __FILE__),
            [],
            (string) filemtime($style_path)
        );
    }

    $script_path = ISS_CONTENT_MODEL_PATH . 'assets/admin-video-transcript.js';
    if (file_exists($script_path)) {
        wp_enqueue_script(
            'iss-content-model-video-transcript-admin',
            plugins_url('../assets/admin-video-transcript.js', __FILE__),
            [],
            (string) filemtime($script_path),
            true
        );
    }
}
add_action('admin_enqueue_scripts', 'iss_content_model_enqueue_video_transcript_admin_assets');
