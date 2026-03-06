<?php
/**
 * Widget variant options (default, interactive)
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class WidgetVariant implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'default', 'label' => __('Default')],
            ['value' => 'interactive', 'label' => __('Interactive')],
        ];
    }
}
