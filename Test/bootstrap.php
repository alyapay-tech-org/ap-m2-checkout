<?php
/**
 * PHPUnit bootstrap for AlyaPay Payment module
 * Provides minimal stub for Magento __() when running standalone
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!function_exists('__')) {
    function __($text, ...$params)
    {
        foreach ($params as $i => $param) {
            $text = str_replace('%' . ($i + 1), (string) $param, $text);
        }
        return (string) $text;
    }
}

// Module is at app/code/AlyaPay/Payment; vendor is at project root (5 levels up from Test)
$autoload = dirname(__DIR__, 5) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    $loader = require $autoload;
} else {
    $loader = require dirname(__DIR__) . '/vendor/autoload.php';
}
// Ensure module is autoloadable when in app/code (not installed via composer)
$loader->addPsr4('AlyaPay\\Payment\\', dirname(__DIR__) . '/');
