<?php
/**
 * Conditionally adds Alya widget script when widget is enabled
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Block\Checkout;

use AlyaPay\Payment\Model\Config;
use Magento\Framework\View\Element\Template;

class WidgetScript extends Template
{
    private const SCRIPT_URL = 'https://cdn.alyapay.com/js/alya-placement.js';

    /**
     * @var Config
     */
    private $config;

    /**
     * @param Template\Context $context
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml()
    {
        if (!$this->config->isAnyWidgetEnabled()) {
            return '';
        }
        return '<script src="' . self::SCRIPT_URL . '" defer></script>' . "\n";
    }
}
