<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();
the_post();

$post_id = get_the_ID();
$summary_meta = iss_publications_get_summary_meta($post_id);
$related_posts = iss_publications_get_related_posts($post_id, 3);
$subtitle = trim((string) iss_publications_get_meta($post_id, '_iss_publication_subtitle', ''));
?>
<main class="wp-site-blocks iss-publication-single">
    <?php echo do_blocks('<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'); ?>

    <section class="section section--with-rail">
        <div class="iss-container">
            <div class="iss-heading iss-heading--uncaged">
                <p class="iss-kicker iss-kicker--compact">Publikation</p>
                <h1 class="iss-heading__title"><?php the_title(); ?></h1>
                <?php if (has_excerpt()) : ?>
                    <p class="iss-heading__text"><?php echo esc_html(get_the_excerpt()); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="iss-container">
            <div class="iss-publication-single__layout">
                <div class="iss-publication-single__cover">
                    <div class="iss-media-card iss-media-card--contain iss-media-card--soft iss-media-card--framed">
                        <?php if (has_post_thumbnail()) : ?>
                            <?php the_post_thumbnail('large'); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="iss-publication-single__summary">
                    <?php if ($subtitle !== '') : ?>
                        <div class="iss-heading iss-heading--uncaged">
                            <p class="iss-heading__text"><?php echo esc_html($subtitle); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($summary_meta)) : ?>
                        <ul class="iss-publication-meta">
                            <?php foreach ($summary_meta as $label => $value) : ?>
                                <li><strong><?php echo esc_html($label); ?>:</strong> <?php echo esc_html($value); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="iss-container">
            <div class="iss-heading">
                <p class="iss-kicker iss-kicker--compact">Über die Publikation</p>
                <h2 class="iss-heading__title">Beschreibung</h2>
            </div>

            <div class="iss-indent iss-indent--uncaged">
                <?php the_content(); ?>
            </div>

            <?php echo do_blocks('<!-- wp:iss/publication-order-panel /-->'); ?>
        </div>
    </section>

    <?php if (!empty($related_posts)) : ?>
        <section class="section">
            <div class="iss-container">
                <div class="iss-heading">
                    <p class="iss-kicker iss-kicker--compact">Weiter lesen</p>
                    <h2 class="iss-heading__title">Weitere Publikationen</h2>
                </div>

                <div class="iss-card-grid iss-publications-grid">
                    <?php foreach ($related_posts as $related_post) : ?>
                        <?php echo iss_publications_render_archive_card($related_post->ID); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php echo do_blocks('<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->'); ?>
</main>
<?php
get_footer();
