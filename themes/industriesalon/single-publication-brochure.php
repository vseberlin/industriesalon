<?php
/**
 * Sale brochure single template.
 *
 * Theme-owned public structure for sale-enabled publication brochures. The
 * publication plugin provides classification and data; this template owns the
 * public layout.
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id = (int) get_queried_object_id();
if ($post_id <= 0 || !function_exists('industriesalon_publications_is_sale_brochure') || !industriesalon_publications_is_sale_brochure($post_id)) {
    include get_query_template('single');
    return;
}

$post = get_post($post_id);
if (!$post instanceof WP_Post) {
    include get_query_template('single');
    return;
}

$has_cover = has_post_thumbnail($post_id);
$excerpt = trim((string) get_the_excerpt($post_id));
$content = trim((string) apply_filters('the_content', $post->post_content));
$fact_strip = function_exists('industriesalon_publications_render_brochure_fact_strip')
    ? industriesalon_publications_render_brochure_fact_strip($post_id)
    : '';
$order_panel = '';
if (function_exists('iss_publications_get_order_panel_context') && function_exists('industriesalon_publications_render_order_panel')) {
    $order_panel = industriesalon_publications_render_order_panel(iss_publications_get_order_panel_context($post_id));
}
$related = function_exists('industriesalon_publications_render_brochure_related')
    ? industriesalon_publications_render_brochure_related($post_id)
    : '';

$grid_classes = ['iss-container', 'iss-publication-brochure-single__grid'];
if (!$has_cover) {
    $grid_classes[] = 'iss-publication-brochure-single__grid--no-cover';
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('iss-publication-brochure-template'); ?>>
<?php wp_body_open(); ?>
<?php echo do_blocks('<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'); ?>

<main id="wp--skip-link--target" class="iss-publication-brochure-single iss-page--header-offset">
    <section class="section section--plain iss-publication-brochure-single__sheet">
        <div class="<?php echo esc_attr(implode(' ', $grid_classes)); ?>">
            <aside class="iss-publication-brochure-single__context">
                <?php if ($has_cover) : ?>
                    <figure class="iss-publication-brochure-single__cover">
                        <div class="iss-publication-brochure-single__cover-frame">
                            <?php echo get_the_post_thumbnail($post_id, 'large'); ?>
                        </div>
                    </figure>
                <?php endif; ?>

                <?php echo wp_kses_post($related); ?>
            </aside>

            <article class="iss-publication-brochure-single__main">
                <header class="iss-heading iss-heading--uncaged iss-publication-brochure-single__intro">
                    <p class="iss-kicker iss-kicker--compact"><?php echo esc_html__('Publikation', 'industriesalon'); ?></p>
                    <h1 class="iss-heading__title"><?php echo esc_html(get_the_title($post_id)); ?></h1>
                    <?php if ($excerpt !== '') : ?>
                        <p class="iss-publication-brochure-single__standfirst"><?php echo esc_html($excerpt); ?></p>
                    <?php endif; ?>
                </header>

                <?php echo wp_kses_post($fact_strip); ?>

                <?php if ($content !== '') : ?>
                    <div class="iss-publication-brochure-single__body">
                        <p class="iss-publication-brochure-single__body-label"><?php echo esc_html__('Im Heft', 'industriesalon'); ?></p>
                        <div class="entry-content">
                            <?php echo wp_kses_post($content); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </article>

            <?php if ($order_panel !== '') : ?>
                <div class="iss-publication-brochure-single__order">
                    <?php echo wp_kses_post($order_panel); ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php echo do_blocks('<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->'); ?>
<?php wp_footer(); ?>
</body>
</html>
