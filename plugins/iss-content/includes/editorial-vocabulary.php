<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_editorial_gesture_registry(): array
{
    return [
        'intro' => [
            'label' => __('Intro', 'iss-content-model'),
            'absorbs' => ['intro'],
        ],
        'kapitel' => [
            'label' => __('Kapitel', 'iss-content-model'),
            'absorbs' => ['kapitel'],
        ],
        'fliesstext' => [
            'label' => __('Fliesstext', 'iss-content-model'),
            'absorbs' => ['fliesstext'],
        ],
        'leitfrage' => [
            'label' => __('Leitfrage', 'iss-content-model'),
            'absorbs' => ['leitfrage'],
        ],
        'zitat' => [
            'label' => __('Zitat', 'iss-content-model'),
            'absorbs' => ['zitat'],
        ],
        'schluss' => [
            'label' => __('Schluss', 'iss-content-model'),
            'absorbs' => ['schluss'],
        ],
        'galerie' => [
            'label' => __('Galerie', 'iss-content-model'),
            'absorbs' => ['galerie', 'photoalbum'],
        ],
        'vollbild' => [
            'label' => __('Vollbild', 'iss-content-model'),
            'absorbs' => ['vollbild'],
        ],
        'objektfokus' => [
            'label' => __('Objektfokus', 'iss-content-model'),
            'absorbs' => ['objektfokus'],
        ],
        'material' => [
            'label' => __('Material', 'iss-content-model'),
            'absorbs' => ['material'],
        ],
        'massstab' => [
            'label' => __('Massstab', 'iss-content-model'),
            'absorbs' => ['massstab'],
        ],
        'programm' => [
            'label' => __('Programm', 'iss-content-model'),
            'absorbs' => ['programm'],
        ],
        'upload_intake' => [
            'label' => __('Upload-Aufruf', 'iss-content-model'),
            'absorbs' => ['upload_intake'],
        ],
    ];
}

function iss_content_model_editorial_skin_registry(): array
{
    return [
        'typografisch' => [
            'label' => __('Typografisch', 'iss-content-model'),
            'features' => [
                'rail' => ['enabled' => false],
            ],
        ],
        'dossier' => [
            'label' => __('Dossier', 'iss-content-model'),
            'features' => [
                'rail' => [
                    'enabled' => true,
                    'placement' => 'left',
                    'mode' => 'anchor-nav',
                    'treatment' => 'sticky',
                ],
            ],
        ],
        'quellenbuehne' => [
            'label' => __('Quellenbuehne', 'iss-content-model'),
            'features' => [
                'rail' => ['enabled' => false],
            ],
        ],
        'objektalbum' => [
            'label' => __('Objektalbum', 'iss-content-model'),
            'features' => [
                'rail' => ['enabled' => false],
            ],
        ],
        'bildmatrix' => [
            'label' => __('Bildmatrix', 'iss-content-model'),
            'features' => [
                'rail' => [
                    'enabled' => true,
                    'placement' => 'top',
                    'mode' => 'section-index',
                    'treatment' => 'line',
                ],
            ],
        ],
        'buehne' => [
            'label' => __('Buehne', 'iss-content-model'),
            'features' => [
                'rail' => ['enabled' => false],
            ],
        ],
        'chronik' => [
            'label' => __('Chronik', 'iss-content-model'),
            'features' => [
                'rail' => [
                    'enabled' => true,
                    'placement' => 'right',
                    'mode' => 'section-index',
                    'treatment' => 'line',
                ],
            ],
        ],
    ];
}

function iss_content_model_editorial_resolve_rail_feature(string $skin, array $overrides = []): array
{
    $registry = iss_content_model_editorial_skin_registry();
    $canonical = sanitize_key($skin);
    $defaults = is_array($registry[$canonical]['features']['rail'] ?? null)
        ? $registry[$canonical]['features']['rail']
        : ['enabled' => false];

    $feature = array_merge($defaults, $overrides);
    $feature['enabled'] = !empty($feature['enabled']);

    $placement = sanitize_key((string) ($feature['placement'] ?? ''));
    $feature['placement'] = in_array($placement, ['left', 'right', 'top', 'bottom', 'horizontal'], true) ? $placement : 'right';

    $mode = sanitize_key((string) ($feature['mode'] ?? ''));
    $feature['mode'] = in_array($mode, ['anchor-nav', 'section-index', 'contextual'], true) ? $mode : 'anchor-nav';

    $treatment = sanitize_key((string) ($feature['treatment'] ?? ''));
    $feature['treatment'] = in_array($treatment, ['quiet', 'card', 'line', 'sticky', 'overlay'], true) ? $treatment : 'quiet';

    return $feature;
}
