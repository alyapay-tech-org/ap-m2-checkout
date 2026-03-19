define([
    'ko',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'AlyaPay_Payment/js/binding/alyapay-placement'
], function (ko, Component, quote) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'AlyaPay_Payment/payment/alyapay',
            redirectAfterPlaceOrder: true
        },

        isPlaceOrderActionAllowed: ko.observable(true),

        getCode: function () {
            return 'alyapay';
        },

        getTitle: function () {
            return window.checkoutConfig.payment.alyapay ? (window.checkoutConfig.payment.alyapay.title || 'AlyaPay') : 'AlyaPay';
        },

        isWidgetEnabled: function () {
            return window.checkoutConfig.payment.alyapay &&
                window.checkoutConfig.payment.alyapay.widget &&
                window.checkoutConfig.payment.alyapay.widget.enabled === true;
        },

        getWidgetPrice: function () {
            try {
                var getTotals = quote.getTotals;
                if (typeof getTotals !== 'function') return '0.00';
                var totals = getTotals()();
                if (totals && totals.grand_total !== undefined) {
                    return parseFloat(totals.grand_total).toFixed(2);
                }
            } catch (e) {}
            return '0.00';
        },

        getWidgetCurrency: function () {
            return window.checkoutConfig.payment.alyapay &&
                window.checkoutConfig.payment.alyapay.widget &&
                window.checkoutConfig.payment.alyapay.widget.currency
                ? window.checkoutConfig.payment.alyapay.widget.currency
                : 'MAD';
        },

        getWidgetTheme: function () {
            return window.checkoutConfig.payment.alyapay &&
                window.checkoutConfig.payment.alyapay.widget &&
                window.checkoutConfig.payment.alyapay.widget.theme
                ? window.checkoutConfig.payment.alyapay.widget.theme
                : 'light';
        },

        getWidgetVariant: function () {
            return window.checkoutConfig.payment.alyapay &&
                window.checkoutConfig.payment.alyapay.widget &&
                window.checkoutConfig.payment.alyapay.widget.variant
                ? window.checkoutConfig.payment.alyapay.widget.variant
                : 'default';
        },

        getWidgetDetail: function () {
            return window.checkoutConfig.payment.alyapay &&
                window.checkoutConfig.payment.alyapay.widget &&
                window.checkoutConfig.payment.alyapay.widget.detail
                ? window.checkoutConfig.payment.alyapay.widget.detail
                : 'modal';
        },

        getWidgetLogoPosition: function () {
            return window.checkoutConfig.payment.alyapay &&
                window.checkoutConfig.payment.alyapay.widget &&
                window.checkoutConfig.payment.alyapay.widget.logo_position
                ? window.checkoutConfig.payment.alyapay.widget.logo_position
                : 'right';
        },

        getWidgetLang: function () {
            var w = window.checkoutConfig && window.checkoutConfig.payment && window.checkoutConfig.payment.alyapay && window.checkoutConfig.payment.alyapay.widget;
            if (w && w.lang && w.lang.length) return w.lang;
            var locale = window.checkoutConfig.storeLocale || 'en_US';
            var lang = locale.indexOf('_') > 0 ? locale.substring(0, locale.indexOf('_')) : locale;
            return (lang === 'fr' || lang === 'ar') ? lang : 'en';
        }
    });
});
