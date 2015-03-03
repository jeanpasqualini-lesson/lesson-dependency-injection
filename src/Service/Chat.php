<?php
/**
 * Created by PhpStorm.
 * User: adibox
 * Date: 25/02/15
 * Time: 11:09
 */

namespace Service;

use Model\Animal;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class Chat implements Animal {

    public $name = "unknow";

    public $logger;

    public function __construct($logger = null)
    {
        $this->setLogger($logger);
    }

    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    public function test()
    {
        if($this->logger === null) throw new \Exception("logger is not setted");

        $this->logger->log(LogLevel::DEBUG, "Le chat $this->name fonctionne");
    }
}