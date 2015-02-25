<?php
/**
 * Created by PhpStorm.
 * User: adibox
 * Date: 25/02/15
 * Time: 10:37
 */
namespace service;

use Psr\Log\AbstractLogger;

class logger extends AbstractLogger {
    public function __construct()
    {
        echo "logger inited ".PHP_EOL;
    }

    public function log($level, $message, array $context = array())
    {
        echo "[$level] $message ".PHP_EOL;
    }
}