<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_wf_import_md_build_object_url(int $object_id): string
{
    return 'https://berlin.museum-digital.de/object/' . $object_id . '?navlang=de';
}

function iss_wf_import_md_build_json_url(int $object_id): string
{
    return 'https://berlin.museum-digital.de/json/object/' . $object_id;
}

function iss_wf_import_md_build_manifest_url(int $object_id): string
{
    return 'https://berlin.museum-digital.de/apis/iiif-presentation/' . $object_id . '/manifest';
}

function iss_wf_import_md_fetch_url(string $url): array
{
    $response = wp_remote_get($url, [
        'timeout' => 30,
        'redirection' => 5,
        'user-agent' => 'ISS Archive Importer/' . ISS_WF_IMPORT_VERSION . '; ' . home_url('/'),
    ]);

    if (is_wp_error($response)) {
        return [
            'ok' => false,
            'message' => $response->get_error_message(),
            'code' => 0,
            'body' => '',
        ];
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);

    return [
        'ok' => $code >= 200 && $code < 300,
        'message' => $code >= 200 && $code < 300 ? '' : ('HTTP ' . $code),
        'code' => $code,
        'body' => $body,
    ];
}

function iss_wf_import_md_match_first(string $pattern, string $html): string
{
    if (!preg_match($pattern, $html, $match)) {
        return '';
    }

    return html_entity_decode(trim((string) ($match[1] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function iss_wf_import_md_discover_object_ids_from_seed(string $seed_url): array
{
    $response = iss_wf_import_md_fetch_url($seed_url);
    if (empty($response['ok'])) {
        return [];
    }

    $html = (string) ($response['body'] ?? '');
    if ($html === '') {
        return [];
    }

    preg_match_all('/museum-digital\.de\/index\.php\?t=objekt(?:&amp;|&)oges=(\d+)/i', $html, $matches);
    if (empty($matches[1]) || !is_array($matches[1])) {
        return [];
    }

    return array_values(array_unique(array_filter(array_map('absint', $matches[1]))));
}

function iss_wf_import_md_discover_seed_rows(string $seed_url): array
{
    $response = iss_wf_import_md_fetch_url($seed_url);
    if (empty($response['ok'])) {
        return [];
    }

    $html = (string) ($response['body'] ?? '');
    if ($html === '') {
        return [];
    }

    preg_match_all('/<tr class="row-\d+[^"]*">\s*(.*?)<\/tr>/si', $html, $row_matches);
    if (empty($row_matches[1]) || !is_array($row_matches[1])) {
        return [];
    }

    $rows = [];
    foreach ($row_matches[1] as $row_html) {
        if (!preg_match('/oges=(\d+)/i', $row_html, $id_match)) {
            continue;
        }

        $object_id = absint($id_match[1] ?? 0);
        if ($object_id <= 0) {
            continue;
        }

        preg_match_all('/<td class="column-(\d+)">(.*?)<\/td>/si', $row_html, $columns, PREG_SET_ORDER);
        if (!$columns) {
            continue;
        }

        $column_map = [];
        foreach ($columns as $column_match) {
            $column_map[(int) ($column_match[1] ?? 0)] = (string) ($column_match[2] ?? '');
        }

        $inventory = iss_wf_import_md_clean_text(wp_strip_all_tags((string) ($column_map[1] ?? '')));
        $title_html = (string) ($column_map[2] ?? '');
        $title = '';
        if (preg_match('/target="blank">(.*?)<img/si', $title_html, $title_match)) {
            $title = iss_wf_import_md_clean_text((string) ($title_match[1] ?? ''));
        } else {
            $title = iss_wf_import_md_clean_text(wp_strip_all_tags($title_html));
        }

        $description = iss_wf_import_md_clean_text(str_replace(['<br />', '<br/>', '<br>'], "\n", (string) ($column_map[3] ?? '')));
        $keywords = iss_wf_import_md_clean_text(str_replace(['<br />', '<br/>', '<br>'], ', ', (string) ($column_map[4] ?? '')));

        $image_url = '';
        if (preg_match('/data-src="([^"]+)"/i', $title_html, $image_match) || preg_match('/src="([^"]+)"/i', $title_html, $image_match)) {
            $image_url = esc_url_raw((string) ($image_match[1] ?? ''));
        }

        $rows[] = [
            'object_id' => $object_id,
            'inventory_number' => $inventory,
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'image_url' => $image_url,
        ];
    }

    return $rows;
}

function iss_wf_import_md_seed_row_to_payload(array $row): array
{
    $keywords = array_filter(array_map('trim', explode(',', (string) ($row['keywords'] ?? ''))));
    $tags = [];
    foreach ($keywords as $tag_name) {
        $tags[] = [
            'tag_id' => 0,
            'tag_name' => $tag_name,
        ];
    }

    $image_url = esc_url_raw((string) ($row['image_url'] ?? ''));

    return [
        'object_id' => absint($row['object_id'] ?? 0),
        'object_inventory_number' => sanitize_text_field((string) ($row['inventory_number'] ?? '')),
        'object_type' => 'Objekt',
        'object_name' => sanitize_text_field((string) ($row['title'] ?? '')),
        'object_description' => sanitize_textarea_field((string) ($row['description'] ?? '')),
        'object_material_technique' => '',
        'object_dimensions' => '',
        'object_last_updated' => '',
        'object_institution' => [
            'institution_id' => 29,
            'institution_name' => 'Industriesalon Schöneweide',
        ],
        'object_images' => $image_url !== '' ? [[
            'quell_id' => 0,
            'name' => sanitize_text_field((string) ($row['title'] ?? '')),
            'is_main' => 'j',
            'order' => 1,
            'folder' => '',
            'filename_loc' => wp_basename(parse_url($image_url, PHP_URL_PATH) ?: ''),
            'beschreibung' => sanitize_text_field((string) ($row['inventory_number'] ?? '')),
            'owner' => '',
            'creator' => '',
            'rights' => '',
            'herkunft' => 'wf-seed-row',
            'type' => 'image',
            'intern' => 'j',
            'preview' => '',
            'direct_url' => $image_url,
        ]] : [],
        'object_collection' => [],
        'object_events' => [],
        'object_relation_places' => [],
        'object_relation_times' => [],
        'object_relation_people' => [],
        'exhibitions' => [],
        'object_tags' => $tags,
        'licence' => [
            'metadata_rights_holder' => '',
            'metadata_rights_status' => '',
        ],
        'additional' => [
            'detailed_description' => '',
        ],
        'transcripts' => [
            'original' => [],
            'translation' => [],
        ],
        'detailed_description' => '',
        'expected_language' => 'de',
    ];
}

function iss_wf_import_md_parse_object_html(int $object_id, string $html): array
{
    $title = iss_wf_import_md_match_first('/<h1>(.*?)<\/h1>/si', $html);
    $description = iss_wf_import_md_match_first('/<meta name="description" content="(.*?)"\s*\/?>/si', $html);
    $keywords = iss_wf_import_md_match_first('/<meta name="keywords" content="(.*?)"\s*\/?>/si', $html);
    $image_url = iss_wf_import_md_match_first('/<img class="imgExplSizeSet" src="([^"]+)"/si', $html);
    $rights_line = iss_wf_import_md_match_first('/<figcaption>\s*Herkunft\/Rechte:\s*(.*?)<\/figcaption>/si', $html);
    $inventory = iss_wf_import_md_match_first('/<span class=\'bcSelElem\' property="name">\[(.*?)\]<\/span>/si', $html);
    $institution = iss_wf_import_md_match_first('/href="\/institution\/\d+">\s*<span property="name">(.*?)<\/span>/si', $html);

    $collections = [];
    if (preg_match_all('/href="\/collection\/\d+">\s*<span property="name">(.*?)<\/span>/si', $html, $collection_matches) && !empty($collection_matches[1])) {
        foreach ($collection_matches[1] as $collection_name) {
            $collections[] = [
                'collection_id' => 0,
                'collection_name' => html_entity_decode(trim((string) $collection_name), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            ];
        }
    }

    $tags = [];
    foreach (array_filter(array_map('trim', explode(',', $keywords))) as $tag_name) {
        $tags[] = [
            'tag_id' => 0,
            'tag_name' => $tag_name,
        ];
    }

    $rights_holder = '';
    $rights_status = '';
    if ($rights_line !== '' && preg_match('/^(.*?)\s*\((.*?)\)$/', wp_strip_all_tags($rights_line), $rights_match)) {
        $rights_holder = trim((string) ($rights_match[1] ?? ''));
        $rights_status = trim((string) ($rights_match[2] ?? ''));
    } else {
        $rights_holder = trim(wp_strip_all_tags($rights_line));
    }

    return [
        'object_id' => $object_id,
        'object_inventory_number' => $inventory,
        'object_type' => 'Objekt',
        'object_name' => $title,
        'object_description' => $description,
        'object_material_technique' => '',
        'object_dimensions' => '',
        'object_last_updated' => '',
        'object_institution' => [
            'institution_id' => 0,
            'institution_name' => $institution,
        ],
        'object_images' => $image_url !== '' ? [[
            'quell_id' => 0,
            'name' => $title,
            'is_main' => 'j',
            'order' => 1,
            'folder' => '',
            'filename_loc' => wp_basename(parse_url($image_url, PHP_URL_PATH) ?: ''),
            'beschreibung' => $inventory,
            'owner' => $rights_holder,
            'creator' => '',
            'rights' => $rights_status,
            'herkunft' => 'html-fallback',
            'type' => 'image',
            'intern' => 'j',
            'preview' => '',
            'direct_url' => $image_url,
        ]] : [],
        'object_collection' => $collections,
        'object_events' => [],
        'object_relation_places' => [],
        'object_relation_times' => [],
        'object_relation_people' => [],
        'exhibitions' => [],
        'object_tags' => $tags,
        'licence' => [
            'metadata_rights_holder' => $rights_holder,
            'metadata_rights_status' => $rights_status,
        ],
        'additional' => [
            'detailed_description' => '',
        ],
        'transcripts' => [
            'original' => [],
            'translation' => [],
        ],
        'detailed_description' => '',
        'expected_language' => 'de',
    ];
}

function iss_wf_import_md_fetch_payload(int $object_id): array
{
    $json_fetch = iss_wf_import_md_fetch_url(iss_wf_import_md_build_json_url($object_id));
    $json_payload = json_decode((string) ($json_fetch['body'] ?? ''), true);
    if (is_array($json_payload) && !empty($json_payload['object_id'])) {
        return [
            'ok' => true,
            'payload' => $json_payload,
            'source' => 'json',
            'message' => '',
        ];
    }

    $html_fetch = iss_wf_import_md_fetch_url(iss_wf_import_md_build_object_url($object_id));
    if (empty($html_fetch['ok'])) {
        return [
            'ok' => false,
            'payload' => [],
            'source' => '',
            'message' => (string) ($json_fetch['message'] ?? $html_fetch['message'] ?? 'fetch failed'),
        ];
    }

    return [
        'ok' => true,
        'payload' => iss_wf_import_md_parse_object_html($object_id, (string) ($html_fetch['body'] ?? '')),
        'source' => 'html',
        'message' => '',
    ];
}

function iss_wf_import_md_payload_has_core_identity(array $payload): bool
{
    $title = trim((string) ($payload['object_name'] ?? ''));
    $inventory = trim((string) ($payload['object_inventory_number'] ?? ''));

    return $title !== '' || $inventory !== '';
}

function iss_wf_import_md_merge_seed_payload(array $payload, array $seed_payload): array
{
    if (!$seed_payload) {
        return $payload;
    }

    $fields = [
        'object_inventory_number',
        'object_name',
        'object_description',
        'object_tags',
        'object_images',
        'object_institution',
        'licence',
        'expected_language',
    ];

    foreach ($fields as $field) {
        $value = $payload[$field] ?? null;
        $empty = false;

        if (is_array($value)) {
            $empty = $value === [];
        } else {
            $empty = trim((string) $value) === '';
        }

        if ($empty && array_key_exists($field, $seed_payload)) {
            $payload[$field] = $seed_payload[$field];
        }
    }

    return $payload;
}

function iss_wf_import_md_extract_year(array $payload): int
{
    $photo_texts = [
        (string) ($payload['object_name'] ?? ''),
        (string) ($payload['object_description'] ?? ''),
        (string) ($payload['detailed_description'] ?? ''),
        (string) (($payload['additional']['detailed_description'] ?? '')),
    ];

    foreach ($photo_texts as $text) {
        if (preg_match('/\bfoto\b[^\d]{0,40}(1[89]\d{2}|20\d{2})\b/ui', $text, $match)) {
            return (int) $match[1];
        }
    }

    $event_sets = [
        (array) ($payload['object_events'] ?? []),
        (array) ($payload['object_relation_times'] ?? []),
    ];

    foreach ($event_sets as $events) {
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $start = (string) (($event['time']['time_start'] ?? '') ?: ($event['time_start'] ?? ''));
            if (preg_match('/\b(1[89]\d{2}|20\d{2})\b/', $start, $match)) {
                return (int) $match[1];
            }
        }
    }

    $texts = [
        (string) ($payload['object_description'] ?? ''),
        (string) ($payload['detailed_description'] ?? ''),
        (string) (($payload['additional']['detailed_description'] ?? '')),
    ];

    foreach ($texts as $text) {
        if (preg_match('/\b(1[89]\d{2}|20\d{2})\b/', $text, $match)) {
            return (int) $match[1];
        }
    }

    return 0;
}

function iss_wf_import_md_format_decade(int $year): string
{
    if ($year < 1000) {
        return '';
    }

    $decade = (int) floor($year / 10) * 10;
    return $decade . 'er';
}

function iss_wf_import_md_clean_text(string $text): string
{
    $text = wp_strip_all_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/\r\n|\r/", "\n", $text);
    $text = preg_replace("/[ \t]+/", ' ', (string) $text);
    $text = preg_replace("/\n{3,}/", "\n\n", (string) $text);
    return trim((string) $text);
}

function iss_wf_import_md_build_excerpt(array $payload): string
{
    $description = iss_wf_import_md_clean_text((string) ($payload['object_description'] ?? ''));
    if ($description === '') {
        return '';
    }

    $excerpt = strtok($description, "\n");
    $excerpt = $excerpt !== false ? trim((string) $excerpt) : $description;

    return mb_substr($excerpt, 0, 240);
}

function iss_wf_import_md_build_post_content(array $payload): string
{
    $sections = [];

    $description = iss_wf_import_md_clean_text((string) ($payload['object_description'] ?? ''));
    if ($description !== '') {
        $sections[] = $description;
    }

    $detailed = iss_wf_import_md_clean_text((string) (($payload['detailed_description'] ?? '') ?: ($payload['additional']['detailed_description'] ?? '')));
    if ($detailed !== '' && $detailed !== $description) {
        $sections[] = "Ausführliche Beschreibung:\n" . $detailed;
    }

    return implode("\n\n", $sections);
}

function iss_wf_import_md_named_refs(array $items, string $id_key, string $name_key, string $type = ''): array
{
    $refs = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $id = (int) ($item[$id_key] ?? 0);
        $name = sanitize_text_field((string) ($item[$name_key] ?? ''));
        if ($id <= 0 && $name === '') {
            continue;
        }

        $refs[] = [
            'id' => $id,
            'source_id' => $id > 0 ? (string) $id : '',
            'name' => $name,
            'type' => $type,
            'note' => '',
            'source_url' => '',
        ];
    }

    return $refs;
}

function iss_wf_import_md_resolve_profile(string $seed_url = '', string $profile = ''): string
{
    $profile = sanitize_key($profile);
    if (in_array($profile, ['roehren-halbleiter', 'anlagen-arbeitsplaetze', 'geraete-bauteile', 'telekommunikation-fernsehtechnik', 'diverses-gebaeude-schaltbilder', 'menschen-im-wf'], true)) {
        return $profile;
    }

    $seed_url = mb_strtolower($seed_url);
    if ($seed_url !== '' && str_contains($seed_url, 'fotostelle-wf-menschen-im-wf')) {
        return 'menschen-im-wf';
    }

    if ($seed_url !== '' && str_contains($seed_url, 'anlagen-automaten-arbeitsplaetze')) {
        return 'anlagen-arbeitsplaetze';
    }

    if ($seed_url !== '' && str_contains($seed_url, 'geraete-einschuebe-bauteile')) {
        return 'geraete-bauteile';
    }

    if ($seed_url !== '' && str_contains($seed_url, 'telekommunikation-sende-und-fernsehtechnik')) {
        return 'telekommunikation-fernsehtechnik';
    }

    if ($seed_url !== '' && str_contains($seed_url, 'diverses-gebaeude-schaltbilder-etc')) {
        return 'diverses-gebaeude-schaltbilder';
    }

    return 'roehren-halbleiter';
}

function iss_wf_import_md_map_classification_menschen(array $payload): array
{
    $title = (string) ($payload['object_name'] ?? '');
    $description = (string) ($payload['object_description'] ?? '');
    $detailed_description = (string) ($payload['detailed_description'] ?? '');
    $haystack_parts = [$title, $description, $detailed_description];
    $tag_parts = [];

    foreach ((array) ($payload['object_tags'] ?? []) as $tag) {
        if (!is_array($tag)) {
            continue;
        }

        $tag_name = (string) ($tag['tag_name'] ?? '');
        $haystack_parts[] = $tag_name;
        $tag_parts[] = $tag_name;
    }

    $haystack = mb_strtolower(implode(' ', $haystack_parts));
    $title_haystack = mb_strtolower($title);
    $tag_haystack = mb_strtolower(implode(' ', $tag_parts));
    $families = [];
    $contexts = [];
    $fields = ['alltag'];

    $family_needles = [
        'arbeitsplatz' => ['arbeitsplatz', 'montage', 'röhrenaufbau', 'roehrenaufbau', 'werkbank', 'prüftechnik', 'prueftechnik', 'fabriksaal'],
        'gruppenfoto' => ['gruppenfoto', 'gruppenaufnahme', 'belegschaft', 'kolleginnen', 'kollegen', 'versammlung'],
        'portraet' => ['portrait', 'porträt', 'kopfportrait', 'kopfporträt'],
        'festszene' => ['feier', 'frauentag', 'fasching', 'weihnacht', 'jubiläum', 'jubilaum', 'auszeichnung', 'ehrung'],
        'sport-freizeit' => ['sport', 'clubhaus', 'ruderverein', 'müggelsee', 'mueggelsee', 'bootshaus', 'ferien', 'ausflug'],
        'architektur' => ['fassade', 'fabriktor', 'außenaufnahme', 'aussenaufnahme', 'gebäude', 'gebaeude', 'clubhaus', 'kinderkrippe'],
        'kinder' => ['kinderkrippe', 'kindergarten', 'kinder'],
    ];

    foreach ($family_needles as $slug => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                $families[] = $slug;
                break;
            }
        }
    }

    if (preg_match('/\bfrau(enarbeit)?\b/u', $haystack)) {
        $contexts[] = 'frauenarbeit';
    }

    if (str_contains($haystack, 'innenraumansicht') || str_contains($haystack, 'innenaufnahme') || str_contains($haystack, 'speisesaal') || str_contains($haystack, 'fabriksaal')) {
        $contexts[] = 'innenraumansicht';
    }

    if (str_contains($haystack, 'außenaufnahme') || str_contains($haystack, 'aussenaufnahme') || str_contains($haystack, 'fassade') || str_contains($haystack, 'fabriktor') || str_contains($haystack, 'gewässer') || str_contains($haystack, 'gewaesser')) {
        $contexts[] = 'aussenaufnahme';
    }

    if (str_contains($haystack, 'momentaufnahme')) {
        $contexts[] = 'momentaufnahme';
    }

    if (in_array('portraet', $families, true) || str_contains($tag_haystack, 'mann') || str_contains($tag_haystack, 'frau')) {
        $contexts[] = 'portraet';
    }

    if (in_array('festszene', $families, true)) {
        $contexts[] = 'feier';
    }

    if (in_array('sport-freizeit', $families, true)) {
        $contexts[] = 'freizeit';
    }

    if (in_array('arbeitsplatz', $families, true)) {
        $contexts[] = 'produktion';
        $fields[] = 'arbeit';
    }

    if (in_array('sport-freizeit', $families, true)) {
        $fields[] = 'freizeit';
        $fields[] = 'sport';
    }

    if (in_array('festszene', $families, true)) {
        $fields[] = 'kultur';
    }

    if (in_array('kinder', $families, true) || str_contains($title_haystack, 'kinder')) {
        $fields[] = 'soziales';
    }

    if (in_array('gruppenfoto', $families, true) || in_array('portraet', $families, true)) {
        $fields[] = 'menschen';
    }

    if (in_array('architektur', $families, true)) {
        $fields[] = 'betriebsorte';
    }

    if (!$families) {
        $families[] = 'menschen-im-wf';
    }

    if (!$contexts) {
        $contexts[] = 'alltag';
    }

    return [
        'fields' => array_values(array_unique($fields)),
        'families' => array_values(array_unique($families)),
        'contexts' => array_values(array_unique($contexts)),
    ];
}

function iss_wf_import_md_map_classification_anlagen(array $payload): array
{
    $title = (string) ($payload['object_name'] ?? '');
    $description = (string) ($payload['object_description'] ?? '');
    $detailed_description = (string) ($payload['detailed_description'] ?? '');
    $haystack_parts = [$title, $description, $detailed_description];
    $tag_parts = [];

    foreach ((array) ($payload['object_tags'] ?? []) as $tag) {
        if (!is_array($tag)) {
            continue;
        }

        $tag_name = (string) ($tag['tag_name'] ?? '');
        $haystack_parts[] = $tag_name;
        $tag_parts[] = $tag_name;
    }

    $haystack = mb_strtolower(implode(' ', $haystack_parts));
    $title_haystack = mb_strtolower($title);
    $tag_haystack = mb_strtolower(implode(' ', $tag_parts));
    $families = [];
    $contexts = [];
    $fields = [];
    $has_machine_signal = false;
    $has_montage_signal = str_contains($haystack, 'montage');
    $has_montage_station_signal = false;

    $anlagen_needles = [
        'trockenanlage' => ['trockenanlage'],
        'ofen' => ['ofen', 'glühofen', 'gluehofen', 'trockenschrank'],
        'pruefstand' => ['prüfstand', 'pruefstand', 'prüffeld', 'prueffeld', 'messplatz'],
        'fertigungslinie' => ['fertigung', 'fertigungslinie', 'produktionslinie', 'fertigungsstraße', 'fertigungsstrasse'],
        'montageplatz' => ['montageplatz'],
        'arbeitsplatz' => ['arbeitsplatz', 'arbeitsplätze', 'arbeitsplaetze', 'arbeitsraum'],
        'innenraum' => ['innenraum', 'werkhalle', 'fabrikhalle', 'hallenansicht', 'keller'],
        'laboraufbau' => ['labor', 'laboraufbau', 'versuchsaufbau'],
        'transport' => ['transport', 'förderband', 'foerderband', 'materialfluss', 'wagen', 'förderstrecke', 'foerderstrecke'],
    ];

    foreach ($anlagen_needles as $slug => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                $families[] = $slug;
                break;
            }
        }
    }

    if (
        str_contains($haystack, 'montageplatz')
        || ($has_montage_signal && preg_match('/arbeitsplatz|arbeitsraum|werkbank|tisch|\bplatz\b/u', $haystack))
    ) {
        $has_montage_station_signal = true;
        $families[] = 'montageplatz';
    }

    foreach (['maschine', 'automat', 'aggregat', 'wickelmaschine'] as $needle) {
        if (str_contains($haystack, $needle)) {
            $has_machine_signal = true;
            break;
        }
    }

    if ($has_machine_signal || array_intersect($families, ['trockenanlage', 'ofen', 'pruefstand', 'fertigungslinie', 'innenraum', 'laboraufbau', 'transport'])) {
        $fields[] = 'anlage';
    }

    if (array_intersect($families, ['arbeitsplatz', 'montageplatz', 'innenraum'])) {
        $fields[] = 'arbeitsplatz';
    }

    if ($has_machine_signal && (str_contains($haystack, 'automat') || str_contains($title_haystack, 'automat') || str_contains($tag_haystack, 'automat'))) {
        $fields[] = 'automat';
    }

    if (str_contains($haystack, 'innenraum') || str_contains($haystack, 'innenraumansicht') || str_contains($haystack, 'werkhalle') || str_contains($haystack, 'fabrikhalle') || str_contains($haystack, 'keller')) {
        $contexts[] = 'innenraumansicht';
    }

    if (str_contains($haystack, 'arbeitsplatz') || str_contains($haystack, 'fertigung') || str_contains($haystack, 'montage') || str_contains($haystack, 'besteller') || str_contains($haystack, 'arbeiter') || str_contains($haystack, 'arbeitsprozess')) {
        $contexts[] = 'arbeitsprozess';
    }

    if ($has_montage_signal) {
        $contexts[] = 'montage';
    }

    if (str_contains($haystack, 'sachaufnahme') || str_contains($tag_haystack, 'sachaufnahme') || str_contains($haystack, 'negativ (film)')) {
        $contexts[] = 'sachaufnahme';
    }

    if ($has_machine_signal) {
        $contexts[] = 'maschine';
    }

    if (str_contains($haystack, 'produktion') || str_contains($haystack, 'fertigung') || str_contains($haystack, 'werkstatt') || str_contains($haystack, 'montage')) {
        $contexts[] = 'produktion';
    }

    if (str_contains($haystack, 'vergleich') || str_contains($haystack, 'maßstab') || str_contains($haystack, 'massstab')) {
        $contexts[] = 'vergleichsaufnahme';
    }

    if (!$fields && $families) {
        $fields[] = 'anlage';
    }

    if (!$contexts) {
        $contexts[] = 'sachaufnahme';
    }

    return [
        'fields' => array_values(array_unique($fields)),
        'families' => array_values(array_unique($families)),
        'contexts' => array_values(array_unique($contexts)),
    ];
}

function iss_wf_import_md_map_classification_geraete(array $payload): array
{
    $title = (string) ($payload['object_name'] ?? '');
    $description = (string) ($payload['object_description'] ?? '');
    $detailed_description = (string) ($payload['detailed_description'] ?? '');
    $haystack_parts = [$title, $description, $detailed_description];
    $tag_parts = [];

    foreach ((array) ($payload['object_tags'] ?? []) as $tag) {
        if (!is_array($tag)) {
            continue;
        }

        $tag_name = (string) ($tag['tag_name'] ?? '');
        $haystack_parts[] = $tag_name;
        $tag_parts[] = $tag_name;
    }

    $haystack = mb_strtolower(implode(' ', $haystack_parts));
    $families = [];
    $contexts = ['sachaufnahme'];
    $fields = ['geraete-bauteile'];

    $family_needles = [
        'einschub' => ['einschub', 'einschübe'],
        'messgeraet' => ['normalgenerator', 'oszillograph', 'feldstärkenmesser', 'feldstarkenmesser', 'messgerät', 'messgerat', 'quarzrevolver'],
        'schrank' => ['schrank', 'geräteschrank', 'geraeteschrank'],
        'geraet' => ['gerät', 'geraet', 'aggregat', 'orgel', 'generator', 'oszillograph', 'filmabtaster'],
        'bauteil' => ['bauteil', 'spule', 'transformator', 'drehschalter', 'halter', 'feinsicherung', 'wendel', 'metallringe', 'quarz'],
    ];

    foreach ($family_needles as $slug => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                $families[] = $slug;
                break;
            }
        }
    }

    if (str_contains($haystack, 'einschub')) {
        $contexts[] = 'komponente';
    }

    if (str_contains($haystack, 'rückansicht') || str_contains($haystack, 'rueckansicht') || str_contains($haystack, 'untersicht') || str_contains($haystack, 'geöffnet') || str_contains($haystack, 'geoeffnet')) {
        $contexts[] = 'dokumentation';
    }

    if (str_contains($haystack, 'repro') || str_contains($haystack, 'reproduktionsfoto')) {
        $contexts[] = 'reproduktion';
    }

    if (str_contains($haystack, 'maßstab') || str_contains($haystack, 'massstab') || str_contains($haystack, 'größenvergleich') || str_contains($haystack, 'groessenvergleich')) {
        $contexts[] = 'vergleichsaufnahme';
    }

    return [
        'fields' => array_values(array_unique($fields)),
        'families' => array_values(array_unique($families ?: ['geraet'])),
        'contexts' => array_values(array_unique($contexts)),
    ];
}

function iss_wf_import_md_map_classification_telekommunikation(array $payload): array
{
    $title = (string) ($payload['object_name'] ?? '');
    $description = (string) ($payload['object_description'] ?? '');
    $detailed_description = (string) ($payload['detailed_description'] ?? '');
    $haystack_parts = [$title, $description, $detailed_description];
    $tag_parts = [];

    foreach ((array) ($payload['object_tags'] ?? []) as $tag) {
        if (!is_array($tag)) {
            continue;
        }

        $tag_name = (string) ($tag['tag_name'] ?? '');
        $haystack_parts[] = $tag_name;
        $tag_parts[] = $tag_name;
    }

    $haystack = mb_strtolower(implode(' ', $haystack_parts));
    $families = [];
    $contexts = ['sachaufnahme'];
    $fields = ['telekommunikation-fernsehtechnik'];

    $family_needles = [
        'relais' => ['relais', 'telegrafenrelais'],
        'sender' => ['tonsender', 'sender', 'ukw'],
        'einschub' => ['einschub'],
        'kamera' => ['fernsehkamera', 'reporterkamera', 'kamera'],
        'fernsehgeraet' => ['industriefernseher', 'fernsehfilmabtaster', 'filmabtaster', 'monitor'],
        'messgeraet' => ['feldstärkenmesser', 'feldstarkenmesser'],
        'geraet' => ['gerät', 'geraet', 'einrichtung', 'schrank'],
    ];

    foreach ($family_needles as $slug => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                $families[] = $slug;
                break;
            }
        }
    }

    if (
        str_contains($haystack, 'fernseh')
        || str_contains($haystack, 'kamera')
        || str_contains($haystack, 'filmabtaster')
        || str_contains($haystack, 'monitor')
    ) {
        $fields[] = 'fernsehtechnik';
    }

    if (
        str_contains($haystack, 'telegraf')
        || str_contains($haystack, 'telegraph')
        || str_contains($haystack, 'ukw')
        || str_contains($haystack, 'relais')
        || str_contains($haystack, 'tonsender')
    ) {
        $fields[] = 'telekommunikation';
    }

    if (str_contains($haystack, 'repro') || str_contains($haystack, 'reproduktionsfoto')) {
        $contexts[] = 'reproduktion';
    }

    if (str_contains($haystack, 'maßstab') || str_contains($haystack, 'massstab') || str_contains($haystack, 'größenvergleich') || str_contains($haystack, 'groessenvergleich')) {
        $contexts[] = 'vergleichsaufnahme';
    }

    return [
        'fields' => array_values(array_unique($fields)),
        'families' => array_values(array_unique($families ?: ['geraet'])),
        'contexts' => array_values(array_unique($contexts)),
    ];
}

function iss_wf_import_md_map_classification_diverses(array $payload): array
{
    $title = (string) ($payload['object_name'] ?? '');
    $description = (string) ($payload['object_description'] ?? '');
    $detailed_description = (string) ($payload['detailed_description'] ?? '');
    $haystack_parts = [$title, $description, $detailed_description];
    $tag_parts = [];

    foreach ((array) ($payload['object_tags'] ?? []) as $tag) {
        if (!is_array($tag)) {
            continue;
        }

        $tag_name = (string) ($tag['tag_name'] ?? '');
        $haystack_parts[] = $tag_name;
        $tag_parts[] = $tag_name;
    }

    $haystack = mb_strtolower(implode(' ', $haystack_parts));
    $families = [];
    $contexts = ['sachaufnahme'];
    $fields = ['diverses'];

    $family_needles = [
        'schaltbild' => ['schaltbild', 'journal motion picture', 'repro aus', 'zeilenauflösung', 'zeilenaufloesung'],
        'gebaeudeansicht' => ['hausansicht', 'gebäude', 'gebaeude', 'fassade', 'werkstor', 'toransicht'],
        'musikinstrument' => ['orgel', 'vocata'],
        'verpackung' => ['verpackung', 'verpackungskiste'],
        'geraet' => ['beschriftungsfeld', 'löschfahrzeug', 'loeschfahrzeug'],
    ];

    foreach ($family_needles as $slug => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                $families[] = $slug;
                break;
            }
        }
    }

    if (in_array('schaltbild', $families, true)) {
        $fields[] = 'schaltbild';
        $contexts[] = 'reproduktion';
    }

    if (in_array('gebaeudeansicht', $families, true)) {
        $fields[] = 'gebaeude';
        $contexts[] = 'aussenaufnahme';
    }

    if (str_contains($haystack, 'repro') || str_contains($haystack, 'reproduktionsfoto') || str_contains($haystack, 'journal')) {
        $contexts[] = 'dokumentation';
    }

    return [
        'fields' => array_values(array_unique($fields)),
        'families' => array_values(array_unique($families ?: ['geraet'])),
        'contexts' => array_values(array_unique($contexts)),
    ];
}

function iss_wf_import_md_map_classification(array $payload, string $profile = 'roehren-halbleiter'): array
{
    if ($profile === 'anlagen-arbeitsplaetze') {
        return iss_wf_import_md_map_classification_anlagen($payload);
    }

    if ($profile === 'geraete-bauteile') {
        return iss_wf_import_md_map_classification_geraete($payload);
    }

    if ($profile === 'telekommunikation-fernsehtechnik') {
        return iss_wf_import_md_map_classification_telekommunikation($payload);
    }

    if ($profile === 'diverses-gebaeude-schaltbilder') {
        return iss_wf_import_md_map_classification_diverses($payload);
    }

    if ($profile === 'menschen-im-wf') {
        return iss_wf_import_md_map_classification_menschen($payload);
    }

    $title = (string) ($payload['object_name'] ?? '');
    $description = (string) ($payload['object_description'] ?? '');
    $detailed_description = (string) ($payload['detailed_description'] ?? '');
    $haystack_parts = [
        $title,
        $description,
        $detailed_description,
    ];
    $tag_parts = [];

    foreach ((array) ($payload['object_tags'] ?? []) as $tag) {
        if (is_array($tag)) {
            $tag_name = (string) ($tag['tag_name'] ?? '');
            $haystack_parts[] = $tag_name;
            $tag_parts[] = $tag_name;
        }
    }

    $haystack = mb_strtolower(implode(' ', $haystack_parts));
    $title_haystack = mb_strtolower($title);
    $tag_haystack = mb_strtolower(implode(' ', $tag_parts));
    $families = [];
    $contexts = [];
    $fields = [];

    $has_tube_context = (bool) preg_match('/röhre|roehre|thyratron|klystron|magnetron|bildröhre|bildroehre|gasentladung|kaltkathoden|relaisröhre|relaisroehre/', $haystack);
    $has_material_context = (bool) preg_match('/silizium|germanium|werkstoff|einkristall|silizium-bombe|silzium-bombe|si-kristall|pulver|drähte|drahte|golddraht(?!diode)/', $haystack);
    $has_diode_context = (bool) preg_match('/\bdiode\b|dioden|halbleiter-diode|halbleiter diode|zener-diode|zenerdiode|kristalldiode|lumineszenzdiode|tunneldiode|rauschdiode|scheibendiode|gräfdiode|grafdiode|golddrahtdiode/', $haystack);
    $material_object_pattern = '/silizium-einkristall|germanium-einkristall|si-kristall|silizium-kristall|germanium-kristall|einkristall|silizium-pulver|germanium-pulver|siliziumdrähte|siliziumdrahte|germaniumdrähte|germaniumdrahte|silizium-bombe|silzium-bombe|keramiklötung|keramiklotung|metallisierung/u';
    $has_material_object_signal = (bool) preg_match($material_object_pattern, $title_haystack . ' ' . $tag_haystack);
    $has_diode_product_signal = (bool) preg_match('/\bdiode\b|dioden|kristalldiode|golddrahtdiode|lumineszenzdiode|tunneldiode|rauschdiode|scheibendiode|gräfdiode|grafdiode|\boa[\s\-]?\d+|\bga[\s\-]?\d+/u', $title_haystack . ' ' . $tag_haystack);

    $picture_tube_pattern = '/bildröhre|bildroehre|fernsehröhre|fernsehroehre|farbfernsehröhre|farbfernsehroehre|elektronenstrahlröhre|elektronenstrahlroehre|implodierte farbfernseh|implusionsgeschützte bildröhre|implusionsgeschuetzte bildroehre/u';

    $family_needles = [
        'thyratron' => ['thyratron'],
        'klystron' => ['klystron'],
        'magnetron' => ['magnetron'],
        'miniaturroehre' => ['miniaturröhre', 'miniaturroehre'],
        'gasentladungsroehre' => ['gasentladung', 'gasentladungsröhre', 'gasentladungsroehre'],
        'roehrensystem' => ['röhrensystem', 'roehrensystem', 'gitter für röhrensysteme', 'gitter fur rohrensysteme'],
        'verpackung' => ['verpackung', 'produktschachtel'],
        'baugruppe' => ['baugruppe', 'ringmodulator'],
    ];

    foreach ($family_needles as $slug => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                $families[] = $slug;
                break;
            }
        }
    }

    if (
        preg_match($picture_tube_pattern, $title_haystack)
        || preg_match($picture_tube_pattern, $tag_haystack)
    ) {
        $families[] = 'bildroehre';
    }

    if ($has_diode_product_signal && !$has_tube_context && !$has_material_object_signal) {
        $families[] = 'diode';
    }

    $material_process_override = (bool) preg_match('/keramiklötung|keramiklotung|metallisierung/u', $title_haystack . ' ' . $tag_haystack);

    if ($has_material_object_signal && !$has_diode_product_signal && (!$has_tube_context || $material_process_override)) {
        $families[] = 'werkstoff';
    }

    if ($has_diode_product_signal && !$has_tube_context) {
        $families[] = 'diode';
    }

    if (array_intersect($families, ['diode', 'werkstoff', 'baugruppe', 'verpackung'])) {
        $fields[] = 'halbleiter';
    }

    if ($has_tube_context) {
        $fields[] = 'roehre';
    }

    if (str_contains($haystack, 'verpackung') || str_contains($haystack, 'produktschachtel')) {
        $contexts[] = 'verpackung';
    }

    if (str_contains($haystack, 'vergleich') || str_contains($haystack, 'maßstab') || str_contains($haystack, 'massstab')) {
        $contexts[] = 'vergleichsaufnahme';
    }

    if (in_array('werkstoff', $families, true)) {
        $contexts[] = 'werkstoff';
    }

    if (str_contains($haystack, 'produktion') || str_contains($haystack, 'fertigung') || str_contains($haystack, 'arbeitsplatz') || str_contains($haystack, 'prüffeld')) {
        $contexts[] = 'produktion';
    }

    if (!$contexts) {
        $contexts[] = 'produkt';
    }

    if (!$fields && in_array('diode', $families, true)) {
        $fields[] = 'halbleiter';
    }

    if (!$fields && $families) {
        $fields[] = 'roehre';
    }

    return [
        'fields' => array_values(array_unique($fields)),
        'families' => array_values(array_unique($families)),
        'contexts' => array_values(array_unique($contexts)),
    ];
}

function iss_wf_import_md_payload_is_allowed(array $payload): bool
{
    $institution_name = mb_strtolower(trim((string) ($payload['object_institution']['institution_name'] ?? '')));
    $allowed_institution = $institution_name === '' || str_contains($institution_name, 'industriesalon schöneweide') || str_contains($institution_name, 'industriesalon schoeneweide');

    $allowed_collection_match = false;
    foreach ((array) ($payload['object_collection'] ?? []) as $collection) {
        if (!is_array($collection)) {
            continue;
        }

        $collection_name = mb_strtolower(trim((string) ($collection['collection_name'] ?? '')));
        if ($collection_name === '') {
            continue;
        }

        if (
            str_contains($collection_name, 'fotostelle wf - röhren und halbleiter')
            || str_contains($collection_name, 'fotostelle wf - rohren und halbleiter')
            || str_contains($collection_name, 'fotostelle wf - menschen im wf')
            || str_contains($collection_name, 'technisches fotoarchiv')
            || str_contains($collection_name, 'werks für fernsehelektronik')
            || str_contains($collection_name, 'werks fur fernsehelektronik')
        ) {
            $allowed_collection_match = true;
            break;
        }
    }

    if (!$allowed_institution) {
        return false;
    }

    if (!empty($payload['object_collection']) && !$allowed_collection_match) {
        return false;
    }

    return true;
}

function iss_wf_import_md_matches_family_filter(array $payload, string $family_filter, string $profile = 'roehren-halbleiter'): bool
{
    $family_filter = sanitize_title($family_filter);
    if ($family_filter === '') {
        return true;
    }

    $classification = iss_wf_import_md_map_classification($payload, $profile);
    if ($family_filter === 'diode-product') {
        $families = (array) ($classification['families'] ?? []);
        $title = mb_strtolower((string) ($payload['object_name'] ?? ''));
        $is_diode = in_array('diode', $families, true);
        $is_material = in_array('werkstoff', $families, true);
        $is_tube = in_array('gasentladungsroehre', $families, true)
            || in_array('thyratron', $families, true)
            || in_array('klystron', $families, true)
            || in_array('magnetron', $families, true)
            || in_array('roehrensystem', $families, true);

        if (!$is_diode || $is_material || $is_tube) {
            return false;
        }

        if (preg_match('/\boa[\s\-]?\d+|\bga[\s\-]?\d+|tunneldiode|rauschdiode|scheibendiode|gräfdiode|grafdiode|golddrahtdiode|zenerdiode|lumineszenzdiode|kristalldiode|diodenquartett|diodenmagazin|halbleiter[\s\-]?diode|diode\b/u', $title)) {
            return true;
        }

        return false;
    }

    if (in_array($family_filter, $classification['families'], true)) {
        return true;
    }

    $strict_families = [
        'thyratron',
        'klystron',
        'magnetron',
        'bildroehre',
        'miniaturroehre',
        'gasentladungsroehre',
        'roehrensystem',
        'werkstoff',
        'verpackung',
        'baugruppe',
        'diode',
        'trockenanlage',
        'ofen',
        'pruefstand',
        'fertigungslinie',
        'montageplatz',
        'arbeitsplatz',
        'innenraum',
        'laboraufbau',
        'transport',
    ];

    if (in_array($family_filter, $strict_families, true)) {
        return false;
    }

    $haystack = mb_strtolower(
        (string) ($payload['object_name'] ?? '') . ' ' .
        (string) ($payload['object_description'] ?? '')
    );

    return str_contains($haystack, str_replace('-', '', $family_filter))
        || str_contains($haystack, str_replace('-', ' ', $family_filter));
}

function iss_wf_import_md_build_image_sources(array $payload): array
{
    $sources = [];

    foreach ((array) ($payload['object_images'] ?? []) as $image) {
        if (!is_array($image)) {
            continue;
        }

        $folder = trim((string) ($image['folder'] ?? ''), '/');
        $filename = sanitize_file_name((string) ($image['filename_loc'] ?? ''));
        $preview = sanitize_file_name((string) ($image['preview'] ?? ''));
        $direct_url = esc_url_raw((string) ($image['direct_url'] ?? ''));

        if (($folder === '' || $filename === '') && $direct_url === '') {
            continue;
        }

        $sources[] = [
            'source_id' => absint($image['quell_id'] ?? 0),
            'source_url' => $direct_url !== '' ? $direct_url : ('https://asset.museum-digital.org/berlin/' . $folder . '/' . $filename),
            'preview_url' => $preview !== '' ? ('https://asset.museum-digital.org/berlin/' . $folder . '/' . $preview) : '',
            'attachment_id' => 0,
            'preview_attachment_id' => 0,
            'filename' => $filename,
            'label' => sanitize_text_field((string) ($image['name'] ?? '')),
            'owner' => sanitize_text_field((string) ($image['owner'] ?? '')),
            'creator' => sanitize_text_field((string) ($image['creator'] ?? '')),
            'rights' => sanitize_text_field((string) ($image['rights'] ?? '')),
            'type' => sanitize_key((string) ($image['type'] ?? 'image')),
            'is_main' => (string) ($image['is_main'] ?? '') === 'j',
        ];
    }

    return $sources;
}

function iss_wf_import_md_build_events(array $payload): array
{
    $events = [];

    foreach ((array) ($payload['object_events'] ?? []) as $event) {
        if (!is_array($event)) {
            continue;
        }

        $events[] = [
            'event_id' => absint($event['event_id'] ?? 0),
            'event_type' => absint($event['event_type'] ?? 0),
            'event_type_name' => sanitize_text_field((string) ($event['event_type_name'] ?? '')),
            'note' => sanitize_textarea_field((string) ($event['event_note'] ?? '')),
            'time_label' => sanitize_text_field((string) (($event['time']['time_name'] ?? '') ?: '')),
            'time_start' => sanitize_text_field((string) (($event['time']['time_start'] ?? '') ?: '')),
            'time_end' => sanitize_text_field((string) (($event['time']['time_end'] ?? '') ?: '')),
            'people_id' => absint($event['people_id'] ?? 0),
            'people_name' => sanitize_text_field((string) (($event['people']['displayname'] ?? '') ?: '')),
            'place_id' => absint($event['place_id'] ?? 0),
            'place_name' => sanitize_text_field((string) (($event['place']['place_name'] ?? '') ?: '')),
            'place_latitude' => (float) (($event['place']['place_latitude'] ?? 0.0) ?: 0.0),
            'place_longitude' => (float) (($event['place']['place_longitude'] ?? 0.0) ?: 0.0),
        ];
    }

    return $events;
}

function iss_wf_import_md_build_record(array $payload, string $profile = 'roehren-halbleiter'): array
{
    $object_id = absint($payload['object_id'] ?? 0);
    $year = iss_wf_import_md_extract_year($payload);
    $decade = iss_wf_import_md_format_decade($year);
    $classification = iss_wf_import_md_map_classification($payload, $profile);
    $image_sources = iss_wf_import_md_build_image_sources($payload);

    return [
        'object_id' => $object_id,
        'source_url' => iss_wf_import_md_build_object_url($object_id),
        'json_url' => iss_wf_import_md_build_json_url($object_id),
        'manifest_url' => iss_wf_import_md_build_manifest_url($object_id),
        'title' => sanitize_text_field((string) ($payload['object_name'] ?? '')),
        'excerpt' => iss_wf_import_md_build_excerpt($payload),
        'content' => iss_wf_import_md_build_post_content($payload),
        'inventory_number' => sanitize_text_field((string) ($payload['object_inventory_number'] ?? '')),
        'object_type' => sanitize_text_field((string) ($payload['object_type'] ?? '')),
        'material_technique' => sanitize_text_field((string) ($payload['object_material_technique'] ?? '')),
        'dimensions' => sanitize_text_field((string) ($payload['object_dimensions'] ?? '')),
        'year' => $year > 0 ? (string) $year : '',
        'decade' => $decade,
        'last_updated' => sanitize_text_field((string) ($payload['object_last_updated'] ?? '')),
        'institution_id' => absint($payload['object_institution']['institution_id'] ?? 0),
        'institution_name' => sanitize_text_field((string) ($payload['object_institution']['institution_name'] ?? '')),
        'rights_holder' => sanitize_text_field((string) ($payload['licence']['metadata_rights_holder'] ?? '')),
        'rights_status' => sanitize_text_field((string) ($payload['licence']['metadata_rights_status'] ?? '')),
        'image_sources' => $image_sources,
        'tags' => iss_wf_import_md_named_refs((array) ($payload['object_tags'] ?? []), 'tag_id', 'tag_name', 'tag'),
        'collections' => iss_wf_import_md_named_refs((array) ($payload['object_collection'] ?? []), 'collection_id', 'collection_name', 'collection'),
        'people' => array_values(array_merge(
            iss_wf_import_md_named_refs(array_map(static function ($item) {
                return is_array($item) ? ($item['people'] ?? []) : [];
            }, (array) ($payload['object_relation_people'] ?? [])), 'people_id', 'displayname', 'person')
        )),
        'places' => array_values(array_merge(
            iss_wf_import_md_named_refs(array_map(static function ($item) {
                return is_array($item) ? ($item['place'] ?? []) : [];
            }, (array) ($payload['object_relation_places'] ?? [])), 'place_id', 'place_name', 'place')
        )),
        'events' => iss_wf_import_md_build_events($payload),
        'classification' => $classification,
    ];
}

function iss_wf_import_md_find_existing_post(array $record): ?WP_Post
{
    $object_id = (int) ($record['object_id'] ?? 0);
    $source_url = (string) ($record['source_url'] ?? '');
    $inventory = (string) ($record['inventory_number'] ?? '');

    if ($object_id > 0) {
        $posts = get_posts([
            'post_type' => ISS_WF_IMPORT_OBJECT_POST_TYPE,
            'post_status' => 'any',
            'numberposts' => 1,
            'meta_key' => ISS_WF_IMPORT_OBJECT_MD_ID_META,
            'meta_value' => $object_id,
        ]);
        if ($posts) {
            return $posts[0];
        }
    }

    if ($inventory !== '') {
        $posts = get_posts([
            'post_type' => ISS_WF_IMPORT_OBJECT_POST_TYPE,
            'post_status' => 'any',
            'numberposts' => 2,
            'meta_key' => ISS_WF_IMPORT_OBJECT_INVENTORY_META,
            'meta_value' => $inventory,
        ]);
        if (count($posts) === 1) {
            return $posts[0];
        }
    }

    if ($source_url !== '') {
        $posts = get_posts([
            'post_type' => ISS_WF_IMPORT_OBJECT_POST_TYPE,
            'post_status' => 'any',
            'numberposts' => 1,
            'meta_key' => ISS_WF_IMPORT_SOURCE_URL_META,
            'meta_value' => $source_url,
        ]);
        if ($posts) {
            return $posts[0];
        }
    }

    return null;
}

function iss_wf_import_md_find_possible_duplicates(array $record): array
{
    $title = (string) ($record['title'] ?? '');
    $inventory = (string) ($record['inventory_number'] ?? '');

    if ($inventory === '' && $title === '') {
        return [];
    }

    $args = [
        'post_type' => ISS_WF_IMPORT_OBJECT_POST_TYPE,
        'post_status' => 'any',
        'numberposts' => 10,
    ];

    if ($inventory !== '') {
        $args['meta_query'] = [[
            'key' => ISS_WF_IMPORT_OBJECT_INVENTORY_META,
            'value' => $inventory,
        ]];
    }

    $posts = get_posts($args);
    $matches = [];

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $existing_md_id = (int) get_post_meta($post->ID, ISS_WF_IMPORT_OBJECT_MD_ID_META, true);
        if ($existing_md_id > 0 && $existing_md_id === (int) ($record['object_id'] ?? 0)) {
            continue;
        }

        if ($inventory === '' && $title !== '' && !hash_equals($post->post_title, $title)) {
            continue;
        }

        $matches[] = [
            'post_id' => $post->ID,
            'title' => $post->post_title,
        ];
    }

    return $matches;
}

function iss_wf_import_md_parse_selection(string $selection): string
{
    $selection = sanitize_key($selection);
    if (!in_array($selection, ['all', 'remaining'], true)) {
        return 'all';
    }

    return $selection;
}

function iss_wf_import_md_download_image(string $url, int $post_id, string $title = '')
{
    if ($url === '') {
        return new WP_Error('missing_image_url', 'No image URL available.');
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $tmp = download_url($url, 30);
    if (is_wp_error($tmp)) {
        return $tmp;
    }

    $file_array = [
        'name' => wp_basename(parse_url($url, PHP_URL_PATH) ?: ('museum-digital-' . $post_id . '.jpg')),
        'tmp_name' => $tmp,
    ];

    $attachment_id = media_handle_sideload($file_array, $post_id, $title);
    if (is_wp_error($attachment_id)) {
        @unlink($tmp);
        return $attachment_id;
    }

    update_post_meta($attachment_id, ISS_WF_IMPORT_ATTACHMENT_SOURCE_URL_META, esc_url_raw($url));

    return (int) $attachment_id;
}

function iss_wf_import_md_assign_terms(int $post_id, array $record): void
{
    $classification = (array) ($record['classification'] ?? []);

    wp_set_object_terms($post_id, ['museum-digital'], ISS_WF_IMPORT_SOURCE_TAXONOMY, true);

    $field_terms = array_values(array_filter(array_map('sanitize_title', (array) ($classification['fields'] ?? []))));
    $family_terms = array_values(array_filter(array_map('sanitize_title', (array) ($classification['families'] ?? []))));
    $context_terms = array_values(array_filter(array_map('sanitize_title', (array) ($classification['contexts'] ?? []))));
    $decade = sanitize_title((string) ($record['decade'] ?? ''));

    wp_set_object_terms($post_id, $field_terms, ISS_WF_IMPORT_FIELD_TAXONOMY, false);
    wp_set_object_terms($post_id, $family_terms, ISS_WF_IMPORT_FAMILY_TAXONOMY, false);
    wp_set_object_terms($post_id, $context_terms, ISS_WF_IMPORT_CONTEXT_TAXONOMY, false);
    wp_set_object_terms($post_id, $decade !== '' ? [$decade] : [], ISS_WF_IMPORT_DECADE_TAXONOMY, false);
}

function iss_wf_import_md_save_record(array $record): array
{
    $existing = iss_wf_import_md_find_existing_post($record);
    $postarr = [
        'post_type' => ISS_WF_IMPORT_OBJECT_POST_TYPE,
        'post_status' => 'publish',
        'post_title' => $record['title'],
        'post_excerpt' => $record['excerpt'],
        'post_content' => $record['content'],
    ];

    $action = 'create';
    if ($existing instanceof WP_Post) {
        $postarr['ID'] = $existing->ID;
        $action = 'update';
    }

    $post_id = wp_insert_post(wp_slash($postarr), true);
    if (is_wp_error($post_id)) {
        return [
            'ok' => false,
            'action' => $action,
            'message' => $post_id->get_error_message(),
        ];
    }

    $post_id = (int) $post_id;

    update_post_meta($post_id, ISS_WF_IMPORT_SOURCE_SITE_META, 'berlin.museum-digital.de');
    update_post_meta($post_id, ISS_WF_IMPORT_SOURCE_KIND_META, 'museum-digital-object');
    update_post_meta($post_id, ISS_WF_IMPORT_SOURCE_EXTERNAL_ID_META, (string) $record['object_id']);
    update_post_meta($post_id, ISS_WF_IMPORT_SOURCE_URL_META, $record['source_url']);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_MD_ID_META, (int) $record['object_id']);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_JSON_URL_META, $record['json_url']);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_MD_MANIFEST_URL_META, $record['manifest_url']);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_TYPE_META, $record['object_type']);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_INVENTORY_META, $record['inventory_number']);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_MATERIAL_META, $record['material_technique']);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_DIMENSIONS_META, $record['dimensions']);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_YEAR_META, $record['year']);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_DECADE_META, $record['decade']);
    update_post_meta($post_id, ISS_WF_IMPORT_SOURCE_MODIFIED_GMT_META, $record['last_updated']);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_MD_INSTITUTION_ID_META, (int) $record['institution_id']);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_MD_INSTITUTION_NAME_META, $record['institution_name']);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_RIGHTS_HOLDER_META, $record['rights_holder']);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_RIGHTS_STATUS_META, $record['rights_status']);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_MD_METADATA_HOLDER_META, $record['rights_holder']);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_MD_METADATA_RIGHTS_META, $record['rights_status']);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_IMAGE_SOURCE_META, $record['image_sources']);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_TAGS_META, $record['tags']);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_COLLECTIONS_META, $record['collections']);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_PEOPLE_RELATIONS_META, $record['people']);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_PLACE_RELATIONS_META, $record['places']);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_EVENTS_META, $record['events']);

    $main_image = null;
    foreach ((array) $record['image_sources'] as $image_source) {
        if (!is_array($image_source) || empty($image_source['is_main'])) {
            continue;
        }
        $main_image = $image_source;
        break;
    }
    if ($main_image === null && !empty($record['image_sources'][0]) && is_array($record['image_sources'][0])) {
        $main_image = $record['image_sources'][0];
    }

    if (is_array($main_image)) {
        $new_main_image_url = (string) ($main_image['source_url'] ?? '');
        $current_attachment_id = (int) get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_PRIMARY_ATTACHMENT_META, true);
        $current_image_url = (string) get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_MD_IMAGE_URL_META, true);

        update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_MD_IMAGE_URL_META, (string) ($main_image['source_url'] ?? ''));
        update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_MD_IMAGE_RIGHTS_META, (string) ($main_image['rights'] ?? ''));
        update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_MD_IMAGE_OWNER_META, (string) ($main_image['owner'] ?? ''));

        if ($current_attachment_id <= 0 || $current_image_url !== $new_main_image_url) {
            $attachment_id = iss_wf_import_md_download_image((string) ($main_image['source_url'] ?? ''), $post_id, (string) $record['title']);
            if (!is_wp_error($attachment_id)) {
                set_post_thumbnail($post_id, (int) $attachment_id);
                update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_PRIMARY_ATTACHMENT_META, (int) $attachment_id);
            }
        }
    }

    iss_wf_import_md_assign_terms($post_id, $record);

    return [
        'ok' => true,
        'action' => $action,
        'post_id' => $post_id,
        'message' => '',
    ];
}

if (defined('WP_CLI') && WP_CLI) {
    class ISS_WF_Import_Museum_Digital_CLI_Command
    {
        /**
         * Import or dry-run museum-digital archive objects.
         *
         * ## OPTIONS
         *
         * [--seed-url=<url>]
         * : WF seed page used for object discovery.
         *
         * [--object-id=<id>]
         * : Import a single museum-digital object id directly.
         *
         * [--family=<slug-or-label>]
         * : Optional pilot family filter, e.g. thyratron or diode.
         *
         * [--profile=<slug>]
         * : Optional classifier profile. Supported: roehren-halbleiter, anlagen-arbeitsplaetze, geraete-bauteile, telekommunikation-fernsehtechnik, diverses-gebaeude-schaltbilder, menschen-im-wf.
         *
         * [--limit=<number>]
         * : Limit processed objects. Default 25.
         *
         * [--offset=<number>]
         * : Skip this many matched records after filtering. Default 0.
         *
         * [--selection=<mode>]
         * : all or remaining. remaining skips exact existing museum-digital matches before batching. Default all.
         *
         * [--only-new]
         * : Alias for --selection=remaining.
         *
         * [--skip-possible-duplicates]
         * : Skip records that have legacy duplicate candidates.
         *
         * [--mode=<mode>]
         * : dry-run or import. Default dry-run.
         *
         * ## EXAMPLES
         *
         *     wp iss-archive import-museum-digital --seed-url='https://wf-museum.de/home-2/wf-technik/roehren-und-halbleiter/' --family=thyratron --limit=25 --mode=dry-run
         *     wp iss-archive import-museum-digital --object-id=86046 --mode=import
         */
        public function __invoke(array $args, array $assoc_args): void
        {
            $seed_url = esc_url_raw((string) ($assoc_args['seed-url'] ?? ''));
            $object_id = absint($assoc_args['object-id'] ?? 0);
            $family_filter = sanitize_title((string) ($assoc_args['family'] ?? ''));
            $profile = iss_wf_import_md_resolve_profile($seed_url, (string) ($assoc_args['profile'] ?? ''));
            $limit = max(1, absint($assoc_args['limit'] ?? 25));
            $offset = max(0, absint($assoc_args['offset'] ?? 0));
            $selection = iss_wf_import_md_parse_selection((string) ($assoc_args['selection'] ?? (($assoc_args['only-new'] ?? false) ? 'remaining' : 'all')));
            $skip_possible_duplicates = !empty($assoc_args['skip-possible-duplicates']);
            $mode = sanitize_key((string) ($assoc_args['mode'] ?? 'dry-run'));

            if (!in_array($mode, ['dry-run', 'import'], true)) {
                WP_CLI::error('Invalid --mode. Use dry-run or import.');
            }

            if ($object_id <= 0 && $seed_url === '') {
                WP_CLI::error('Provide either --object-id or --seed-url.');
            }

            $source_term_id = iss_wf_import_ensure_source_term_by_slug('museum-digital', 'museum-digital');
            if ($source_term_id <= 0) {
                WP_CLI::warning('Could not ensure source taxonomy term museum-digital.');
            }

            $seed_rows = [];
            $object_ids = [];
            if ($object_id > 0) {
                $object_ids[] = $object_id;
            } else {
                $seed_rows = iss_wf_import_md_discover_seed_rows($seed_url);
                $object_ids = array_values(array_filter(array_map(static function ($row) {
                    return absint($row['object_id'] ?? 0);
                }, $seed_rows)));

                if (!$object_ids) {
                    $object_ids = iss_wf_import_md_discover_object_ids_from_seed($seed_url);
                }
            }

            if (!$object_ids) {
                WP_CLI::error('No museum-digital object ids discovered.');
            }

            $processed = 0;
            $skipped_matches = 0;
            $rows = [];
            $summary = [
                'discovered' => count($object_ids),
                'considered' => 0,
                'selection' => $selection,
                'profile' => $profile,
                'skip_possible_duplicates' => $skip_possible_duplicates,
                'matched_filter' => 0,
                'offset' => $offset,
                'selection_candidates' => 0,
                'existing_exact_skipped' => 0,
                'possible_duplicates' => 0,
                'possible_duplicates_skipped' => 0,
                'scope_filtered_out' => 0,
                'family_filtered_out' => 0,
                'created' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'errors' => 0,
            ];

            $seed_rows_by_id = [];
            foreach ($seed_rows as $seed_row) {
                if (!is_array($seed_row)) {
                    continue;
                }
                $seed_rows_by_id[(int) ($seed_row['object_id'] ?? 0)] = $seed_row;
            }

            foreach ($object_ids as $current_object_id) {
                if ($processed >= $limit) {
                    break;
                }

                $summary['considered']++;
                $seed_payload = isset($seed_rows_by_id[(int) $current_object_id]) ? iss_wf_import_md_seed_row_to_payload($seed_rows_by_id[(int) $current_object_id]) : [];
                if ($seed_payload && !iss_wf_import_md_matches_family_filter($seed_payload, $family_filter, $profile)) {
                    $summary['family_filtered_out']++;
                    continue;
                }

                $fetched_payload = iss_wf_import_md_fetch_payload((int) $current_object_id);
                if (empty($fetched_payload['ok']) && !$seed_payload) {
                    $summary['errors']++;
                    $rows[] = [
                        'object_id' => $current_object_id,
                        'action' => 'error',
                        'title' => '',
                        'family' => '',
                        'year' => '',
                        'match' => (string) ($fetched_payload['message'] ?? 'fetch failed'),
                    ];
                    continue;
                }

                $payload = !empty($fetched_payload['ok']) ? ($fetched_payload['payload'] ?? []) : $seed_payload;
                if (is_array($payload) && $seed_payload && !iss_wf_import_md_payload_has_core_identity($payload)) {
                    $payload = iss_wf_import_md_merge_seed_payload($payload, $seed_payload);
                }
                if (!is_array($payload)) {
                    $summary['errors']++;
                    $rows[] = [
                        'object_id' => $current_object_id,
                        'action' => 'error',
                        'title' => '',
                        'family' => '',
                        'year' => '',
                        'match' => 'invalid json',
                    ];
                    continue;
                }

                if (!iss_wf_import_md_payload_is_allowed($payload)) {
                    $summary['scope_filtered_out']++;
                    continue;
                }

                if (!iss_wf_import_md_matches_family_filter($payload, $family_filter, $profile)) {
                    $summary['family_filtered_out']++;
                    continue;
                }

                $record = iss_wf_import_md_build_record($payload, $profile);
                $existing = iss_wf_import_md_find_existing_post($record);
                $possible_duplicates = iss_wf_import_md_find_possible_duplicates($record);
                if ($possible_duplicates) {
                    $summary['possible_duplicates']++;
                    if ($skip_possible_duplicates) {
                        $summary['possible_duplicates_skipped']++;
                        continue;
                    }
                }

                $is_remaining_candidate = !($existing instanceof WP_Post);
                if ($selection === 'remaining') {
                    if (!$is_remaining_candidate) {
                        $summary['existing_exact_skipped']++;
                        continue;
                    }
                }

                $summary['matched_filter']++;
                if ($is_remaining_candidate) {
                    $summary['selection_candidates']++;
                }

                if ($skipped_matches < $offset) {
                    $skipped_matches++;
                    continue;
                }

                $processed++;

                $family = implode(',', (array) ($record['classification']['families'] ?? []));
                $match_note = $existing instanceof WP_Post
                    ? ('exact:' . $existing->ID)
                    : ($possible_duplicates ? ('possible:' . implode(',', wp_list_pluck($possible_duplicates, 'post_id'))) : 'new');

                if ($mode === 'import') {
                    $result = iss_wf_import_md_save_record($record);
                    if (empty($result['ok'])) {
                        $summary['errors']++;
                        $rows[] = [
                            'object_id' => $record['object_id'],
                            'action' => 'error',
                            'title' => $record['title'],
                            'family' => $family,
                            'year' => $record['year'],
                            'match' => (string) ($result['message'] ?? 'save failed'),
                        ];
                        continue;
                    }

                    if (($result['action'] ?? '') === 'create') {
                        $summary['created']++;
                    } else {
                        $summary['updated']++;
                    }

                    $rows[] = [
                        'object_id' => $record['object_id'],
                        'action' => (string) $result['action'],
                        'title' => $record['title'],
                        'family' => $family,
                        'year' => $record['year'],
                        'match' => 'post:' . (int) ($result['post_id'] ?? 0),
                    ];
                    continue;
                }

                $summary['unchanged']++;
                $rows[] = [
                    'object_id' => $record['object_id'],
                    'action' => $existing instanceof WP_Post ? 'update' : 'create',
                    'title' => $record['title'],
                    'family' => $family,
                    'year' => $record['year'],
                    'match' => $match_note . ':' . (string) (($fetched_payload['source'] ?? '') !== '' ? $fetched_payload['source'] : 'seed'),
                ];
            }

            WP_CLI::log('Summary: ' . wp_json_encode($summary, JSON_UNESCAPED_UNICODE));
            WP_CLI\Utils\format_items('table', $rows, ['object_id', 'action', 'family', 'year', 'match', 'title']);
        }
    }

    WP_CLI::add_command('iss-archive import-museum-digital', 'ISS_WF_Import_Museum_Digital_CLI_Command');
}
