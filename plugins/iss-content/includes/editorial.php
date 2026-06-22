<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_register_editorial_formats(array $formats): array
{
    $formats['ausstellung'] = [
        'label' => __('Ausstellung', 'iss-content-model'),
        'base' => 'ordered',
        'post_types' => [ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE],
        'default_skin' => 'standard',
        'default_variant' => 'standard',
        'sections' => [
            'kapitel' => [
                'label' => __('Kapitel', 'iss-content-model'),
                'description' => __('Numbered chapter break', 'iss-content-model'),
                'supports' => [],
            ],
            'leitfrage' => [
                'label' => __('Leitfrage', 'iss-content-model'),
                'description' => __('The guiding question', 'iss-content-model'),
                'supports' => [],
            ],
            'objektfokus' => [
                'label' => __('Objektfokus', 'iss-content-model'),
                'description' => __('1-3 archive objects inline', 'iss-content-model'),
                'supports' => ['object_refs'],
            ],
            'quellenauszug' => [
                'label' => __('Quellenauszug', 'iss-content-model'),
                'description' => __('Source quote and citation', 'iss-content-model'),
                'supports' => ['quote', 'object_refs', 'media_refs'],
            ],
            'massstab' => [
                'label' => __('Massstab', 'iss-content-model'),
                'description' => __('Stats and context paragraph', 'iss-content-model'),
                'supports' => [],
            ],
            'zitat' => [
                'label' => __('Zitat', 'iss-content-model'),
                'description' => __('Direct quote with attribution', 'iss-content-model'),
                'supports' => ['quote', 'object_refs'],
            ],
            'bildstrecke' => [
                'label' => __('Dokumentarische Strecke', 'iss-content-model'),
                'description' => __('Photo sequence with captions', 'iss-content-model'),
                'supports' => ['object_refs', 'media_refs'],
            ],
            'vollbild' => [
                'label' => __('Vollbild', 'iss-content-model'),
                'description' => __('One image, full viewport, short panel', 'iss-content-model'),
                'supports' => ['media_refs'],
            ],
            'fliesstext' => [
                'label' => __('Fliesstext', 'iss-content-model'),
                'description' => __('Essay paragraph or connective text', 'iss-content-model'),
                'supports' => [],
            ],
            'schluss' => [
                'label' => __('Schluss', 'iss-content-model'),
                'description' => __('Closing statement and onward direction', 'iss-content-model'),
                'supports' => ['links'],
            ],
            'aside' => [
                'label' => __('Ausstellungsentscheidung', 'iss-content-model'),
                'description' => __('Curator speaks directly', 'iss-content-model'),
                'supports' => ['links'],
            ],
        ],
    ];

    return $formats;
}
add_filter('iss_editorial_formats', 'iss_content_model_register_editorial_formats');
