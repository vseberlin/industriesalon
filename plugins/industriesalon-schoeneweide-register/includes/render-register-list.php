<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('iss_register_render_places_cards')) {
    function iss_register_render_places_cards(array $places, int $limit = 0): string
    {
        if (!$places) {
            return '<p class="iss-register-empty">Keine Standorte verfügbar.</p>';
        }

        $items = $limit > 0 ? array_slice($places, 0, $limit) : $places;
        $html = '<div class="iss-register-places-grid">';

        foreach ($items as $place) {
            if (!is_array($place)) {
                continue;
            }

            $id = iss_register_safe_text($place['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $status = iss_register_safe_text($place['status'] ?? '');
            $area = iss_register_safe_text($place['area'] ?? 'Gebiet offen');

            $html .= '<article class="iss-register-place-card">';
            $html .= sprintf(
                '<button type="button" class="iss-register-place-card__button" data-place-id="%s">',
                esc_attr($id)
            );
            $html .= iss_register_render_image($place, 'Bild gesucht', 'iss-register-place-card__media');
            $html .= '<div class="iss-register-place-card__body">';
            $html .= sprintf(
                '<h4 class="iss-register-place-card__title">%s</h4>',
                esc_html(iss_register_safe_text($place['name'] ?? 'Unbenannter Standort'))
            );
            $html .= '<p class="iss-register-place-card__meta">';
            $html .= sprintf('<span>%s</span>', esc_html($area));
            $html .= sprintf(
                '<span class="iss-register-badge iss-register-badge--%1$s">%2$s</span>',
                esc_attr(iss_register_status_class($status)),
                esc_html(iss_register_status_label($status))
            );
            $html .= '</p>';
            $html .= sprintf(
                '<p class="iss-register-place-card__text">%s</p>',
                esc_html(iss_register_place_summary($place, 135))
            );
            $html .= '<span class="iss-register-inline-link">Details ansehen</span>';
            $html .= '</div>';
            $html .= '</button>';
            $html .= '</article>';
        }

        $html .= '</div>';

        return $html;
    }
}

if (!function_exists('iss_register_render_discover_quick_list')) {
    function iss_register_render_discover_quick_list(array $places, int $limit = 8): string
    {
        if (!$places) {
            return '<li class="iss-register-empty">Keine Daten vorhanden.</li>';
        }

        $items = array_slice($places, 0, max(1, $limit));
        $html = '';

        foreach ($items as $place) {
            if (!is_array($place)) {
                continue;
            }

            $id = iss_register_safe_text($place['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $status = iss_register_safe_text($place['status'] ?? '');
            $html .= '<li>';
            $html .= sprintf(
                '<button type="button" class="iss-register-quick-link" data-place-id="%1$s"><strong>%2$s</strong><span>%3$s · %4$s</span></button>',
                esc_attr($id),
                esc_html(iss_register_safe_text($place['name'] ?? 'Unbenannter Standort')),
                esc_html(iss_register_safe_text($place['area'] ?? 'Gebiet offen')),
                esc_html(iss_register_status_label($status))
            );
            $html .= '</li>';
        }

        return $html;
    }
}

if (!function_exists('iss_register_render_then_now_cards')) {
    function iss_register_render_then_now_cards(array $places, int $limit = 10): string
    {
        if (!$places) {
            return '<p class="iss-register-empty">Keine Standorte für den Bildvergleich.</p>';
        }

        $items = array_slice($places, 0, max(1, $limit));
        $html = '<div class="iss-register-then-now-grid">';

        foreach ($items as $place) {
            if (!is_array($place)) {
                continue;
            }

            $id = iss_register_safe_text($place['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $html .= '<article class="iss-register-then-now-card">';
            $html .= sprintf('<button type="button" class="iss-register-then-now-card__button" data-place-id="%s">', esc_attr($id));
            $html .= '<div class="iss-register-then-now-card__media-grid">';
            $html .= iss_register_render_image($place, 'Archivbild gesucht', 'iss-register-then-now-card__media', ['archive_images', 'document_images']);
            $html .= iss_register_render_image($place, 'Aktuelles Bild gesucht', 'iss-register-then-now-card__media', ['current_images', 'document_images']);
            $html .= '</div>';
            $html .= '<div class="iss-register-then-now-card__body">';
            $html .= sprintf(
                '<h4 class="iss-register-then-now-card__title">%s</h4>',
                esc_html(iss_register_safe_text($place['name'] ?? 'Unbenannter Standort'))
            );
            $html .= sprintf(
                '<p class="iss-register-then-now-card__text">%s</p>',
                esc_html(iss_register_place_summary($place, 120))
            );
            $html .= '<span class="iss-register-inline-link">Mehr erfahren</span>';
            $html .= '</div>';
            $html .= '</button>';
            $html .= '</article>';
        }

        $html .= '</div>';

        return $html;
    }
}
