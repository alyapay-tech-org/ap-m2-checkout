/**
 * Place-order mixin: Store redirect URL when AlyaPay is used.
 */
define([], function () {
    'use strict';

    return function (placeOrderAction) {
        return function (paymentData, messageContainer) {
            window.__alyapayPendingRedirect = null;
            if (paymentData && paymentData.method === 'alyapay') {
                var config = window.checkoutConfig && window.checkoutConfig.payment && window.checkoutConfig.payment.alyapay;
                if (config && config.defaultRedirectUrl) {
                    window.__alyapayPendingRedirect = config.defaultRedirectUrl;
                }
            }
            return placeOrderAction(paymentData, messageContainer);
        };
    };
});
