/**
 * This function is executed after the PayPal Onboarding process is finished
 *
 * @param authCode
 * @param sharedId
 */
function pms_ppcp_connect_callback(authCode, sharedId) {
    let ajaxNonce = jQuery('#pms-paypal-connect-onboarding').data('ajax-nonce');

    jQuery.post(ajaxurl, { 
        action: 'pms_ppcp_process_onboarding', 
        sharedId: sharedId,
        authCode: authCode,
        ajaxNonce: ajaxNonce
    }, function (response) {
        try {
            let data = JSON.parse(response);

            if (data.success) {
                location.reload();
            } else {
                console.log('PayPal Onboarding Error: ' + data.message);
                jQuery('.isu-minibrowser-component-context-popup').remove();
            }
        } catch (e) {
            console.log('Unexpected response: ' + response);
        }
    });

}


jQuery(document).ready(function() {

    // With this header the PayPal onboarding popup loses window.opener and can never call pms_ppcp_connect_callback
    let coopWarning = jQuery('#pms-paypal-connect-coop-warning');

    if ( coopWarning.length && window.fetch ) {
        let checkUrl = new URL( ajaxurl, window.location.href );
        checkUrl.searchParams.set( 'action', 'pms_ppcp_check_headers' );

        fetch( checkUrl, { credentials: 'same-origin', cache: 'no-store' } )
            .then(function (response) {
                // Browsers read the value as a case-sensitive token, so only this exact form severs the opener
                if ( /^same-origin(;|$)/.test( response.headers.get('Cross-Origin-Opener-Policy') || '' ) )
                    coopWarning.prop('hidden', false);
            })
            .catch(function () {});
    }

    jQuery('.pms-paypal-connect__disconnect-handler').click(function (e) {
        e.preventDefault()

        let pmsPaypalDisconnectPrompt = prompt('Are you sure you want to disconnect this website from Paypal? Payments will not be processed anymore. \nPlease type DISCONNECT in order to remove the PayPal connection:')

        if ( pmsPaypalDisconnectPrompt === 'DISCONNECT' ) {

            let ajaxNonce = jQuery(this).data('ajax-nonce');

            jQuery.post(ajaxurl, {
                action   : 'pms_ppcp_disconnect_paypal',
                ajaxNonce: ajaxNonce
            }, function (response) {
                try {
                    let data = JSON.parse(response);

                    if (data.success) {
                        location.reload();
                    }
                    else {
                        console.log('PayPal Disconnecting Error: ' + data.message);
                    }

                } catch (e) {
                    console.log('Unexpected response: ' + response);
                }

            });
        } else {
            return false
        }
    })

});