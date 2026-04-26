<?php

if (!defined('ABSPATH')) {
    exit;
}

add_filter('template_include', function ($template) {
    if (is_singular(ISS_PUBLICATIONS_POST_TYPE)) {
        $single = ISS_PUBLICATIONS_PATH . 'templates/single-publication.php';
        if (file_exists($single)) {
            return $single;
        }
    }

    if (is_post_type_archive(ISS_PUBLICATIONS_POST_TYPE) || is_tax(['publication_type', 'publication_topic'])) {
        $archive = ISS_PUBLICATIONS_PATH . 'templates/archive-publication.php';
        if (file_exists($archive)) {
            return $archive;
        }
    }

    return $template;
}, 99);
