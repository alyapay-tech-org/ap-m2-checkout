<?php
/**
 * Widget language options
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class WidgetLang implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'fr', 'label' => __('French')],
            ['value' => 'en', 'label' => __('English')],
            ['value' => 'ar', 'label' => __('Arabic')],
            ['value' => 'auto', 'label' => __('Auto (from store locale)')],
        ];
    }
}
