-- Remove occurrence rows from the retired legacy calendar origin.
-- Current public occurrence producers are wp and supersaas.

START TRANSACTION;

DELETE FROM wp_iss_occurrences
WHERE origin = 'legacy';

COMMIT;
