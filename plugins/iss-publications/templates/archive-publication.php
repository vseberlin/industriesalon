<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

$title = is_tax(['publication_type', 'publication_topic']) ? single_term_title('', false) : post_type_archive_title('', false);
$description = is_tax(['publication_type', 'publication_topic']) ? trim(wp_strip_all_tags(term_description())) : '';
if ($description === '') {
    $description = 'Bücher, Broschüren und Kataloge aus der Arbeit des Industriesalon Schöneweide – zu Architektur, Industriegeschichte, Stadtentwicklung und Menschen im Ort.';
}

$featured_block = do_blocks('<!-- wp:iss/featured-publication /-->');
?>
<main class="wp-site-blocks iss-publications-page">
    <?php echo do_blocks('<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'); ?>

    <section class="iss-page-hero">
        <div class="iss-page-hero__inner">
            <p class="iss-kicker iss-kicker--light iss-kicker--lg iss-page-hero__kicker">Publikationen</p>
            <h1 class="iss-page-hero__title"><?php echo esc_html($title); ?></h1>
            <div class="iss-page-hero__text"><p><?php echo esc_html($description); ?></p></div>
        </div>
    </section>

    <section class="section">
        <div class="iss-container">
            <div class="iss-heading">
                <p class="iss-kicker iss-kicker--compact">Edition</p>
                <h2 class="iss-heading__title">Industriekultur zum Nachlesen</h2>
            </div>
            <div class="iss-indent iss-indent--uncaged">
                <p>Die Publikationen des Industriesalon Schöneweide verbinden Forschung, Ausstellungspraxis, Archivarbeit und lokale Geschichte. Sie dokumentieren Orte, Akteur:innen und Zusammenhänge, die den Standort Schöneweide und seine Industriekultur bis heute prägen.</p>
            </div>
        </div>
    </section>

    <?php if ($featured_block !== '') : ?>
        <section class="section">
            <div class="iss-container">
                <?php echo $featured_block; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="section section--with-rail">
        <div class="iss-container">
            <div class="iss-heading">
                <p class="iss-kicker iss-kicker--compact">Alle Publikationen</p>
                <h2 class="iss-heading__title">Bücher, Broschüren und Kataloge</h2>
            </div>

            <?php if (have_posts()) : ?>
                <div class="iss-card-grid iss-publications-grid">
                    <?php while (have_posts()) : the_post(); ?>
                        <?php echo iss_publications_render_archive_card(get_the_ID()); ?>
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
                <p><?php esc_html_e('Derzeit sind noch keine Publikationen veröffentlicht.', 'iss-publications'); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <?php echo do_blocks('<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->'); ?>
</main>
<?php
get_footer();
