<?php
/**
 * Widget detail options (modal, panel)
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class WidgetDetail implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'modal', 'label' => __('Modal')],
            ['value' => 'panel', 'label' => __('Panel')],
        ];
    }
}
