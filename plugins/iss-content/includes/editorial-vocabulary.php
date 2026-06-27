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
            'absorbs' => ['kapitel', 'aside'],
        ],
        'fliesstext' => [
            'label' => __('Fliesstext', 'iss-content-model'),
            'absorbs' => ['fliesstext', 'bericht'],
        ],
        'leitfrage' => [
            'label' => __('Leitfrage', 'iss-content-model'),
            'absorbs' => ['leitfrage'],
        ],
        'zitat' => [
            'label' => __('Zitat', 'iss-content-model'),
            'absorbs' => ['zitat', 'quellenauszug'],
        ],
        'schluss' => [
            'label' => __('Schluss', 'iss-content-model'),
            'absorbs' => ['schluss'],
        ],
        'galerie' => [
            'label' => __('Galerie', 'iss-content-model'),
            'absorbs' => ['galerie', 'bildstrecke', 'image_wall', 'autoalbum', 'photoalbum'],
        ],
        'vollbild' => [
            'label' => __('Vollbild', 'iss-content-model'),
            'absorbs' => ['vollbild'],
        ],
        'objektfokus' => [
            'label' => __('Objektfokus', 'iss-content-model'),
            'absorbs' => ['objektfokus', 'quellen'],
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
            'aliases' => ['standard', 'vortrag', 'lesung', 'gespraech', 'praesentation', 'workshop', 'konzert', 'repair'],
            'features' => [
                'rail' => ['enabled' => false],
            ],
        ],
        'dossier' => [
            'label' => __('Dossier', 'iss-content-model'),
            'aliases' => ['brief', 'field'],
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
            'aliases' => ['frauen-im-werk'],
            'features' => [
                'rail' => ['enabled' => false],
            ],
        ],
        'objektalbum' => [
            'label' => __('Objektalbum', 'iss-content-model'),
            'aliases' => ['kinder-im-werk'],
            'features' => [
                'rail' => ['enabled' => false],
            ],
        ],
        'bildmatrix' => [
            'label' => __('Bildmatrix', 'iss-content-model'),
            'aliases' => ['blueprint-matrix'],
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
            'aliases' => ['festival'],
            'features' => [
                'rail' => ['enabled' => false],
            ],
        ],
        'chronik' => [
            'label' => __('Chronik', 'iss-content-model'),
            'aliases' => ['dokumentarisch'],
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

function iss_content_model_editorial_skin_aliases(): array
{
    $aliases = [];
    foreach (iss_content_model_editorial_skin_registry() as $canonical => $skin) {
        $canonical = sanitize_key((string) $canonical);
        if ($canonical === '') {
            continue;
        }
        $aliases[$canonical] = $canonical;
        foreach ((array) ($skin['aliases'] ?? []) as $alias) {
            $alias = sanitize_key((string) $alias);
            if ($alias !== '') {
                $aliases[$alias] = $canonical;
            }
        }
    }

    return $aliases;
}

function iss_content_model_editorial_canonical_skin(string $skin): string
{
    $skin = sanitize_key($skin);
    if ($skin === '') {
        return '';
    }

    $aliases = iss_content_model_editorial_skin_aliases();

    return (string) ($aliases[$skin] ?? $skin);
}

function iss_content_model_editorial_skin_is(string $skin, string $canonical): bool
{
    return iss_content_model_editorial_canonical_skin($skin) === sanitize_key($canonical);
}

function iss_content_model_editorial_resolve_rail_feature(string $skin, array $overrides = []): array
{
    $registry = iss_content_model_editorial_skin_registry();
    $canonical = iss_content_model_editorial_canonical_skin($skin);
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
