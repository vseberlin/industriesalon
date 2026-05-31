<?php
/**
 * Plugin Name: ISS Content Model
 * Description: Shared CPTs and structured fields for Veranstaltungen, Ausstellungen, Projekte, and Team, with minimal timeline wiring.
 * Version: 0.1.0
 * Author: Industriesalon
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ISS_CONTENT_MODEL_VERSION', '0.2.0');
define('ISS_CONTENT_MODEL_PATH', plugin_dir_path(__FILE__));

define('ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE', 'veranstaltung');
define('ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE', 'ausstellung');
define('ISS_CONTENT_MODEL_PROJEKT_POST_TYPE', 'projekt');
define('ISS_CONTENT_MODEL_TEAM_POST_TYPE', 'team_member');
define('ISS_CONTENT_MODEL_VIDEO_POST_TYPE', 'video');
define('ISS_CONTENT_MODEL_ENTITY_PROFILE_POST_TYPE', 'entity_profile');

define('ISS_CONTENT_MODEL_TEAM_ROLE_TAXONOMY', 'team_role');
define('ISS_CONTENT_MODEL_PROJECT_STATUS_TAXONOMY', 'project_status');
define('ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY', 'ausstellung_typ');
define('ISS_CONTENT_MODEL_COLLECTION_AREA_TAXONOMY', 'sammlungsbereich');
define('ISS_CONTENT_MODEL_INDUSTRY_SITE_TAXONOMY', 'industrieort');
define('ISS_CONTENT_MODEL_TOPIC_TAXONOMY', 'iss_topic');
define('ISS_CONTENT_MODEL_VIDEO_CATEGORY_TAXONOMY', 'video_kategorie');

function iss_content_model_landing_route_map() {
    return [
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE => 'veranstaltungen',
        ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE   => 'ausstellungen',
        ISS_CONTENT_MODEL_PROJEKT_POST_TYPE       => 'projekte',
        'fuehrung'                                => 'fuehrungen',
        'publication'                             => 'publikationen',
    ];
}

function iss_content_model_landing_route_slugs() {
    return array_values(iss_content_model_landing_route_map());
}

function iss_content_model_page_claims_public_slug($slug) {
    $page = get_page_by_path($slug, OBJECT, 'page');

    if (!$page instanceof WP_Post) {
        return false;
    }

    return !in_array($page->post_status, ['trash', 'auto-draft'], true);
}

function iss_content_model_prevent_archive_page_slug_collisions($args, $post_type) {
    $route_map = iss_content_model_landing_route_map();

    if (!isset($route_map[$post_type]) || empty($args['has_archive'])) {
        return $args;
    }

    if (iss_content_model_page_claims_public_slug($route_map[$post_type])) {
        $args['has_archive'] = false;
    }

    return $args;
}
add_filter('register_post_type_args', 'iss_content_model_prevent_archive_page_slug_collisions', 10, 2);

function iss_content_model_page_touches_landing_route($post) {
    if (!$post instanceof WP_Post || 'page' !== $post->post_type) {
        return false;
    }

    $route_slugs = iss_content_model_landing_route_slugs();
    $old_slugs   = array_map('sanitize_title', (array) get_post_meta($post->ID, '_wp_old_slug', false));

    if (in_array($post->post_name, $route_slugs, true)) {
        return true;
    }

    return (bool) array_intersect($route_slugs, $old_slugs);
}

function iss_content_model_maybe_flush_landing_routes($post_id, $post = null) {
    $post = $post instanceof WP_Post ? $post : get_post($post_id);

    if (!iss_content_model_page_touches_landing_route($post)) {
        return;
    }

    flush_rewrite_rules(false);
}

add_action('save_post_page', 'iss_content_model_maybe_flush_landing_routes', 10, 2);
add_action('trashed_post', 'iss_content_model_maybe_flush_landing_routes');
add_action('untrashed_post', 'iss_content_model_maybe_flush_landing_routes');
add_action('before_delete_post', 'iss_content_model_maybe_flush_landing_routes');

require_once ISS_CONTENT_MODEL_PATH . 'includes/post-types.php';
require_once ISS_CONTENT_MODEL_PATH . 'includes/meta.php';
require_once ISS_CONTENT_MODEL_PATH . 'includes/admin.php';
require_once ISS_CONTENT_MODEL_PATH . 'includes/blocks.php';
require_once ISS_CONTENT_MODEL_PATH . 'includes/timeline-sync.php';
require_once ISS_CONTENT_MODEL_PATH . 'includes/videos.php';
require_once ISS_CONTENT_MODEL_PATH . 'includes/video-import.php';

register_activation_hook(__FILE__, function () {
    iss_content_model_register_post_types();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
