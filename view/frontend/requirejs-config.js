/**
 * Mixins: Wire AlyaPay redirect into Magento checkout.
 */
var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/action/place-order': {
                'AlyaPay_Payment/js/action/place-order-mixin': true
            },
            'Magento_Checkout/js/action/redirect-on-success': {
                'AlyaPay_Payment/js/action/redirect-on-success-mixin': true
            }
        }
    }
};
