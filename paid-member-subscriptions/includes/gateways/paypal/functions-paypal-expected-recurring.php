<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Stores the amount and currency PayPal bills a recurring profile with, on the profile's initial payment
 *
 * @param PMS_Payment $payment  The initial payment of the PayPal profile.
 * @param mixed       $amount   The amount PayPal bills each cycle.
 * @param string      $currency The currency PayPal bills in.
 *
 * @return bool Whether the terms were stored.
 *
 */
function pms_paypal_save_profile_terms( $payment, $amount, $currency ) {

    $currency = pms_normalize_currency_code( $currency );

    if ( empty( $payment->id ) || (float) $amount <= 0 || $currency === '' )
        return false;

    pms_update_payment_meta( $payment->id, 'paypal_profile_amount_per_cycle', pms_paypal_normalize_amount( $amount ) );
    pms_update_payment_meta( $payment->id, 'paypal_profile_currency', $currency );

    return true;

}

/**
 * Returns the amount and currency PayPal bills a recurring profile with
 *
 * When the terms are not stored, reads them from the PayPal Express `recurring_payment_profile_created` IPN logged on
 * the payment and stores them.
 *
 * @param PMS_Payment $payment The initial payment of the PayPal profile.
 *
 * @return array|null array( 'amount' => 2-dp string, 'currency' => uppercase code ), or null when they are not known.
 *
 */
function pms_paypal_get_profile_terms( $payment ) {

    $amount   = pms_get_payment_meta( $payment->id, 'paypal_profile_amount_per_cycle', true );
    $currency = pms_get_payment_meta( $payment->id, 'paypal_profile_currency', true );

    if ( ! empty( $amount ) && ! empty( $currency ) )
        return array( 'amount' => $amount, 'currency' => $currency );

    if ( empty( $payment->logs ) || ! is_array( $payment->logs ) )
        return null;

    foreach ( array_reverse( $payment->logs ) as $log ) {

        if ( empty( $log['type'] ) || $log['type'] !== 'paypal_ipn_received' || empty( $log['data']['data'] ) || ! is_array( $log['data']['data'] ) )
            continue;

        $ipn = $log['data']['data'];

        if ( empty( $ipn['txn_type'] ) || $ipn['txn_type'] !== 'recurring_payment_profile_created' || empty( $ipn['amount_per_cycle'] ) )
            continue;

        if ( ! empty( $ipn['recurring_payment_id'] ) && ! empty( $payment->profile_id ) && $ipn['recurring_payment_id'] !== $payment->profile_id )
            continue;

        $currency = ! empty( $ipn['currency_code'] ) ? $ipn['currency_code'] : ( isset( $ipn['mc_currency'] ) ? $ipn['mc_currency'] : '' );

        if ( pms_paypal_save_profile_terms( $payment, $ipn['amount_per_cycle'], $currency ) )
            return array( 'amount' => pms_paypal_normalize_amount( $ipn['amount_per_cycle'] ), 'currency' => pms_normalize_currency_code( $currency ) );

    }

    return null;

}

/**
 * Returns what a PayPal renewal IPN is expected to have: its currency, the amounts it may have, and its plan
 *
 * Every amount comes from a value the site set or stored itself. The renewal IPN's own amount_per_cycle is never one.
 * When the profile terms are stored, the expected amounts are the terms and the last renewal. Otherwise they are the last
 * renewal, the subscription billing amount, the initial payment when it was priced like the renewals, and the plan price
 * when it was not.
 * The last renewal's amount is `exact`, so that accepted renewals cannot move the accepted amount by a cent each cycle.
 * The last renewal is the newest completed payment with the profile ID that has the member and the plan of the initial payment.
 * PayPal Standard takes the initial payment from the IPN's `custom`, which the buyer's browser sends, so a payment of the
 * profile for another member or plan must not count. PayPal Standard renewals saved without a profile ID are not found.
 *
 * @param PMS_Payment                        $initial_payment     The initial payment of the PayPal profile.
 * @param PMS_Subscription_Plan|null         $plan                The plan of the initial payment.
 * @param PMS_Member_Subscription|false|null $member_subscription The member subscription the renewal extends, or null when the charge comes from an older PayPal profile.
 * @param array                              $payment_data        IPN data, when called for an IPN. Its `profile_id` is the profile the renewal came from.
 *
 * @return array array(
 *     'currency' => uppercase code,
 *     'amounts'  => list of array( 'amount' => 2-dp string, 'source' => profile_amount_per_cycle|last_renewal|subscription_billing_amount|initial_payment|plan_price, 'exact' => bool ),
 *     'amount'   => the first of `amounts`, or the `legacy` amount when `amounts` is empty. The matcher reads `amounts` only.
 *     'plan_id'  => int,
 *     'legacy'   => array( 'amount', 'currency' ) the earlier exact check expected, used to log the renewals it would have rejected,
 * )
 *
 */
function pms_paypal_resolve_expected_recurring( $initial_payment, $plan, $member_subscription = null, $payment_data = array() ) {

    $profile_id = ! empty( $payment_data['profile_id'] ) ? $payment_data['profile_id'] : $initial_payment->profile_id;

    $last_renewal = null;

    if ( ! empty( $profile_id ) ) {

        foreach ( pms_get_payments( array( 'profile_id' => $profile_id, 'status' => 'completed', 'number' => 10, 'orderby' => 'id', 'order' => 'DESC' ) ) as $profile_payment ) {

            if (
                (int) $profile_payment->id !== (int) $initial_payment->id
                && $profile_payment->type !== 'recurring_payment_profile_created'
                && (int) $profile_payment->user_id === (int) $initial_payment->user_id
                && (int) $profile_payment->subscription_id === (int) $initial_payment->subscription_id
            ) {
                $last_renewal = $profile_payment;
                break;
            }

        }

    }

    $has_subscription      = is_object( $member_subscription );
    $terms                 = pms_paypal_get_profile_terms( $initial_payment );
    $renewal_currency      = $last_renewal !== null ? pms_get_payment_currency( $last_renewal ) : '';
    $subscription_currency = $has_subscription ? pms_get_member_subscription_currency( $member_subscription ) : '';
    $initial_currency      = pms_get_payment_currency( $initial_payment );

    if ( $terms !== null )
        $currency = $terms['currency'];
    elseif ( $last_renewal !== null )
        $currency = $renewal_currency;
    elseif ( $has_subscription )
        $currency = $subscription_currency;
    else
        $currency = $initial_currency;

    $candidates = array();

    if ( $terms !== null )
        $candidates[] = array( 'amount' => $terms['amount'], 'currency' => $terms['currency'], 'source' => 'profile_amount_per_cycle' );

    if ( $last_renewal !== null && (float) $last_renewal->amount > 0 )
        $candidates[] = array( 'amount' => $last_renewal->amount, 'currency' => $renewal_currency, 'source' => 'last_renewal', 'exact' => true );

    if ( $terms === null ) {

        if ( $has_subscription && (float) $member_subscription->billing_amount > 0 )
            $candidates[] = array( 'amount' => $member_subscription->billing_amount, 'currency' => $subscription_currency, 'source' => 'subscription_billing_amount' );

        $initial_prorated = ! empty( pms_get_payment_meta( $initial_payment->id, 'pms_payment_prorated', true ) )
            || ( is_array( $initial_payment->logs ) && in_array( 'pms_payment_prorated', array_column( $initial_payment->logs, 'type' ), true ) );

        $initial_priced_like_renewals = (float) $initial_payment->amount > 0
            && $initial_currency === $currency
            && ! $initial_prorated
            && ( ! is_object( $plan ) || ( ! $plan->has_sign_up_fee() && ! $plan->has_trial() ) )
            && ( ! $has_subscription || empty( $member_subscription->trial_end ) );

        $discount_deleted = false;

        if ( $initial_priced_like_renewals && ! empty( $initial_payment->discount_code ) ) {

            // Any status, since a recurring discount keeps reducing the PayPal charges after the code expires, is deactivated or is trashed
            $discount_ids = get_posts( array(
                'post_type'   => 'pms-discount-codes',
                'post_status' => array( 'any', 'trash' ),
                'meta_key'    => 'pms_discount_code',
                'meta_value'  => $initial_payment->discount_code,
                'numberposts' => 1,
                'fields'      => 'ids',
            ) );

            if ( empty( $discount_ids ) )
                $discount_deleted = true;
            else
                $initial_priced_like_renewals = get_post_meta( $discount_ids[0], 'pms_discount_recurring_payments', true ) === 'checked';

        }

        if ( $initial_priced_like_renewals )
            $candidates[] = array( 'amount' => $initial_payment->amount, 'currency' => $initial_currency, 'source' => 'initial_payment' );

        // A deleted code may have been recurring or for the first payment only, so the plan price stays a candidate too
        if ( is_object( $plan ) && ( ! $initial_priced_like_renewals || $discount_deleted ) ) {

            if ( $currency === pms_normalize_currency_code( pms_get_active_currency() ) ) {
                $plan_price = $plan->price;
            } else {
                /**
                 * Filters the price of a plan in a currency other than the site currency, as a PayPal renewal amount
                 *
                 * @param float|string|null     $price    The plan price in that currency. Default null, when the price is not known.
                 * @param PMS_Subscription_Plan $plan     The plan.
                 * @param string                $currency Uppercase ISO 4217 code.
                 *
                 */
                $plan_price = apply_filters( 'pms_paypal_plan_price_in_currency', null, $plan, $currency );
            }

            if ( is_numeric( $plan_price ) && (float) $plan_price > 0 )
                $candidates[] = array( 'amount' => $plan_price, 'currency' => $currency, 'source' => 'plan_price' );

        }

    }

    $amounts = array();

    foreach ( $candidates as $candidate ) {

        if ( $candidate['currency'] === $currency )
            $amounts[] = array( 'amount' => pms_paypal_normalize_amount( $candidate['amount'] ), 'source' => $candidate['source'], 'exact' => ! empty( $candidate['exact'] ) );

    }

    $legacy_amount = pms_paypal_normalize_amount( _pms_paypal_legacy_expected_recurring_amount( $initial_payment, $plan, $member_subscription ) );

    $expected = array(
        'currency' => $currency,
        'amounts'  => $amounts,
        'amount'   => ! empty( $amounts ) ? $amounts[0]['amount'] : $legacy_amount,
        'plan_id'  => absint( $initial_payment->subscription_id ),
        'legacy'   => array(
            'amount'   => $legacy_amount,
            'currency' => ! empty( $initial_payment->currency ) ? strtoupper( $initial_payment->currency ) : pms_get_active_currency(),
        ),
    );

    /**
     * Filters what a PayPal renewal IPN is expected to have
     *
     * @param array                              $expected            Currency, amounts with their sources, plan ID and the `legacy` expectation, as pms_paypal_resolve_expected_recurring() documents them.
     * @param PMS_Payment                        $initial_payment     The initial payment of the PayPal profile.
     * @param PMS_Member_Subscription|false|null $member_subscription The member subscription the renewal extends, or null when the charge comes from an older PayPal profile.
     * @param array                              $payment_data        IPN data, or an empty array outside an IPN.
     *
     */
    return apply_filters( 'pms_paypal_ipn_expected_recurring', $expected, $initial_payment, $member_subscription, $payment_data );

}

/**
 * Returns the `legacy` amount of pms_paypal_resolve_expected_recurring(): the subscription billing amount, else the
 * initial payment amount when the plan has no sign-up fee, else the plan price, else the initial payment amount
 *
 * @access private
 *
 * @param PMS_Payment                        $payment             The initial payment of the PayPal profile.
 * @param PMS_Subscription_Plan|null         $plan                The plan of the initial payment.
 * @param PMS_Member_Subscription|false|null $member_subscription The member subscription the renewal extends.
 *
 * @return mixed
 *
 */
function _pms_paypal_legacy_expected_recurring_amount( $payment, $plan = null, $member_subscription = null ) {
    if ( is_object( $member_subscription ) && (float) $member_subscription->billing_amount > 0 ) {
        return $member_subscription->billing_amount;
    }

    if ( is_object( $plan ) && ! $plan->has_sign_up_fee() && $payment->amount !== '' && $payment->amount !== null ) {
        return $payment->amount;
    }

    if ( is_object( $plan ) && $plan->price !== '' && $plan->price !== null ) {
        return $plan->price;
    }

    return $payment->amount;
}

/**
 * Returns what changed when PMS accepts a PayPal renewal that the earlier exact check would have rejected
 *
 * @param array $payment_data IPN data of the accepted renewal.
 * @param array $expected     As pms_paypal_resolve_expected_recurring() returns it, with the `legacy` expectation.
 * @param float $tolerance    Allowed difference between the received amount and an expected amount.
 *
 * @return array|null The old and accepted amount and currency, the `source` of the expected amount the renewal matched
 *                    (`currency` when only the currency changed, `bypass` when no expected amount matched) and the transaction ID.
 *                    Null when there is no `legacy` expectation or it matches the renewal.
 *
 */
function pms_paypal_ipn_expected_override( $payment_data, $expected, $tolerance = 0.01 ) {

    if ( empty( $expected['legacy'] ) || ! is_array( $expected['legacy'] ) )
        return null;

    $received_amount   = pms_paypal_normalize_amount( isset( $payment_data['amount'] ) ? $payment_data['amount'] : 0 );
    $received_currency = strtoupper( ! empty( $payment_data['currency'] ) ? $payment_data['currency'] : '' );
    $legacy_amount     = pms_paypal_normalize_amount( isset( $expected['legacy']['amount'] ) ? $expected['legacy']['amount'] : 0 );
    $legacy_currency   = strtoupper( isset( $expected['legacy']['currency'] ) ? $expected['legacy']['currency'] : '' );

    if ( $legacy_amount === $received_amount && $legacy_currency === $received_currency )
        return null;

    if ( $legacy_amount === $received_amount ) {
        $source = 'currency';
    } else {
        $candidate = pms_paypal_match_amount_candidate( $received_amount, pms_paypal_expected_amount_candidates( $expected ), $tolerance );
        $source    = $candidate !== null ? $candidate['source'] : 'bypass';
    }

    return array(
        'old_expected_amount'   => $legacy_amount,
        'old_expected_currency' => $legacy_currency,
        'accepted_amount'       => $received_amount,
        'accepted_currency'     => $received_currency,
        'source'                => $source,
        'transaction_id'        => isset( $payment_data['transaction_id'] ) ? sanitize_text_field( $payment_data['transaction_id'] ) : '',
    );

}

/**
 * Logs a PayPal renewal that the earlier exact check would have rejected, on the initial payment and on the renewal
 *
 * @param PMS_Payment $initial_payment The initial payment of the PayPal profile.
 * @param PMS_Payment $renewal_payment The payment the renewal was saved as.
 * @param array       $payment_data    IPN data of the renewal.
 * @param array       $expected        As pms_paypal_resolve_expected_recurring() returns it.
 *
 * @return void
 *
 */
function pms_paypal_log_expected_override( $initial_payment, $renewal_payment, $payment_data, $expected ) {

    $override = pms_paypal_ipn_expected_override( $payment_data, $expected, pms_paypal_ipn_amount_tolerance( $payment_data, $initial_payment, $expected ) );

    if ( $override === null )
        return;

    $initial_payment->log_data( 'paypal_ipn_expected_override', $override );
    $renewal_payment->log_data( 'paypal_ipn_expected_override', array( 'source' => $override['source'], 'initial_payment_id' => (int) $initial_payment->id ) );

}
