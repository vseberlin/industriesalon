<?php
/** Read-only verification for the first-party dbDelta declarations; schema SQL remains in each owner. */
if (!defined('ABSPATH')) {
    exit;
}

/** Reject incomplete tables/indexes before an owner records its schema version. */
function iss_core_verify_schema(array $statements): void
{
    global $wpdb;
    if (!$statements) {
        throw new RuntimeException('Schema verification requires owner declarations.');
    }

    foreach ($statements as $statement) {
        if (!preg_match('/^CREATE TABLE\s+`?([a-zA-Z0-9_]+)`?\s*\((.*)\)\s*[^;]*;?\s*$/sD', trim($statement), $definition)) {
            throw new RuntimeException('Unsupported first-party schema declaration.');
        }
        $table = $definition[1];
        $columns = [];
        $indexes = [];
        foreach (explode("\n", trim($definition[2])) as $line) {
            $line = trim($line, " \t\r\n,");
            if (preg_match('/^(PRIMARY KEY|UNIQUE KEY|KEY)\s*(?:`?([a-zA-Z0-9_]+)`?)?\s*\((.+)\)$/iD', $line, $index)) {
                $kind = strtoupper($index[1]);
                $name = $kind === 'PRIMARY KEY' ? 'PRIMARY' : $index[2];
                $parts = [];
                foreach (explode(',', $index[3]) as $part) {
                    if (!preg_match('/^`?([a-zA-Z0-9_]+)`?(?:\((\d+)\))?$/D', trim($part), $column)) {
                        throw new RuntimeException('Unsupported first-party index declaration.');
                    }
                    $parts[] = [$column[1], isset($column[2]) ? (int) $column[2] : null];
                }
                $indexes[$name] = ['non_unique' => $kind === 'KEY' ? 1 : 0, 'columns' => $parts];
            } elseif (preg_match('/^`?([a-zA-Z0-9_]+)`?\s+([a-z]+(?:\([^)]*\))?(?: unsigned)?)(?:\s|$)/i', $line, $column)) {
                $columns[$column[1]] = [
                    'type' => strtolower((string) preg_replace('/\b(tinyint|smallint|mediumint|int|bigint)\(\d+\)/i', '$1', $column[2])),
                    'null' => stripos($line, 'NOT NULL') === false ? 'YES' : 'NO',
                    'auto_increment' => stripos($line, 'AUTO_INCREMENT') !== false,
                ];
            } else {
                throw new RuntimeException('Unsupported first-party column declaration.');
            }
        }
        if (!$columns || !$indexes) {
            throw new RuntimeException('First-party schema declarations require columns and indexes.');
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Installation/diagnostics inspect owner tables, never public rendering.
        $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table));
        if ($wpdb->last_error !== '' || $engine !== 'InnoDB') {
            throw new RuntimeException(esc_html('Schema verification requires an InnoDB table: ' . $table));
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read-only schema verification using a prepared identifier.
        $stored_columns = $wpdb->get_results($wpdb->prepare('SHOW COLUMNS FROM %i', $table), ARRAY_A);
        if ($wpdb->last_error !== '' || !$stored_columns) {
            throw new RuntimeException(esc_html('Schema columns could not be read: ' . $table));
        }
        $actual_columns = [];
        foreach ($stored_columns as $column) {
            $actual_columns[$column['Field']] = [
                'type' => strtolower((string) preg_replace('/\b(tinyint|smallint|mediumint|int|bigint)\(\d+\)/i', '$1', $column['Type'])),
                'null' => $column['Null'],
                'auto_increment' => str_contains($column['Extra'], 'auto_increment'),
            ];
        }
        foreach ($columns as $name => $expected) {
            if (($actual_columns[$name] ?? null) !== $expected) {
                throw new RuntimeException(esc_html('Schema column does not match its owner: ' . $table . '.' . $name));
            }
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read-only schema verification using a prepared identifier.
        $stored_indexes = $wpdb->get_results($wpdb->prepare('SHOW INDEX FROM %i', $table), ARRAY_A);
        if ($wpdb->last_error !== '' || !$stored_indexes) {
            throw new RuntimeException(esc_html('Schema indexes could not be read: ' . $table));
        }
        $actual_indexes = [];
        foreach ($stored_indexes as $index) {
            if ($index['Index_type'] !== 'BTREE') {
                continue;
            }
            $name = $index['Key_name'];
            $actual_indexes[$name]['non_unique'] = (int) $index['Non_unique'];
            $actual_indexes[$name]['columns'][(int) $index['Seq_in_index']] = [$index['Column_name'], $index['Sub_part'] !== null ? (int) $index['Sub_part'] : null];
        }
        foreach ($indexes as $name => $expected) {
            $actual = $actual_indexes[$name] ?? [];
            if (isset($actual['columns'])) {
                ksort($actual['columns']);
                $actual['columns'] = array_values($actual['columns']);
            }
            if ($actual !== $expected) {
                throw new RuntimeException(esc_html('Schema index does not match its owner: ' . $table . '.' . $name));
            }
        }
    }
}

/** Automatic upgrades are privileged admin work; failures remain visible without blocking diagnostics. */
function iss_core_admin_schema_upgrade(callable $upgrade): void
{
    if (!current_user_can('manage_options') || wp_doing_ajax()) {
        return;
    }
    try {
        $upgrade();
    } catch (Throwable $error) {
        add_action('admin_notices', static function () use ($error): void {
            printf('<div class="notice notice-error"><p>%s</p></div>', esc_html(__('Schema upgrade failed: ', 'iss-core') . $error->getMessage()));
        });
    }
}
