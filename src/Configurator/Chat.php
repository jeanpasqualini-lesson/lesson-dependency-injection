<?php
/**
 * Created by PhpStorm.
 * User: adibox
 * Date: 25/02/15
 * Time: 11:46
 */

namespace Configurator;


use Psr\Log\LoggerInterface;

class Chat {
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function configure(\service\Chat $chat)
    {
        $chat->setLogger($this->logger);
    }
}