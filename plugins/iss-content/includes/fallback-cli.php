<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ISS_Content_Fallback_CLI_Command
{
    public function dry_run(array $args, array $assoc_args): void
    {
        unset($args);
        $report = iss_content_fallback_project([
            'dry_run' => true,
            'type' => sanitize_key((string) ($assoc_args['type'] ?? '')),
            'mark_stale' => true,
        ]);
        \WP_CLI::log((string) wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function project(array $args, array $assoc_args): void
    {
        unset($args);
        $type = sanitize_key((string) ($assoc_args['type'] ?? ''));
        $all = !empty($assoc_args['all']);
        if ($type === '' && !$all) {
            \WP_CLI::error('Use --type=<type> or --all.');
        }
        $report = iss_content_fallback_project([
            'dry_run' => false,
            'type' => $all ? '' : $type,
            'mark_stale' => true,
        ]);
        \WP_CLI::log((string) wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        \WP_CLI::success('Fallback projection complete.');
    }

    public function status(array $args, array $assoc_args): void
    {
        unset($args, $assoc_args);
        \WP_CLI::log((string) wp_json_encode(iss_content_fallback_status_report(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function enable(array $args, array $assoc_args): void
    {
        unset($args, $assoc_args);
        \WP_CLI::log((string) wp_json_encode(iss_content_fallback_enable(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        \WP_CLI::success('Fallback mode enabled.');
    }

    public function disable(array $args, array $assoc_args): void
    {
        unset($args, $assoc_args);
        \WP_CLI::log((string) wp_json_encode(iss_content_fallback_disable(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        \WP_CLI::success('Fallback mode disabled.');
    }
}

$iss_content_fallback_cli_command = new ISS_Content_Fallback_CLI_Command();
\WP_CLI::add_command('iss fallback', $iss_content_fallback_cli_command);
\WP_CLI::add_command('iss fallback dry-run', [$iss_content_fallback_cli_command, 'dry_run']);
