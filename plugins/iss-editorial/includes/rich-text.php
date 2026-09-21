<?php
/** Versioned text capabilities; public presentation belongs to the theme. */
if (!defined('ABSPATH')) {
    exit;
}

function iss_editorial_supports_version(array $format, $version): bool
{
    return is_int($version) && in_array($version, $format['supported_versions'] ?? [1], true);
}

function iss_editorial_text_profile(array $format, string $type, string $field): string
{
    return ($format['document_version'] ?? 1) >= 2
        ? (string) ($format['sections'][$type]['rich_text'][$field] ?? '') : '';
}

function iss_editorial_rich_text_tags(string $profile): array
{
    $tags = ['br' => [], 'strong' => [], 'em' => [], 'span' => ['class' => true]];
    if ($profile !== 'inline-card') {
        $tags['a'] = ['href' => true, 'target' => true, 'rel' => true];
    }
    if ($profile === 'block') {
        $tags += ['p' => [], 'ul' => [], 'ol' => [], 'li' => []];
    }
    return $tags;
}

function iss_editorial_sanitize_rich_text(string $value, string $profile): string
{
    $value = wp_kses(iss_editorial_strip_unsafe_body_hrefs($value), iss_editorial_rich_text_tags($profile), ['http', 'https', 'mailto', 'tel']);
    $html = new WP_HTML_Tag_Processor($value);
    while ($html->next_tag('span')) {
        $classes = preg_split('/\s+/', (string) $html->get_attribute('class'));
        $classes = array_filter($classes, static fn($class) => preg_match('/^iss-(ink|mark)-[0-9a-f]{6}$/D', $class));
        if ($classes) {
            $html->set_attribute('class', implode(' ', array_unique($classes)));
        } else {
            $html->remove_attribute('class');
        }
    }
    $html = new WP_HTML_Tag_Processor($html->get_updated_html());
    while ($html->next_tag('a')) {
        if ($html->get_attribute('target') === '_blank') {
            $html->set_attribute('rel', 'noopener noreferrer');
        } else {
            $html->remove_attribute('target');
            $html->remove_attribute('rel');
        }
    }
    return $html->get_updated_html();
}

/** Reject lossy edits instead of silently removing unsupported imported markup. */
function iss_editorial_rich_text_is_supported(string $value, string $profile): bool
{
    $normalize = static fn($html) => preg_replace('/<br\s*\/?\s*>/i', '<br>', $html);
    return $normalize($value) === $normalize(iss_editorial_sanitize_rich_text($value, $profile));
}

function iss_editorial_text_palette(): array
{
    $palette = (array) wp_get_global_settings(['color', 'palette']);
    $colors = [];
    $origins = [($palette['theme'] ?? []) ?: ($palette['default'] ?? []), $palette['custom'] ?? []];
    foreach ($origins as $origin) {
        foreach ((array) $origin as $color) {
            $hex = sanitize_hex_color($color['color'] ?? '');
            if ($hex && strlen($hex) === 7) {
                $colors[strtolower($hex)] = ['color' => strtolower($hex), 'name' => sanitize_text_field($color['name'] ?? $hex)];
            }
        }
    }
    return array_values($colors);
}
