DELETE FROM `wp_postmeta`
WHERE `meta_key` IN (
  '_iss_event_layout',
  '_iss_event_format',
  '_iss_event_scheme'
);
