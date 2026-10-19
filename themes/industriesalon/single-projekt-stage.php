<?php
/** Theme-owned project stage, composed with the shared editorial gestures. */
if (!defined('ABSPATH')) { exit; }
$post_id = (int) get_queried_object_id();
$prefer_draft = is_preview() && current_user_can('edit_post', $post_id);
$document = iss_editorial_get_read_model($post_id, 'projekt', $prefer_draft);
$identity = $document['features']['identity'] ?? [];
$palette = $identity['palette'] ?? 'neutral';
$logo_id = absint($identity['media_refs'][0]['id'] ?? 0);
$rail = industriesalon_get_editorial_project_rail_context($post_id, $prefer_draft);
$placement = $rail['rail_feature']['placement'] ?? 'horizontal';
$has_opening = industriesalon_project_has_opening($document);
$side_rail = !empty($rail['rail_feature']['enabled']) && in_array($placement, ['left', 'right'], true);
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php echo do_blocks('<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core renders the trusted ISS template part.
?>
<main id="wp--skip-link--target" class="iss-project-stage iss-project-stage--<?php echo esc_attr($palette); ?> iss-page--header-offset">
    <?php while (have_posts()) : the_post(); ?>
        <?php if ($has_opening) : ?>
            <?php echo industriesalon_render_project_opening($document, $post_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme renderer escapes fields and media. ?>
        <?php else : ?>
        <header class="iss-project-stage__opening">
            <div class="iss-container iss-project-stage__hero">
                <div class="iss-project-stage__intro">
                    <p class="iss-kicker"><a href="<?php echo esc_url(home_url('/projekte/')); ?>"><?php esc_html_e('Projekte im Industriesalon', 'industriesalon'); ?></a></p>
                    <h1><?php the_title(); ?></h1>
                    <?php if (has_excerpt()) : ?><p class="iss-project-stage__lead"><?php echo esc_html(get_the_excerpt()); ?></p><?php endif; ?>
                </div>
                <?php if ($logo_id && wp_attachment_is_image($logo_id)) : ?>
                    <div class="iss-project-stage__identity"><?php echo wp_get_attachment_image($logo_id, 'large', false, ['class' => 'iss-project-stage__logo', 'alt' => '', 'loading' => 'eager']); ?></div>
                <?php endif; ?>
            </div>
        </header>
        <?php endif; ?>
        <div class="iss-project-stage__layout<?php echo $side_rail ? ' iss-project-stage__layout--' . esc_attr($placement) : ''; ?>">
            <?php if ($side_rail) { echo industriesalon_render_editorial_project_rail($rail['rail_section'] ?? [], $rail['nav_items'] ?? []); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The shared renderer escapes its values.
            } ?>
            <div class="iss-project-stage__content"><?php echo do_blocks('<!-- wp:post-content /-->'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Native content block includes the shared report connections and editorial renderer. ?></div>
        </div>
    <?php endwhile; ?>
</main>
<?php echo do_blocks('<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core renders the trusted ISS template part.
?>
<?php wp_footer(); ?>
</body>
</html>
