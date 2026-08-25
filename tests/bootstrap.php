<?php

require dirname(__DIR__) . '/vendor/autoload.php';

if (!function_exists('__')) {
    function __(string $message, string $domain = 'glpi'): string
    {
        return $message;
    }
}
