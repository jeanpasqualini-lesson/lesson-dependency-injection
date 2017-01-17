<?php

namespace tests;


/**
 * ErrorException
 *
 * @author Jean Pasqualini <jpasqualini75@gmail.com>
 * @package tests;
 */
class ErrorException
{
    protected $type;

    public function __construct($type)
    {
        $this->type = $type;
    }
}