<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PMS_REFUND_META_BACKFILL_HOOK', 'pms_refund_meta_backfill_batch' );
define( 'PMS_REFUND_META_BACKFILL_GROUP', 'pms_refund_meta_backfill' );
define( 'PMS_REFUND_META_BACKFILL_BATCH_SIZE', 100 );

define( 'PMS_PAYMENT_CURRENCY_BACKFILL_HOOK', 'pms_payment_currency_backfill_batch' );
define( 'PMS_PAYMENT_CURRENCY_BACKFILL_GROUP', 'pms_payment_currency_backfill' );
define( 'PMS_PAYMENT_CURRENCY_BACKFILL_BATCH_SIZE', 100 );

define( 'PMS_PAYMENT_CURRENCY_CORRECTION_HOOK', 'pms_payment_currency_correction_batch' );
define( 'PMS_PAYMENT_CURRENCY_CORRECTION_GROUP', 'pms_payment_currency_correction' );
define( 'PMS_PAYMENT_CURRENCY_CORRECTION_BATCH_SIZE', 100 );

define( 'PMS_PAYMENT_BACKFILLS_PENDING_SCHEDULE_OPTION', 'pms_payment_backfills_pending_schedule' );

add_action( 'action_scheduler_init', 'pms_run_pending_payment_backfill_schedule', 20 );
add_action( 'init', 'pms_run_pending_payment_backfill_schedule', 5 );
add_action( 'pms_update_check', 'pms_maybe_schedule_payment_backfills' );

/**
 * Whether Action Scheduler is ready to accept scheduled actions.
 */
function pms_payment_backfills_action_scheduler_ready() {

    return function_exists( 'as_schedule_single_action' )
        && class_exists( 'ActionScheduler' )
        && ActionScheduler::is_initialized();

}

/**
 * @param int    $timestamp
 * @param string $hook
 * @param string $group
 * @return int Action ID, or 0 on failure.
 */
function pms_schedule_backfill_batch_action( $timestamp, $hook, $group ) {

    if ( ! pms_payment_backfills_action_scheduler_ready() ) {
        return 0;
    }

    return (int) as_schedule_single_action( $timestamp, $hook, array(), $group );

}

/**
 * @param string $complete_option
 * @param string $hook
 * @param string $group
 */
function pms_payment_backfill_is_scheduled_or_complete( $complete_option, $hook, $group ) {

    if ( get_option( $complete_option ) ) {
        return true;
    }

    return function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( $hook, null, $group );

}

/**
 * Whether every enabled backfill job is done or has a queued Action Scheduler batch.
 */
function pms_payment_backfills_initial_schedule_complete() {

    return pms_payment_backfill_is_scheduled_or_complete( 'pms_refund_meta_backfill_complete', PMS_REFUND_META_BACKFILL_HOOK, PMS_REFUND_META_BACKFILL_GROUP )
        && pms_payment_backfill_is_scheduled_or_complete( 'pms_payment_currency_backfill_complete', PMS_PAYMENT_CURRENCY_BACKFILL_HOOK, PMS_PAYMENT_CURRENCY_BACKFILL_GROUP )
        && ( ! pms_payment_currency_correction_enabled() || pms_payment_backfill_is_scheduled_or_complete( 'pms_payment_currency_correction_complete', PMS_PAYMENT_CURRENCY_CORRECTION_HOOK, PMS_PAYMENT_CURRENCY_CORRECTION_GROUP ) );

}

/**
 * Mark backfills for scheduling on the next Action Scheduler init.
 *
 * pms_update_check runs on plugins_loaded, before the AS store inits on init.
 */
function pms_maybe_schedule_payment_backfills() {

    update_option( PMS_PAYMENT_BACKFILLS_PENDING_SCHEDULE_OPTION, 1, false );

}

/**
 * Schedule pending backfills once Action Scheduler is ready.
 */
function pms_run_pending_payment_backfill_schedule() {

    if ( ! get_option( PMS_PAYMENT_BACKFILLS_PENDING_SCHEDULE_OPTION ) ) {
        return;
    }

    if ( ! pms_payment_backfills_action_scheduler_ready() ) {
        return;
    }

    pms_maybe_schedule_payment_backfills_now();

    if ( pms_payment_backfills_initial_schedule_complete() ) {
        delete_option( PMS_PAYMENT_BACKFILLS_PENDING_SCHEDULE_OPTION );
    }

}

/**
 * Schedule refund-meta backfill, currency backfill and currency correction batches when prerequisites are met.
 */
function pms_maybe_schedule_payment_backfills_now() {

    pms_maybe_schedule_backfill_batch( 'pms_refund_meta_backfill_complete', PMS_REFUND_META_BACKFILL_HOOK, PMS_REFUND_META_BACKFILL_GROUP );
    pms_maybe_schedule_backfill_batch( 'pms_payment_currency_backfill_complete', PMS_PAYMENT_CURRENCY_BACKFILL_HOOK, PMS_PAYMENT_CURRENCY_BACKFILL_GROUP );

    if ( pms_payment_currency_correction_enabled() )
        pms_maybe_schedule_backfill_batch( 'pms_payment_currency_correction_complete', PMS_PAYMENT_CURRENCY_CORRECTION_HOOK, PMS_PAYMENT_CURRENCY_CORRECTION_GROUP );

}

/**
 * @param string $complete_option
 * @param string $hook
 * @param string $group
 * @return bool True when complete, already scheduled, or newly scheduled.
 */
function pms_maybe_schedule_backfill_batch( $complete_option, $hook, $group ) {

    if ( get_option( $complete_option ) ) {
        return true;
    }

    if ( ! pms_payment_backfills_action_scheduler_ready() ) {
        return false;
    }

    if ( as_has_scheduled_action( $hook, null, $group ) ) {
        return true;
    }

    return ! empty( pms_schedule_backfill_batch_action( time(), $hook, $group ) );

}

/**
 * @param string   $complete_option
 * @param string   $hook
 * @param string   $group
 * @param string   $batch_size_filter
 * @param int      $default_batch_size
 * @param callable $get_payment_ids            Receives ( $batch_size, $after_id ).
 * @param callable $process_payment_id
 * @param string   $cursor_option              Stores the highest payment ID scanned.
 */
function pms_run_backfill_batch( $complete_option, $hook, $group, $batch_size_filter, $default_batch_size, $get_payment_ids, $process_payment_id, $cursor_option = '' ) {

    if ( get_option( $complete_option ) )
        return;

    if ( empty( $cursor_option ) )
        $cursor_option = $complete_option . '_cursor';

    $after_id    = (int) get_option( $cursor_option, 0 );
    $batch_size  = (int) apply_filters( $batch_size_filter, $default_batch_size );
    $payment_ids = call_user_func( $get_payment_ids, $batch_size, $after_id );

    if ( empty( $payment_ids ) ) {
        update_option( $complete_option, 1 );
        return;
    }

    foreach ( $payment_ids as $payment_id ) {
        call_user_func( $process_payment_id, (int) $payment_id );
    }

    update_option( $cursor_option, (int) max( $payment_ids ) );

    if ( count( $payment_ids ) < $batch_size ) {
        update_option( $complete_option, 1 );
        return;
    }

    $action_id = pms_schedule_backfill_batch_action( time() + 1, $hook, $group );

    if ( empty( $action_id ) ) {
        update_option( PMS_PAYMENT_BACKFILLS_PENDING_SCHEDULE_OPTION, 1, false );
    }

}

add_action( PMS_REFUND_META_BACKFILL_HOOK, 'pms_run_refund_meta_backfill_batch' );

function pms_run_refund_meta_backfill_batch() {

    pms_run_backfill_batch(
        'pms_refund_meta_backfill_complete',
        PMS_REFUND_META_BACKFILL_HOOK,
        PMS_REFUND_META_BACKFILL_GROUP,
        'pms_refund_meta_backfill_batch_size',
        PMS_REFUND_META_BACKFILL_BATCH_SIZE,
        'pms_get_refund_meta_backfill_payment_ids',
        'pms_backfill_payment_refund_meta_from_logs'
    );

}

/**
 * @param int $batch_size
 * @param int $after_id
 * @return int[]
 */
function pms_get_refund_meta_backfill_payment_ids( $batch_size, $after_id = 0 ) {

    global $wpdb;

    return $wpdb->get_col(
        $wpdb->prepare(
            "SELECT p.id FROM {$wpdb->prefix}pms_payments p
            LEFT JOIN {$wpdb->prefix}pms_paymentmeta pm ON p.id = pm.payment_id AND pm.meta_key = 'refund_date'
            WHERE p.status = 'refunded' AND pm.payment_id IS NULL AND p.id > %d
            ORDER BY p.id ASC
            LIMIT %d",
            $after_id,
            $batch_size
        )
    );

}

/**
 * @param int $payment_id
 * @return bool
 */
function pms_backfill_payment_refund_meta_from_logs( $payment_id ) {

    if ( pms_get_payment_refund_date( $payment_id ) )
        return false;

    $payment = pms_get_payment( $payment_id );

    if ( empty( $payment->id ) || $payment->status !== 'refunded' )
        return false;

    $parsed = pms_parse_refund_data_from_payment_logs( $payment );

    if ( empty( $parsed ) )
        return false;

    return pms_record_payment_refund_meta( $payment_id, $parsed['amount'], $parsed['source'], $parsed['date'] );

}

add_action( PMS_PAYMENT_CURRENCY_BACKFILL_HOOK, 'pms_run_payment_currency_backfill_batch' );

function pms_run_payment_currency_backfill_batch() {

    pms_run_backfill_batch(
        'pms_payment_currency_backfill_complete',
        PMS_PAYMENT_CURRENCY_BACKFILL_HOOK,
        PMS_PAYMENT_CURRENCY_BACKFILL_GROUP,
        'pms_payment_currency_backfill_batch_size',
        PMS_PAYMENT_CURRENCY_BACKFILL_BATCH_SIZE,
        'pms_get_payment_currency_backfill_payment_ids',
        'pms_backfill_payment_currency'
    );

}

/**
 * @param int $batch_size
 * @param int $after_id
 * @return int[]
 */
function pms_get_payment_currency_backfill_payment_ids( $batch_size, $after_id = 0 ) {

    global $wpdb;

    return $wpdb->get_col(
        $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}pms_payments
            WHERE (currency IS NULL OR currency = '') AND id > %d
            ORDER BY id ASC
            LIMIT %d",
            $after_id,
            $batch_size
        )
    );

}

/**
 * @param int $payment_id
 * @return bool
 */
function pms_backfill_payment_currency( $payment_id ) {

    $payment = pms_get_payment( $payment_id );

    if ( ! $payment || empty( $payment->id ) || ! empty( $payment->currency ) )
        return false;

    return $payment->update( array( 'currency' => pms_get_payment_currency( $payment ) ) );

}

/**
 * Whether the payment currency correction runs
 *
 * @return bool
 *
 */
function pms_payment_currency_correction_enabled() {

    /**
     * Filters whether PMS sets the `currency` column of payments to the currency in their meta `currency`, where the column
     * holds the site currency instead. Return false to never schedule or run the correction.
     *
     * @param bool $enabled Default true.
     *
     */
    return (bool) apply_filters( 'pms_payment_currency_correction_enabled', true );

}

add_action( PMS_PAYMENT_CURRENCY_CORRECTION_HOOK, 'pms_run_payment_currency_correction_batch' );

/**
 * Runs one batch of the payment currency correction
 *
 * The batch size is filtered by `pms_payment_currency_correction_batch_size`.
 *
 * @return void
 *
 */
function pms_run_payment_currency_correction_batch() {

    if ( ! pms_payment_currency_correction_enabled() )
        return;

    pms_run_backfill_batch(
        'pms_payment_currency_correction_complete',
        PMS_PAYMENT_CURRENCY_CORRECTION_HOOK,
        PMS_PAYMENT_CURRENCY_CORRECTION_GROUP,
        'pms_payment_currency_correction_batch_size',
        PMS_PAYMENT_CURRENCY_CORRECTION_BATCH_SIZE,
        'pms_get_payment_currency_correction_payment_ids',
        'pms_correct_payment_currency'
    );

}

/**
 * Returns the IDs of payments whose `currency` column holds the site currency while their meta `currency` holds another one
 *
 * @param int $batch_size
 * @param int $after_id
 *
 * @return int[]
 *
 */
function pms_get_payment_currency_correction_payment_ids( $batch_size, $after_id = 0 ) {

    global $wpdb;

    $site_currency = pms_normalize_currency_code( pms_get_active_currency() );

    return $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT p.id FROM {$wpdb->prefix}pms_payments p
            INNER JOIN {$wpdb->prefix}pms_paymentmeta pm ON p.id = pm.payment_id AND pm.meta_key = 'currency'
            WHERE p.currency = %s AND pm.meta_value <> '' AND UPPER(TRIM(pm.meta_value)) <> %s AND p.id > %d
            ORDER BY p.id ASC
            LIMIT %d",
            $site_currency,
            $site_currency,
            $after_id,
            $batch_size
        )
    );

}

/**
 * Sets the `currency` column of a payment to the currency in its meta `currency` when the column holds the site currency instead
 *
 * Writes only the meta currency, and only when pms_get_payment_currency() returns it too, so that a gateway that cannot
 * charge the meta currency or a `pms_get_payment_currency` filter keeps the column as it is. Skips payments that Multiple
 * Currencies converted to the site currency, because their column is the charged currency.
 *
 * @param int $payment_id
 *
 * @return bool Whether the payment was corrected.
 *
 */
function pms_correct_payment_currency( $payment_id ) {

    $payment = pms_get_payment( $payment_id );

    if ( empty( $payment->id ) )
        return false;

    $from = pms_normalize_currency_code( $payment->currency );

    if ( $from !== pms_normalize_currency_code( pms_get_active_currency() ) )
        return false;

    $to = pms_get_payment_currency( $payment );

    if ( $to === $from || $to !== pms_normalize_currency_code( pms_get_payment_meta( $payment->id, 'currency', true ) ) )
        return false;

    if ( is_array( $payment->logs ) && in_array( 'pms_mc_currency_converted', array_column( $payment->logs, 'type' ), true ) )
        return false;

    if ( ! $payment->update( array( 'currency' => $to ) ) )
        return false;

    $payment->log_data( 'payment_currency_corrected', array( 'from' => $from, 'to' => $to, 'source' => 'meta' ) );

    return true;

}
