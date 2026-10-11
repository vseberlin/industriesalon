<?php
/** Coalesce ordinary save callbacks through the existing graph retry queue. */
if (!defined('ABSPATH')) { exit; }

/** True means the durable queue accepted responsibility (or this is its writer). */
function iss_graph_defer_post_projection(int $post_id, string $reason, string $owner): bool
{
    global $wpdb;
    if (($GLOBALS['iss_graph_reconciling_post'] ?? 0) === $post_id) { return true; }
    if (!iss_graph_mark_post_dirty($post_id, $reason, $owner)) { return false; }
    // Remember request-local work only; durability, leases and retries belong to
    // the existing queue. Include the DB prefix so fixture/site switches isolate it.
    $GLOBALS['iss_graph_request_projection_ids'][$wpdb->prefix][$post_id] = $post_id;
    return true;
}

function iss_graph_flush_request_projections(): void
{
    global $wpdb;
    $ids = $GLOBALS['iss_graph_request_projection_ids'][$wpdb->prefix] ?? [];
    unset($GLOBALS['iss_graph_request_projection_ids'][$wpdb->prefix]);
    if (!$ids || !iss_graph_can_reconcile_current_request()) { return; }
    try {
        // Only this request's IDs; never consume unrelated historical work here.
        $result = iss_graph_drain_dirty_queue(['post_ids' => array_values($ids), 'batch_size' => 50, 'max_runtime' => 5]);
        if (!empty($result['failed']) || !empty($result['dead']) || !empty($result['last_error'])) {
            error_log('ISS projection update incomplete; inspect wp iss-graph autonomy-health and reconcile.');
        }
    } catch (Throwable $error) {
        // Canonical saves already succeeded. Leave durable work for maintenance.
        error_log('ISS projection update deferred: ' . $error->getMessage());
    }
}
// REST metadata and classic metabox callbacks finish before this boundary.
add_action('shutdown', 'iss_graph_flush_request_projections', 5);
