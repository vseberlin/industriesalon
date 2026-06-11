-- Remove inactive programme/calendar metadata after the occurrence projection migration.
-- Rollback source: ops/content-backups/2026-06-11-before-programme-legacy-meta-purge-wp_postmeta.sql

DELETE FROM wp_postmeta
WHERE meta_key IN (
    'iss_timeline_item_id',
    '_iss_legacy_archive_term_slug',
    'iss_archive_term_slug',
    'iss_exhibition_source',
    'iss_exhibition_type'
);

DELETE FROM wp_options
WHERE option_name IN (
    'iss_calendar_source_map',
    'iss_calendar_series_map',
    'iss_calendar_cron_sync'
);
