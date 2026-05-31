<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

$title = is_tax('fuehrung_typ') ? single_term_title('', false) : post_type_archive_title('', false);
$description = is_tax('fuehrung_typ') ? term_description() : '';
if (!$description) {
    $description = 'Öffentliche Führungen, Gruppenangebote und besondere Formate auf einen Blick. Jede Führung führt direkt zu den nächsten verfügbaren Terminen.';
}
?>
<main class="wp-site-blocks">
    <?php echo do_blocks('<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'); ?>

    <section class="iss-container iss-fuehrungen-archive-head section">
        <div class="iss-heading">
            <p class="iss-kicker iss-kicker--lg">Führungen</p>
            <h1 class="iss-heading__title"><?php echo esc_html($title); ?></h1>
            <p class="iss-heading__text"><?php echo wp_kses_post(wp_strip_all_tags($description)); ?></p>
        </div>
    </section>

    <section class="iss-container iss-fuehrungen-archive-grid-wrap section--with-rail">
        <?php if (have_posts()) : ?>
            <div class="iss-card-grid iss-fuehrungen-archive-grid">
                <?php while (have_posts()) : the_post(); ?>
                    <?php echo iss_fuehrung_render_archive_card(get_the_ID()); ?>
                <?php endwhile; ?>
            </div>

            <div class="iss-fuehrungen-pagination">
                <?php
                echo wp_kses_post(paginate_links([
                    'type' => 'list',
                    'prev_text' => '←',
                    'next_text' => '→',
                ]));
                ?>
            </div>
        <?php else : ?>
            <p>Derzeit sind noch keine Führungen veröffentlicht.</p>
        <?php endif; ?>
    </section>

    <?php echo do_blocks('<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->'); ?>
</main>
<?php
get_footer();
