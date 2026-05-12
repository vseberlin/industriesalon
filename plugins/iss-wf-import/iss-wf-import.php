<?php
/**
 * Plugin Name: ISS Archive
 * Description: Owns the local archive content model for archive posts, collections, and objects.
 * Version: 0.4.0
 * Author: Industriesalon
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Internal compatibility note:
 * the plugin directory and PHP prefixes stay `iss_wf_import` for now so the
 * existing runtime and stored references remain stable.
 */
define('ISS_WF_IMPORT_VERSION', '0.4.0');
define('ISS_WF_IMPORT_PATH', plugin_dir_path(__FILE__));
define('ISS_WF_IMPORT_URL', plugin_dir_url(__FILE__));
define('ISS_WF_IMPORT_REWRITE_VERSION', '2026-05-05-archive-routing-2');
define('ISS_ARCHIVE_VERSION', ISS_WF_IMPORT_VERSION);
define('ISS_ARCHIVE_PATH', ISS_WF_IMPORT_PATH);
define('ISS_ARCHIVE_URL', ISS_WF_IMPORT_URL);

define('ISS_WF_IMPORT_POST_TYPE', 'archivbeitrag');
define('ISS_WF_IMPORT_COLLECTION_POST_TYPE', 'archivsammlung');
define('ISS_WF_IMPORT_OBJECT_POST_TYPE', 'archivobjekt');
define('ISS_WF_IMPORT_SOURCE_TAXONOMY', 'archiv_quelle');
define('ISS_WF_IMPORT_CATEGORY_TAXONOMY', 'archiv_kategorie');
define('ISS_WF_IMPORT_TAG_TAXONOMY', 'archiv_schlagwort');
define('ISS_WF_IMPORT_FIELD_TAXONOMY', 'archiv_themenfeld');
define('ISS_WF_IMPORT_FAMILY_TAXONOMY', 'archiv_objektfamilie');
define('ISS_WF_IMPORT_CONTEXT_TAXONOMY', 'archiv_kontext');
define('ISS_WF_IMPORT_DECADE_TAXONOMY', 'archiv_dekade');

define('ISS_WF_IMPORT_REMOTE_POST_ID_META', 'iss_source_post_id');
define('ISS_WF_IMPORT_SOURCE_SITE_META', 'iss_source_site');
define('ISS_WF_IMPORT_SOURCE_KIND_META', 'iss_archive_source_kind');
define('ISS_WF_IMPORT_SOURCE_EXTERNAL_ID_META', 'iss_archive_source_external_id');
define('ISS_WF_IMPORT_SOURCE_URL_META', 'iss_source_url');
define('ISS_WF_IMPORT_SOURCE_SLUG_META', 'iss_source_slug');
define('ISS_WF_IMPORT_SOURCE_DATE_GMT_META', 'iss_source_date_gmt');
define('ISS_WF_IMPORT_SOURCE_MODIFIED_GMT_META', 'iss_source_modified_gmt');
define('ISS_WF_IMPORT_SOURCE_AUTHOR_META', 'iss_source_author');

define('ISS_WF_IMPORT_COLLECTION_ITEMS_META', 'iss_archive_collection_items');
define('ISS_WF_IMPORT_COLLECTION_CHILDREN_META', 'iss_archive_collection_children');
define('ISS_WF_IMPORT_COLLECTION_SOURCE_IDS_META', 'iss_archive_collection_source_ids');
define('ISS_WF_IMPORT_COLLECTION_SCHEMA_OPTION', 'iss_wf_import_collection_schema_version');
define('ISS_WF_IMPORT_COLLECTION_SCHEMA_VERSION', '2026-05-12-collection-schema-3');
define('ISS_WF_IMPORT_COLLECTION_BACKFILL_OPTION', 'iss_wf_import_collection_backfill_version');
define('ISS_WF_IMPORT_COLLECTION_BACKFILL_VERSION', '2026-05-12-collection-backfill-3');
define('ISS_WF_IMPORT_OBJECT_SCHEMA_OPTION', 'iss_wf_import_object_schema_version');
define('ISS_WF_IMPORT_OBJECT_SCHEMA_VERSION', '2026-05-12-object-schema-1');
define('ISS_WF_IMPORT_OBJECT_BACKFILL_OPTION', 'iss_wf_import_object_backfill_version');
define('ISS_WF_IMPORT_OBJECT_BACKFILL_VERSION', '2026-05-12-object-backfill-2');
define('ISS_WF_IMPORT_MEDIA_SCHEMA_OPTION', 'iss_wf_import_media_schema_version');
define('ISS_WF_IMPORT_MEDIA_SCHEMA_VERSION', '2026-05-12-media-schema-1');
define('ISS_WF_IMPORT_MEDIA_BACKFILL_OPTION', 'iss_wf_import_media_backfill_version');
define('ISS_WF_IMPORT_MEDIA_BACKFILL_VERSION', '2026-05-12-media-backfill-1');
define('ISS_WF_IMPORT_RELATION_SCHEMA_OPTION', 'iss_wf_import_relation_schema_version');
define('ISS_WF_IMPORT_RELATION_SCHEMA_VERSION', '2026-05-12-relation-schema-1');
define('ISS_WF_IMPORT_RELATION_BACKFILL_OPTION', 'iss_wf_import_relation_backfill_version');
define('ISS_WF_IMPORT_RELATION_BACKFILL_VERSION', '2026-05-12-relation-backfill-2');
define('ISS_WF_IMPORT_IMPORT_SCHEMA_OPTION', 'iss_wf_import_import_schema_version');
define('ISS_WF_IMPORT_IMPORT_SCHEMA_VERSION', '2026-05-12-import-schema-1');
define('ISS_WF_IMPORT_IMPORT_BACKFILL_OPTION', 'iss_wf_import_import_backfill_version');
define('ISS_WF_IMPORT_IMPORT_BACKFILL_VERSION', '2026-05-12-import-backfill-2');
define('ISS_WF_IMPORT_ASSERTION_SCHEMA_OPTION', 'iss_wf_import_assertion_schema_version');
define('ISS_WF_IMPORT_ASSERTION_SCHEMA_VERSION', '2026-05-12-assertion-schema-1');
define('ISS_WF_IMPORT_ASSERTION_BACKFILL_OPTION', 'iss_wf_import_assertion_backfill_version');
define('ISS_WF_IMPORT_ASSERTION_BACKFILL_VERSION', '2026-05-12-assertion-backfill-1');
define('ISS_WF_IMPORT_OBJECT_PRIMARY_ATTACHMENT_META', 'iss_archive_primary_attachment_id');
define('ISS_WF_IMPORT_OBJECT_PREVIEW_ATTACHMENT_META', 'iss_archive_preview_attachment_id');
define('ISS_WF_IMPORT_OBJECT_TYPE_META', 'iss_archive_object_type');
define('ISS_WF_IMPORT_OBJECT_INVENTORY_META', 'iss_archive_inventory_number');
define('ISS_WF_IMPORT_OBJECT_RIGHTS_HOLDER_META', 'iss_archive_rights_holder');
define('ISS_WF_IMPORT_OBJECT_RIGHTS_STATUS_META', 'iss_archive_rights_status');
define('ISS_WF_IMPORT_OBJECT_CREATOR_META', 'iss_archive_creator');
define('ISS_WF_IMPORT_OBJECT_MATERIAL_META', 'iss_archive_material');
define('ISS_WF_IMPORT_OBJECT_DIMENSIONS_META', 'iss_archive_dimensions');
define('ISS_WF_IMPORT_OBJECT_JSON_URL_META', 'iss_archive_json_url');
define('ISS_WF_IMPORT_OBJECT_IMAGE_SOURCE_META', 'iss_archive_object_images');
define('ISS_WF_IMPORT_OBJECT_TAGS_META', 'iss_archive_object_tags');
define('ISS_WF_IMPORT_OBJECT_COLLECTIONS_META', 'iss_archive_object_collections');
define('ISS_WF_IMPORT_OBJECT_SERIES_META', 'iss_archive_object_series');
define('ISS_WF_IMPORT_OBJECT_EVENTS_META', 'iss_archive_object_events');
define('ISS_WF_IMPORT_OBJECT_PLACE_RELATIONS_META', 'iss_archive_object_places');
define('ISS_WF_IMPORT_OBJECT_PEOPLE_RELATIONS_META', 'iss_archive_object_people');
define('ISS_WF_IMPORT_OBJECT_RELATIONS_META', 'iss_related_archive_objects');
define('ISS_WF_IMPORT_OBJECT_MD_ID_META', 'iss_md_object_id');
define('ISS_WF_IMPORT_OBJECT_MD_MANIFEST_URL_META', 'iss_md_manifest_url');
define('ISS_WF_IMPORT_OBJECT_MD_IMAGE_URL_META', 'iss_md_image_url');
define('ISS_WF_IMPORT_OBJECT_MD_IMAGE_RIGHTS_META', 'iss_md_image_rights');
define('ISS_WF_IMPORT_OBJECT_MD_IMAGE_OWNER_META', 'iss_md_image_owner');
define('ISS_WF_IMPORT_OBJECT_MD_METADATA_RIGHTS_META', 'iss_md_metadata_rights_status');
define('ISS_WF_IMPORT_OBJECT_MD_METADATA_HOLDER_META', 'iss_md_metadata_rights_holder');
define('ISS_WF_IMPORT_OBJECT_MD_INSTITUTION_ID_META', 'iss_md_institution_id');
define('ISS_WF_IMPORT_OBJECT_MD_INSTITUTION_NAME_META', 'iss_md_institution_name');
define('ISS_WF_IMPORT_OBJECT_YEAR_META', 'iss_archive_year');
define('ISS_WF_IMPORT_OBJECT_DECADE_META', 'iss_archive_decade');

define('ISS_WF_IMPORT_HASH_META', '_iss_wf_source_hash');
define('ISS_WF_IMPORT_LAST_SYNCED_META', '_iss_wf_last_synced_gmt');
define('ISS_WF_IMPORT_ATTACHMENT_SOURCE_URL_META', '_iss_wf_source_media_url');
define('ISS_WF_IMPORT_ATTACHMENT_OWNER_OBJECT_META', 'iss_archive_owner_object_id');
define('ISS_WF_IMPORT_ATTACHMENT_ARCHIVE_OWNED_META', 'iss_archive_owned_asset');
define('ISS_WF_IMPORT_PLACE_SUGGESTIONS_META', 'iss_wf_place_suggestions');
define('ISS_WF_IMPORT_PLACE_SUGGESTED_AT_META', '_iss_wf_place_suggested_at_gmt');
define('ISS_WF_IMPORT_MD_PARSER_VERSION', 'museum-digital-importer-2026-05-12');

/**
 * Stable archive model modules.
 */
require_once ISS_WF_IMPORT_PATH . 'includes/post-type.php';
require_once ISS_WF_IMPORT_PATH . 'includes/meta.php';
require_once ISS_WF_IMPORT_PATH . 'includes/collection-service.php';
require_once ISS_WF_IMPORT_PATH . 'includes/object-service.php';
require_once ISS_WF_IMPORT_PATH . 'includes/media-service.php';
require_once ISS_WF_IMPORT_PATH . 'includes/relation-service.php';
require_once ISS_WF_IMPORT_PATH . 'includes/import-service.php';
require_once ISS_WF_IMPORT_PATH . 'includes/assertion-service.php';
require_once ISS_WF_IMPORT_PATH . 'includes/archive-browser-service.php';
require_once ISS_WF_IMPORT_PATH . 'includes/suggestions.php';
require_once ISS_WF_IMPORT_PATH . 'includes/blocks.php';
require_once ISS_WF_IMPORT_PATH . 'includes/admin.php';
require_once ISS_WF_IMPORT_PATH . 'includes/collection-editor.php';
require_once ISS_WF_IMPORT_PATH . 'includes/museum-digital-importer.php';

register_activation_hook(__FILE__, function () {
    iss_wf_import_register_post_type_and_taxonomies();
    iss_wf_import_ensure_source_term();
    iss_wf_import_get_collection_service()->install_schema();
    iss_wf_import_get_collection_service()->backfill_all_collections();
    update_option(ISS_WF_IMPORT_COLLECTION_BACKFILL_OPTION, ISS_WF_IMPORT_COLLECTION_BACKFILL_VERSION, false);
    iss_wf_import_get_object_service()->install_schema();
    iss_wf_import_get_object_service()->backfill_all_objects();
    update_option(ISS_WF_IMPORT_OBJECT_BACKFILL_OPTION, ISS_WF_IMPORT_OBJECT_BACKFILL_VERSION, false);
    iss_wf_import_get_media_service()->install_schema();
    iss_wf_import_get_media_service()->backfill_all_media();
    update_option(ISS_WF_IMPORT_MEDIA_BACKFILL_OPTION, ISS_WF_IMPORT_MEDIA_BACKFILL_VERSION, false);
    iss_wf_import_get_relation_service()->install_schema();
    iss_wf_import_get_relation_service()->backfill_all_relations();
    update_option(ISS_WF_IMPORT_RELATION_BACKFILL_OPTION, ISS_WF_IMPORT_RELATION_BACKFILL_VERSION, false);
    iss_wf_import_get_import_service()->install_schema();
    iss_wf_import_get_import_service()->backfill_all_source_records();
    update_option(ISS_WF_IMPORT_IMPORT_BACKFILL_OPTION, ISS_WF_IMPORT_IMPORT_BACKFILL_VERSION, false);
    iss_wf_import_get_assertion_service()->install_schema();
    iss_wf_import_get_assertion_service()->backfill_all_object_assertions();
    update_option(ISS_WF_IMPORT_ASSERTION_BACKFILL_OPTION, ISS_WF_IMPORT_ASSERTION_BACKFILL_VERSION, false);
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});

function iss_wf_import_maybe_flush_rewrite_rules(): void
{
    $stored = (string) get_option('iss_wf_import_rewrite_version', '');
    if ($stored === ISS_WF_IMPORT_REWRITE_VERSION) {
        return;
    }

    flush_rewrite_rules(false);
    update_option('iss_wf_import_rewrite_version', ISS_WF_IMPORT_REWRITE_VERSION, false);
}
add_action('init', 'iss_wf_import_maybe_flush_rewrite_rules', 99);

function iss_wf_import_is_archive_owned_attachment(int $attachment_id): bool
{
    if ($attachment_id <= 0 || get_post_type($attachment_id) !== 'attachment') {
        return false;
    }

    if (get_post_meta($attachment_id, ISS_WF_IMPORT_ATTACHMENT_ARCHIVE_OWNED_META, true) === 'yes') {
        return true;
    }

    $parent_id = (int) wp_get_post_parent_id($attachment_id);
    return ($parent_id > 0 && get_post_type($parent_id) === ISS_WF_IMPORT_OBJECT_POST_TYPE);
}

function iss_wf_import_mark_attachment_as_archive_owned(int $attachment_id, int $object_id = 0, string $source_url = ''): void
{
    if ($attachment_id <= 0 || get_post_type($attachment_id) !== 'attachment') {
        return;
    }

    if ($object_id <= 0) {
        $object_id = (int) wp_get_post_parent_id($attachment_id);
    }

    update_post_meta($attachment_id, ISS_WF_IMPORT_ATTACHMENT_ARCHIVE_OWNED_META, 'yes');

    if ($object_id > 0) {
        update_post_meta($attachment_id, ISS_WF_IMPORT_ATTACHMENT_OWNER_OBJECT_META, $object_id);

        $parent_id = (int) wp_get_post_parent_id($attachment_id);
        if ($parent_id !== $object_id) {
            wp_update_post([
                'ID' => $attachment_id,
                'post_parent' => $object_id,
            ]);
        }
    }

    if ($source_url !== '') {
        update_post_meta($attachment_id, ISS_WF_IMPORT_ATTACHMENT_SOURCE_URL_META, esc_url_raw($source_url));
    }
}

function iss_wf_import_backfill_archive_attachment_flags(): void
{
    $version = '2026-05-10-archive-attachment-protection-1';
    $stored = (string) get_option('iss_wf_import_archive_attachment_backfill_version', '');
    if ($stored === $version) {
        return;
    }

    global $wpdb;

    $attachment_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT a.ID
        FROM {$wpdb->posts} a
        INNER JOIN {$wpdb->posts} p ON p.ID = a.post_parent
        WHERE a.post_type = %s
          AND p.post_type = %s",
        'attachment',
        ISS_WF_IMPORT_OBJECT_POST_TYPE
    ));

    foreach ((array) $attachment_ids as $attachment_id) {
        iss_wf_import_mark_attachment_as_archive_owned((int) $attachment_id);
    }

    $object_ids = get_posts([
        'post_type' => ISS_WF_IMPORT_OBJECT_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [
            [
                'key' => ISS_WF_IMPORT_OBJECT_PRIMARY_ATTACHMENT_META,
                'compare' => 'EXISTS',
            ],
        ],
        'no_found_rows' => true,
    ]);

    foreach ($object_ids as $object_id) {
        $attachment_id = (int) get_post_meta((int) $object_id, ISS_WF_IMPORT_OBJECT_PRIMARY_ATTACHMENT_META, true);
        if ($attachment_id > 0) {
            iss_wf_import_mark_attachment_as_archive_owned($attachment_id, (int) $object_id);
        }
    }

    update_option('iss_wf_import_archive_attachment_backfill_version', $version, false);
}
add_action('init', 'iss_wf_import_backfill_archive_attachment_flags', 100);

function iss_wf_import_skip_archive_owned_webp_attachment_paths(array $paths, int $attachment_id): array
{
    if (iss_wf_import_is_archive_owned_attachment($attachment_id)) {
        return [];
    }

    return $paths;
}
add_filter('webpc_attachment_paths', 'iss_wf_import_skip_archive_owned_webp_attachment_paths', 10, 2);
