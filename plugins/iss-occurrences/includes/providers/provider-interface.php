<?php

if (!defined('ABSPATH')) {
    exit;
}

interface ISS_Occurrences_Source_Provider
{
    public function get_post_type(): string;

    public function get_kind(): string;

    public function is_calendar_enabled(WP_Post $post): bool;

    public function get_start_end_for_post(WP_Post $post): array;

    public function get_occurrences_for_post(WP_Post $post): array;
}
