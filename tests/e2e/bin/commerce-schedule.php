<?php
/** Included in the isolated occurrence/commerce fixture; exercises actual projection replacement. */
if (!defined('WP_CLI') || !WP_CLI || !isset($db, $expect, $call, $payload, $occurrence_table, $base)) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Disposable fixture tables only.
$stage = 'schedule';
$data = $payload('booking', 'fixture-schedule-identity');
$expect($call($data)->get_status() === 200, 'Schedule fixture booking accepted');
$booking = $read()[0]; $departure_id = (int) $booking['departure_id'];
$projected = $db->get_row($db->prepare("SELECT * FROM `$occurrence_table` WHERE source_post_id = %d LIMIT 1", $base), ARRAY_A);
$old_occurrence_id = (int) $projected['id']; unset($projected['id']);
$expect($db->delete($occurrence_table, ['id' => $old_occurrence_id]) === 1 && $db->insert($occurrence_table, $projected) === 1, 'Replace source projection row');
$expect((int) $db->insert_id !== $old_occurrence_id, 'Projection received a new row ID');
ISS_Commerce_Ledger::sync_schedule();
$slots = iss_occurrences_get_booking_slots(['source_post_id' => $base, 'source_post_type' => 'veranstaltung']);
$expect((int) $slots[0]['departure_id'] === $departure_id && ISS_Commerce_Ledger::booking((int) $booking['id']) === $booking, 'Projection rebuild preserves booking and inventory identities');
$changed_start = gmdate('Y-m-d H:i:s', (int) strtotime($projected['starts_at'] . ' UTC') + DAY_IN_SECONDS);
$db->update($occurrence_table, ['starts_at' => $changed_start], ['source_post_id' => $base]);
ISS_Commerce_Ledger::sync_schedule();
$departure = ISS_Commerce_Ledger::departure($departure_id);
$expect((int) $departure['needs_review'] === 1 && !(int) $departure['booking_open'] && (int) $departure['schedule_revision'] === 2, 'Reschedule closes inventory for review');
$expect(ISS_Commerce_Ledger::allocated($departure_id) === 2 && ISS_Commerce_Ledger::booking((int) $booking['id']) === $booking, 'Reschedule does not release or rewrite existing reservation');
$slots = iss_occurrences_get_booking_slots(['source_post_id' => $base, 'source_post_type' => 'veranstaltung']);
$expect($slots[0]['availability_state'] === 'unavailable', 'Public availability closes after reschedule');
$db->update($occurrence_table, ['starts_at' => $projected['starts_at']], ['source_post_id' => $base]);
ISS_Commerce_Ledger::sync_schedule();
$set_capacity($base, 10);
$db->delete($occurrence_table, ['source_post_id' => $base]);
ISS_Commerce_Ledger::sync_schedule();
$expect(!(int) ISS_Commerce_Ledger::departure($departure_id)['booking_open'] && ISS_Commerce_Ledger::allocated($departure_id) === 2, 'Missing schedule closes departure while retaining seats');
$db->insert($occurrence_table, $projected);
ISS_Commerce_Ledger::sync_schedule(); $set_capacity($base, 10);
$reset();
