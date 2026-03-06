<?php
/**
 * Widget logo position options (right, left)
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class WidgetLogoPosition implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'right', 'label' => __('Right')],
            ['value' => 'left', 'label' => __('Left')],
        ];
    }
}
