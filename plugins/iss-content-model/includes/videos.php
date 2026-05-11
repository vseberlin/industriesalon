<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_register_video_library_block(): void
{
    if (!function_exists('register_block_type')) {
        return;
    }

    register_block_type('iss/video-library', [
        'api_version' => 2,
        'render_callback' => 'iss_content_model_render_video_library_block',
    ]);

    register_block_type('iss/video-library-filters', [
        'api_version' => 2,
        'render_callback' => 'iss_content_model_render_video_library_filters_block',
    ]);

    register_block_type('iss/video-library-feature', [
        'api_version' => 2,
        'render_callback' => 'iss_content_model_render_video_library_feature_block',
    ]);

    register_block_type('iss/video-library-playlists', [
        'api_version' => 2,
        'render_callback' => 'iss_content_model_render_video_library_playlists_block',
    ]);

    register_block_type('iss/video-library-external', [
        'api_version' => 2,
        'render_callback' => 'iss_content_model_render_video_library_external_block',
    ]);

    register_block_type('iss/video-library-inventory', [
        'api_version' => 2,
        'render_callback' => 'iss_content_model_render_video_library_inventory_block',
    ]);

    register_block_type('iss/video-library-cta', [
        'api_version' => 2,
        'render_callback' => 'iss_content_model_render_video_library_cta_block',
    ]);

    register_block_type('iss/video-player', [
        'api_version' => 2,
        'render_callback' => 'iss_content_model_render_video_player_block',
    ]);

    register_block_type('iss/video-transcript', [
        'api_version' => 2,
        'render_callback' => 'iss_content_model_render_video_transcript_block',
    ]);
}
add_action('init', 'iss_content_model_register_video_library_block', 25);

function iss_content_model_enqueue_video_library_assets(): void
{
    wp_enqueue_script(
        'iss-content-model-video-library',
        plugins_url('../assets/video-library.js', __FILE__),
        [],
        ISS_CONTENT_MODEL_VERSION,
        true
    );
}

function iss_content_model_normalize_video_watch_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    return function_exists('iss_content_model_convert_youtube_embed_to_watch_url')
        ? iss_content_model_convert_youtube_embed_to_watch_url($url)
        : esc_url_raw($url);
}

function iss_content_model_get_video_embed_url(string $url): string
{
    $watch_url = iss_content_model_normalize_video_watch_url($url);
    if ($watch_url === '') {
        return '';
    }

    if (preg_match('~youtube\.com/watch\?v=([^&]+)~i', $watch_url, $matches)) {
        return 'https://www.youtube.com/embed/' . rawurlencode($matches[1]);
    }

    return $watch_url;
}

function iss_content_model_get_video_source_family_options(): array
{
    return [
        'core' => __('Eigener Bestand', 'iss-content-model'),
        'external_report' => __('Externer Bericht', 'iss-content-model'),
        'place_context' => __('Ort / Kontext', 'iss-content-model'),
    ];
}

function iss_content_model_get_video_source_family_label(string $family): string
{
    $options = iss_content_model_get_video_source_family_options();
    return $options[$family] ?? $options['core'];
}

function iss_content_model_normalize_video_source_family(string $family): string
{
    $family = sanitize_key($family);
    return array_key_exists($family, iss_content_model_get_video_source_family_options()) ? $family : 'core';
}

function iss_content_model_get_video_thumbnail_url(int $post_id, string $video_url): string
{
    $featured_image = get_the_post_thumbnail_url($post_id, 'large');
    if (is_string($featured_image) && $featured_image !== '') {
        return $featured_image;
    }

    $watch_url = iss_content_model_normalize_video_watch_url($video_url);
    if (preg_match('~youtube\.com/watch\?v=([^&]+)~i', $watch_url, $matches)) {
        return 'https://img.youtube.com/vi/' . rawurlencode($matches[1]) . '/hqdefault.jpg';
    }

    return '';
}

function iss_content_model_get_video_cards(): array
{
    $posts = get_posts([
        'post_type' => ISS_CONTENT_MODEL_VIDEO_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => [
            'menu_order' => 'ASC',
            'date' => 'DESC',
        ],
        'suppress_filters' => true,
    ]);

    if (!$posts) {
        return [];
    }

    $cards = [];
    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $video_url = trim((string) get_post_meta($post->ID, 'iss_video_url', true));
        if ($video_url === '') {
            continue;
        }

        $source_url = trim((string) get_post_meta($post->ID, 'iss_video_source_url', true));
        $excerpt = has_excerpt($post) ? get_the_excerpt($post) : wp_trim_words(wp_strip_all_tags((string) $post->post_content), 28);
        $terms = get_the_terms($post, ISS_CONTENT_MODEL_VIDEO_CATEGORY_TAXONOMY);
        $categories = [];
        $primary_term = null;

        if (is_array($terms)) {
            foreach ($terms as $term) {
                if (!$term instanceof WP_Term) {
                    continue;
                }

                $categories[] = [
                    'id' => (int) $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                ];

                if ($primary_term === null) {
                    $primary_term = $term;
                }
            }
        }

        $cards[] = [
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
            'excerpt' => $excerpt,
            'permalink' => get_permalink($post),
            'video_url' => esc_url($video_url),
            'source_url' => esc_url($source_url !== '' ? $source_url : $video_url),
            'source_family' => iss_content_model_normalize_video_source_family((string) get_post_meta($post->ID, 'iss_video_source_family', true)),
            'source_label' => trim((string) get_post_meta($post->ID, 'iss_video_source_label', true)),
            'year' => trim((string) get_post_meta($post->ID, 'iss_video_year', true)),
            'featured' => !empty(get_post_meta($post->ID, 'iss_video_featured', true)),
            'has_transcript' => trim((string) get_post_field('post_content', $post->ID)) !== '',
            'embed_url' => esc_url(iss_content_model_get_video_embed_url($video_url)),
            'thumbnail_url' => esc_url(iss_content_model_get_video_thumbnail_url((int) $post->ID, $video_url)),
            'categories' => $categories,
            'primary_category' => $primary_term instanceof WP_Term ? [
                'name' => $primary_term->name,
                'slug' => $primary_term->slug,
            ] : null,
        ];
    }

    return $cards;
}

function iss_content_model_sort_video_groups(array $groups): array
{
    $preferred_order = [
        'zeitzeugen',
        'werk-technik',
        'orte-wandel',
        'fuehrungen',
        'gespraeche-debatten',
    ];

    uksort($groups, static function ($a, $b) use ($preferred_order) {
        $a_index = array_search($a, $preferred_order, true);
        $b_index = array_search($b, $preferred_order, true);
        $a_index = $a_index === false ? 999 : $a_index;
        $b_index = $b_index === false ? 999 : $b_index;

        if ($a_index === $b_index) {
            return strcmp($a, $b);
        }

        return $a_index <=> $b_index;
    });

    return $groups;
}

function iss_content_model_group_video_cards(array $cards): array
{
    $groups = [];

    foreach ($cards as $card) {
        $categories = $card['categories'];
        if (!$categories) {
            $groups['ohne-kategorie']['term'] = [
                'name' => __('Weiteres Material', 'iss-content-model'),
                'slug' => 'ohne-kategorie',
            ];
            $groups['ohne-kategorie']['cards'][] = $card;
            continue;
        }

        foreach ($categories as $category) {
            $slug = (string) $category['slug'];
            if (!isset($groups[$slug])) {
                $groups[$slug] = [
                    'term' => $category,
                    'cards' => [],
                ];
            }

            $groups[$slug]['cards'][] = $card;
        }
    }

    return iss_content_model_sort_video_groups($groups);
}

function iss_content_model_pick_featured_video(array $cards): ?array
{
    foreach ($cards as $card) {
        if (!empty($card['featured'])) {
            return $card;
        }
    }

    return $cards[0] ?? null;
}

function iss_content_model_get_playlist_copy(string $slug, string $term_name): array
{
    $map = [
        'zeitzeugen' => [
            'label' => __('3 Stimmen zum Wandel', 'iss-content-model'),
            'text' => __('Gespräche, in denen Werkalltag, Übergänge und Erfahrungswissen zuerst über Menschen und ihre Erinnerungen lesbar werden.', 'iss-content-model'),
            'relation' => __('Weiter in Archiv und Transkript', 'iss-content-model'),
        ],
        'werk-technik' => [
            'label' => __('Werkorte in Bewegung', 'iss-content-model'),
            'text' => __('Filme zu Produktion, Technik und industriellen Räumen. Hier wird die Sammlung als bewegtes Material sichtbar.', 'iss-content-model'),
            'relation' => __('Weiter in Sammlung und Werkgeschichte', 'iss-content-model'),
        ],
        'orte-wandel' => [
            'label' => __('Vom Film zum Ort', 'iss-content-model'),
            'text' => __('Videos, die Fabriken, Straeume und Umbrueche mit Karten, Ortsdossiers und Stadtraum verbinden.', 'iss-content-model'),
            'relation' => __('Weiter im Atlas', 'iss-content-model'),
        ],
        'fuehrungen' => [
            'label' => __('Vom Film zur Fuehrung', 'iss-content-model'),
            'text' => __('Bewegte Bilder, die sich in Rundgaengen, Stationen und oeffentlichen Formaten fortsetzen.', 'iss-content-model'),
            'relation' => __('Weiter zu Fuehrungen', 'iss-content-model'),
        ],
        'gespraeche-debatten' => [
            'label' => __('Oeffentliche Stimmen', 'iss-content-model'),
            'text' => __('Gespräche und Debatten, in denen Industriegeschichte in gegenwärtige Fragen und öffentliche Diskussionen übergeht.', 'iss-content-model'),
            'relation' => __('Weiter ins Programm', 'iss-content-model'),
        ],
    ];

    if (isset($map[$slug])) {
        return $map[$slug];
    }

    return [
        'label' => $term_name,
        'text' => __('Eine kuratierte Folge von Videos aus demselben thematischen Zusammenhang.', 'iss-content-model'),
        'relation' => __('Weiter in der Bibliothek', 'iss-content-model'),
    ];
}

function iss_content_model_render_video_card(array $card): string
{
    $category_names = implode(', ', array_filter(array_map(static function ($category) {
        return (string) ($category['name'] ?? '');
    }, $card['categories'])));

    ob_start();
    ?>
    <article class="iss-video-card" data-video-id="<?php echo esc_attr((string) $card['id']); ?>">
        <button
            type="button"
            class="iss-video-card__trigger"
            data-video-id="<?php echo esc_attr((string) $card['id']); ?>"
            data-video-embed="<?php echo esc_url($card['embed_url']); ?>"
            data-video-title="<?php echo esc_attr($card['title']); ?>"
            data-video-text="<?php echo esc_attr($card['excerpt']); ?>"
            data-video-year="<?php echo esc_attr($card['year']); ?>"
            data-video-categories="<?php echo esc_attr($category_names); ?>"
            data-video-url="<?php echo esc_url($card['source_url']); ?>"
            data-video-permalink="<?php echo esc_url($card['permalink']); ?>"
            data-video-has-transcript="<?php echo $card['has_transcript'] ? '1' : '0'; ?>"
        >
            <span class="iss-video-card__media">
                <?php if ($card['thumbnail_url'] !== '') : ?>
                    <img src="<?php echo esc_url($card['thumbnail_url']); ?>" alt="<?php echo esc_attr($card['title']); ?>">
                <?php else : ?>
                    <span class="iss-video-card__fallback" aria-hidden="true"></span>
                <?php endif; ?>
            </span>
            <span class="iss-video-card__body">
                <span class="iss-video-card__title"><span><?php echo esc_html($card['title']); ?></span></span>
                <?php if ($card['excerpt'] !== '') : ?>
                    <span class="iss-video-card__text"><?php echo esc_html($card['excerpt']); ?></span>
                <?php endif; ?>
                <span class="iss-video-card__meta">
                    <?php
                    $meta_bits = array_filter([
                        $card['year'],
                        $card['source_label'],
                    ]);
                    echo esc_html(implode(' · ', $meta_bits));
                    ?>
                </span>
            </span>
        </button>
    </article>
    <?php

    return (string) ob_get_clean();
}

function iss_content_model_render_video_playlist_item(array $card, string $relation_line = ''): string
{
    $category_names = implode(', ', array_filter(array_map(static function ($category) {
        return (string) ($category['name'] ?? '');
    }, $card['categories'])));

    ob_start();
    ?>
    <article class="iss-video-card iss-video-card--playlist" data-video-id="<?php echo esc_attr((string) $card['id']); ?>">
        <button
            type="button"
            class="iss-video-card__trigger iss-video-playlist__item-trigger"
            data-video-id="<?php echo esc_attr((string) $card['id']); ?>"
            data-video-embed="<?php echo esc_url($card['embed_url']); ?>"
            data-video-title="<?php echo esc_attr($card['title']); ?>"
            data-video-text="<?php echo esc_attr($card['excerpt']); ?>"
            data-video-year="<?php echo esc_attr($card['year']); ?>"
            data-video-categories="<?php echo esc_attr($category_names); ?>"
            data-video-url="<?php echo esc_url($card['source_url']); ?>"
            data-video-permalink="<?php echo esc_url($card['permalink']); ?>"
            data-video-has-transcript="<?php echo $card['has_transcript'] ? '1' : '0'; ?>"
        >
            <span class="iss-video-playlist__item-media">
                <?php if ($card['thumbnail_url'] !== '') : ?>
                    <img src="<?php echo esc_url($card['thumbnail_url']); ?>" alt="<?php echo esc_attr($card['title']); ?>">
                <?php else : ?>
                    <span class="iss-video-card__fallback" aria-hidden="true"></span>
                <?php endif; ?>
            </span>
            <span class="iss-video-playlist__item-body">
                <span class="iss-video-playlist__item-title"><?php echo esc_html($card['title']); ?></span>
                <span class="iss-video-playlist__item-meta">
                    <?php
                    $meta_bits = array_filter([
                        $card['year'],
                        $card['source_label'],
                    ]);
                    echo esc_html(implode(' · ', $meta_bits));
                    ?>
                </span>
                <?php if ($relation_line !== '') : ?>
                    <span class="iss-video-playlist__item-relation"><?php echo esc_html($relation_line); ?></span>
                <?php endif; ?>
            </span>
        </button>
    </article>
    <?php

    return (string) ob_get_clean();
}

function iss_content_model_render_video_playlist_section(array $group, bool $with_head = true): string
{
    $slug = (string) ($group['term']['slug'] ?? '');
    $term_name = (string) ($group['term']['name'] ?? '');
    $cards = array_values($group['cards'] ?? []);
    if (!$cards) {
        return '';
    }

    $lead = $cards[0];
    $queue = array_slice($cards, 1, 5);
    $copy = iss_content_model_get_playlist_copy($slug, $term_name);
    $category_names = implode(', ', array_filter(array_map(static function ($category) {
        return (string) ($category['name'] ?? '');
    }, $lead['categories'])));

    ob_start();
    ?>
    <section id="<?php echo esc_attr($slug); ?>" class="iss-video-library__section iss-video-library__section--playlist">
        <?php if ($with_head) : ?>
            <div class="iss-video-library__section-head">
                <p class="iss-video-library__playlist-label"><?php echo esc_html((string) $copy['label']); ?></p>
                <h3 class="iss-video-library__section-title"><?php echo esc_html($term_name); ?></h3>
                <p class="iss-video-library__playlist-text"><?php echo esc_html((string) $copy['text']); ?></p>
            </div>
        <?php endif; ?>
        <div class="iss-video-playlist">
            <article class="iss-video-playlist__lead">
                <button
                    type="button"
                    class="iss-video-playlist__lead-trigger"
                    data-video-id="<?php echo esc_attr((string) $lead['id']); ?>"
                    data-video-embed="<?php echo esc_url($lead['embed_url']); ?>"
                    data-video-title="<?php echo esc_attr($lead['title']); ?>"
                    data-video-text="<?php echo esc_attr($lead['excerpt']); ?>"
                    data-video-year="<?php echo esc_attr($lead['year']); ?>"
                    data-video-categories="<?php echo esc_attr($category_names); ?>"
                    data-video-url="<?php echo esc_url($lead['source_url']); ?>"
                    data-video-permalink="<?php echo esc_url($lead['permalink']); ?>"
                    data-video-has-transcript="<?php echo $lead['has_transcript'] ? '1' : '0'; ?>"
                >
                    <span class="iss-video-playlist__lead-media">
                        <?php if ($lead['thumbnail_url'] !== '') : ?>
                            <img src="<?php echo esc_url($lead['thumbnail_url']); ?>" alt="<?php echo esc_attr($lead['title']); ?>">
                        <?php else : ?>
                            <span class="iss-video-card__fallback" aria-hidden="true"></span>
                        <?php endif; ?>
                    </span>
                    <span class="iss-video-playlist__lead-copy">
                        <span class="iss-video-playlist__lead-kicker"><?php echo esc_html($term_name); ?></span>
                        <span class="iss-video-playlist__lead-title"><?php echo esc_html($lead['title']); ?></span>
                        <?php if ($lead['excerpt'] !== '') : ?>
                            <span class="iss-video-playlist__lead-text"><?php echo esc_html($lead['excerpt']); ?></span>
                        <?php endif; ?>
                        <span class="iss-video-playlist__lead-meta">
                            <?php
                            $meta_bits = array_filter([
                                $lead['year'],
                                $lead['source_label'],
                            ]);
                            echo esc_html(implode(' · ', $meta_bits));
                            ?>
                        </span>
                        <span class="iss-video-playlist__lead-relation"><?php echo esc_html((string) $copy['relation']); ?></span>
                    </span>
                </button>
            </article>
            <div class="iss-video-playlist__queue">
                <?php foreach ($queue as $card) : ?>
                    <?php echo iss_content_model_render_video_playlist_item($card, (string) $copy['relation']); ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php

    return (string) ob_get_clean();
}

function iss_content_model_filter_video_cards_by_source_family(array $cards, string $family): array
{
    $family = iss_content_model_normalize_video_source_family($family);

    return array_values(array_filter($cards, static function (array $card) use ($family): bool {
        return (($card['source_family'] ?? 'core') === $family);
    }));
}

function iss_content_model_get_video_library_payload(): array
{
    $cards = iss_content_model_get_video_cards();
    if (!$cards) {
        return [];
    }

    $featured = iss_content_model_pick_featured_video($cards);
    if (!$featured) {
        return [];
    }

    $external_cards = iss_content_model_filter_video_cards_by_source_family($cards, 'external_report');
    $core_cards = array_values(array_filter($cards, static function (array $card): bool {
        return (($card['source_family'] ?? 'core') !== 'external_report');
    }));

    return [
        'cards' => $cards,
        'featured' => $featured,
        'external_cards' => $external_cards,
        'groups' => iss_content_model_group_video_cards($core_cards),
    ];
}

function iss_content_model_render_video_library_filters(array $groups, array $external_cards): string
{
    ob_start();
    ?>
    <nav class="iss-video-library__filters" aria-label="<?php echo esc_attr__('Thematische Einstiege', 'iss-content-model'); ?>">
        <?php foreach ($groups as $group) : ?>
            <a class="iss-video-library__filter" href="#<?php echo esc_attr((string) $group['term']['slug']); ?>">
                <?php echo esc_html((string) $group['term']['name']); ?>
            </a>
        <?php endforeach; ?>
        <?php if ($external_cards) : ?>
            <a class="iss-video-library__filter" href="#externe-berichte">
                <?php esc_html_e('Externe Berichte', 'iss-content-model'); ?>
            </a>
        <?php endif; ?>
    </nav>
    <?php

    return (string) ob_get_clean();
}

function iss_content_model_render_video_library_feature(array $featured): string
{
    $category_names = implode(', ', array_filter(array_map(static function ($category) {
        return (string) ($category['name'] ?? '');
    }, $featured['categories'] ?? [])));

    ob_start();
    ?>
    <section
        class="iss-video-library__feature"
        data-video-player
        data-default-video-id="<?php echo esc_attr((string) $featured['id']); ?>"
    >
        <div class="iss-video-library__feature-media">
            <iframe
                data-video-player-frame
                src="<?php echo esc_url($featured['embed_url']); ?>"
                title="<?php echo esc_attr($featured['title']); ?>"
                loading="lazy"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                referrerpolicy="strict-origin-when-cross-origin"
                allowfullscreen
            ></iframe>
        </div>

        <div class="iss-video-library__feature-copy">
            <p class="iss-video-library__feature-kicker" data-video-player-kicker><?php echo esc_html($category_names); ?></p>
            <h2 class="iss-video-library__feature-title" data-video-player-title><?php echo esc_html($featured['title']); ?></h2>
            <p class="iss-video-library__feature-text" data-video-player-text><?php echo esc_html($featured['excerpt']); ?></p>
            <div class="iss-video-library__feature-meta">
                <p data-video-player-year-wrap <?php echo $featured['year'] === '' ? 'hidden' : ''; ?>><strong><?php esc_html_e('Jahr', 'iss-content-model'); ?></strong> <span data-video-player-year><?php echo esc_html($featured['year']); ?></span></p>
                <p><strong><?php esc_html_e('Quelle', 'iss-content-model'); ?></strong> <?php echo esc_html($featured['source_label'] !== '' ? $featured['source_label'] : __('Video', 'iss-content-model')); ?></p>
                <div class="iss-video-library__feature-links">
                    <p class="iss-video-library__feature-link"><a class="iss-action-link" data-video-player-link href="<?php echo esc_url($featured['source_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Zum Original', 'iss-content-model'); ?></a></p>
                    <p class="iss-video-library__feature-link" data-video-player-transcript-wrap <?php echo empty($featured['has_transcript']) ? 'hidden' : ''; ?>><a class="iss-action-link" data-video-player-transcript href="<?php echo esc_url(trailingslashit((string) $featured['permalink']) . '#transkript'); ?>"><?php esc_html_e('Transkript lesen', 'iss-content-model'); ?></a></p>
                    <p class="iss-video-library__feature-link" data-video-player-return-wrap hidden><button type="button" class="iss-video-library__return-link" data-video-player-return><?php esc_html_e('Zur Auswahl zurück', 'iss-content-model'); ?></button></p>
                </div>
            </div>
        </div>
    </section>
    <?php

    return (string) ob_get_clean();
}

function iss_content_model_render_video_library_cta(): string
{
    ob_start();
    ?>
    <section class="iss-video-library__cta">
        <div class="iss-video-library__cta-copy">
            <p class="iss-kicker iss-kicker--compact"><?php esc_html_e('Weiter lesen', 'iss-content-model'); ?></p>
            <h3 class="iss-video-library__cta-title"><?php esc_html_e('Diese Videos sind Teil derselben Sammlung wie Archiv, Atlas, Führungen und Ausstellungen.', 'iss-content-model'); ?></h3>
            <p class="iss-video-library__cta-text"><?php esc_html_e('Nicht als Nebenmaterial, sondern als eigene Lesart des Ortsgedächtnisses.', 'iss-content-model'); ?></p>
        </div>
        <p><a class="iss-action-link" href="/sammlungen/"><?php esc_html_e('Zur Sammlungsseite', 'iss-content-model'); ?></a></p>
    </section>
    <?php

    return (string) ob_get_clean();
}

function iss_content_model_render_video_inventory_section(array $cards): string
{
    ob_start();
    ?>
    <section class="iss-video-library__section iss-video-library__section--inventory">
        <div class="iss-video-library__section-head">
            <p class="iss-video-library__playlist-label"><?php esc_html_e('Vollstaendiger Bestand', 'iss-content-model'); ?></p>
            <h3 class="iss-video-library__section-title"><?php esc_html_e('Gesamte Videobibliothek', 'iss-content-model'); ?></h3>
            <p class="iss-video-library__playlist-text"><?php esc_html_e('Alle veroeffentlichten Videos bleiben als vollstaendige Rechercheoberflaeche verfuegbar.', 'iss-content-model'); ?></p>
        </div>
        <div class="iss-video-library__grid iss-video-library__grid--inventory">
            <?php
            foreach ($cards as $card) {
                echo iss_content_model_render_video_card($card);
            }
            ?>
        </div>
    </section>
    <?php

    return (string) ob_get_clean();
}

function iss_content_model_render_video_external_reports_section(array $cards): string
{
    if (!$cards) {
        return '';
    }

    $group = [
        'term' => [
            'slug' => 'externe-berichte',
            'name' => __('Presse und externe Berichte', 'iss-content-model'),
        ],
        'cards' => $cards,
    ];

    ob_start();
    ?>
    <section id="externe-berichte" class="iss-video-library__section iss-video-library__section--external">
        <div class="iss-video-library__section-head">
            <p class="iss-video-library__playlist-label"><?php esc_html_e('Externe Blicke', 'iss-content-model'); ?></p>
            <h3 class="iss-video-library__section-title"><?php esc_html_e('Presse und Berichte ueber Schoneweide', 'iss-content-model'); ?></h3>
            <p class="iss-video-library__playlist-text"><?php esc_html_e('Diese Videos gehoeren nicht zum eigenen Bestand, helfen aber dabei, Aussenwahrnehmung, Berichterstattung und Kontext sichtbar zu machen.', 'iss-content-model'); ?></p>
        </div>
        <?php echo iss_content_model_render_video_playlist_section($group, false); ?>
    </section>
    <?php

    return (string) ob_get_clean();
}

function iss_content_model_render_video_library_block($attributes = [], $content = '', $block = null): string
{
    $payload = iss_content_model_get_video_library_payload();
    if ($payload === []) {
        return '<div class="iss-video-library"><p class="iss-video-library__empty">' . esc_html__('Zurzeit sind noch keine Videos veröffentlicht.', 'iss-content-model') . '</p></div>';
    }
    iss_content_model_enqueue_video_library_assets();

    $wrapper = (function_exists('get_block_wrapper_attributes') && $block instanceof WP_Block)
        ? get_block_wrapper_attributes([
            'class' => 'iss-video-library iss-video-library--interactive',
        ])
        : 'class="iss-video-library iss-video-library--interactive"';

    ob_start();
    ?>
    <div <?php echo $wrapper; ?>>
        <?php echo iss_content_model_render_video_library_filters($payload['groups'], $payload['external_cards']); ?>

        <?php echo iss_content_model_render_video_library_feature($payload['featured']); ?>

        <div class="iss-video-library__playlists">
            <?php
            foreach ($payload['groups'] as $group) {
                echo iss_content_model_render_video_playlist_section($group);
            }
            ?>
        </div>

        <?php echo iss_content_model_render_video_external_reports_section($payload['external_cards']); ?>

        <?php echo iss_content_model_render_video_inventory_section($payload['cards']); ?>

        <?php echo iss_content_model_render_video_library_cta(); ?>
    </div>
    <?php

    return (string) ob_get_clean();
}

function iss_content_model_render_video_library_filters_block($attributes = [], $content = '', $block = null): string
{
    $payload = iss_content_model_get_video_library_payload();
    if ($payload === []) {
        return '';
    }

    return iss_content_model_render_video_library_filters($payload['groups'], $payload['external_cards']);
}

function iss_content_model_render_video_library_feature_block($attributes = [], $content = '', $block = null): string
{
    $payload = iss_content_model_get_video_library_payload();
    if ($payload === []) {
        return '';
    }

    iss_content_model_enqueue_video_library_assets();
    return iss_content_model_render_video_library_feature($payload['featured']);
}

function iss_content_model_render_video_library_playlists_block($attributes = [], $content = '', $block = null): string
{
    $payload = iss_content_model_get_video_library_payload();
    if ($payload === []) {
        return '';
    }

    iss_content_model_enqueue_video_library_assets();
    ob_start();
    echo '<div class="iss-video-library__playlists">';
    foreach ($payload['groups'] as $group) {
        echo iss_content_model_render_video_playlist_section($group);
    }
    echo '</div>';

    return (string) ob_get_clean();
}

function iss_content_model_render_video_library_external_block($attributes = [], $content = '', $block = null): string
{
    $payload = iss_content_model_get_video_library_payload();
    if ($payload === []) {
        return '';
    }

    iss_content_model_enqueue_video_library_assets();
    return iss_content_model_render_video_external_reports_section($payload['external_cards']);
}

function iss_content_model_render_video_library_inventory_block($attributes = [], $content = '', $block = null): string
{
    $payload = iss_content_model_get_video_library_payload();
    if ($payload === []) {
        return '';
    }

    iss_content_model_enqueue_video_library_assets();
    return iss_content_model_render_video_inventory_section($payload['cards']);
}

function iss_content_model_render_video_library_cta_block($attributes = [], $content = '', $block = null): string
{
    $payload = iss_content_model_get_video_library_payload();
    if ($payload === []) {
        return '';
    }

    return iss_content_model_render_video_library_cta();
}

function iss_content_model_get_single_video_card(int $post_id): ?array
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== ISS_CONTENT_MODEL_VIDEO_POST_TYPE) {
        return null;
    }

    $cards = iss_content_model_get_video_cards();
    foreach ($cards as $card) {
        if ((int) ($card['id'] ?? 0) === $post_id) {
            return $card;
        }
    }

    return null;
}

function iss_content_model_render_video_player_block($attributes = [], $content = '', $block = null): string
{
    $post_id = isset($block->context['postId']) ? (int) $block->context['postId'] : (int) get_the_ID();
    $card = iss_content_model_get_single_video_card($post_id);
    if (!$card) {
        return '';
    }

    $wrapper = (function_exists('get_block_wrapper_attributes') && $block instanceof WP_Block)
        ? get_block_wrapper_attributes(['class' => 'iss-video-single-player'])
        : 'class="iss-video-single-player"';

    $excerpt = trim((string) $card['excerpt']);
    $category_names = implode(', ', array_filter(array_map(static function ($category) {
        return (string) ($category['name'] ?? '');
    }, $card['categories'])));

    ob_start();
    ?>
    <section <?php echo $wrapper; ?>>
        <div class="iss-video-single-player__layout">
            <div class="iss-video-single-player__media">
                <iframe
                    src="<?php echo esc_url($card['embed_url']); ?>"
                    title="<?php echo esc_attr($card['title']); ?>"
                    loading="lazy"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen
                ></iframe>
            </div>
            <div class="iss-video-single-player__copy">
                <?php if ($category_names !== '') : ?>
                    <p class="iss-kicker iss-kicker--compact"><?php echo esc_html($category_names); ?></p>
                <?php endif; ?>
                <h1 class="iss-video-single-player__title"><?php echo esc_html($card['title']); ?></h1>
                <?php if ($excerpt !== '') : ?>
                    <p class="iss-video-single-player__standfirst"><?php echo esc_html($excerpt); ?></p>
                <?php endif; ?>
                <div class="iss-video-single-player__meta">
                    <?php if ($card['year'] !== '') : ?>
                        <p><strong><?php esc_html_e('Jahr', 'iss-content-model'); ?></strong> <?php echo esc_html($card['year']); ?></p>
                    <?php endif; ?>
                    <?php if ($card['source_label'] !== '') : ?>
                        <p><strong><?php esc_html_e('Quelle', 'iss-content-model'); ?></strong> <?php echo esc_html($card['source_label']); ?></p>
                    <?php endif; ?>
                    <p><strong><?php esc_html_e('Transkript', 'iss-content-model'); ?></strong> <?php echo !empty($card['has_transcript']) ? esc_html__('vorhanden', 'iss-content-model') : esc_html__('noch nicht veröffentlicht', 'iss-content-model'); ?></p>
                </div>
                <p class="iss-video-single-player__links">
                    <a class="iss-action-link" href="<?php echo esc_url($card['source_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Zum Original', 'iss-content-model'); ?></a>
                    <?php if (!empty($card['has_transcript'])) : ?>
                        <a class="iss-action-link" href="#transkript"><?php esc_html_e('Zum Transkript', 'iss-content-model'); ?></a>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </section>
    <?php

    return (string) ob_get_clean();
}

function iss_content_model_render_video_transcript_block($attributes = [], $content = '', $block = null): string
{
    $post_id = isset($block->context['postId']) ? (int) $block->context['postId'] : (int) get_the_ID();
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_CONTENT_MODEL_VIDEO_POST_TYPE) {
        return '';
    }

    $content_html = apply_filters('the_content', (string) get_post_field('post_content', $post_id));
    if (trim(wp_strip_all_tags($content_html)) === '') {
        return '';
    }

    $wrapper = (function_exists('get_block_wrapper_attributes') && $block instanceof WP_Block)
        ? get_block_wrapper_attributes(['class' => 'iss-video-transcript'])
        : 'class="iss-video-transcript"';

    ob_start();
    ?>
    <section id="transkript" <?php echo $wrapper; ?>>
        <div class="iss-heading">
            <p class="iss-kicker iss-kicker--compact"><?php esc_html_e('Transkript', 'iss-content-model'); ?></p>
            <h2 class="iss-heading__title"><?php esc_html_e('Gespräch und Inhalt', 'iss-content-model'); ?></h2>
        </div>
        <div class="iss-video-transcript__body">
            <?php echo $content_html; ?>
        </div>
    </section>
    <?php

    return (string) ob_get_clean();
}
