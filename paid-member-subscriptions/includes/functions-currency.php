<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Normalizes a currency code to an uppercase ISO 4217 code
 *
 * @param mixed $currency
 *
 * @return string The uppercase three-letter code, or an empty string when the value is not one.
 *
 */
function pms_normalize_currency_code( $currency ) {

    if ( ! is_string( $currency ) )
        return '';

    $currency = strtoupper( trim( $currency ) );

    return preg_match( '/^[A-Z]{3}$/', $currency ) ? $currency : '';

}

/**
 * Whether a payment gateway can charge in a currency
 *
 * PayPal cannot charge some currencies. Multiple Currencies charges those in the site currency, converted, and keeps
 * the currency the member chose in the payment and subscription meta `currency`.
 *
 * @param string $gateway  Payment gateway slug.
 * @param string $currency Uppercase ISO 4217 code.
 *
 * @return bool
 *
 */
function pms_gateway_can_charge_currency( $gateway, $currency ) {

    static $paypal_unsupported_currencies = null;

    if ( ! in_array( $gateway, array( 'paypal_standard', 'paypal_express', 'paypal_connect' ), true ) || ! function_exists( 'pms_ppcp_get_paypal_unsupported_currencies' ) )
        return true;

    if ( $paypal_unsupported_currencies === null )
        $paypal_unsupported_currencies = pms_ppcp_get_paypal_unsupported_currencies();

    return ! isset( $paypal_unsupported_currencies[ $currency ] );

}

/**
 * Returns the currency a payment was charged in
 *
 * Checks the payment meta `currency` first, because some multi-currency setups keep the charged currency there
 * and leave the site currency in the payment's `currency` column, then the column, then the site currency.
 * A meta currency the payment's gateway cannot charge is skipped.
 *
 * @param PMS_Payment|int|null $payment The payment or its ID.
 *
 * @return string Uppercase ISO 4217 code.
 *
 */
function pms_get_payment_currency( $payment = null ) {

    if ( is_numeric( $payment ) )
        $payment = pms_get_payment( $payment );

    $currency = ! empty( $payment->id ) ? pms_normalize_currency_code( pms_get_payment_meta( $payment->id, 'currency', true ) ) : '';

    if ( $currency !== '' && ! pms_gateway_can_charge_currency( $payment->payment_gateway, $currency ) )
        $currency = '';

    if ( $currency === '' && ! empty( $payment->currency ) )
        $currency = pms_normalize_currency_code( $payment->currency );

    if ( $currency === '' )
        $currency = pms_normalize_currency_code( pms_get_active_currency() );

    if ( $currency === '' )
        $currency = 'USD';

    /**
     * Filters the currency a payment was charged in
     *
     * A value that is not a three-letter currency code is ignored.
     *
     * @param string           $currency Uppercase ISO 4217 code.
     * @param PMS_Payment|null $payment  The payment, or null when none was given.
     *
     */
    $filtered_currency = pms_normalize_currency_code( apply_filters( 'pms_get_payment_currency', $currency, $payment ) );

    return $filtered_currency !== '' ? $filtered_currency : $currency;

}

/**
 * Returns the currency a member subscription is billed in
 *
 * Checks the subscription meta `currency` first, then the currency of the subscription's latest completed payment,
 * then the site currency. A meta currency the subscription's gateway cannot charge is skipped.
 *
 * @param PMS_Member_Subscription|int|null $subscription The member subscription or its ID.
 *
 * @return string Uppercase ISO 4217 code.
 *
 */
function pms_get_member_subscription_currency( $subscription = null ) {

    if ( is_numeric( $subscription ) )
        $subscription = pms_get_member_subscription( $subscription );

    $currency = '';

    if ( ! empty( $subscription->id ) ) {

        $currency = pms_normalize_currency_code( pms_get_member_subscription_meta( $subscription->id, 'currency', true ) );

        if ( $currency !== '' && ! pms_gateway_can_charge_currency( $subscription->payment_gateway, $currency ) )
            $currency = '';

        if ( $currency === '' ) {

            $payments = pms_get_payments( array( 'member_subscription_id' => $subscription->id, 'status' => 'completed', 'number' => 1, 'orderby' => 'id', 'order' => 'DESC' ) );

            if ( ! empty( $payments ) )
                $currency = pms_get_payment_currency( $payments[0] );

        }

    }

    if ( $currency === '' )
        $currency = pms_normalize_currency_code( pms_get_active_currency() );

    if ( $currency === '' )
        $currency = 'USD';

    /**
     * Filters the currency a member subscription is billed in
     *
     * A value that is not a three-letter currency code is ignored.
     *
     * @param string                       $currency     Uppercase ISO 4217 code.
     * @param PMS_Member_Subscription|null $subscription The member subscription, or null when none was found.
     *
     */
    $filtered_currency = pms_normalize_currency_code( apply_filters( 'pms_get_member_subscription_currency', $currency, $subscription ) );

    return $filtered_currency !== '' ? $filtered_currency : $currency;

}
