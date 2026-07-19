<?php
/**
 * Apply the curated Kino Spreehöfe identity facts, historical image, and epochs.
 *
 * Extract the paired uploads artifact at the target WordPress root first:
 * tar -xzf ops/uploads/2026-07-19-kino-spreehoefe-historical-media.tar.gz
 *
 * Run after the matching code deployment:
 * wp eval-file ops/migrations/2026-07-19-kino-spreehoefe-place-dossier.php --allow-root
 *
 * Rollback sources:
 * - wp_iss_backup_20260719_kino_place_meta
 * - wp_iss_backup_20260719_kino_place_epochs
 *
 * The migration-created attachment can be identified by:
 * _wp_attached_file = 2026/04/treskowbruecke-gasanstalt-oberspree-1926.webp
 */

if (!defined('ABSPATH') || !defined('WP_CLI')) {
    exit(1);
}

$post_id = 12899;
$post = get_post($post_id);
if (!$post instanceof WP_Post || $post->post_type !== 'register_place' || $post->post_name !== 'kino-spreehofe') {
    WP_CLI::error('Expected register_place 12899 with slug kino-spreehofe.');
}

if (!function_exists('iss_register_get_epoch_service')) {
    WP_CLI::error('Missing register epoch service.');
}

global $wpdb;

$meta_backup_table = $wpdb->prefix . 'iss_backup_20260719_kino_place_meta';
$epoch_backup_table = $wpdb->prefix . 'iss_backup_20260719_kino_place_epochs';
$epoch_table = iss_register_get_epoch_service()->get_table_name();

foreach ([$meta_backup_table, $epoch_backup_table] as $backup_table) {
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $backup_table)) === $backup_table) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration guard.
        WP_CLI::error('Backup table already exists: ' . $backup_table);
    }
}

$meta_keys = [
    'construction_period',
    'original_name',
    'monument_status',
    'monument_record_url',
    'previous_use',
    'archive_images',
    '_thumbnail_id',
];
$quoted_meta_keys = implode(', ', array_map(static function (string $key): string {
    return "'" . esc_sql($key) . "'";
}, $meta_keys));

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- One-off migration backups with prefix-derived escaped identifiers.
$meta_backup_sql = sprintf(
    'CREATE TABLE `%s` AS SELECT * FROM `%s` WHERE post_id = %d AND meta_key IN (%s)',
    esc_sql($meta_backup_table),
    esc_sql($wpdb->postmeta),
    $post_id,
    $quoted_meta_keys
);
if ($wpdb->query($meta_backup_sql) === false) {
    WP_CLI::error('Could not create targeted postmeta backup.');
}

$epoch_backup_sql = sprintf(
    'CREATE TABLE `%s` AS SELECT * FROM `%s` WHERE place_post_id = %d',
    esc_sql($epoch_backup_table),
    esc_sql($epoch_table),
    $post_id
);
if ($wpdb->query($epoch_backup_sql) === false) {
    WP_CLI::error('Could not create targeted epoch backup.');
}
// phpcs:enable

$relative_file = '2026/04/treskowbruecke-gasanstalt-oberspree-1926.webp';
$uploads = wp_upload_dir();
$absolute_file = trailingslashit((string) $uploads['basedir']) . $relative_file;
if (!is_file($absolute_file)) {
    WP_CLI::error('Missing extracted historical image: ' . $absolute_file);
}

$attachment_ids = get_posts([
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'posts_per_page' => 1,
    'fields' => 'ids',
    'meta_key' => '_wp_attached_file', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One exact attachment lookup in a one-off migration.
    'meta_value' => $relative_file, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Paired with the exact attached-file key.
    'suppress_filters' => true,
]);
$attachment_id = $attachment_ids ? (int) $attachment_ids[0] : 0;

$attachment_post = [
    'post_title' => 'Treskowbrücke und Gasanstalt Oberspree, 1926',
    'post_excerpt' => 'Treskowbrücke 1926, mittig die Gasanstalt Oberspree.',
    'post_content' => 'Historische Postkarte: Treskowbrücke 1926, mittig die Gasanstalt Oberspree. Digitaler Ausschnitt aus der BIWAQ-Tafel „Nutzungsmischung aus Tradition – Freizeit- und Gewerbezentrum Spreehöfe“, Stand September 2012. Urheber und ursprünglicher Postkartenverlag sind nicht ermittelt. Quelle: https://www.yumpu.com/de/document/view/2150402/tafeln-1-5-biwaq-schoeneweide/2',
    'post_status' => 'inherit',
    'post_mime_type' => 'image/webp',
    'guid' => trailingslashit((string) $uploads['baseurl']) . $relative_file,
];

if ($attachment_id > 0) {
    $attachment_post['ID'] = $attachment_id;
    $updated_attachment = wp_update_post($attachment_post, true);
    if (is_wp_error($updated_attachment)) {
        WP_CLI::error($updated_attachment->get_error_message());
    }
} else {
    $attachment_id = wp_insert_attachment($attachment_post, $absolute_file, 0, true);
    if (is_wp_error($attachment_id)) {
        WP_CLI::error($attachment_id->get_error_message());
    }
    $attachment_id = (int) $attachment_id;
}

$attachment_metadata = [
    'width' => 1521,
    'height' => 1034,
    'file' => $relative_file,
    'filesize' => 493150,
    'sizes' => [
        'medium' => [
            'file' => 'treskowbruecke-gasanstalt-oberspree-1926-300x204.webp',
            'width' => 300,
            'height' => 204,
            'mime-type' => 'image/webp',
            'filesize' => 21462,
        ],
        'large' => [
            'file' => 'treskowbruecke-gasanstalt-oberspree-1926-1024x696.webp',
            'width' => 1024,
            'height' => 696,
            'mime-type' => 'image/webp',
            'filesize' => 187142,
        ],
        'thumbnail' => [
            'file' => 'treskowbruecke-gasanstalt-oberspree-1926-150x150.webp',
            'width' => 150,
            'height' => 150,
            'mime-type' => 'image/webp',
            'filesize' => 8826,
        ],
        'medium_large' => [
            'file' => 'treskowbruecke-gasanstalt-oberspree-1926-768x522.webp',
            'width' => 768,
            'height' => 522,
            'mime-type' => 'image/webp',
            'filesize' => 121176,
        ],
    ],
    'image_meta' => [
        'aperture' => '0',
        'credit' => '',
        'camera' => '',
        'caption' => '',
        'created_timestamp' => '0',
        'copyright' => '',
        'focal_length' => '0',
        'iso' => '0',
        'shutter_speed' => '0',
        'title' => '',
        'orientation' => '0',
        'keywords' => [],
        'alt' => '',
    ],
];

update_post_meta($attachment_id, '_wp_attached_file', $relative_file);
update_post_meta($attachment_id, '_wp_attachment_metadata', $attachment_metadata);
update_post_meta(
    $attachment_id,
    '_wp_attachment_image_alt',
    'Historische Ansicht der Treskowbrücke mit Straßenbahn und der Gasanstalt Oberspree im Hintergrund, 1926'
);

$archive_images = [[
    'media_id' => $attachment_id,
    'url' => wp_get_attachment_url($attachment_id),
    'caption' => 'Treskowbrücke 1926, mittig die Gasanstalt Oberspree.',
    'year' => '1926',
    'source' => 'Industriesalon/BIWAQ Schöneweide, Tafel „Nutzungsmischung aus Tradition“, 2012; https://www.yumpu.com/de/document/view/2150402/tafeln-1-5-biwaq-schoeneweide/2',
    'photographer' => 'Unbekannt',
    'rights' => 'Historische Postkarte; Urheber nicht ermittelt; Wiederverwendung aus dem Industriesalon/BIWAQ-Projekt.',
    'is_featured' => true,
    'visibility' => 'public',
]];

$place_meta = [
    'construction_period' => '1898–1906',
    'original_name' => 'Städtische Gasanstalt Oberspree',
    'monument_status' => 'Gesamtanlage',
    'monument_record_url' => 'https://denkmaldatenbank.berlin.de/daobj.php?obj_dok_nr=09020128',
    'previous_use' => 'Teil des 1898 eröffneten Gaswerks Oberspree an der Wilhelminenhofstraße 88/89. Ab 1937/39 nutzte ADMOS den rückwärtigen Grundstücksteil; ab 1951 gehörte der Betrieb als Werk III zum VEB Berliner Metallhütten- und Halbzeugwerke. Zwei ehemalige Produktionshallen wurden 1998 zum Kino umgebaut.',
    'archive_images' => $archive_images,
    '_thumbnail_id' => $attachment_id,
];
foreach ($place_meta as $key => $value) {
    update_post_meta($post_id, $key, $value);
}

$epochs = [
    [
        'era_slug' => 'kaiserzeit',
        'function_key' => 'infrastructure',
        'phase_name' => 'Gasanstalt Oberspree',
        'summary' => '1898 nahm die von der Imperial Continental Gas Association errichtete Gasanstalt Oberspree auf dem Grundstück Wilhelminenhofstraße 88/89 den Betrieb auf. 1898/99 entstanden Retortenhaus sowie Maschinen- und Apparatehaus; 1905/06 folgten Kesselhaus, Hochdruckanlage und Erweiterungen.',
        'start_year' => 1898,
        'end_year' => 1918,
        'is_current' => false,
        'source_confidence' => 'url',
        'source_summary' => 'Landesdenkmalamt Berlin, Denkmaldatenbank: Gaswerk Oberspree.',
        'source_links' => ['https://denkmaldatenbank.berlin.de/daobj.php?obj_dok_nr=09020128'],
        'media_ids' => [],
    ],
    [
        'era_slug' => 'weimar',
        'function_key' => 'infrastructure',
        'phase_name' => 'Gaswerk bis zum Ende der Stadtgaserzeugung',
        'summary' => 'Das Gaswerk versorgte Schöneweide und benachbarte Ortsteile mit Stadtgas. Die Erzeugung von Stadtgas endete 1927; Teile der Speicher- und Verteilungsanlagen blieben erhalten und wurden weiter genutzt.',
        'start_year' => 1919,
        'end_year' => 1927,
        'is_current' => false,
        'source_confidence' => 'url',
        'source_summary' => 'Landesdenkmalamt Berlin; historische Postkarte auf der Industriesalon/BIWAQ-Tafel „Nutzungsmischung aus Tradition“ (2012).',
        'source_links' => [
            'https://denkmaldatenbank.berlin.de/daobj.php?obj_dok_nr=09020128',
            'https://www.yumpu.com/de/document/view/2150402/tafeln-1-5-biwaq-schoeneweide/2',
        ],
        'media_ids' => [$attachment_id],
    ],
    [
        'era_slug' => 'ns-zeit',
        'function_key' => 'industrial',
        'phase_name' => 'ADMOS auf dem Gaswerksgelände',
        'summary' => 'Ab 1937/39 übernahmen die Allgemeinen Deutschen Metallwerke Oberschöneweide (ADMOS) den rückwärtigen Teil des Gaswerksgeländes und produzierten dort hochwertige Metalllegierungen. Eine Grundstücksakte von 1939 dokumentiert die Abtrennung und geplante Bebauung.',
        'start_year' => 1937,
        'end_year' => 1945,
        'is_current' => false,
        'source_confidence' => 'url',
        'source_summary' => 'Landesdenkmalamt Berlin; Landesarchiv Berlin, Grundstücksakte A Rep. 046-08 Nr. 312.',
        'source_links' => [
            'https://denkmaldatenbank.berlin.de/daobj.php?obj_dok_nr=09020128',
            'https://www.deutsche-digitale-bibliothek.de/item/MJWLVMMH2KNHVZNIQHPRISNZX4Y7GK2G',
        ],
        'media_ids' => [],
    ],
    [
        'era_slug' => 'ddr',
        'function_key' => 'industrial',
        'phase_name' => 'VEB Berliner Metallhütten- und Halbzeugwerke',
        'summary' => 'Zum 1. Januar 1951 wurde der Oberschöneweider Betrieb als Werk III in den VEB Berliner Metallhütten- und Halbzeugwerke eingegliedert. Der große Gasbehälter auf dem Areal blieb bis 1974 in Betrieb.',
        'start_year' => 1951,
        'end_year' => 1990,
        'is_current' => false,
        'source_confidence' => 'url',
        'source_summary' => 'Landesdenkmalamt Berlin; Industriesalon Schöneweide / Deutsche Digitale Bibliothek.',
        'source_links' => [
            'https://denkmaldatenbank.berlin.de/daobj.php?obj_dok_nr=09020128',
            'https://www.deutsche-digitale-bibliothek.de/item/X2T4JHIXVTV5LZRU4XOSPG4WYU4WGAXS',
        ],
        'media_ids' => [],
    ],
    [
        'era_slug' => 'nach-1990',
        'function_key' => 'mixed',
        'phase_name' => 'Rückbau und Umnutzung zu den Spreehöfen',
        'summary' => 'Nach 1990 wurden Teile des Retorten- und Kesselhauses für Parkplatz, Kino und Gewerbe abgebrochen. 1993 wurde das Eisengerüst des großen Gasbehälters demontiert; anschließend entwickelte sich das Areal zu den Spreehöfen.',
        'start_year' => 1991,
        'end_year' => 1997,
        'is_current' => false,
        'source_confidence' => 'url',
        'source_summary' => 'Landesdenkmalamt Berlin, Denkmaldatenbank: Gaswerk Oberspree.',
        'source_links' => ['https://denkmaldatenbank.berlin.de/daobj.php?obj_dok_nr=09020128'],
        'media_ids' => [],
    ],
    [
        'era_slug' => 'nach-1990',
        'function_key' => 'culture',
        'phase_name' => 'Kino Spreehöfe',
        'summary' => 'Zwei ehemalige Produktionshallen von ADMOS/BMHW wurden zu fünf Kinosälen umgebaut. Die „Kinowelt in den Spreehöfen“ eröffnete am 23. September 1998. Nach dem Betreiberwechsel 2001 wird das Haus seit dem 2. Februar 2006 als Kino Spreehöfe geführt.',
        'start_year' => 1998,
        'end_year' => null,
        'is_current' => true,
        'source_confidence' => 'url',
        'source_summary' => 'Berliner Monatszeitschrift 11/1998; Kino Spreehöfe, Über uns.',
        'source_links' => [
            'https://berlingeschichte.de/bms/bmstext/9811dokb.htm',
            'https://www.kino-spreehoefe.de/unterseite/6618/%C3%9Cber_uns',
        ],
        'media_ids' => [],
    ],
];

$saved = iss_register_get_epoch_service()->save_epochs_for_place(
    $post_id,
    $epochs,
    ['source' => 'ops/migrations/2026-07-19-kino-spreehoefe-place-dossier.php']
);
if (is_wp_error($saved)) {
    WP_CLI::error($saved->get_error_message());
}

if (function_exists('iss_register_clear_places_cache')) {
    iss_register_clear_places_cache();
}

$saved_epochs = iss_register_get_epoch_service()->get_epochs_for_place($post_id);
$current_epochs = array_values(array_filter($saved_epochs, static function (array $epoch): bool {
    return !empty($epoch['is_current']);
}));
$saved_images = get_post_meta($post_id, 'archive_images', true);

if (
    count($saved_epochs) !== 6
    || count($current_epochs) !== 1
    || (int) ($saved_images[0]['media_id'] ?? 0) !== $attachment_id
    || get_post_thumbnail_id($post_id) !== $attachment_id
) {
    WP_CLI::error('Post-write verification failed. Restore from the targeted backup tables.');
}

WP_CLI::success(sprintf(
    'Applied Kino Spreehöfe dossier data with attachment %d and %d epochs; backups: %s, %s.',
    $attachment_id,
    count($saved_epochs),
    $meta_backup_table,
    $epoch_backup_table
));
