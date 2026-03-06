<?php
/**
 * Backend model for amount_min/amount_max - read-only, ignores save attempts.
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model\Config\Backend;

use Magento\Framework\App\Config\Value;

class AmountLimit extends Value
{
    private const DEFAULTS = [
        'payment/alyapay/amount_min' => 500,
        'payment/alyapay/amount_max' => 15000,
    ];

    /**
     * @inheritdoc
     * Force value to default - field is read-only, ignore any change.
     */
    public function beforeSave(): AmountLimit
    {
        $path = $this->getPath();
        $default = self::DEFAULTS[$path] ?? null;
        if ($default !== null) {
            $this->setValue((string) $default);
        }
        return parent::beforeSave();
    }
}
