<?php

namespace tests;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use tests\Loader\StringLoader;

class ContainerTest extends \PHPUnit_Framework_TestCase
{
    public function load($configs)
    {
        $containersDumped = array();

        $previousContainerDumped = null;
        $previousContainerType = null;
        foreach ($configs as $loader => $config)
        {
            $containerBuilder = new ContainerBuilder();
            $phpDumper = new PhpDumper($containerBuilder);

            $containerDumped = null;
            switch ($loader) {
                case 'closure':
                    $closureLoader = new ClosureLoader($containerBuilder);
                    $closureLoader->load($config);
                    $containerBuilder->compile();
                    $containerDumped = $phpDumper->dump();
                    break;
                case 'yaml':
                case 'xml':
                    $stringLoader = new StringLoader($containerBuilder);
                    $stringLoader->load($config, $loader);
                    $containerBuilder->compile();
                    $containerDumped = $phpDumper->dump();
                    break;
            }

            if (null !== $containerDumped && null != $previousContainerDumped) {
                $containersDumped[$loader] = $containerDumped;
                $this->assertEquals($containerDumped, $previousContainerDumped, $previousContainerType. ' vs '.$loader);
            }

            $previousContainerDumped = $containerDumped;
            $previousContainerType = $loader;
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

    public function testAutoWireWithClassExist()
    {
        $this->setName(
            __METHOD__.
            PHP_EOL.'One dependency declared on php and on services'.
            PHP_EOL.'One dependency declared on php and not on services'
        );

        $context = <<<EVBUFFER_EOF
namespace Fixture { 
    class AutoWireWithClassExist { public function __construct(Logger #logger, Router #router) {} } 
    class Logger {} 
    class Router {}
};
EVBUFFER_EOF;

        eval(str_replace('#', '$', $context));

        $yamlConfig = <<<EVBUFFER_EOF
services:
  logger:
    class: Fixture\Logger
  fixture:
    class: Fixture\AutoWireWithClassExist
    autowire: true
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
    
    <services>
        <service id="logger" class="Fixture\Logger"/>
        <service id="fixture" class="Fixture\AutoWireWithClassExist" autowire="true">
        </service>
    </services>
</container>
EVBUFFER_EOF;


        $this->load(array(
            'yaml' => $yamlConfig,
            'xml'  => $xmlConfig,
        ));
    }

    // Error on compile
    public function testAutoWireWithClassNotExist()
    {
        $this->setExpectedException(RuntimeException::class);

        $context = <<<EVBUFFER_EOF
namespace Fixture { 
    class AutoWireWithClassNotExist { public function __construct(ClassNotExist #classNotExist) {} }
};
EVBUFFER_EOF;

        eval(str_replace('#', '$', $context));

        $yamlConfig = <<<EVBUFFER_EOF
services:
  fixture:
    class: Fixture\AutoWireWithClassNotExist
    autowire: true
EVBUFFER_EOF;


        $this->load(array(
            'yaml' => $yamlConfig,
        ));
    }

    // Not error on compile
    public function testDefineServiceWithClassNotExist()
    {
        $yamlConfig = <<<EVBUFFER_EOF
services:
  fixture:
    class: NotExist
EVBUFFER_EOF;

        $this->load(array(
            'yaml' => $yamlConfig
        ));
    }
}