<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_render_touchtable_result(array $result): void
{
    if (($result['error'] ?? '') !== '') {
        echo '<div class="notice notice-error"><p>' . esc_html((string) $result['error']) . '</p></div>';
        return;
    }

    echo '<h2>Pull Ergebnis</h2>';

    foreach (['pages' => 'Seiten', 'media' => 'Medien'] as $group => $label) {
        $stats = $result['stats'][$group] ?? iss_register_source_snapshot_stats_seed();
        echo '<h3>' . esc_html($label) . '</h3>';
        echo '<ul>';
        echo '<li>Total: <strong>' . esc_html((string) $stats['total']) . '</strong></li>';
        echo '<li>Created: <strong>' . esc_html((string) $stats['created']) . '</strong></li>';
        echo '<li>Updated: <strong>' . esc_html((string) $stats['updated']) . '</strong></li>';
        echo '<li>Skipped unchanged: <strong>' . esc_html((string) $stats['skipped_unchanged']) . '</strong></li>';
        echo '<li>Matched: <strong>' . esc_html((string) $stats['matched']) . '</strong></li>';
        echo '<li>Unmatched: <strong>' . esc_html((string) $stats['unmatched']) . '</strong></li>';
        echo '<li>Errors: <strong>' . esc_html((string) $stats['errors']) . '</strong></li>';
        echo '</ul>';
    }
}

function iss_register_render_touchtable_snapshot_table(): void
{
    $items = iss_register_get_recent_source_snapshots(12);
    if (!$items) {
        echo '<p>Noch keine lokalen Touchtable-Quellstaende gespeichert.</p>';
        return;
    }

    echo '<h2>Letzte Quellstaende</h2>';
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>Titel</th><th>Typ</th><th>Art</th><th>Kandidat</th><th>Verknuepft</th><th>Status</th><th>Quelle</th><th>Gespeichert</th>';
    echo '</tr></thead><tbody>';

    foreach ($items as $item) {
        $candidate = (int) ($item['candidate_register_place_id'] ?? 0);
        $candidate_label = $candidate > 0 ? get_the_title($candidate) : 'Kein Kandidat';
        $match = (int) ($item['matched_register_place_id'] ?? 0);
        $match_label = $match > 0 ? get_the_title($match) : 'Nicht verknuepft';
        $source_url = (string) ($item['source_url'] ?? '');
        $status = iss_register_source_review_statuses()[(string) ($item['review_status'] ?? 'new')] ?? 'Neu';

        echo '<tr>';
        echo '<td>' . esc_html((string) ($item['title'] ?? '')) . '</td>';
        echo '<td>' . esc_html((string) ($item['item_type'] ?? '')) . '</td>';
        echo '<td>' . esc_html((string) ($item['source_kind'] ?? '')) . '</td>';
        echo '<td>' . esc_html($candidate_label) . '</td>';
        echo '<td>' . esc_html($match_label) . '</td>';
        echo '<td>' . esc_html($status) . '</td>';
        echo '<td>';
        if ($source_url !== '') {
            echo '<a href="' . esc_url($source_url) . '" target="_blank" rel="noreferrer">Öffnen</a>';
        } else {
            echo '&mdash;';
        }
        echo '</td>';
        echo '<td>' . esc_html((string) ($item['modified'] ?? '')) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

function iss_register_render_touchtable_import_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }

    $result = null;

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_POST['iss_register_touchtable_action'])
        && wp_unslash($_POST['iss_register_touchtable_action']) === 'pull'
    ) {
        check_admin_referer('iss_register_touchtable_pull', 'iss_register_touchtable_nonce');
        $dry_run = isset($_POST['dry_run']) ? (bool) rest_sanitize_boolean(wp_unslash($_POST['dry_run'])) : false;
        $result = iss_register_touchtable_pull_snapshots(['dry_run' => $dry_run]);
    }

    $page_count = iss_register_count_source_snapshots_by_type('page');
    $media_count = iss_register_count_source_snapshots_by_type('media');

    echo '<div class="wrap">';
    echo '<h1>Touchtable Pull</h1>';
    echo '<p>Zieht den oeffentlich erreichbaren Inhalt von <code>touchtable.industriesalon.de</code> in lokale Quellstaende. Dieser Schritt speichert Roh- und Extraktdaten, publiziert aber nichts automatisch in <code>register_place</code>.</p>';
    echo '<p><a class="button button-secondary" href="' . esc_url(iss_register_touchtable_review_page_url()) . '">Zur Touchtable Review</a></p>';
    echo '<p>Gespeicherte Quellstaende: <strong>' . esc_html((string) $page_count) . '</strong> Seiten, <strong>' . esc_html((string) $media_count) . '</strong> Medien.</p>';
    echo '<form method="post">';
    wp_nonce_field('iss_register_touchtable_pull', 'iss_register_touchtable_nonce');
    echo '<input type="hidden" name="iss_register_touchtable_action" value="pull">';
    echo '<table class="form-table" role="presentation"><tbody>';
    echo '<tr><th scope="row"><label for="dry_run">Dry run</label></th><td><label><input type="checkbox" id="dry_run" name="dry_run" value="1"> Nur pruefen, nichts speichern</label></td></tr>';
    echo '</tbody></table>';
    submit_button('Touchtable jetzt ziehen');
    echo '</form>';

    if (is_array($result)) {
        iss_register_render_touchtable_result($result);
    }

    iss_register_render_touchtable_snapshot_table();
    echo '</div>';
}

add_action('admin_menu', function () {
    add_management_page(
        'Touchtable Pull',
        'Touchtable Pull',
        'manage_options',
        ISS_REGISTER_TOUCHTABLE_PAGE_SLUG,
        'iss_register_render_touchtable_import_page'
    );
});
