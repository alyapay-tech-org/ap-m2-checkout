<?php
/**
 * Widget theme options
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class WidgetTheme implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'light', 'label' => __('Light')],
            ['value' => 'light-plain', 'label' => __('Light (Plain)')],
            ['value' => 'dark', 'label' => __('Dark')],
            ['value' => 'dark-plain', 'label' => __('Dark (Plain)')],
            ['value' => 'neutral', 'label' => __('Neutral')],
            ['value' => 'neutral-plain', 'label' => __('Neutral (Plain)')],
        ];
    }
}
