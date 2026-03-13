/**
 * Custom binding: creates alya-placement element with attributes set BEFORE DOM attachment
 * Fixes timing issue where AlyaPay custom element reads attributes before Knockout's attr binding runs
 */
define([
    'ko',
    'Magento_Checkout/js/model/quote'
], function (ko, quote) {
    'use strict';

    function createPlacementElement(config) {
        var el = document.createElement('alya-placement');
        el.setAttribute('key', 'checkout');
        el.setAttribute('price', config.price);
        el.setAttribute('currency', config.currency);
        el.setAttribute('lang', config.lang);
        el.setAttribute('installments', '4');
        el.setAttribute('theme', config.theme);
        el.setAttribute('variant', config.variant);
        el.setAttribute('detail', config.detail);
        el.setAttribute('logo-position', config.logoPosition);
        return el;
    }

    function getTotals() {
        try {
            var getTotals = quote.getTotals;
            if (typeof getTotals === 'function') {
                var totals = getTotals()();
                if (totals && totals.grand_total !== undefined) {
                    return parseFloat(totals.grand_total).toFixed(2);
                }
            }
        } catch (e) {}
        return '0.00';
    }

    function getWidgetConfig() {
        var w = window.checkoutConfig && window.checkoutConfig.payment && window.checkoutConfig.payment.alyapay && window.checkoutConfig.payment.alyapay.widget;
        if (!w) return null;
        var locale = (window.checkoutConfig.storeLocale || 'en_US');
        var lang = locale.indexOf('fr') === 0 ? 'fr' : (locale.indexOf('ar') === 0 ? 'ar' : 'en');
        return {
            price: getTotals(),
            currency: w.currency || 'MAD',
            lang: lang,
            theme: w.theme || 'light',
            variant: w.variant || 'default',
            detail: w.detail || 'modal',
            logoPosition: w.logo_position || 'right'
        };
    }

    function attachPlacement(containerEl, config) {
        var placementEl = createPlacementElement(config);
        containerEl.appendChild(placementEl);

        try {
            var totalsFn = quote.getTotals;
            if (typeof totalsFn === 'function') {
                var computed = totalsFn();
                if (computed && typeof computed.subscribe === 'function') {
                    computed.subscribe(function () {
                        placementEl.setAttribute('price', getTotals());
                    });
                }
            }
        } catch (e) {}
    }

    ko.bindingHandlers.alyapayPlacement = {
        init: function (element, valueAccessor, allBindings, viewModel, bindingContext) {
            var config = getWidgetConfig();
            if (!config) return;

            if (typeof customElements !== 'undefined' && customElements.get('alya-placement')) {
                attachPlacement(element, config);
            } else if (typeof customElements !== 'undefined' && typeof customElements.whenDefined === 'function') {
                customElements.whenDefined('alya-placement').then(function () {
                    attachPlacement(element, config);
                });
            } else {
                attachPlacement(element, config);
            }
        }
    };
});
