<?php

namespace tests;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use tests\Loader\StringLoader;

class ContainerTest extends \PHPUnit_Framework_TestCase
{
    public function load($configs)
    {
        $containersDumped = array();

        $previousContainerDumped = null;
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
                    break;
                case 'yaml':
                case 'xml':
                    $stringLoader = new StringLoader($containerBuilder);
                    $stringLoader->load($config, $loader);
                    $containerDumped = $phpDumper->dump();
                    break;
            }

            if (null !== $containerDumped && null != $previousContainerDumped) {
                $containersDumped[$loader] = $containerDumped;
                $this->assertEquals($containerDumped, $previousContainerDumped);
            }

            $previousContainerDumped = $containerDumped;
        }
    }

    public function testParameters()
    {
        $closureConfig = function(ContainerBuilder $builder)
        {
            $builder->setParameter('cat', 'red');
        };

        $yamlConfig = <<<EVBUFFER_EOF
parameters:
  cat: red
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <parameters>
        <parameter key="cat">red</parameter>
    </parameters>
</container>
EVBUFFER_EOF;


        $this->load(
            array(
                'closure'   => $closureConfig,
                'yaml'      => $yamlConfig,
                'xml'       => $xmlConfig
            )
        );
    }
}