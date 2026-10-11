<?php
/**
 * Optional MU-plugin include for the disposable local browser fixture only.
 * Define ISS_TEST_FRONTEND_FIXTURE=true and WP_ENVIRONMENT_TYPE=local in its
 * wp-config.php, then require this file from that fixture's MU-plugin.
 * Uses public owner reads; creates no posts, templates or projection rows.
 */
if (!defined('ABSPATH') || !defined('ISS_TEST_FRONTEND_FIXTURE') || ISS_TEST_FRONTEND_FIXTURE !== true || wp_get_environment_type() !== 'local') { return; }
add_action('template_redirect', static function (): void {
    $surface = sanitize_key($_GET['iss_frontend_fixture'] ?? '');
    if (!in_array($surface, ['inline', 'multiple'], true)) { return; }
    nocache_headers();
    header('X-Robots-Tag: noindex, nofollow');
    ob_start();
    if ($surface === 'inline') {
        global $post;
        foreach (get_posts(['post_type' => 'fuehrung', 'post_status' => 'publish', 'posts_per_page' => -1]) as $source) {
            if (!iss_occurrences_get_booking_slots(['source_post_id' => $source->ID, 'source_post_type' => 'fuehrung'])) { continue; }
            $post = $source;
            setup_postdata($post);
            echo render_block(['blockName' => 'iss/tour-calendar', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]);
            wp_reset_postdata();
            break;
        }
    } else {
        for ($index = 0; $index < 2; ++$index) {
            echo render_block(['blockName' => 'industriesalon/timeline-query', 'attrs' => ['limit' => 2, 'showCalendarBridge' => true, 'showLoadMore' => true], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]);
        }
    }
    $content = ob_get_clean();
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    wp_head();
    echo '</head><body><main>' . $content . '</main>';
    wp_footer();
    echo '</body></html>';
    exit;
}, 1);
