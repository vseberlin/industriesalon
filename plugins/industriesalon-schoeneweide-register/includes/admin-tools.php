<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_render_coordinate_backfill_result(array $result): void
{
    $stats = $result['stats'];
    echo '<h2>Koordinaten-Geocoding</h2>';
    echo '<ul>';
    echo '<li>Ausgewählte Einträge: <strong>' . esc_html((string) $stats['selected']) . '</strong></li>';
    if (!empty($result['dry_run'])) {
        echo '<li>Would update: <strong>' . esc_html((string) $stats['would_update']) . '</strong></li>';
    } else {
        echo '<li>Updated: <strong>' . esc_html((string) $stats['updated']) . '</strong></li>';
    }
    echo '<li>No match: <strong>' . esc_html((string) $stats['no_match']) . '</strong></li>';
    echo '<li>Errors: <strong>' . esc_html((string) $stats['errors']) . '</strong></li>';
    echo '</ul>';

    if (!empty($result['messages'])) {
        echo '<h3>Hinweise</h3><ul>';
        foreach ($result['messages'] as $message) {
            echo '<li>' . esc_html($message) . '</li>';
        }
        echo '</ul>';
    }
}

function iss_register_render_tools_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }

    $geocode_result = null;
    $geocode_error = '';
    $epoch_migration_result = null;
    $epoch_migration_error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['iss_register_tools_action'])) {
        $action = sanitize_key((string) wp_unslash($_POST['iss_register_tools_action']));

        if ($action === 'geocode') {
            check_admin_referer('iss_register_run_geocode', 'iss_register_geocode_nonce');
            $dry_run = isset($_POST['geocode_dry_run']) ? (bool) rest_sanitize_boolean(wp_unslash($_POST['geocode_dry_run'])) : false;
            $force = isset($_POST['geocode_force']) ? (bool) rest_sanitize_boolean(wp_unslash($_POST['geocode_force'])) : false;
            $limit = max(1, min(200, (int) wp_unslash($_POST['geocode_limit'] ?? 10)));
            $geocode_result = iss_register_run_coordinate_backfill([
                'dry_run' => $dry_run,
                'force' => $force,
                'limit' => $limit,
            ]);

            if (($geocode_result['stats']['selected'] ?? 0) === 0) {
                $geocode_error = 'Keine passenden Register-Orte für diesen Koordinatenlauf gefunden.';
            }
        } elseif ($action === 'epoch_seed_migration') {
            check_admin_referer('iss_register_run_epoch_seed_migration', 'iss_register_epoch_seed_nonce');

            $confirmed_backup = !empty($_POST['confirm_backup']);
            $confirmed_export = !empty($_POST['confirm_export']);
            if (!$confirmed_backup || !$confirmed_export) {
                $epoch_migration_error = 'Vor der Epochensaat bitte Datenbank-Backup und Exportbestaetigung setzen.';
            } else {
                $epoch_migration_result = iss_register_get_epoch_service()->run_seed_migration();
            }
        }
    }

    $counts = wp_count_posts(ISS_REGISTER_POST_TYPE);
    $existing_count = is_object($counts) ? (int) ($counts->publish ?? 0) : 0;
    $coverage = iss_register_get_coordinate_coverage();

    echo '<div class="wrap">';
    echo '<h1>Schöneweide Register Werkzeuge</h1>';
    echo '<p>Die lokale WordPress-Datenbank ist jetzt die einzige Quelle für <code>' . esc_html(ISS_REGISTER_POST_TYPE) . '</code>. JSON-Import und statische Fallback-Daten sind entfernt. Dieses Werkzeug ergänzt nur fehlende Koordinaten für bereits gepflegte lokale Einträge.</p>';
    echo '<p>Aktuell veröffentlichte Register-Orte: <strong>' . esc_html((string) $existing_count) . '</strong></p>';
    echo '<h2>Koordinaten aus Adresse</h2>';
    echo '<p>Aktuell mit Adresse: <strong>' . esc_html((string) $coverage['with_address']) . '</strong>, mit Koordinaten: <strong>' . esc_html((string) $coverage['with_coordinates']) . '</strong>, offen: <strong>' . esc_html((string) $coverage['missing_coordinates']) . '</strong>.</p>';
    echo '<p>Geocoding läuft über Nominatim und schreibt die gefundenen Werte in <code>lat</code>, <code>lng</code> und <code>coordinates_accuracy</code>.</p>';
    echo '<form method="post">';
    wp_nonce_field('iss_register_run_geocode', 'iss_register_geocode_nonce');
    echo '<input type="hidden" name="iss_register_tools_action" value="geocode">';
    echo '<table class="form-table" role="presentation"><tbody>';
    echo '<tr><th scope="row"><label for="geocode_limit">Batch-Größe</label></th><td><input type="number" id="geocode_limit" name="geocode_limit" min="1" max="200" value="10" class="small-text"> <span class="description">Nominatim ist langsam und sollte in kleinen Batches laufen.</span></td></tr>';
    echo '<tr><th scope="row"><label for="geocode_dry_run">Dry run</label></th><td><label><input type="checkbox" id="geocode_dry_run" name="geocode_dry_run" value="1" checked> Nur prüfen, nichts speichern</label></td></tr>';
    echo '<tr><th scope="row"><label for="geocode_force">Bereits gesetzte Koordinaten erneut prüfen</label></th><td><label><input type="checkbox" id="geocode_force" name="geocode_force" value="1"> Auch vorhandene Koordinaten in den Batch aufnehmen</label></td></tr>';
    echo '</tbody></table>';
    submit_button('Koordinaten geocodieren', 'secondary');
    echo '</form>';

    if ($geocode_error !== '') {
        echo '<div class="notice notice-error"><p>' . esc_html($geocode_error) . '</p></div>';
    } elseif (is_array($geocode_result)) {
        iss_register_render_coordinate_backfill_result($geocode_result);
    }

    $epoch_counts = iss_register_get_epoch_service()->get_counts_by_era_and_function();
    $epoch_total = array_sum((array) ($epoch_counts['eras'] ?? []));
    $export_url = rest_url(ISS_REGISTER_REST_NAMESPACE . '/export');

    echo '<hr>';
    echo '<h2>Historische Zeitschichten</h2>';
    echo '<p>Die Epochen liegen in einer eigenen Tabelle hinter <code>register_place</code>. Bestehende URLs und Ortseintraege bleiben unveraendert.</p>';
    echo '<p>Gespeicherte Epochen: <strong>' . esc_html((string) $epoch_total) . '</strong></p>';
    echo '<p><a class="button" href="' . esc_url($export_url) . '">Vollständigen Register-Export herunterladen</a></p>';
    echo '<form method="post">';
    wp_nonce_field('iss_register_run_epoch_seed_migration', 'iss_register_epoch_seed_nonce');
    echo '<input type="hidden" name="iss_register_tools_action" value="epoch_seed_migration">';
    echo '<p><label><input type="checkbox" name="confirm_backup" value="1"> DB-Backup wurde vorab erstellt.</label></p>';
    echo '<p><label><input type="checkbox" name="confirm_export" value="1"> Exportdatei wurde vor der Migration erzeugt.</label></p>';
    submit_button('Historische Start-Epochen erzeugen', 'secondary', 'submit', false);
    echo '</form>';

    if ($epoch_migration_error !== '') {
        echo '<div class="notice notice-error"><p>' . esc_html($epoch_migration_error) . '</p></div>';
    } elseif (is_array($epoch_migration_result)) {
        $stats = (array) ($epoch_migration_result['stats'] ?? []);
        echo '<div class="notice notice-success"><p>';
        echo 'Ausgewählt: ' . esc_html((string) ($stats['selected'] ?? 0));
        echo ', erzeugt: ' . esc_html((string) ($stats['seeded'] ?? 0));
        echo ', übersprungen (vorhanden): ' . esc_html((string) ($stats['skipped_existing'] ?? 0));
        echo ', übersprungen (leer): ' . esc_html((string) ($stats['skipped_empty'] ?? 0));
        echo ', Fehler: ' . esc_html((string) ($stats['errors'] ?? 0));
        echo '</p></div>';
    }

    echo '</div>';
}

add_action('admin_menu', function () {
    add_management_page(
        'Schöneweide Register Werkzeuge',
        'Schöneweide Register Werkzeuge',
        'manage_options',
        ISS_REGISTER_TOOLS_PAGE_SLUG,
        'iss_register_render_tools_page'
    );
});
