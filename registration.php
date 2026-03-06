<?php
/**
 * AlyaPay Payment Module for Magento 2
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'AlyaPay_Payment',
    __DIR__
);
