<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_frontend_dense_image_wall_class_list(string $class_string): array
{
    $classes = preg_split('/\s+/', trim($class_string));
    if (!is_array($classes)) {
        return [];
    }

    $classes = array_filter(array_map('sanitize_html_class', $classes));

    return array_values(array_unique($classes));
}

function iss_frontend_register_dense_image_wall_block(): void
{
    if (!function_exists('register_block_type')) {
        return;
    }

    $block_dir = ISS_FRONTEND_EDITORIAL_PATH . 'blocks/dense-image-wall';
    $script_path = $block_dir . '/index.js';
    $style_path = $block_dir . '/style.css';

    if (file_exists($script_path)) {
        wp_register_script(
            'iss-frontend-dense-image-wall-editor',
            ISS_FRONTEND_EDITORIAL_URL . 'blocks/dense-image-wall/index.js',
            [
                'wp-block-editor',
                'wp-blocks',
                'wp-components',
                'wp-element',
            ],
            (string) filemtime($script_path),
            true
        );
    }

    if (file_exists($style_path)) {
        wp_register_style(
            'iss-frontend-dense-image-wall',
            ISS_FRONTEND_EDITORIAL_URL . 'blocks/dense-image-wall/style.css',
            [],
            (string) filemtime($style_path)
        );
    }

    if (file_exists($block_dir . '/block.json')) {
        register_block_type($block_dir, [
            'editor_script' => 'iss-frontend-dense-image-wall-editor',
            'style' => 'iss-frontend-dense-image-wall',
            'render_callback' => 'iss_frontend_render_dense_image_wall_block',
        ]);
    }
}
add_action('init', 'iss_frontend_register_dense_image_wall_block', 18);

function iss_frontend_render_dense_image_wall_block($attributes = []): string
{
    $attributes = is_array($attributes) ? $attributes : [];
    $items = isset($attributes['items']) && is_array($attributes['items']) ? $attributes['items'] : [];
    if (!$items) {
        return '';
    }

    $columns = max(2, min(6, (int) ($attributes['columns'] ?? 4)));
    $row_height = max(80, min(260, (int) ($attributes['rowHeight'] ?? 118)));
    $style = '--iss-dense-image-wall-columns:' . $columns . ';--iss-dense-image-wall-row-size:' . $row_height . 'px;';

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => 'wp-block-group iss-dense-image-wall',
            'style' => $style,
        ])
        : 'class="wp-block-group iss-dense-image-wall" style="' . esc_attr($style) . '"';

    $out = '<div ' . $wrapper . '>';

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $attachment_id = absint($item['id'] ?? 0);
        $url = trim((string) ($item['url'] ?? ''));
        $col_span = max(1, min($columns, (int) ($item['colSpan'] ?? 1)));
        $row_span = max(1, min(6, (int) ($item['rowSpan'] ?? 1)));
        $link_url = trim((string) ($item['linkUrl'] ?? ''));
        $safe_link_url = $link_url !== '' ? esc_url($link_url) : '';
        $alt = trim((string) ($item['alt'] ?? ''));
        $kicker = trim((string) ($item['kicker'] ?? ''));
        $title = trim((string) ($item['title'] ?? ''));
        $text = trim((string) ($item['text'] ?? ''));
        $caption = trim((string) ($item['caption'] ?? ''));
        $caption_html = trim((string) ($item['captionHtml'] ?? ''));
        $cell_class = trim((string) ($item['className'] ?? ($item['cellClassName'] ?? '')));
        $caption_class = trim((string) ($item['captionClassName'] ?? ''));

        if ($url === '' && $attachment_id <= 0) {
            continue;
        }

        if ($alt === '' && $attachment_id > 0) {
            $alt = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
        }

        $classes = ['wp-block-image', 'iss-dense-image-wall__cell'];
        if ($col_span >= 2) {
            $classes[] = 'iss-dense-image-wall__cell--wide';
        }
        if ($row_span >= 2) {
            $classes[] = 'iss-dense-image-wall__cell--tall';
        }
        if ($caption_html !== '' || $caption !== '' || $kicker !== '' || $title !== '' || $text !== '') {
            $classes[] = 'iss-dense-image-wall__cell--captioned';
        }
        $classes = array_merge($classes, iss_frontend_dense_image_wall_class_list($cell_class));

        $cell_style = [];
        if ($col_span > 1) {
            $cell_style[] = 'grid-column:span ' . $col_span;
        }
        if ($row_span > 1) {
            $cell_style[] = 'grid-row:span ' . $row_span;
        }

        $out .= '<figure class="' . esc_attr(implode(' ', array_unique($classes))) . '"';
        if ($cell_style) {
            $out .= ' style="' . esc_attr(implode(';', $cell_style)) . '"';
        }
        $out .= '>';

        $image_html = '';
        if ($attachment_id > 0) {
            $image_html = wp_get_attachment_image($attachment_id, 'large', false, [
                'alt' => $alt,
                'loading' => 'lazy',
                'decoding' => 'async',
            ]);
        }

        if ($image_html === '' && $url !== '') {
            $image_html = '<img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" loading="lazy" decoding="async">';
        }

        if ($safe_link_url !== '') {
            $out .= '<a href="' . $safe_link_url . '">' . $image_html . '</a>';
        } else {
            $out .= $image_html;
        }

        if ($caption_html !== '' || $caption !== '' || $kicker !== '' || $title !== '' || $text !== '') {
            $caption_classes = array_merge(
                ['iss-dense-image-wall__caption'],
                iss_frontend_dense_image_wall_class_list($caption_class)
            );
            $out .= '<figcaption class="' . esc_attr(implode(' ', array_unique($caption_classes))) . '">';
            if ($caption_html !== '') {
                $out .= wp_kses_post($caption_html);
            } elseif ($kicker !== '' || $title !== '' || $text !== '') {
                if ($kicker !== '') {
                    $out .= '<p class="iss-dense-image-wall__kicker">' . esc_html($kicker) . '</p>';
                }
                if ($title !== '') {
                    $out .= '<p class="iss-dense-image-wall__title">' . esc_html($title) . '</p>';
                }
                if ($text !== '') {
                    $out .= '<p class="iss-dense-image-wall__text">' . esc_html($text) . '</p>';
                }
            } else {
                $out .= esc_html($caption);
            }
            $out .= '</figcaption>';
        }

        $out .= '</figure>';
    }

    $out .= '</div>';

    return $out;
}
