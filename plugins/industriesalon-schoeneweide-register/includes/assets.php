<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    wp_register_script(
        'iss-register-frontend-core',
        ISS_REGISTER_URL . 'assets/js/register-app/core.js',
        [],
        ISS_REGISTER_VERSION,
        true
    );

    wp_register_script(
        'iss-register-frontend-dom',
        ISS_REGISTER_URL . 'assets/js/register-app/dom.js',
        ['iss-register-frontend-core'],
        ISS_REGISTER_VERSION,
        true
    );

    wp_register_script(
        'iss-register-frontend-bootstrap',
        ISS_REGISTER_URL . 'assets/js/register-app/bootstrap.js',
        ['iss-register-frontend-core'],
        ISS_REGISTER_VERSION,
        true
    );

    wp_register_script(
        'iss-register-frontend-store',
        ISS_REGISTER_URL . 'assets/js/register-app/store.js',
        ['iss-register-frontend-core'],
        ISS_REGISTER_VERSION,
        true
    );

    wp_register_script(
        'iss-register-frontend-services',
        ISS_REGISTER_URL . 'assets/js/register-app/services.js',
        ['iss-register-frontend-core'],
        ISS_REGISTER_VERSION,
        true
    );

    wp_register_script(
        'iss-register-frontend-renderer',
        ISS_REGISTER_URL . 'assets/js/register-app/renderer.js',
        ['iss-register-frontend-core', 'iss-register-frontend-dom'],
        ISS_REGISTER_VERSION,
        true
    );

    wp_register_script(
        'iss-register-frontend-controller',
        ISS_REGISTER_URL . 'assets/js/register-app/controller.js',
        [
            'iss-register-frontend-bootstrap',
            'iss-register-frontend-store',
            'iss-register-frontend-services',
            'iss-register-frontend-renderer',
        ],
        ISS_REGISTER_VERSION,
        true
    );

    wp_register_script(
        'iss-register-frontend-app',
        ISS_REGISTER_URL . 'assets/js/register-frontend-app.js',
        ['iss-register-frontend-controller'],
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
