/**
 * Redirect-on-success mixin: Redirect to AlyaPay when AlyaPay was used.
 */
define([
    'mage/url',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/full-screen-loader'
], function (url, quote, fullScreenLoader) {
    'use strict';

    return function (redirectOnSuccess) {
        return {
            execute: function () {
                var redirectUrl = redirectOnSuccess.redirectUrl;
                var alyapayRedirectUrl = window.__alyapayPendingRedirect;
                var paymentMethod = quote.paymentMethod();
                var alyapayConfig = window.checkoutConfig && window.checkoutConfig.payment && window.checkoutConfig.payment.alyapay;

                if (alyapayRedirectUrl) {
                    redirectUrl = alyapayRedirectUrl;
                    delete window.__alyapayPendingRedirect;
                } else if (paymentMethod && paymentMethod.method === 'alyapay' && alyapayConfig && alyapayConfig.defaultRedirectUrl) {
                    redirectUrl = alyapayConfig.defaultRedirectUrl;
                }

                fullScreenLoader.startLoader();
                window.location.replace(url.build(redirectUrl));
            }
        };
    };
});
