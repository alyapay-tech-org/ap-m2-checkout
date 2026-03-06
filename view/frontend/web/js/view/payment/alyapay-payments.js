/**
 * Registers AlyaPay payment method renderer
 */
define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push({
        type: 'alyapay',
        component: 'AlyaPay_Payment/js/view/payment/method-renderer/alyapay'
    });

    return Component.extend({});
});
