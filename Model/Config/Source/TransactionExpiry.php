<?php
/**
 * Transaction expiry options (minutes)
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class TransactionExpiry implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '0', 'label' => __('No expiry')],
            ['value' => '30', 'label' => __('30 minutes (default)')],
            ['value' => '60', 'label' => __('60 minutes')],
            ['value' => '120', 'label' => __('2 hours')],
            ['value' => '360', 'label' => __('6 hours')],
            ['value' => '1440', 'label' => __('24 hours')],
        ];
    }
}
