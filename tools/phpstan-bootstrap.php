<?php

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

$constants = [
    'ISS_CONTENT_MODEL_PATH' => __DIR__ . '/../plugins/iss-content-model/',
    'ISS_FUEHRUNGEN_PATH' => __DIR__ . '/../plugins/iss-fuehrungen/',
    'ISS_FUEHRUNGEN_URL' => 'https://example.test/wp-content/plugins/iss-fuehrungen/',
    'ISS_GRAPH_PATH' => __DIR__ . '/../plugins/iss-graph/',
    'ISS_GRAPH_URL' => 'https://example.test/wp-content/plugins/iss-graph/',
    'ISS_PROGRAMM_VERSION' => '0.1.0',
    'ISS_PUBLICATIONS_PATH' => __DIR__ . '/../plugins/iss-publications/',
    'ISS_PUBLICATIONS_URL' => 'https://example.test/wp-content/plugins/iss-publications/',
    'ISS_REGISTER_PATH' => __DIR__ . '/../plugins/industriesalon-schoeneweide-register/',
    'ISS_REGISTER_URL' => 'https://example.test/wp-content/plugins/industriesalon-schoeneweide-register/',
    'ISS_RELATIONS_META_KEY' => 'iss_related_places',
    'ISS_RELATIONS_PATH' => __DIR__ . '/../plugins/iss-relations/',
    'ISS_RELATIONS_TAXONOMY' => 'iss_place_ref',
    'ISS_RELATIONS_URL' => 'https://example.test/wp-content/plugins/iss-relations/',
    'ISS_WF_IMPORT_PATH' => __DIR__ . '/../plugins/iss-wf-import/',
    'ISS_WF_IMPORT_URL' => 'https://example.test/wp-content/plugins/iss-wf-import/',
];

foreach ($constants as $name => $value) {
    if (!defined($name)) {
        define($name, $value);
    }
}

if (!class_exists('WP_CLI')) {
    class WP_CLI
    {
        public static function add_command(string $name, $callable): void
        {
        }

        public static function error($message): void
        {
        }

        public static function error_multi_line(array $messages): void
        {
        }

        public static function log($message): void
        {
        }

        public static function success($message): void
        {
        }

        public static function warning($message): void
        {
        }
    }
}
