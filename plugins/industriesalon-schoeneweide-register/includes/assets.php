<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    wp_register_script(
        'iss-register-frontend-app',
        ISS_REGISTER_URL . 'assets/js/register-frontend-app.js',
        [],
        ISS_REGISTER_VERSION,
        true
    );

    wp_register_style(
        'iss-register-frontend-style',
        ISS_REGISTER_URL . 'assets/css/register-frontend.css',
        [],
        ISS_REGISTER_VERSION
    );
});
