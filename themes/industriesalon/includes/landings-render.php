<?php

if (!defined('ABSPATH')) {
    exit;
}

function industriesalon_get_editorial_landing_skins(): array
{
    return [
        'standard',
        'typografisch',
        'frontpage',
        'dossier',
    ];
}

add_filter('iss_editorial_format_skins', function (array $skins, string $format_slug): array {
    if ($format_slug !== 'landing') {
        return $skins;
    }

    return [
        'standard' => [
            'slug' => 'standard',
            'label' => __('Standard', 'industriesalon'),
        ],
        'typografisch' => [
            'slug' => 'typografisch',
            'label' => __('Typografisch', 'industriesalon'),
        ],
        'frontpage' => [
            'slug' => 'frontpage',
            'label' => __('Frontpage', 'industriesalon'),
        ],
        'dossier' => [
            'slug' => 'dossier',
            'label' => __('Dossier', 'industriesalon'),
        ],
    ];
}, 10, 2);

function industriesalon_resolve_editorial_landing_skin(array $document): string
{
    $skin = sanitize_key((string) ($document['skin'] ?? 'typografisch'));

    return in_array($skin, industriesalon_get_editorial_landing_skins(), true) ? $skin : 'typografisch';
}

function industriesalon_editorial_landing_is_enabled(int $post_id): bool
{
    if ($post_id <= 0 || !function_exists('iss_editorial_document_is_enabled') || !function_exists('iss_editorial_get_format_for_post')) {
        return false;
    }

    $format = iss_editorial_get_format_for_post($post_id);

    return (string) ($format['slug'] ?? '') === 'landing' && iss_editorial_document_is_enabled($post_id, 'landing');
}

function industriesalon_get_editorial_landing_post_skin(int $post_id): string
{
    if (!industriesalon_editorial_landing_is_enabled($post_id) || !function_exists('iss_editorial_get_read_model')) {
        return '';
    }

    return industriesalon_resolve_editorial_landing_skin(iss_editorial_get_read_model($post_id, 'landing', false));
}

function industriesalon_editorial_landing_treatment_slug(array $section): string
{
    $type = sanitize_key((string) ($section['type'] ?? 'gateway'));
    $defaults = [
        'statement' => 'statement.lead',
        'fliesstext' => 'text.standard',
        'gateway' => 'gateway.cards',
        'feature' => 'feature.media-panel',
        'dynamic_slot' => 'slot.projects',
    ];
    $allowed = [
        'statement' => ['statement.lead', 'statement.callout'],
        'fliesstext' => ['text.standard'],
        'gateway' => ['gateway.cards', 'gateway.link-list', 'gateway.feature-strip'],
        'feature' => ['feature.media-panel', 'feature.media-text', 'feature.microblocks'],
        'dynamic_slot' => ['slot.projects', 'slot.timeline', 'slot.visit-info', 'slot.newsletter'],
    ];
    $default = $defaults[$type] ?? 'gateway.cards';
    $treatment = strtolower((string) ($section['treatment'] ?? $default));
    $treatment = (string) preg_replace('/[^a-z0-9_.-]/', '', $treatment);

    return in_array($treatment, $allowed[$type] ?? [], true) ? $treatment : $default;
}

function industriesalon_editorial_landing_class_slug(string $value): string
{
    return sanitize_html_class(str_replace(['.', '_'], '-', $value));
}

function industriesalon_editorial_landing_section_classes(array $section, string $skin, int $item_count = 0): array
{
    $type = sanitize_key((string) ($section['type'] ?? 'section'));
    $anchor = sanitize_title((string) ($section['anchor'] ?? ''));
    $slot_key = sanitize_key((string) ($section['slot_key'] ?? ''));
    $treatment = industriesalon_editorial_landing_treatment_slug($section);
    $classes = [
        'iss-section',
        'iss-landing-section',
        'iss-landing-section--gesture-' . sanitize_html_class($type),
        'iss-landing-section--skin-' . sanitize_html_class($skin),
        'iss-landing-section--treatment-' . industriesalon_editorial_landing_class_slug($treatment),
    ];

    if ($item_count > 0) {
        $classes[] = 'iss-landing-section--items-' . min(4, max(0, $item_count));
    }
    if ($type === 'statement') {
        $classes[] = 'iss-front-schoneweide-statement';
    }
    if ($type === 'feature' && $treatment === 'feature.media-text') {
        $classes[] = 'iss-media-text';
        $classes[] = 'iss-media-text--50-50';
        $classes[] = 'iss-media-text--gap-l';
    }
    if ($anchor === 'vor-ort') {
        $classes[] = 'iss-front-explore';
    }
    if ($anchor === 'archiv-wissen') {
        $classes[] = 'iss-4-card-row';
    }
    if ($anchor === 'raum-nutzen') {
        $classes[] = 'iss-section--rental';
    }
    if ($anchor === 'industriesalon') {
        $classes[] = 'iss-media-text';
        $classes[] = 'iss-media-text--45-55';
        $classes[] = 'iss-media-text--gap-l';
        $classes[] = 'iss-media-text--flip';
        $classes[] = 'iss-media-text--overlay-heading';
        $classes[] = 'section--alt';
    }
    if ($slot_key === 'front-projects') {
        $classes[] = 'iss-front-project-notes';
    } elseif ($slot_key === 'front-timeline') {
        $classes[] = 'iss-section--timeline';
    } elseif ($slot_key === 'front-visit-info') {
        $classes[] = 'iss-section--visit-info';
    } elseif ($slot_key === 'front-newsletter') {
        $classes[] = 'iss-section--newsletter';
    }

    return array_unique($classes);
}

function industriesalon_editorial_landing_section_attrs(array $section, string $skin, int $item_count = 0): string
{
    $type = sanitize_key((string) ($section['type'] ?? 'section'));
    $treatment = industriesalon_editorial_landing_treatment_slug($section);
    $anchor = sanitize_title((string) ($section['anchor'] ?? ''));
    $attrs = 'class="' . esc_attr(implode(' ', industriesalon_editorial_landing_section_classes($section, $skin, $item_count))) . '"';
    if ($anchor !== '') {
        $attrs .= ' id="' . esc_attr($anchor) . '"';
    }
    $attrs .= ' data-section-gesture="' . esc_attr($type) . '" data-section-treatment="' . esc_attr($treatment) . '"';

    return $attrs;
}

function industriesalon_render_editorial_landing_links(array $links): string
{
    $html = '';
    foreach ($links as $link) {
        if (!is_array($link)) {
            continue;
        }
        $label = trim((string) ($link['label'] ?? ''));
        $url = industriesalon_editorial_landing_link_url($link);
        if ($label === '' || $url === '') {
            continue;
        }
        $html .= '<a class="iss-button iss-landing-section__action" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }

    if ($html === '') {
        return '';
    }

    return '<div class="iss-landing-section__actions">' . $html . '</div>';
}

function industriesalon_editorial_landing_link_url(array $link): string
{
    $page_id = absint($link['page_id'] ?? 0);
    if ($page_id > 0 && get_post_type($page_id) === 'page' && get_post_status($page_id) === 'publish') {
        return (string) get_permalink($page_id);
    }

    return trim((string) ($link['url'] ?? ''));
}

function industriesalon_render_editorial_landing_copy(array $section): string
{
    $kicker = trim((string) ($section['kicker'] ?? ''));
    $title = trim((string) ($section['title'] ?? ''));
    $body = trim((string) ($section['body'] ?? ''));
    $links = is_array($section['links'] ?? null) ? $section['links'] : [];
    $links_html = industriesalon_render_editorial_landing_links($links);

    if ($kicker === '' && $title === '' && $body === '' && $links_html === '') {
        return '';
    }

    ob_start();
    ?>
    <div class="iss-landing-section__copy">
        <?php if ($kicker !== '') : ?>
            <p class="iss-kicker iss-kicker--compact iss-landing-section__kicker"><?php echo esc_html($kicker); ?></p>
        <?php endif; ?>
        <?php if ($title !== '') : ?>
            <h2 class="iss-landing-section__title"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>
        <?php if ($body !== '') : ?>
            <div class="iss-landing-section__body"><?php echo wp_kses_post(wpautop($body)); ?></div>
        <?php endif; ?>
        <?php echo $links_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Link output is escaped in helper. ?>
    </div>
    <?php
    return trim((string) ob_get_clean());
}

function industriesalon_render_editorial_landing_item_media(array $item): string
{
    $refs = is_array($item['media_refs'] ?? null) ? $item['media_refs'] : [];
    $reference = is_array($refs[0] ?? null) ? $refs[0] : [];
    $attachment_id = absint($reference['id'] ?? 0);
    if ($attachment_id <= 0) {
        return '';
    }

    $caption = trim((string) ($reference['label'] ?? ''));
    ob_start();
    ?>
    <figure class="iss-landing-gateway__media">
        <?php echo wp_get_attachment_image($attachment_id, 'medium_large', false, ['loading' => 'lazy']); ?>
        <?php if ($caption !== '') : ?>
            <figcaption><?php echo esc_html($caption); ?></figcaption>
        <?php endif; ?>
    </figure>
    <?php
    return trim((string) ob_get_clean());
}

function industriesalon_render_editorial_landing_gateway_item(array $item): string
{
    $label = trim((string) ($item['label'] ?? ''));
    $text = trim((string) ($item['text'] ?? ''));
    $url = industriesalon_editorial_landing_link_url($item);
    if ($label === '' || $url === '') {
        return '';
    }

    $media = industriesalon_render_editorial_landing_item_media($item);
    ob_start();
    ?>
    <article class="iss-landing-gateway__item">
        <a class="iss-landing-gateway__link" href="<?php echo esc_url($url); ?>">
            <?php echo $media; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Media is rendered through wp_get_attachment_image above. ?>
            <span class="iss-landing-gateway__item-body">
                <strong class="iss-landing-gateway__item-title"><?php echo esc_html($label); ?></strong>
                <?php if ($text !== '') : ?>
                    <span class="iss-landing-gateway__item-text"><?php echo esc_html($text); ?></span>
                <?php endif; ?>
            </span>
        </a>
    </article>
    <?php
    return trim((string) ob_get_clean());
}

function industriesalon_render_editorial_landing_gateway(array $section, int $rendered_index, string $skin): string
{
    $items = is_array($section['items'] ?? null) ? $section['items'] : [];
    $copy_html = industriesalon_render_editorial_landing_copy($section);
    $items_html = '';
    $item_count = 0;

    foreach ($items as $item) {
        if (is_array($item)) {
            $item_html = industriesalon_render_editorial_landing_gateway_item($item);
            if ($item_html !== '') {
                $items_html .= $item_html;
                ++$item_count;
            }
        }
    }

    if ($copy_html === '' && $items_html === '') {
        return '';
    }

    ob_start();
    ?>
    <section <?php echo industriesalon_editorial_landing_section_attrs($section, $skin, $item_count); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are escaped in helper. ?>>
        <div class="iss-container iss-landing-section__inner">
            <?php echo $copy_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Copy output is escaped in helper. ?>
            <?php if ($items_html !== '') : ?>
                <div class="iss-landing-gateway" data-item-count="<?php echo esc_attr((string) $item_count); ?>">
                    <?php echo $items_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Items are escaped in industriesalon_render_editorial_landing_gateway_item(). ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php
    unset($rendered_index);
    return trim((string) ob_get_clean());
}

function industriesalon_render_editorial_landing_statement(array $section, int $rendered_index, string $skin): string
{
    $copy_html = industriesalon_render_editorial_landing_copy($section);
    if ($copy_html === '') {
        return '';
    }

    ob_start();
    ?>
    <section <?php echo industriesalon_editorial_landing_section_attrs($section, $skin); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are escaped in helper. ?>>
        <div class="iss-container iss-landing-section__inner">
            <?php echo $copy_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Copy output is escaped in helper. ?>
        </div>
    </section>
    <?php
    unset($rendered_index);
    return trim((string) ob_get_clean());
}

function industriesalon_render_editorial_landing_text(array $section, int $rendered_index, string $skin): string
{
    $copy_html = industriesalon_render_editorial_landing_copy($section);
    if ($copy_html === '') {
        return '';
    }

    ob_start();
    ?>
    <section <?php echo industriesalon_editorial_landing_section_attrs($section, $skin); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are escaped in helper. ?>>
        <div class="iss-container iss-landing-section__inner iss-landing-text">
            <?php echo $copy_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Copy output is escaped in helper. ?>
        </div>
    </section>
    <?php
    unset($rendered_index);
    return trim((string) ob_get_clean());
}

function industriesalon_render_editorial_landing_feature_media(array $section): string
{
    $refs = is_array($section['media_refs'] ?? null) ? $section['media_refs'] : [];
    $reference = is_array($refs[0] ?? null) ? $refs[0] : [];
    $attachment_id = absint($reference['id'] ?? 0);
    if ($attachment_id <= 0) {
        return '';
    }

    return '<figure class="iss-landing-feature__media">' . wp_get_attachment_image($attachment_id, 'large', false, ['loading' => 'lazy']) . '</figure>';
}

function industriesalon_render_editorial_landing_media_text_media(array $section): string
{
    $refs = is_array($section['media_refs'] ?? null) ? $section['media_refs'] : [];
    $reference = is_array($refs[0] ?? null) ? $refs[0] : [];
    $attachment_id = absint($reference['id'] ?? 0);
    if ($attachment_id <= 0) {
        return '';
    }

    $caption = trim((string) ($reference['label'] ?? ''));
    $image = wp_get_attachment_image($attachment_id, 'large', false, ['loading' => 'lazy']);
    if ($image === '') {
        return '';
    }

    $html = '<figure class="iss-media-text__image">' . $image;
    if ($caption !== '') {
        $html .= '<figcaption class="wp-element-caption">' . esc_html($caption) . '</figcaption>';
    }
    $html .= '</figure>';

    return $html;
}

function industriesalon_render_editorial_landing_facts(array $facts): string
{
    $html = '';
    foreach ($facts as $fact) {
        if (!is_array($fact)) {
            continue;
        }
        $value = trim((string) ($fact['value'] ?? ''));
        $label = trim((string) ($fact['label'] ?? ''));
        if ($value === '' && $label === '') {
            continue;
        }
        $html .= '<li class="iss-landing-feature__fact">';
        if ($value !== '') {
            $html .= '<strong class="iss-landing-feature__fact-value">' . esc_html($value) . '</strong>';
        }
        if ($label !== '') {
            $html .= '<span class="iss-landing-feature__fact-label">' . esc_html($label) . '</span>';
        }
        $html .= '</li>';
    }

    return $html !== '' ? '<ul class="iss-landing-feature__facts">' . $html . '</ul>' : '';
}

function industriesalon_render_editorial_landing_microblocks(array $facts): string
{
    $html = '';
    $icons = ['location', 'award', 'group', 'roof'];
    foreach ($facts as $index => $fact) {
        if (!is_array($fact)) {
            continue;
        }
        $title = trim((string) ($fact['value'] ?? ''));
        $text = trim((string) ($fact['label'] ?? ''));
        if ($title === '' && $text === '') {
            continue;
        }
        $icon = $icons[$index % count($icons)];
        $html .= '<div class="iss-microblock">';
        $html .= '<p class="iss-microblock__icon" aria-hidden="true"><span class="iss-icon iss-icon--' . esc_attr($icon) . ' iss-icon--accent" aria-hidden="true"></span></p>';
        $html .= '<div class="iss-microblock__content">';
        if ($title !== '') {
            $html .= '<h4 class="iss-microblock__title">' . esc_html($title) . '</h4>';
        }
        if ($text !== '') {
            $html .= '<p class="iss-microblock__text">' . esc_html($text) . '</p>';
        }
        $html .= '</div></div>';
    }

    return $html !== '' ? '<div class="iss-microblocks">' . $html . '</div>' : '';
}

function industriesalon_render_editorial_landing_media_text_overlay_copy(array $section): string
{
    $kicker = trim((string) ($section['kicker'] ?? ''));
    $title = trim((string) ($section['title'] ?? ''));
    $body = trim((string) ($section['body'] ?? ''));
    if ($kicker === '' && $title === '' && $body === '') {
        return '';
    }

    ob_start();
    ?>
    <div class="iss-heading iss-media-text__heading iss-landing-surface iss-landing-surface--dark">
        <?php if ($kicker !== '') : ?>
            <p class="iss-kicker"><?php echo esc_html($kicker); ?></p>
        <?php endif; ?>
        <?php if ($title !== '') : ?>
            <h2 class="iss-heading__title iss-media-text__title"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>
        <?php if ($body !== '') : ?>
            <p class="iss-heading__text iss-media-text__text"><?php echo esc_html(wp_strip_all_tags($body)); ?></p>
        <?php endif; ?>
    </div>
    <?php
    return trim((string) ob_get_clean());
}

function industriesalon_render_editorial_landing_media_text_copy(array $section): string
{
    $kicker = trim((string) ($section['kicker'] ?? ''));
    $title = trim((string) ($section['title'] ?? ''));
    $body = trim((string) ($section['body'] ?? ''));
    $links_html = industriesalon_render_editorial_landing_links(is_array($section['links'] ?? null) ? $section['links'] : []);
    if ($kicker === '' && $title === '' && $body === '' && $links_html === '') {
        return '';
    }

    ob_start();
    ?>
    <div class="iss-media-text__copy">
        <?php if ($kicker !== '') : ?>
            <p class="iss-kicker"><?php echo esc_html($kicker); ?></p>
        <?php endif; ?>
        <?php if ($title !== '') : ?>
            <h2 class="iss-heading__title iss-media-text__title"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>
        <?php if ($body !== '') : ?>
            <div class="iss-heading__text iss-media-text__text"><?php echo wp_kses_post($body); ?></div>
        <?php endif; ?>
        <?php echo $links_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Links are escaped in helper. ?>
    </div>
    <?php
    return trim((string) ob_get_clean());
}

function industriesalon_render_editorial_landing_media_text_actions(array $links): string
{
    $link = is_array($links[0] ?? null) ? $links[0] : [];
    $label = trim((string) ($link['label'] ?? ''));
    $url = industriesalon_editorial_landing_link_url($link);
    if ($label === '' || $url === '') {
        return '';
    }

    return '<p class="iss-media-text__actions"><a class="iss-media-text__link" href="' . esc_url($url) . '">' . esc_html($label) . '</a></p>';
}

function industriesalon_render_editorial_landing_feature_media_text(array $section, int $rendered_index, string $skin): string
{
    $copy_html = industriesalon_render_editorial_landing_media_text_copy($section);
    $facts_html = industriesalon_render_editorial_landing_facts(is_array($section['facts'] ?? null) ? $section['facts'] : []);
    $media_html = industriesalon_render_editorial_landing_media_text_media($section);
    if ($copy_html === '' && $facts_html === '' && $media_html === '') {
        return '';
    }

    ob_start();
    ?>
    <section <?php echo industriesalon_editorial_landing_section_attrs($section, $skin); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are escaped in helper. ?>>
        <div class="iss-container">
            <div class="iss-media-text__layout">
                <div class="iss-media-text__content">
                    <div class="iss-media-text__inner iss-landing-surface iss-landing-surface--light">
                        <?php echo $copy_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Copy is escaped in helper. ?>
                        <?php echo $facts_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Facts are escaped in helper. ?>
                    </div>
                </div>
                <?php if ($media_html !== '') : ?>
                    <div class="iss-media-text__media-col">
                        <div class="iss-media-text__media iss-media-card iss-media-card--cover iss-media-card--flat">
                            <?php echo $media_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Media output uses wp_get_attachment_image. ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php
    unset($rendered_index);
    return trim((string) ob_get_clean());
}

function industriesalon_render_editorial_landing_feature_microblocks(array $section, int $rendered_index, string $skin): string
{
    $facts_html = industriesalon_render_editorial_landing_microblocks(is_array($section['facts'] ?? null) ? $section['facts'] : []);
    $actions_html = industriesalon_render_editorial_landing_media_text_actions(is_array($section['links'] ?? null) ? $section['links'] : []);
    $overlay_html = industriesalon_render_editorial_landing_media_text_overlay_copy($section);
    $media_html = industriesalon_render_editorial_landing_media_text_media($section);
    if ($facts_html === '' && $actions_html === '' && $overlay_html === '' && $media_html === '') {
        return '';
    }

    ob_start();
    ?>
    <section <?php echo industriesalon_editorial_landing_section_attrs($section, $skin); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are escaped in helper. ?>>
        <div class="iss-container">
            <div class="iss-media-text__layout">
                <div class="iss-media-text__content">
                    <div class="iss-media-text__inner">
                        <?php echo $facts_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Microblocks are escaped in helper. ?>
                        <?php echo $actions_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Actions are escaped in helper. ?>
                    </div>
                </div>
                <div class="iss-media-text__media-col">
                    <div class="iss-media-text__media iss-media-card iss-media-card--red iss-media-card--cover">
                        <?php echo $overlay_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Copy is escaped in helper. ?>
                        <?php echo $media_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Media output uses wp_get_attachment_image. ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php
    unset($rendered_index);
    return trim((string) ob_get_clean());
}

function industriesalon_render_editorial_landing_feature(array $section, int $rendered_index, string $skin): string
{
    $treatment = industriesalon_editorial_landing_treatment_slug($section);
    if ($treatment === 'feature.media-text') {
        return industriesalon_render_editorial_landing_feature_media_text($section, $rendered_index, $skin);
    }
    if ($treatment === 'feature.microblocks') {
        return industriesalon_render_editorial_landing_feature_microblocks($section, $rendered_index, $skin);
    }

    $copy_html = industriesalon_render_editorial_landing_copy($section);
    $facts_html = industriesalon_render_editorial_landing_facts(is_array($section['facts'] ?? null) ? $section['facts'] : []);
    $media_html = industriesalon_render_editorial_landing_feature_media($section);
    if ($copy_html === '' && $facts_html === '' && $media_html === '') {
        return '';
    }

    ob_start();
    ?>
    <section <?php echo industriesalon_editorial_landing_section_attrs($section, $skin); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are escaped in helper. ?>>
        <div class="iss-container iss-landing-section__inner iss-landing-feature">
            <div class="iss-landing-feature__main iss-landing-surface iss-landing-surface--dark">
                <?php echo $copy_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Copy output is escaped in helper. ?>
                <?php echo $facts_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Facts are escaped in helper. ?>
            </div>
            <?php echo $media_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Media output uses wp_get_attachment_image. ?>
        </div>
    </section>
    <?php
    unset($rendered_index);
    return trim((string) ob_get_clean());
}

function industriesalon_render_front_projects_slot(): string
{
    $query = new WP_Query([
        'post_type' => 'projekt',
        'posts_per_page' => 2,
        'orderby' => 'menu_order',
        'order' => 'ASC',
        'no_found_rows' => true,
    ]);
    ob_start();
    ?>
    <div class="iss-front-project-notes__inner">
        <div class="iss-front-project-notes__items">
            <?php if ($query->have_posts()) : ?>
                <?php while ($query->have_posts()) : $query->the_post(); ?>
                    <article class="iss-hero-note iss-front-project-note">
                        <span class="iss-hero-note__marker iss-front-project-note__marker" aria-hidden="true"></span>
                        <?php if (has_post_thumbnail()) : ?>
                            <a class="iss-front-project-note__logo" href="<?php the_permalink(); ?>"><?php the_post_thumbnail('medium'); ?></a>
                        <?php endif; ?>
                        <p class="iss-hero-note__kicker iss-front-project-note__kicker"><?php esc_html_e('Projekt', 'industriesalon'); ?></p>
                        <h3 class="iss-hero-note__title iss-front-project-note__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                        <div class="iss-hero-note__text iss-front-project-note__text"><?php echo wp_kses_post(wpautop(wp_trim_words(get_the_excerpt(), 28, ''))); ?></div>
                    </article>
                <?php endwhile; ?>
                <?php wp_reset_postdata(); ?>
            <?php else : ?>
                <div class="iss-hero-note iss-front-project-note iss-front-project-note--empty">
                    <p class="iss-hero-note__kicker iss-front-project-note__kicker"><?php esc_html_e('Projekte', 'industriesalon'); ?></p>
                    <h3 class="iss-hero-note__title iss-front-project-note__title"><?php esc_html_e('Es sind noch keine Projekte veröffentlicht.', 'industriesalon'); ?></h3>
                </div>
            <?php endif; ?>
        </div>
        <div class="wp-block-buttons iss-front-project-notes__cta is-content-justification-center is-layout-flex wp-container-core-buttons-is-layout-1 wp-block-buttons-is-layout-flex">
            <div class="wp-block-button is-style-fill"><a class="wp-block-button__link wp-element-button" href="/projekte/"><?php esc_html_e('Alle Projekte ansehen', 'industriesalon'); ?></a></div>
        </div>
    </div>
    <?php
    return trim((string) ob_get_clean());
}

function industriesalon_render_front_timeline_slot(): string
{
    return do_blocks('<!-- wp:industriesalon/timeline-query {"shellMode":"body","limit":4,"showMeta":false,"includeRunningRanges":true,"fixedItemTypesList":["fuehrungen","veranstaltungen","ausstellungen"],"showItemTypeFilter":false,"groupRecurringTours":true,"showRecurringNote":false,"showBottomButton":true,"bottomButtonText":"Alle Termine ansehen","bottomButtonUrl":"/kalender/","ticketsButtonText":"Buchen","className":"iss-timeline--scheme-scarlet-red"} /-->');
}

function industriesalon_render_front_visit_info_slot(): string
{
    return '<div class="iss-visit-info iss-visit-info--info-panel">' . do_blocks('<!-- wp:industriesalon/visit-info {"shellMode":"body"} /-->') . '</div>';
}

function industriesalon_render_front_newsletter_slot(): string
{
    ob_start();
    ?>
    <div class="iss-newsletter-band__grid">
        <div class="iss-newsletter-band__main">
            <div class="iss-heading iss-heading--uncaged iss-newsletter-band__heading">
                <p class="iss-kicker"><?php esc_html_e('Auf dem Laufenden bleiben', 'industriesalon'); ?></p>
                <h2 class="iss-heading__title"><?php esc_html_e('Zum Newsletter anmelden', 'industriesalon'); ?></h2>
                <p class="iss-heading__text iss-newsletter-band__lead"><?php esc_html_e('Erhalten Sie Hinweise zu Veranstaltungen, Ausstellungen, Führungen und Neuigkeiten aus dem Industriesalon Schöneweide direkt per E-Mail.', 'industriesalon'); ?></p>
            </div>
            <div class="iss-newsletter-band__formwrap"><?php echo do_blocks('<!-- wp:iss/newsletter-form /-->'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Registered newsletter block owns its public markup. ?></div>
            <p class="iss-newsletter-band__note has-small-font-size"><?php echo wp_kses_post('Ich möchte aktuelle Infos aus dem Industriesalon erhalten und mich für den Newsletter anmelden. Eine Abmeldung ist jederzeit möglich. Mit der Eingabe meiner E-Mail-Adresse erkläre ich mich mit der <a href="/verein/#datenschutz">Datenschutzerklärung</a> einverstanden.'); ?></p>
        </div>
        <div class="iss-newsletter-band__side">
            <div class="iss-newsletter-band__support">
                <p class="iss-newsletter-band__support-title"><?php esc_html_e('Wir bedanken uns herzlich für die Unterstützung durch den', 'industriesalon'); ?></p>
                <figure class="iss-newsletter-band__logo"><?php echo wp_get_attachment_image(25727, 'full', false, ['loading' => 'lazy']); ?></figure>
            </div>
        </div>
    </div>
    <div class="iss-newsletter-band__partners">
        <p class="iss-newsletter-band__partners-title"><?php esc_html_e('Unsere Tourismuspartner:', 'industriesalon'); ?></p>
        <div class="iss-newsletter-band__partner-logos">
            <figure class="iss-newsletter-band__partner-logo iss-newsletter-band__partner-logo--visit-berlin"><img src="/wp-content/uploads/2019/04/Visti-Berlin-350x150-e1575551506288.png" alt="visitBerlin" loading="lazy"></figure>
            <figure class="iss-newsletter-band__partner-logo iss-newsletter-band__partner-logo--erih"><img src="/wp-content/uploads/2019/04/ERIH-Logo-350x150-e1575551585914.png" alt="ERIH - European Route of Industrial Heritage" loading="lazy"></figure>
            <figure class="iss-newsletter-band__partner-logo iss-newsletter-band__partner-logo--bzi"><img src="/wp-content/uploads/2019/12/BZI-e1575551630496.png" alt="Berliner Zentrum Industriekultur" loading="lazy"></figure>
        </div>
    </div>
    <?php
    return trim((string) ob_get_clean());
}

function industriesalon_render_editorial_landing_dynamic_slot(array $section, int $rendered_index, string $skin): string
{
    $slot_key = sanitize_key((string) ($section['slot_key'] ?? ''));
    $copy_html = industriesalon_render_editorial_landing_copy($section);
    $slot_html = '';
    if ($slot_key === 'front-projects') {
        $slot_html = industriesalon_render_front_projects_slot();
    } elseif ($slot_key === 'front-timeline') {
        $slot_html = industriesalon_render_front_timeline_slot();
    } elseif ($slot_key === 'front-visit-info') {
        $slot_html = industriesalon_render_front_visit_info_slot();
    } elseif ($slot_key === 'front-newsletter') {
        $slot_html = industriesalon_render_front_newsletter_slot();
    }

    if (trim($slot_html) === '') {
        return '';
    }

    ob_start();
    ?>
    <section <?php echo industriesalon_editorial_landing_section_attrs($section, $skin); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are escaped in helper. ?>>
        <div class="iss-container iss-landing-section__inner iss-landing-dynamic-slot iss-landing-dynamic-slot--<?php echo esc_attr(industriesalon_editorial_landing_class_slug($slot_key)); ?>">
            <?php echo $copy_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Copy output is escaped in helper. ?>
            <?php echo $slot_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Slot renderers escape their internal output or delegate to registered blocks. ?>
        </div>
    </section>
    <?php
    unset($rendered_index);
    return trim((string) ob_get_clean());
}

function industriesalon_render_editorial_landing_section(array $section, int $rendered_index, string $skin): string
{
    $type = sanitize_key((string) ($section['type'] ?? ''));
    if ($type === 'statement') {
        return industriesalon_render_editorial_landing_statement($section, $rendered_index, $skin);
    }
    if ($type === 'fliesstext') {
        return industriesalon_render_editorial_landing_text($section, $rendered_index, $skin);
    }
    if ($type === 'gateway') {
        return industriesalon_render_editorial_landing_gateway($section, $rendered_index, $skin);
    }
    if ($type === 'feature') {
        return industriesalon_render_editorial_landing_feature($section, $rendered_index, $skin);
    }
    if ($type === 'dynamic_slot') {
        return industriesalon_render_editorial_landing_dynamic_slot($section, $rendered_index, $skin);
    }

    return '';
}

function industriesalon_editorial_landing_render_document(array $document): string
{
    $sections = is_array($document['sections'] ?? null) ? $document['sections'] : [];
    if (!$sections) {
        return '';
    }

    $skin = industriesalon_resolve_editorial_landing_skin($document);
    $html = '';
    $rendered_index = 0;
    foreach ($sections as $section) {
        if (!is_array($section)) {
            continue;
        }

        $section_html = industriesalon_render_editorial_landing_section($section, $rendered_index, $skin);
        if (trim($section_html) !== '') {
            $html .= $section_html;
            ++$rendered_index;
        }
    }

    if (trim($html) === '') {
        return '';
    }

    return '<div class="iss-landing-editorial iss-landing-editorial--skin-' . esc_attr(sanitize_html_class($skin)) . '">' . $html . '</div>';
}

function industriesalon_render_editorial_landing_content(string $content): string
{
    if (is_admin() || !is_singular('page') || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $post_id = get_the_ID();
    if ($post_id <= 0 || !industriesalon_editorial_landing_is_enabled((int) $post_id) || !function_exists('iss_editorial_get_read_model')) {
        return $content;
    }

    $prefer_autosave = is_preview() && current_user_can('edit_post', (int) $post_id);
    $document = iss_editorial_get_read_model((int) $post_id, 'landing', $prefer_autosave);
    $html = industriesalon_editorial_landing_render_document($document);
    if (trim($html) === '') {
        return $content;
    }

    return $html;
}
add_filter('the_content', 'industriesalon_render_editorial_landing_content', 12);

function industriesalon_front_page_landing_has_sections(): bool
{
    if (!is_front_page() || is_admin() || !function_exists('iss_editorial_get_read_model')) {
        return false;
    }

    static $has_sections = null;
    if ($has_sections !== null) {
        return $has_sections;
    }

    $post_id = (int) get_queried_object_id();
    if ($post_id <= 0 || !industriesalon_editorial_landing_is_enabled($post_id)) {
        $has_sections = false;
        return $has_sections;
    }

    $document = iss_editorial_get_read_model($post_id, 'landing', is_preview() && current_user_can('edit_post', $post_id));
    $sections = is_array($document['sections'] ?? null) ? $document['sections'] : [];
    $has_sections = false;
    foreach ($sections as $section) {
        if (!is_array($section)) {
            continue;
        }
        if (in_array((string) ($section['type'] ?? ''), ['statement', 'fliesstext', 'gateway', 'feature', 'dynamic_slot'], true)) {
            $has_sections = true;
            break;
        }
    }

    return $has_sections;
}

function industriesalon_suppress_front_page_landing_fallback_group(string $block_content, array $block): string
{
    if (!industriesalon_front_page_landing_has_sections()) {
        return $block_content;
    }

    $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
    $class_name = ' ' . (string) ($attrs['className'] ?? '') . ' ';
    $anchor = sanitize_title((string) ($attrs['anchor'] ?? ''));
    $fallback_classes = [
        'iss-front-schoneweide-statement',
        'iss-front-explore',
        'iss-front-project-notes',
        'iss-section--timeline',
        'iss-section--rental',
        'iss-media-text',
        'iss-section--visit-info',
        'iss-section--newsletter',
    ];

    foreach ($fallback_classes as $fallback_class) {
        if (strpos($class_name, ' ' . $fallback_class . ' ') !== false) {
            return '';
        }
    }

    if ($anchor === 'archiv-wissen' && strpos($class_name, ' iss-4-card-row ') !== false) {
        return '';
    }

    return $block_content;
}
add_filter('render_block_core/group', 'industriesalon_suppress_front_page_landing_fallback_group', 9, 2);

function industriesalon_render_editorial_landing_block(): string
{
    if (is_admin() || !is_singular('page')) {
        return '';
    }

    $post_id = (int) get_queried_object_id();
    if ($post_id <= 0 || !industriesalon_editorial_landing_is_enabled($post_id) || !function_exists('iss_editorial_get_read_model')) {
        return '';
    }

    $prefer_autosave = is_preview() && current_user_can('edit_post', $post_id);
    $document = iss_editorial_get_read_model($post_id, 'landing', $prefer_autosave);
    return industriesalon_editorial_landing_render_document($document);
}

function industriesalon_register_editorial_landing_block(): void
{
    register_block_type('industriesalon/editorial-landing', [
        'api_version' => 2,
        'render_callback' => 'industriesalon_render_editorial_landing_block',
    ]);
}
add_action('init', 'industriesalon_register_editorial_landing_block');
