<?php
/**
 * Backend model for transaction_expiry - validates 0-60
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class TransactionExpiry extends Value
{
    private const MIN = 0;
    private const MAX = 60;

    /**
     * @inheritdoc
     */
    public function beforeSave(): TransactionExpiry
    {
        $value = (int) $this->getValue();
        if ($value < self::MIN || $value > self::MAX) {
            throw new LocalizedException(
                __('Transaction Expiry must be between %1 and %2 minutes.', self::MIN, self::MAX)
            );
        }
        return parent::beforeSave();
    }
}
