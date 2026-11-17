<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Function that adds the HTML for PayPal Standard in the payments tab from the Settings page
 *
 * @param array $options    - The saved option settings
 *
 */
function pms_add_settings_content_paypal_standard( $options ) {

    if( !in_array( 'paypal_standard', $options['active_pay_gates'] ) && !in_array( 'paypal_express', $options['active_pay_gates'] ) )
        return;

    ?>

    <div class="cozmoslabs-form-subsection-wrapper" id="cozmoslabs-subsection-paypal-configs">

        <h4 class="cozmoslabs-subsection-title">
            <?php echo esc_html( apply_filters( 'pms_settings_page_payment_gateway_paypal_title', esc_html__( 'Paypal Standard', 'paid-member-subscriptions' ) ) ); ?>

            <?php if( in_array( 'paypal_standard', $options['active_pay_gates'] ) ) : ?>
                <a href="https://www.cozmoslabs.com/docs/paid-member-subscriptions/payment-gateways/paypal-standard/?utm_source=wpbackend&utm_medium=pms-documentation&utm_campaign=PMSDocs#Entering_your_PayPal_API_Credentials" target="_blank" data-code="f223" class="pms-docs-link dashicons dashicons-editor-help"></a>
            <?php elseif( in_array( 'paypal_express', $options['active_pay_gates'] ) ) : ?>
                <a href="https://www.cozmoslabs.com/docs/paid-member-subscriptions/add-ons/paypal-pro-and-express-checkout/?utm_source=wpbackend&utm_medium=pms-documentation&utm_campaign=PMSDocs#Entering_your_PayPal_API_Credentials" target="_blank" data-code="f223" class="pms-docs-link dashicons dashicons-editor-help"></a>
            <?php endif; ?>
        </h4>

        <div class="cozmoslabs-form-field-wrapper">
            <label class="cozmoslabs-form-field-label" for="paypal-standard-email"><?php esc_html_e( 'PayPal E-mail Address', 'paid-member-subscriptions' ); ?></label>
            <input id="paypal-standard-email" type="text" name="pms_payments_settings[gateways][paypal][email_address]" value="<?php echo isset( $options['gateways']['paypal']['email_address' ]) ? esc_attr( $options['gateways']['paypal']['email_address'] ) : ''; ?>" class="widefat" />

            <input type="hidden" name="pms_payments_settings[gateways][paypal][name]" value="PayPal" />

            <p class="cozmoslabs-description cozmoslabs-description-align-right"><?php esc_html_e( 'Enter your PayPal e-mail address', 'paid-member-subscriptions' ); ?></p>
        </div>

        <div class="cozmoslabs-form-field-wrapper">
            <label class="cozmoslabs-form-field-label" for="paypal-standard-test-email"><?php esc_html_e( 'Test PayPal E-mail Address', 'paid-member-subscriptions' ); ?></label>
            <input id="paypal-standard-test-email" type="text" name="pms_payments_settings[gateways][paypal][test_email_address]" value="<?php echo isset( $options['gateways']['paypal']['test_email_address' ]) ? esc_attr( $options['gateways']['paypal']['test_email_address'] ) : ''; ?>" class="widefat" />

            <p class="cozmoslabs-description cozmoslabs-description-align-right"><?php esc_html_e( 'PayPal E-mail address to use for test transactions', 'paid-member-subscriptions' ); ?></p>
        </div>

        <?php do_action( 'pms_settings_page_payment_gateway_paypal_extra_fields', $options ); ?>

        <!-- IPN Message -->

        <?php if( in_array( 'paypal_standard', $options['active_pay_gates'] ) ) : ?>
            <?php
                $paypal_ipn_url = esc_url( add_query_arg( 'pay_gate_listener', 'paypal_ipn', trailingslashit( home_url() ) ) );
                $paypal_link_doc = '<a href="https://www.cozmoslabs.com/docs/paid-member-subscriptions/payment-gateways/paypal-standard/#Setting_up_Instant_Payment_Notifications_IPN" target="_blank" data-code="f223" class="pms-docs-link dashicons dashicons-editor-help"></a>';
            ?>
        <?php elseif( in_array( 'paypal_express', $options['active_pay_gates'] ) ) : ?>
            <?php
                $paypal_ipn_url = esc_url( add_query_arg( 'pay_gate_listener', 'paypal_epipn', trailingslashit( home_url() ) ) );
                $paypal_link_doc = '<a href="https://www.cozmoslabs.com/docs/paid-member-subscriptions/add-ons/paypal-pro-and-express-checkout/#Setting_up_Instant_Payment_Notifications_IPN" target="_blank" data-code="f223" class="pms-docs-link dashicons dashicons-editor-help"></a>';
            ?>
        <?php else: ?>
            <?php $paypal_ipn_url = ""; ?>
        <?php endif; ?>

        <div class="cozmoslabs-form-field-wrapper">
            <label class="cozmoslabs-form-field-label" for="paypal-ipn-url"><?php echo esc_html__( 'Use the following URL for the IPN:', 'paid-member-subscriptions' ); ?></label>
            <input id="paypal-ipn-url" type="text" name="paypal-ipn-url" value="<?php echo esc_url( $paypal_ipn_url ); ?>" class="widefat" disabled />
            <a class="paypal-connect__copy button-secondary" data-id="paypal-ipn-url" href="" style="margin-left: 4px;">Copy</a>


            <p class="pms-ipn-notice cozmoslabs-description cozmoslabs-description-space-left">
                <?php printf( wp_kses_post( __( 'In order for <strong>PayPal payments to work correctly</strong>, you need to setup the IPN Url in your PayPal account. %s', 'paid-member-subscriptions' ) ), wp_kses_post( $paypal_link_doc ) ); ?>
            </p>
        </div>

    </div>

    <?php
}
add_action( 'pms-settings-page_payment_gateways_content', 'pms_add_settings_content_paypal_standard' );

/*
 * Display a warning to administrators if the PayPal Email is not entered in settings
 *
 */
function pms_paypal_email_address_admin_warning() {

    if( !current_user_can( 'manage_options' ) )
        return;

    $are_active = array_intersect( array( 'paypal_standard', 'paypal_express', 'paypal_pro' ), pms_get_active_payment_gateways() );

    if( !empty( $are_active ) && pms_get_paypal_email() === false ) {

        echo '<div class="pms-warning-message-wrapper">';
            echo '<p>' . wp_kses_post( sprintf( __( 'Your <strong>PayPal Email Address</strong> is missing. In order to make payments you will need to add the Email Address of your PayPal account %1$s here %2$s.', 'paid-member-subscriptions' ), '<a href="' . esc_url( admin_url( 'admin.php?page=pms-settings-page&tab=payments' ) ) .'" target="_blank">', '</a>' ) ) . '</p>';
            echo '<p><em>' . esc_html__( 'This message is visible only by Administrators.', 'paid-member-subscriptions' ) . '</em></p>';
        echo '</div>';

    }

}
add_action( 'pms_register_form_top', 'pms_paypal_email_address_admin_warning' );
add_action( 'pms_new_subscription_form_top', 'pms_paypal_email_address_admin_warning' );
add_action( 'pms_upgrade_subscription_form_top', 'pms_paypal_email_address_admin_warning' );
add_action( 'pms_renew_subscription_form_top', 'pms_paypal_email_address_admin_warning' );
add_action( 'pms_retry_payment_form_top', 'pms_paypal_email_address_admin_warning' );

function pms_wppb_paypal_email_address_admin_warning() {

    if( !current_user_can( 'manage_options' ) )
        return;

    $fields = get_option( 'wppb_manage_fields' );

    if ( empty( $fields ) )
        return;

    $are_active = array_intersect( array( 'paypal_standard', 'paypal_express', 'paypal_pro' ), pms_get_active_payment_gateways() );

    foreach( $fields as $field ) {
        if ( $field['field'] == 'Subscription Plans' && !empty( $are_active ) && pms_get_paypal_email() === false ) {
            echo '<div class="pms-warning-message-wrapper">';
                echo '<p>' . wp_kses_post( sprintf( __( 'Your <strong>PayPal Email Address</strong> is missing. In order to make payments you will need to add the Email Address of your PayPal account %1$s here %2$s.', 'paid-member-subscriptions' ), '<a href="' . admin_url( 'admin.php?page=pms-settings-page&tab=payments' ) .'" target="_blank">', '</a>' ) ) . '</p>';
                echo '<p><em>' . esc_html__( 'This message is visible only by Administrators.', 'paid-member-subscriptions' ) . '</em></p>';
            echo '</div>';

            break;
        }
    }

}
add_action( 'wppb_before_register_fields', 'pms_wppb_paypal_email_address_admin_warning' );

/**
 * Returns the PayPal Email Address
 *
 * @since 1.8.5
 */
function pms_get_paypal_email() {
    $settings = get_option( 'pms_payments_settings' );

    $slug = 'email_address';

    if( isset( $settings['test_mode'] ) && $settings['test_mode'] == '1' )
        $slug = 'test_email_address';

    if ( !empty( $settings['gateways']['paypal_standard'][$slug] ) )
        return $settings['gateways']['paypal_standard'][$slug];

    if ( !empty( $settings['gateways']['paypal'][$slug] ) )
        return $settings['gateways']['paypal'][$slug];

    return false;
}

/**
 * Whether any active recurring PayPal Standard or PayPal Express subscriptions exist.
 *
 * @return bool
 */
function pms_has_active_paypal_legacy_subscriptions() {

    global $wpdb;

    $subscription_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}pms_member_subscriptions
            WHERE status = %s
            AND payment_gateway IN ( 'paypal_standard', 'paypal_express' )
            AND payment_profile_id IS NOT NULL
            AND payment_profile_id != ''
            LIMIT 1",
            'active'
        )
    );

    return ! empty( $subscription_id );
}

/**
 * PayPal legacy gateways need the merchant email for IPN verification.
 *
 * @return bool
 */
function pms_check_paypal_legacy_email_missing() {

    if ( pms_get_paypal_email() !== false ) {
        return false;
    }

    return pms_has_active_paypal_legacy_subscriptions();
}

/**
 * @param array $issues Existing dashboard issues.
 * @return array
 */
function pms_add_paypal_legacy_email_dashboard_issue( $issues ) {

    if ( pms_check_paypal_legacy_email_missing() ) {
        $issues['paypal_legacy_email_missing'] = true;
    }

    return $issues;
}
add_filter( 'pms_dashboard_issues', 'pms_add_paypal_legacy_email_dashboard_issue' );

/**
 * @param array|null $interpreted_issue Current interpretation.
 * @param string     $issue_key         Issue key.
 * @param array      $issue_data        Raw issue data.
 * @return array|null
 */
function pms_interpret_paypal_legacy_email_dashboard_issue( $interpreted_issue, $issue_key, $issue_data ) {

    if ( $issue_key !== 'paypal_legacy_email_missing' ) {
        return $interpreted_issue;
    }

    $settings_url = admin_url( 'admin.php?page=pms-settings-page&tab=payments' );

    return array(
        'severity'    => 'critical',
        'title'       => __( 'PayPal Email Address Missing', 'paid-member-subscriptions' ),
        'description' => sprintf(
            __( 'You have active subscriptions using %1$s or %2$s, but the PayPal Email Address is not set. Add it in payment settings so Instant Payment Notifications (IPNs) from PayPal can be received and processed.', 'paid-member-subscriptions' ),
            '<strong>' . esc_html__( 'PayPal Standard', 'paid-member-subscriptions' ) . '</strong>',
            '<strong>' . esc_html__( 'PayPal Express', 'paid-member-subscriptions' ) . '</strong>'
        ),
        'actions'     => array(
            array(
                'text'     => __( 'Go to Payments Settings', 'paid-member-subscriptions' ),
                'url'      => $settings_url,
                'type'     => 'primary',
                'target'   => '_self',
                'behavior' => 'url',
            ),
        ),
    );
}
add_filter( 'pms_interpret_dashboard_issue', 'pms_interpret_paypal_legacy_email_dashboard_issue', 10, 3 );

function pms_paypal_normalize_amount( $amount ) {
    return number_format( (float) $amount, 2, '.', '' );
}

function pms_paypal_log_ipn_mismatch( $payment, $data, $desc ) {
    if ( empty( $payment->id ) ) {
        return;
    }

    $payment->log_data( 'paypal_ipn_payment_mismatch', array(
        'data' => $data,
        'desc' => $desc,
    ) );
}

/**
 * Returns the normalized amounts an IPN may have, from either shape of the expected array
 *
 * @param array $expected Either an `amounts` list, whose entries are arrays with `amount`, `source` and `exact` or bare
 *                        amounts, or a single `amount`, which must match exactly.
 *
 * @return array List of array( 'amount' => 2-dp string, 'source' => string, 'exact' => bool ).
 *
 */
function pms_paypal_expected_amount_candidates( $expected ) {

    if ( ! isset( $expected['amounts'] ) || ! is_array( $expected['amounts'] ) )
        return isset( $expected['amount'] ) ? array( array( 'amount' => pms_paypal_normalize_amount( $expected['amount'] ), 'source' => 'expected', 'exact' => true ) ) : array();

    $candidates = array();

    foreach ( $expected['amounts'] as $candidate ) {

        if ( ! is_array( $candidate ) )
            $candidate = array( 'amount' => $candidate );

        if ( ! isset( $candidate['amount'] ) )
            continue;

        $candidates[] = array(
            'amount' => pms_paypal_normalize_amount( $candidate['amount'] ),
            'source' => isset( $candidate['source'] ) ? $candidate['source'] : 'expected',
            'exact'  => ! empty( $candidate['exact'] ),
        );

    }

    return $candidates;

}

/**
 * Returns how far a received PayPal amount may be from an expected amount that is not marked `exact`
 *
 * @param array       $payment_data IPN data.
 * @param PMS_Payment $payment      The payment the IPN is matched against.
 * @param array       $expected     What the IPN is matched against.
 *
 * @return float
 *
 */
function pms_paypal_ipn_amount_tolerance( $payment_data, $payment, $expected ) {

    /**
     * Filters how far a PayPal amount may be from an expected amount that is not marked `exact`, and still be accepted
     *
     * @param float       $tolerance    Allowed difference, in the IPN currency, rounded to cents. Default 0.01. A negative value counts as 0.
     * @param array       $payment_data IPN data.
     * @param PMS_Payment $payment      The payment the IPN is matched against.
     * @param array       $expected     What the IPN is matched against: an `amounts` list as pms_paypal_resolve_expected_recurring()
     *                                  returns it, or a single `amount`.
     *
     */
    return max( 0.0, (float) apply_filters( 'pms_paypal_ipn_amount_tolerance', 0.01, $payment_data, $payment, $expected ) );

}

/**
 * Returns the first expected amount that a received amount matches
 *
 * An `exact` candidate must match to the cent. Any other may differ by up to the tolerance.
 *
 * @param string $received_amount Normalized received amount.
 * @param array  $candidates      As pms_paypal_expected_amount_candidates() returns them.
 * @param float  $tolerance       Allowed difference, rounded to cents.
 *
 * @return array|null The matching candidate, or null when none matches.
 *
 */
function pms_paypal_match_amount_candidate( $received_amount, $candidates, $tolerance ) {

    $received_cents  = (int) round( (float) $received_amount * 100 );
    $tolerance_cents = (int) round( (float) $tolerance * 100 );

    foreach ( $candidates as $candidate ) {

        $allowed_cents = ! empty( $candidate['exact'] ) ? 0 : $tolerance_cents;

        if ( abs( $received_cents - (int) round( (float) $candidate['amount'] * 100 ) ) <= $allowed_cents )
            return $candidate;

    }

    return null;

}

/**
 * Checks an IPN against the amount, currency and plan it is expected to have, and logs a mismatch
 *
 * @param PMS_Payment $payment      The payment the IPN is matched against.
 * @param array       $payment_data IPN data.
 * @param array       $expected     The `currency` and `plan_id` to match, and either an `amounts` list (matched within
 *                                  pms_paypal_ipn_amount_tolerance()) or a single `amount` (matched exactly).
 * @param array       $post_data    The raw IPN, read only to log a mismatch.
 *
 * @return bool
 *
 */
function pms_paypal_ipn_matches_expected( $payment, $payment_data, $expected, $post_data = array() ) {
    $candidates        = pms_paypal_expected_amount_candidates( $expected );
    $received_amount   = pms_paypal_normalize_amount( isset( $payment_data['amount'] ) ? $payment_data['amount'] : 0 );
    $expected_currency = strtoupper( ! empty( $expected['currency'] ) ? $expected['currency'] : pms_get_active_currency() );
    $received_currency = strtoupper( ! empty( $payment_data['currency'] ) ? $payment_data['currency'] : '' );
    $expected_plan_id  = absint( $expected['plan_id'] );
    $received_plan_id  = absint( isset( $payment_data['subscription_id'] ) ? $payment_data['subscription_id'] : 0 );

    $amount_matches = pms_paypal_match_amount_candidate( $received_amount, $candidates, pms_paypal_ipn_amount_tolerance( $payment_data, $payment, $expected ) ) !== null;

    /**
     * Filters whether a PayPal IPN whose amount differs from the expected amount should still be accepted.
     * Currency, plan and transaction ID checks still apply.
     *
     * @param bool        $bypass       Whether to skip the amount check. Default false.
     * @param array       $payment_data IPN data (amount, currency, subscription_id, transaction_id, and type, status, payment_id, user_id when available).
     * @param PMS_Payment $payment      The payment the IPN is matched against.
     * @param array       $expected     The expected `amount`, `currency` and `plan_id`. For a renewal, also the `amounts` the IPN
     *                                  was matched against, as pms_paypal_resolve_expected_recurring() returns them.
     */
    if ( ! $amount_matches && apply_filters( 'pms_paypal_ipn_bypass_amount_verification', false, $payment_data, $payment, $expected ) ) {
        $amount_matches = true;
    }

    $currency_matches = $expected_currency === $received_currency;

    /**
     * Filters whether a PayPal IPN whose currency differs from the expected currency should still be accepted
     *
     * For converted currencies only, where PayPal charges a converted amount in another currency than the one stored by PMS.
     * Amount, plan and transaction ID checks still apply.
     *
     * @param bool        $bypass       Whether to skip the currency check. Default false.
     * @param array       $payment_data IPN data.
     * @param PMS_Payment $payment      The payment the IPN is matched against.
     * @param array       $expected     The expected `amount`, `currency` and `plan_id`. For a renewal, also the `amounts` the IPN
     *                                  was matched against, as pms_paypal_resolve_expected_recurring() returns them.
     *
     */
    if ( ! $currency_matches && apply_filters( 'pms_paypal_ipn_bypass_currency_verification', false, $payment_data, $payment, $expected ) ) {
        $currency_matches = true;
    }

    $plan_matches = $expected_plan_id === $received_plan_id;

    if ( $amount_matches && $currency_matches && $plan_matches ) {
        return true;
    }

    $profile_id = '';

    if ( ! empty( $payment_data['profile_id'] ) )
        $profile_id = $payment_data['profile_id'];
    elseif ( ! empty( $post_data['recurring_payment_id'] ) )
        $profile_id = $post_data['recurring_payment_id'];
    elseif ( ! empty( $post_data['subscr_id'] ) )
        $profile_id = $post_data['subscr_id'];

    $details = array(
        'reason'               => 'amount_currency_or_plan_mismatch',
        'failed'               => array_keys( array_filter( array( 'amount' => ! $amount_matches, 'currency' => ! $currency_matches, 'plan' => ! $plan_matches ) ) ),
        'expected_amount'      => ! empty( $candidates ) ? $candidates[0]['amount'] : '',
        'received_amount'      => $received_amount,
        'expected_currency'    => $expected_currency,
        'received_currency'    => $received_currency,
        'expected_plan_id'     => $expected_plan_id,
        'received_plan_id'     => $received_plan_id,
        'candidates'           => $candidates,
        'profile_id'           => sanitize_text_field( $profile_id ),
        'transaction_id'       => isset( $payment_data['transaction_id'] ) ? sanitize_text_field( $payment_data['transaction_id'] ) : '',
        'payment_date'         => isset( $payment_data['date'] ) ? sanitize_text_field( $payment_data['date'] ) : '',
        'mc_gross'             => isset( $post_data['mc_gross'] ) ? sanitize_text_field( $post_data['mc_gross'] ) : '',
        'mc_currency'          => isset( $post_data['mc_currency'] ) ? sanitize_text_field( $post_data['mc_currency'] ) : '',
        'ipn_amount_per_cycle' => isset( $post_data['amount_per_cycle'] ) ? sanitize_text_field( $post_data['amount_per_cycle'] ) : '',
        'txn_type'             => sanitize_text_field( ! empty( $payment_data['type'] ) ? $payment_data['type'] : ( isset( $post_data['txn_type'] ) ? $post_data['txn_type'] : '' ) ),
    );

    pms_paypal_log_ipn_mismatch( $payment, $details, 'PayPal IPN rejected: amount, currency, or plan does not match pending payment' );

    /**
     * Fires after a PayPal IPN is rejected because its amount, currency or plan does not match what PMS expects
     *
     * @param PMS_Payment $payment      The payment the IPN was matched against.
     * @param array       $payment_data IPN data.
     * @param array       $expected     The expected `amount`, `currency` and `plan_id`. For a renewal, also the `amounts` the IPN
     *                                  was matched against, as pms_paypal_resolve_expected_recurring() returns them.
     * @param array       $details      What was logged on the payment: the expected and received values, the failed checks and the IPN identifiers.
     *
     */
    do_action( 'pms_paypal_ipn_payment_mismatch', $payment, $payment_data, $expected, $details );

    return false;
}

function pms_paypal_ipn_transaction_id_available( $payment, $transaction_id ) {
    if ( empty( $transaction_id ) || $transaction_id === '-' ) {
        return true;
    }

    $existing_txn_payments = pms_get_payments( array(
        'transaction_id' => $transaction_id,
        'number'         => 1,
    ) );

    if ( empty( $existing_txn_payments ) || empty( $existing_txn_payments[0]->id ) ) {
        return true;
    }

    if ( (int) $existing_txn_payments[0]->id === (int) $payment->id ) {
        return true;
    }

    pms_paypal_log_ipn_mismatch(
        $payment,
        array(
            'reason'         => 'duplicate_transaction_id',
            'transaction_id' => $transaction_id,
            'existing_id'    => $existing_txn_payments[0]->id,
        ),
        'PayPal IPN rejected: transaction ID already used'
    );

    return false;
}

/**
 * The payment log is the only record of a payment that older versions failed on a Pending IPN.
 */
function pms_paypal_ipn_payment_failed_by_pending_ipn( $payment, $transaction_id ) {
    if ( $payment->status !== 'failed' || $payment->payment_gateway !== 'paypal_standard' || $transaction_id === '' || $payment->transaction_id !== $transaction_id || pms_get_payment_refund_date( $payment->id ) || ! is_array( $payment->logs ) ) {
        return false;
    }

    $status_changes = 0;

    foreach ( array_reverse( $payment->logs ) as $log ) {
        if ( empty( $log['type'] ) ) {
            continue;
        }

        if ( $log['type'] === 'status_changed' && ++$status_changes > 1 ) {
            return false;
        }

        if ( $log['type'] === 'payment_failed' ) {
            $ipn = isset( $log['data']['data'] ) ? $log['data']['data'] : array();

            return isset( $ipn['payment_status'], $ipn['txn_id'] ) && strtolower( $ipn['payment_status'] ) === 'pending' && $ipn['txn_id'] === $transaction_id;
        }
    }

    return false;
}

function pms_paypal_ipn_can_complete_pending_payment( $payment, $payment_data, $post_data = array() ) {
    if ( empty( $payment->id ) ) {
        return false;
    }

    $transaction_id = isset( $payment_data['transaction_id'] ) ? $payment_data['transaction_id'] : '';

    if ( $payment->status !== 'pending' && ! pms_paypal_ipn_payment_failed_by_pending_ipn( $payment, $transaction_id ) ) {
        pms_paypal_log_ipn_mismatch(
            $payment,
            array(
                'reason'         => 'payment_not_pending',
                'payment_status' => $payment->status,
            ),
            'PayPal IPN rejected: payment is not pending'
        );

        return false;
    }

    $expected = array(
        'amount'   => $payment->amount,
        'currency' => pms_get_payment_currency( $payment ),
        'plan_id'  => $payment->subscription_id,
    );

    if ( ! pms_paypal_ipn_matches_expected( $payment, $payment_data, $expected, $post_data ) ) {
        return false;
    }

    return pms_paypal_ipn_transaction_id_available( $payment, $transaction_id );
}

/**
 * PayPal sends Pending while it holds a payment (eCheck, review, currency acceptance) and Completed later for the same transaction.
 */
function pms_paypal_ipn_record_pending_payment( $payment, $payment_data, $post_data ) {
    if ( empty( $payment->id ) || $payment->status !== 'pending' ) {
        return;
    }

    $payment->log_data( 'paypal_ipn_pending', array( 'data' => $post_data, 'desc' => 'paypal IPN' ) );

    if ( pms_paypal_ipn_can_complete_pending_payment( $payment, $payment_data, $post_data ) ) {
        $payment->update( array( 'transaction_id' => $payment_data['transaction_id'] ) );
    }
}

/**
 * Returns the amount a PayPal renewal is expected to have
 *
 * @deprecated Use pms_paypal_resolve_expected_recurring(), which returns every amount a renewal may have, and its currency.
 *
 * @param PMS_Payment                        $payment             The initial payment of the PayPal profile.
 * @param PMS_Subscription_Plan|null         $plan                The plan of the initial payment.
 * @param PMS_Member_Subscription|false|null $member_subscription The member subscription the renewal extends.
 *
 * @return mixed The first expected amount, or the payment amount when none is known.
 *
 */
function pms_paypal_expected_recurring_amount( $payment, $plan = null, $member_subscription = null ) {

    $candidates = pms_paypal_expected_amount_candidates( pms_paypal_resolve_expected_recurring( $payment, $plan, $member_subscription ) );

    return ! empty( $candidates ) ? $candidates[0]['amount'] : $payment->amount;

}

/**
 * Add custom log messages for the PayPal Standard gateway
 *
 */
function pms_paypal_payment_logs_system_error_messages( $message, $log ) {

    if ( empty( $log['type'] ) )
        return $message;

    $kses_args = array(
        'strong' => array()
    );

    switch( $log['type'] ) {
        case 'paypal_to_checkout':
            $message = __( 'User sent to <strong>PayPal Checkout</strong> to continue the payment process.', 'paid-member-subscriptions' );
            break;
        case 'paypal_ipn_waiting':
            $message = __( 'Waiting to receive Instant Payment Notification (IPN) from <strong>PayPal</strong>.', 'paid-member-subscriptions' );
            break;
        case 'paypal_ipn_received':
            $message = __( 'Instant Payment Notification (IPN) received from PayPal.', 'paid-member-subscriptions' );
            break;
        case 'paypal_ipn_not_received':
            $message = __( 'Instant Payment Notification (IPN) not received from PayPal.', 'paid-member-subscriptions' );
            break;
        case 'paypal_ipn_pending':
            $message = __( 'PayPal reported the payment as pending. Waiting for PayPal to complete it.', 'paid-member-subscriptions' );

            if( !empty( $log['data']['data']['pending_reason'] ) ) {
                /* translators: %s: pending reason sent by PayPal, for example echeck */
                $message .= ' ' . sprintf( __( 'Reason: <strong>%s</strong>', 'paid-member-subscriptions' ), esc_html( $log['data']['data']['pending_reason'] ) );
            }
            break;
        case 'paypal_ipn_payment_mismatch':
            $message = __( 'PayPal IPN rejected because it does not match the pending payment.', 'paid-member-subscriptions' );
            break;
        case 'paypal_ipn_expected_override':
            if( isset( $log['data']['initial_payment_id'] ) )
                $message = sprintf( __( 'PMS accepted this PayPal renewal through the <strong>%1$s</strong> match, where the earlier exact check would have rejected it. Details are logged on initial payment #%2$s.', 'paid-member-subscriptions' ), esc_html( $log['data']['source'] ), (int)$log['data']['initial_payment_id'] );
            else
                $message = sprintf( __( 'PMS accepted PayPal renewal %1$s at <strong>%4$s %5$s</strong> through the <strong>%6$s</strong> match. The earlier exact check expected %2$s %3$s and would have rejected it.', 'paid-member-subscriptions' ), esc_html( $log['data']['transaction_id'] ), esc_html( $log['data']['old_expected_amount'] ), esc_html( $log['data']['old_expected_currency'] ), esc_html( $log['data']['accepted_amount'] ), esc_html( $log['data']['accepted_currency'] ), esc_html( $log['data']['source'] ) );
            break;
        case 'paypal_deleted_subscription_cancel_failed':
            $message  = sprintf( __( 'Subscription #%1$s was deleted, but PayPal could not cancel its profile <strong>%2$s</strong>.', 'paid-member-subscriptions' ), (int)$log['data']['subscription_id'], esc_html( $log['data']['profile_id'] ) );
            $message .= ' ' . ( !empty( $log['data']['retry'] ) ? __( 'PMS retries every hour.', 'paid-member-subscriptions' ) : __( 'Cancel it in PayPal.', 'paid-member-subscriptions' ) );

            if( !empty( $log['data']['error'] ) )
                $message .= ' ' . sprintf( __( 'PayPal error: %s', 'paid-member-subscriptions' ), esc_html( $log['data']['error'] ) );
            break;
    }

    return apply_filters( 'pms_paypal_payment_logs_system_error_messages', wp_kses_post( $message, $kses_args ), $log );

}
add_filter( 'pms_payment_logs_system_error_messages', 'pms_paypal_payment_logs_system_error_messages', 10, 2 );

/**
 * Returns an array with the API username, API password and API signature of the PayPal business account
 * if they all exist, if not will return false
 *
 * @return mixed array or bool false
 *
 */
function pms_get_paypal_api_credentials() {

    if ( defined( 'PMS_VERSION' ) && version_compare( PMS_VERSION, '1.7.8' ) == -1 ) {
        $pms_settings = get_option( 'pms_settings' );

        if ( !isset( $pms_settings['payments']['gateways']['paypal'] ) )
            return false;

        $pms_settings = $pms_settings['payments']['gateways']['paypal'];
    } else {
        $pms_settings = get_option( 'pms_payments_settings' );

        if ( !isset( $pms_settings['gateways']['paypal'] ) )
            return false;

        $pms_settings = $pms_settings['gateways']['paypal'];
    }

    if ( empty( $pms_settings ) )
        return false;

    if( pms_is_payment_test_mode() )
        $sandbox_prefix = 'test_';
    else
        $sandbox_prefix = '';

    $api_credentials = array(
        'username'  => $pms_settings[$sandbox_prefix . 'api_username'],
        'password'  => $pms_settings[$sandbox_prefix . 'api_password'],
        'signature' => $pms_settings[$sandbox_prefix . 'api_signature']
    );

    $api_credentials = array_map( 'trim', $api_credentials );

    if( count( array_filter($api_credentials) ) == count($api_credentials) )
        return $api_credentials;
    else
        return false;

}

/*
 * Display a warning to the administrators if the API credentials are missing in the
 * register page
 *
 */
function pms_paypal_api_credentials_admin_warning() {

    if( !current_user_can( 'manage_options' ) )
        return;

    $are_active = array_intersect( array( 'paypal_express', 'paypal_pro' ), pms_get_active_payment_gateways() );

    if( pms_get_paypal_api_credentials() == false && !empty( $are_active ) ) {

        echo '<div class="pms-warning-message-wrapper">';
        echo '<strong>' . esc_html__( 'Error', 'paid-member-subscriptions' ) . '</strong>';
            echo '<p>' . sprintf( esc_html__( 'Your %3$s PayPal API credentials %4$s are missing. In order to make payments you will need to add your API credentials %1$s here %2$s.', 'paid-member-subscriptions' ), '<a href="' . esc_url( admin_url( 'admin.php?page=pms-settings-page&tab=payments' ) ) .'" target="_blank">', '</a>', '<strong>', '</strong>' ) . '</p>';
            echo '<p><em>' . esc_html__( 'This message is visible only by Administrators.', 'paid-member-subscriptions' ) . '</em></p>';
        echo '</div>';

    }

}
add_action( 'pms_register_form_top', 'pms_paypal_api_credentials_admin_warning' );
add_action( 'pms_new_subscription_form_top', 'pms_paypal_api_credentials_admin_warning' );
add_action( 'pms_upgrade_subscription_form_top', 'pms_paypal_api_credentials_admin_warning' );
add_action( 'pms_renew_subscription_form_top', 'pms_paypal_api_credentials_admin_warning' );
add_action( 'pms_retry_payment_form_top', 'pms_paypal_api_credentials_admin_warning' );

function pms_wppb_paypal_api_credentials_admin_warning() {

    if( !current_user_can( 'manage_options' ) )
        return;

    $fields = get_option( 'wppb_manage_fields' );

    if ( empty( $fields ) )
        return;

    $are_active = array_intersect( array( 'paypal_express', 'paypal_pro' ), pms_get_active_payment_gateways() );

    foreach( $fields as $field ) {
        if ( $field['field'] == 'Subscription Plans' && !empty( $are_active ) && pms_get_paypal_api_credentials() === false ) {
            echo '<div class="pms-warning-message-wrapper">';
                echo '<p>' . sprintf( esc_html__( 'Your <strong>PayPal API credentials</strong> are missing. In order to make payments you will need to add your API credentials %1$s here %2$s.', 'paid-member-subscriptions' ), '<a href="' . esc_url( admin_url( 'admin.php?page=pms-settings-page&tab=payments' ) ) .'" target="_blank">', '</a>' ) . '</p>';
                echo '<p><em>' . esc_html__( 'This message is visible only by Administrators.', 'paid-member-subscriptions' ) . '</em></p>';
            echo '</div>';

            break;
        }
    }

}
add_action( 'wppb_before_register_fields', 'pms_wppb_paypal_api_credentials_admin_warning' );

/*
 * Checks to see if the payment profile id provided is one supported by
 * PayPal
 *
 * @param string $payment_profile_id
 *
 * @return bool
 *
 */
function pms_is_paypal_payment_profile_id( $payment_profile_id = '' ) {

    if( empty( $payment_profile_id ) )
        return false;

    if( strpos( $payment_profile_id, 'I-' ) !== false )
        return true;
    else
        return false;

}

add_filter( 'pms_sanitize_settings', 'pms_sanitize_paypal_settings', 10, 2 );
function pms_sanitize_paypal_settings( $options, $previous_options ) {

    $settings = get_option( 'pms_payments_settings' );
    $slug     = 'email_address';

    if( isset( $settings['test_mode'] ) && $settings['test_mode'] == '1' )
        $slug = 'test_email_address';

    if ( isset( $options['gateways']['paypal_standard'][$slug] ) )
        $options['gateways']['paypal'][$slug] = $options['gateways']['paypal_standard'][$slug];

    return $options;

}