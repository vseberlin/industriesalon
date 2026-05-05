<?php
/**
 * Plugin Name: ISS WF Import
 * Description: Mirrors WF-Museum archive posts into a local CPT with provenance, local media, and taxonomies for reuse.
 * Version: 0.3.0
 * Author: Industriesalon
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ISS_WF_IMPORT_VERSION', '0.3.0');
define('ISS_WF_IMPORT_PATH', plugin_dir_path(__FILE__));
define('ISS_WF_IMPORT_URL', plugin_dir_url(__FILE__));
define('ISS_WF_IMPORT_REWRITE_VERSION', '2026-05-05-archive-routing-2');

define('ISS_WF_IMPORT_POST_TYPE', 'archivbeitrag');
define('ISS_WF_IMPORT_COLLECTION_POST_TYPE', 'archivsammlung');
define('ISS_WF_IMPORT_OBJECT_POST_TYPE', 'archivobjekt');
define('ISS_WF_IMPORT_SOURCE_TAXONOMY', 'archiv_quelle');
define('ISS_WF_IMPORT_CATEGORY_TAXONOMY', 'archiv_kategorie');
define('ISS_WF_IMPORT_TAG_TAXONOMY', 'archiv_schlagwort');

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

define('ISS_WF_IMPORT_HASH_META', '_iss_wf_source_hash');
define('ISS_WF_IMPORT_LAST_SYNCED_META', '_iss_wf_last_synced_gmt');
define('ISS_WF_IMPORT_ATTACHMENT_SOURCE_URL_META', '_iss_wf_source_media_url');
define('ISS_WF_IMPORT_PLACE_SUGGESTIONS_META', 'iss_wf_place_suggestions');
define('ISS_WF_IMPORT_PLACE_SUGGESTED_AT_META', '_iss_wf_place_suggested_at_gmt');

require_once ISS_WF_IMPORT_PATH . 'includes/post-type.php';
require_once ISS_WF_IMPORT_PATH . 'includes/meta.php';
require_once ISS_WF_IMPORT_PATH . 'includes/suggestions.php';
require_once ISS_WF_IMPORT_PATH . 'includes/importer.php';
require_once ISS_WF_IMPORT_PATH . 'includes/md-importer.php';
require_once ISS_WF_IMPORT_PATH . 'includes/wf-collections.php';
require_once ISS_WF_IMPORT_PATH . 'includes/blocks.php';
require_once ISS_WF_IMPORT_PATH . 'includes/admin.php';
require_once ISS_WF_IMPORT_PATH . 'includes/cli.php';

register_activation_hook(__FILE__, function () {
    iss_wf_import_register_post_type_and_taxonomies();
    iss_wf_import_ensure_source_term();
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
