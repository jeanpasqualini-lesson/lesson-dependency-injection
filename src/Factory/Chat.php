<?php
/**
 * Created by PhpStorm.
 * User: adibox
 * Date: 25/02/15
 * Time: 12:00
 */

namespace Factory;


use Psr\Log\LoggerInterface;

class Chat {
    public function factory(LoggerInterface $logger)
    {
        $chat = new \Service\Chat($logger);

        return $chat;
    }
}