<?php
/**
 * Created by PhpStorm.
 * User: adibox
 * Date: 25/02/15
 * Time: 10:37
 */
namespace Service;

use lib\ColorConsole;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

class logger extends AbstractLogger {

    private $colorConsole;

    public function __construct()
    {
        $this->colorConsole = new ColorConsole();

        $this->log(LogLevel::INFO, "logged inited");
    }

    private function getColorFromLevel($level)
    {
        switch($level)
        {
            case LogLevel::ERROR:
                return "red";
            break;

            case LogLevel::INFO:
                return "blue";
            break;
        }

        return "white";
    }

    public function log($level, $message, array $context = array())
    {
        echo $this->colorConsole->getColoredString("[$level] $message ", $this->getColorFromLevel($level)).PHP_EOL;
    }
}