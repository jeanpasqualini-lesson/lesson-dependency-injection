<?php

namespace tests;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;

class ContainerTest extends \PHPUnit_Framework_TestCase
{
    public function load($configs, $expected = null)
    {
        $containersDumped = array();

        foreach ($configs as $loader => $config)
        {
            $containerBuilder = new ContainerBuilder();
            $phpDumper = new PhpDumper($containerBuilder);

            $containerDumped = null;
            switch ($loader) {
                case 'closure':
                    $closureLoader = new ClosureLoader($containerBuilder);
                    $closureLoader->load($config);
                    $containerDumped = $phpDumper->dump();
            }

            if (null !== $containerDumped) {
                $containersDumped[$loader] = $containerDumped;
                $this->assertEquals($containerDumped, $expected);
            }
        }
    }

    public function test()
    {
        $closureConfig = function(ContainerBuilder $builder)
        {

        };

        $this->load(
            array(
                'closure' => $closureConfig
            )
        );
    }
}