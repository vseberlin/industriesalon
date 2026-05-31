<?php

if (!defined('ABSPATH')) {
    exit;
}

$template_content = isset($GLOBALS['iss_fuehrung_theme_single_template_content'])
    ? (string) $GLOBALS['iss_fuehrung_theme_single_template_content']
    : '';

if (!is_string($template_content) || trim($template_content) === '') {
    require ISS_FUEHRUNGEN_PATH . 'templates/single-fuehrung.php';
    return;
}

$post_id = (int) get_queried_object_id();
$did_setup_post = false;
if ($post_id > 0) {
    $post = get_post($post_id);
    if ($post instanceof WP_Post) {
        $GLOBALS['post'] = $post;
        setup_postdata($post);
        $did_setup_post = true;
    }
}

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
wp_body_open();
echo do_blocks($template_content);
wp_footer();
?>
</body>
</html>
<?php

if ($did_setup_post) {
    wp_reset_postdata();
}
