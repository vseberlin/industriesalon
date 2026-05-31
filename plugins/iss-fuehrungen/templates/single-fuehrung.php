<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

the_post();
$post_id = get_the_ID();
$badge = trim((string) get_post_meta($post_id, 'tour_badge', true));
$color_class = iss_fuehrung_get_color_class($post_id);
$booking_mode = iss_fuehrung_get_effective_booking_mode($post_id);
$inquiry = iss_fuehrung_get_inquiry_data($post_id);
$inquiry_url = trim((string) ($inquiry['url'] ?? ''));
$inquiry_label = trim((string) ($inquiry['label'] ?? ''));
$inquiry_note = trim((string) ($inquiry['note'] ?? ''));
?>
<main class="wp-site-blocks">
    <?php echo do_blocks('<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'); ?>

    <article <?php post_class('iss-fuehrung-single section ' . $color_class); ?>>
        <?php if (has_post_thumbnail()) : ?>
            <section class="iss-fuehrung-hero">
                <?php the_post_thumbnail('full'); ?>
            </section>
        <?php endif; ?>

        <section class="iss-container iss-fuehrung-intro-wrap">
            <div class="iss-fuehrung-intro">
                <div class="iss-fuehrung-intro__main">
                    <div class="iss-heading">
                        <?php if ($badge !== '') : ?>
                            <p class="iss-kicker iss-kicker--lg"><?php echo esc_html($badge); ?></p>
                        <?php endif; ?>
                        <h1 class="iss-heading__title"><?php the_title(); ?></h1>
                        <?php if (has_excerpt()) : ?>
                            <p class="iss-heading__text"><?php echo esc_html(get_the_excerpt()); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php echo do_blocks('<!-- wp:iss/tour-facts /-->'); ?>
                </div>

                <div class="iss-fuehrung-intro__aside">
                    <?php echo do_blocks('<!-- wp:iss/tour-booking-panel /-->'); ?>
                </div>
            </div>
        </section>

        <?php if (in_array($booking_mode, ['calendar', 'hybrid'], true)) : ?>
            <section id="termine" class="iss-container iss-fuehrung-dates-wrap section--with-rail">
                <div class="iss-heading">
                    <p class="iss-kicker iss-kicker--compact">Termine</p>
                    <h2 class="iss-heading__title">Verfügbare Termine</h2>
                    <p class="iss-heading__text">Der Kalender wird aus SuperSaaS synchronisiert. Die Liste darunter bleibt zusätzlich als ruhige, SEO-freundliche Darstellung sichtbar.</p>
                </div>

                <div class="iss-fuehrung-dates-grid">
                    <div class="iss-fuehrung-dates-grid__calendar">
                        <?php
                        echo do_blocks('<!-- wp:iss/tour-calendar {"title":"Termin wählen"} /-->');
                        ?>
                    </div>
                    <div class="iss-fuehrung-dates-grid__list">
                        <?php
                        echo do_blocks('<!-- wp:iss/tour-dates {"title":"Nächste Termine","limit":12} /-->');
                        ?>
                    </div>
                </div>
            </section>
        <?php else : ?>
            <section id="termine" class="iss-container iss-fuehrung-dates-wrap section--with-rail">
                <div class="iss-heading">
                    <p class="iss-kicker iss-kicker--compact"><?php esc_html_e('Anfrage', 'iss-fuehrungen'); ?></p>
                    <h2 class="iss-heading__title"><?php esc_html_e('Buchung auf Anfrage', 'iss-fuehrungen'); ?></h2>
                    <p class="iss-heading__text">
                        <?php
                        if ($inquiry_note !== '') {
                            echo esc_html($inquiry_note);
                        } else {
                            esc_html_e('Diese Führung wird aktuell nicht über den Kalender angeboten. Bitte senden Sie uns eine individuelle Anfrage.', 'iss-fuehrungen');
                        }
                        ?>
                    </p>
                    <?php if ($inquiry_url !== '') : ?>
                        <p><a class="wp-element-button" href="<?php echo esc_url($inquiry_url); ?>"><?php echo esc_html($inquiry_label); ?></a></p>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="iss-container iss-fuehrung-content-wrap">
            <div class="iss-fuehrung-content">
                <?php the_content(); ?>
            </div>
        </section>

        <?php $related = iss_fuehrung_get_related($post_id, 3); ?>
        <?php if ($related) : ?>
            <section class="iss-container iss-fuehrung-related-wrap">
                <div class="iss-heading">
                    <p class="iss-kicker iss-kicker--compact">Weitersehen</p>
                    <h2 class="iss-heading__title">Weitere Führungen</h2>
                </div>
                <?php
                $related_cards = [];
                foreach ($related as $related_post) {
                    $related_cards[] = iss_fuehrung_render_archive_card($related_post->ID);
                }

                if (function_exists('iss_relations_render_cards_grid')) {
                    echo iss_relations_render_cards_grid($related_cards, 'fuehrung', [
                        'layoutVariant' => 'grid',
                        'columns' => 3,
                    ]);
                } else {
                    echo '<div class="iss-related-feed__grid iss-related-feed__grid--fuehrung iss-related-feed__grid--layout-grid iss-related-feed__grid--columns-3">';
                    echo implode('', $related_cards);
                    echo '</div>';
                }
                ?>
            </section>
        <?php endif; ?>
    </article>

    <?php echo do_blocks('<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->'); ?>
</main>
<?php
get_footer();
