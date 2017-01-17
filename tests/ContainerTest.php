<?php

namespace tests;

use ProxyManager\Proxy\LazyLoadingInterface;
use Symfony\Bridge\ProxyManager\LazyProxy\PhpDumper\ProxyDumper;
use Symfony\Component\DependencyInjection\Compiler\AutoAliasServicePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;
use tests\Loader\StringLoader;
use Symfony\Component\DependencyInjection\Container;

class ContainerTest extends \PHPUnit_Framework_TestCase
{
    /** @var Container */
    protected $container;
    /** @var ContainerBuilder */
    protected $containerBuilder;

    protected $lastError;
    protected $expectedError;

    public function setUp()
    {
        $this->expectedError = null;
        $this->lastError = null;
    }

    public function tearDown()
    {
        restore_error_handler();
    }

    protected function registerErrorHandler()
    {
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            $this->lastError = $errno;
            if (null !== $this->expectedError) {
                $this->assertEquals($this->expectedError, $this->lastError);
            }
        });
    }

    public function load($configs, $assert = true)
    {
        $containersDumped = array();

        $previousContainerDumped = null;
        $previousContainerType = null;
        $containerBuilder = null;
        foreach ($configs as $loader => $config)
        {
            $containerBuilder = new ContainerBuilder();
            $containerBuilder->getCompilerPassConfig()->addPass(new AutoAliasServicePass());
            $phpDumper = new PhpDumper($containerBuilder);
            $phpDumper->setProxyDumper(new ProxyDumper());

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

            if (null !== $containerDumped && null != $previousContainerDumped && true === $assert) {
                $containersDumped[$loader] = $containerDumped;
                $this->assertEquals(
                    $previousContainerDumped,
                    $containerDumped,
                    $previousContainerType. ' vs '.$loader
                );
            }

            $previousContainerDumped = $containerDumped;
            $previousContainerType = $loader;
        }

        if (null !== $previousContainerDumped)
        {
            if (strpos($previousContainerDumped, 'class FixtureDefineLazyServiceMain') !== false) {
                file_put_contents(
                    sys_get_temp_dir().'/container.php',
                    str_replace(
                        array(
                            'class ProjectServiceContainer',
                            'class FixtureDefineLazyServiceMain'
                        ),
                        array(
                            '$container = new Class',
                            '; class FixtureDefineLazyServiceMain'
                        ),
                        $previousContainerDumped.PHP_EOL.'return $container;'
                    )
                );
            } else {
                file_put_contents(
                    sys_get_temp_dir().'/container.php',
                    str_replace('class ProjectServiceContainer', 'return new Class', $previousContainerDumped. ';')
                );
            }

            $this->container = require sys_get_temp_dir().'/container.php';
            $this->containerBuilder = $containerBuilder;
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

        $closureConfig = function(ContainerBuilder $builder) {
            $builder->register('logger', 'Fixture\Logger');

            $fixture = new Definition('Fixture\AutoWireWithClassExist');
            $fixture->setAutowired(true);
            $builder->setDefinition('fixture', $fixture);
        };

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml'  => $xmlConfig,
            'closure' => $closureConfig
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

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="fixture" class="Fixture\AutoWireWithClassNotExist" autowire="true"/>
    </services>
</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
            $fixture = new Definition('Fixture\AutoWireWithClassNotExist');
            $fixture->setAutowired(true);
            $builder->setDefinition('fixture', $fixture);
        };

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml' => $xmlConfig,
            'closure' => $closureConfig
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

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {

        };

        $this->load(array(
            'yaml' => $yamlConfig
        ));
    }

    // For service decorators (see above), if the definition does not modify the deprecated status,
    // it will inherit the status from the definition that is decorated.
    // The ability to "un-deprecate" a service is possible only when declaring the definition in PHP.
    public function testDefineServiceAsDeprecated()
    {
        $this->registerErrorHandler();

        $context = <<<EVBUFFER_EOF
namespace Fixture { 
    class DefineServiceAsDeprecated { }
};
EVBUFFER_EOF;

        eval(str_replace('#', '$', $context));

        $yamlConfig = <<<EVBUFFER_EOF
services:
  fixture:
    class: Fixture\DefineServiceAsDeprecated
    deprecated: The "%service_id%" is deprecated since 2.8 and will be removed in 10
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="fixture" class="Fixture\DefineServiceAsDeprecated">
            <deprecated>The "%service_id%" is deprecated since 2.8 and will be removed in 10</deprecated>
        </service>
    </services>

</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
            $fixture = new Definition('Fixture\DefineServiceAsDeprecated');
            $fixture->setDeprecated(
                $status = true,
                $template = 'The "%service_id%" is deprecated since 2.8 and will be removed in 10'
            );
            $builder->setDefinition('fixture', $fixture);
        };

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml' => $xmlConfig,
            'closure' => $closureConfig
        ));

        $this->container->get('fixture');
        $this->assertEquals(E_USER_DEPRECATED, $this->lastError);
    }

    public function testArgumentNull()
    {
        $yamlConfig = <<<EVBUFFER_EOF
services:
  main:
    class: stdClass
    arguments: [null]
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="main" class="stdClass">
        </service>
    </services>

</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
        };

        $this->load(array(
            'yaml' => $yamlConfig,
           // 'xml' => $xmlConfig
        ));

        $this->assertEquals(array(null), $this->containerBuilder->getDefinition('main')->getArguments());
    }

    public function testArgumentArray()
    {
        $yamlConfig = <<<EVBUFFER_EOF
services:
  main:
    class: stdClass
    arguments: 
    # First argument is array(array('name' => john'))
      - 
        - {name: john}
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="main" class="stdClass">
            <argument type="collection">
                <argument type="collection">
                    <argument key="name">john</argument>
                </argument>
            </argument>
        </service>
    </services>

</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
            $builder->setDefinition('main', new Definition(\stdClass::class, array(
                array(
                    array(
                        'name' => 'john'
                    )
                )
            )));
        };

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml' => $xmlConfig,
            'closure' => $closureConfig
        ));

        $this->assertEquals(array(
            array(array('name' => 'john'))
        ), $this->containerBuilder->getDefinition('main')->getArguments());
    }

    public function testArgumentConstant()
    {
        define('Fixture/ArgumentConstant/Constant', 'Value');

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="main" class="stdClass">
            <argument type="constant">Fixture/ArgumentConstant/Constant</argument>
        </service>
    </services>

</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
           $builder->setDefinition('main', new Definition(\stdClass::class, array(
                constant('Fixture/ArgumentConstant/Constant')
           )));
        };

        $this->load(array(
            'xml' => $xmlConfig,
            'closure' => $closureConfig
        ));
    }

    public function testDefineServiceDependencies()
    {
        $context = <<<EVBUFFER_EOF
namespace Fixture\DefineServiceDependencies { 
    class Main { public function __construct(Logger #logger) { } }
    class Logger {}
};
EVBUFFER_EOF;

        eval(str_replace('#', '$', $context));

        $yamlConfig = <<<EVBUFFER_EOF
services:
  logger:
    class: Fixture\DefineServiceDependencies\Logger
  fixture:
    class: Fixture\DefineServiceDependencies\Main
    arguments: ['@logger']
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="logger" class="Fixture\DefineServiceDependencies\Logger" />
        <service id="fixture" class="Fixture\DefineServiceDependencies\Main">
            <argument type="service" id="logger"/>
        </service>
    </services>

</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
            $builder->register('logger', 'Fixture\DefineServiceDependencies\Logger');

            $fixture = new Definition('Fixture\DefineServiceDependencies\Main', array(
                new Reference('logger')
            ));
            $builder->setDefinition('fixture', $fixture);
        };

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml' => $xmlConfig,
            'closure' => $closureConfig
        ));
    }

    public function testDefineParameterDependencies()
    {
        $context = <<<EVBUFFER_EOF
namespace Fixture\DefineParameterDependencies { 
    class Main { public function __construct(#rootDir) { } }
};
EVBUFFER_EOF;

        eval(str_replace('#', '$', $context));

        $yamlConfig = <<<EVBUFFER_EOF
parameters:
  kernel.root_dir: /root
services:
  fixture:
    class: Fixture\DefineParameterDependencies\Main
    arguments: [%kernel.root_dir%]
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <parameters>
        <parameter key="kernel.root_dir">/root</parameter>
    </parameters>
    
    <services>
        <service id="fixture" class="Fixture\DefineParameterDependencies\Main">
            <argument>%kernel.root_dir%</argument>
        </service>
    </services>    
</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
            $builder->setParameter('kernel.root_dir', '/root');

            $builder->setDefinition('fixture', new Definition('Fixture\DefineParameterDependencies\Main', array(
                '%kernel.root_dir%'
            )));
        };

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml' => $xmlConfig,
            'closure' => $closureConfig,
        ));
    }

    public function testDefineLazyService()
    {
        $context = <<<EVBUFFER_EOF
namespace Fixture\DefineLazyService {
    class Main { public function __construct() {} }
}
EVBUFFER_EOF;

        eval(str_replace('#', '$', $context));

        $yamlConfig = <<<EVBUFFER_EOF
services:
  fixture:
    class: Fixture\DefineLazyService\Main
    lazy: true
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
    <services>
        <service id="fixture" class="Fixture\DefineLazyService\Main" lazy="true"/>
    </services>
</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
            $fixture = new Definition('Fixture\DefineLazyService\Main');
            $fixture->setLazy(true);
            $builder->setDefinition('fixture', $fixture);
        };

        $this->load(array(
            'yaml' => $yamlConfig,
            'closure' => $closureConfig
        ), $assert = false);

        $this->assertInstanceOf(LazyLoadingInterface::class, $this->container->get('fixture'));
    }

    public function testRequireFile()
    {
        $context = <<<EVBUFFER_EOF
namespace Fixture\RequireFile {
    class Main { public function __construct() {} }
}
EVBUFFER_EOF;

        eval(str_replace('#', '$', $context));

        $yamlConfig = <<<EVBUFFER_EOF
services:
  fixture:
    class: Fixture\RequireFile\Main
    file:  lib/colorConsole.php
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
    
    <services>
        <service id="fixture" class="Fixture\RequireFile\Main">
            <file>lib/colorConsole.php</file>
            <file>lib/colorConsoless.php</file>
        </service>
    </services>
</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
            $fixture = new Definition('Fixture\RequireFile\Main');
            $fixture->setFile('lib/colorConsole.php');

            $builder->setDefinition('fixture', $fixture);
        };

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml' => $xmlConfig,
            'closure' => $closureConfig,
        ));
    }

    public function testInjectByConstructor()
    {
        $context = <<<EVBUFFER_EOF
namespace Fixture\InjectByConstructor {
    class Logger { }
    class Main { public function __construct() {} }
}
EVBUFFER_EOF;

        eval(str_replace('#', '$', $context));

        $yamlConfig = <<<EVBUFFER_EOF
services:
  logger:
    class: Fixture\InjectByConstructor\Logger
  main:
    class: Fixture\InjectByConstructor\Main
    arguments: ['@logger']
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="logger" class="Fixture\InjectByConstructor\Logger" />
        <service id="main" class="Fixture\InjectByConstructor\Main">
            <argument type="service" id="logger" />
        </service>
    </services>
</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
            $builder->register('logger', 'Fixture\InjectByConstructor\Logger');

            $main = new Definition('Fixture\InjectByConstructor\Main', array(
                new Reference('logger')
            ));
            $builder->setDefinition('main', $main);
        };

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml' => $xmlConfig,
            'closure' => $closureConfig
        ));
    }

    public function testInjectBySetter()
    {
        $context = <<<EVBUFFER_EOF
namespace Fixture\InjectBySetter {
    class Logger { }
    class Main { public function setLogger(Logger #logger) { } }
}
EVBUFFER_EOF;

        eval(str_replace('#', '$', $context));

        $yamlConfig = <<<EVBUFFER_EOF
services:
  logger:
    class: Fixture\InjectBySetter\Logger
  main:
    class: Fixture\InjectBySetter\Main
    calls: 
      - ['setLogger', ['@logger']]
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
    <services>
        <service id="logger" class="Fixture\InjectBySetter\Logger" />
        <service id="main" class="Fixture\InjectBySetter\Main">
            <call method="setLogger">
                <argument type="service" id="logger" />
            </call>
        </service>
    </services>
</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
            $builder->register('logger', 'Fixture\InjectBySetter\Logger');

            $main = new Definition('Fixture\InjectBySetter\Main');
            $main->addMethodCall('setLogger', array(new Reference('logger')));

            $builder->setDefinition('main', $main);
        };

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml' => $xmlConfig,
            'closure' => $closureConfig
        ));
    }

    public function testInjectByProperty()
    {
        $context = <<<EVBUFFER_EOF
namespace Fixture\InjectByProperty {
    class Logger {}
    class Main { protected #logger; }
}
EVBUFFER_EOF;

        eval(str_replace('#', '$', $context));

        $yamlConfig = <<<EVBUFFER_EOF
services:
  logger:
    class: Fixture\InjectByProperty\Logger
  fixture:
    class: Fixture\InjectByProperty\Main
    properties:
      logger: "@logger"
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="logger" class="Fixture\InjectByProperty\Logger" />
        <service id="fixture" class="Fixture\InjectByProperty\Main">
            <property name="logger" type="service" id="logger" />
        </service>
    </services>
</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
            $builder->register('logger', 'Fixture\InjectByProperty\Logger');

            $fixture = new Definition('Fixture\InjectByProperty\Main');
            $fixture->setProperty('logger', new Reference('logger'));

            $builder->setDefinition('fixture', $fixture);
        };

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml' => $xmlConfig,
            'closure' => $closureConfig,
        ));
    }

    public function testConfigurator()
    {
        $context = <<<EVBUFFER_EOF
namespace Fixture\Configurator {
    class Configurator { public function configure(Main #main) {} }
    class Main { }
}
EVBUFFER_EOF;

        eval(str_replace('#', '$', $context));

        $yamlConfig = <<<EVBUFFER_EOF
services:
  configurator:
    class: Fixture\Configurator\Configurator
  main:
    class: Fixture\Configurator\Main
    configurator: ['@configurator', 'configure']
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
    <services>
        <service id="configurator" class="Fixture\Configurator\Configurator" />
        <service id="main" class="Fixture\Configurator\Main">
            <configurator service="configurator" method="configure" />
        </service>
    </services>
</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
            $builder->register('configurator', 'Fixture\Configurator\Configurator');

            $main = new Definition('Fixture\Configurator\Main');
            $main->setConfigurator(array(new Reference('configurator'), 'configure'));
            $builder->setDefinition('main', $main);
        };

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml' => $xmlConfig,
            'closure' => $closureConfig
        ));

        $this->assertInstanceOf('Fixture\Configurator\Main', $this->container->get('main'));
    }

    public function testFactory()
    {
        $context = <<<EVBUFFER_EOF
namespace Fixture\Factory {
    class Factory {
        public function staticFactory()
        {
            return new \stdClass();
        }
        
        public function factory()
        {
            return new \stdClass();
        }
    }
    class Logger {}
    class Main { protected #logger; }
}
EVBUFFER_EOF;

        $yamlConfig = <<<EVBUFFER_EOF
services:
  factory:
    class: Fixture\Factory\Factory
  use_static_factory_by_string:
    class: stdClass
    factory: 'Fixture\Factory\Factory::staticFactory'
  use_service_factory_by_string:
    class: stdClass
    factory: 'factory:factory'
  use_static_factory_by_array:
    class: stdClass
    factory: ['Fixture\Factory\Factory', staticFactory]
  use_service_factory_by_array:
    class: stdClass
    factory: ['@factory', factory]
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="factory" class="Fixture\Factory\Factory" />
        <service id="use_static_factory_by_string" class="stdClass">
            <factory class="Fixture\Factory\Factory" method="staticFactory"/>
        </service>    
        <service id="use_service_factory_by_string" class="stdClass">
            <factory service="factory" method="factory"/>
        </service>
        <service id="use_static_factory_by_array" class="stdClass">
            <factory class="Fixture\Factory\Factory" method="staticFactory"/>
        </service>    
        <service id="use_service_factory_by_array" class="stdClass">
            <factory service="factory" method="factory"/>
        </service>
    </services>
</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
            $builder->register('factory', 'Fixture\Factory\Factory');

            $useStaticFactoryByString = new Definition(\stdClass::class);
            $useStaticFactoryByString->setFactory('Fixture\Factory\Factory::staticFactory');
            $builder->setDefinition('use_static_factory_by_string', $useStaticFactoryByString);

            $useServiceFactoryByString = new Definition(\stdClass::class);
            $useServiceFactoryByString->setFactory(array(new Reference('factory'), 'factory'));
            $builder->setDefinition('use_service_factory_by_string', $useServiceFactoryByString);

            $useStaticFactoryByArray = new Definition(\stdClass::class);
            $useStaticFactoryByArray->setFactory(array('Fixture\Factory\Factory', 'staticFactory'));
            $builder->setDefinition('use_static_factory_by_array', $useStaticFactoryByArray);

            $useServiceFactoryByArray = new Definition(\stdClass::class);
            $useServiceFactoryByArray->setFactory(array(new Reference('factory'), 'factory'));
            $builder->setDefinition('use_service_factory_by_array', $useServiceFactoryByArray);
        };

        eval(str_replace('#', '$', $context));

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml' => $xmlConfig,
            'closure' => $closureConfig,
        ));
    }

    public function testExpression()
    {
        $yamlConfig = <<<EVBUFFER_EOF
services:
  logger: 
    class: stdClass
  fixture:
    class: Test
    arguments: ["@=container.get('logger')"]
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
    <services>
        <service id="logger" class="stdClass" />
        <service id="fixture" class="Test">
            <argument type="expression">container.get('logger')</argument>
        </service>    
    </services>    
</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
            $builder->register('logger', \stdClass::class);

            $fixture = new Definition('Test');
            $fixture->setArguments(array(
                 new Expression('container.get("logger")')
            ));
            $builder->setDefinition('fixture', $fixture);
        };

        // Do not throw error if dependency is not exist, only on runtime

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml' => $xmlConfig,
            'closure' => $closureConfig
        ));
    }

    public function testSynthetic()
    {
        $yamlConfig = <<<EVBUFFER_EOF
services:
  fixture:
    synthetic: true
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
    <services>
        <service id="fixture" synthetic="true" />
    </services>    
</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
            $fixture = new Definition();
            $fixture->setSynthetic(true);

            $builder->setDefinition('fixture', $fixture);
        };

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml' => $xmlConfig,
            'closure' => $closureConfig
        ));

        $this->container->set('fixture', new \stdClass());
    }

    public function testAlias()
    {
        $yamlConfig = <<<EVBUFFER_EOF
services:
  logger.email:
    class: stdClass
  logger:
    alias: logger.email
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="logger.email" class="stdClass" />
        <service id="logger" alias="logger.email" />
    </services>    
</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
            $builder->register('logger.email', \stdClass::class);

            $builder->setAlias('logger', 'logger.email');
        };

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml' => $xmlConfig,
            'closure' => $closureConfig,
        ));
    }

    // Attention ce n'est pas cablé par défaut et donc pas vraiment disponible dans symfony même si c'est dans la doc
    // Sa à l'air de faire la même chose que alias et sa resemble à une grosse blague plutot qu'autre chose
    public function testAutoAlias()
    {
        $yamlConfig = <<<EVBUFFER_EOF
services:
  logger.email:
    class: stdClass
  logger:
    tags:
        - { name: auto_alias, format: logger.email }
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="logger.email" class="stdClass" />
        <service id="logger">
            <tag name="auto_alias" format="logger.email" />
        </service>    
    </services>
</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
            $builder->register('logger.email', \stdClass::class);

            $logger = new Definition();
            $logger->addTag('auto_alias', array(
                'format' => 'logger.email'
            ));

            $builder->setDefinition('logger', $logger);
        };

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml' => $xmlConfig,
            'closure' => $closureConfig
        ));

        $this->assertInstanceOf(\stdClass::class, $this->container->get('logger'));
    }

    public function testExtraKeys()
    {
        $yamlConfig = <<<EVBUFFER_EOF
services:
  logger.email:
    class: stdClass
    machin: true
EVBUFFER_EOF;

        // Unable to add extra keys in xml
        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="logger.email" class="stdClass"/>
    </services>    
</container>
EVBUFFER_EOF;

        // Unable to add extra keys in php
        $closureConfig = function(ContainerBuilder $builder) {
            $builder->setDefinition('logger.email', new Definition(\stdClass::class));
        };

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml' => $xmlConfig,
            'closure' => $closureConfig,
        ));
    }

    // The shared, abstract and tags attributes are not inherited from parent services.
    public function testParent()
    {
        $yamlConfig = <<<EVBUFFER_EOF
services:
  logger:
    class: stdClass
  parent:
    calls:
        - [setLogger, ['@logger']]
    class: stdClass
  children:
    parent: parent
    class: stdClass
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
    
    <services>
        <service id="logger" class="stdClass" />
        <service id="parent" class="stdClass">
            <call method="setLogger">
                <argument type="service" id="logger" />
            </call>
        </service>
        <service id="children" class="stdClass" parent="parent" />
    </services>        
</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder)
        {
             $builder->register('logger', \stdClass::class);

             $parent = new Definition();
             $parent->addMethodCall('setLogger', array(new Reference('logger')));
             $parent->setClass(\stdClass::class);
             $builder->setDefinition('parent', $parent);

             $children = new DefinitionDecorator('parent');
             $children->setClass(\stdClass::class);
             $builder->setDefinition('children', $children);
        };

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml' => $xmlConfig,
            'closure' => $closureConfig
        ));

        $childrenDefintion = $this->containerBuilder->getDefinition('children');

        $this->assertEquals(array(array('setLogger', array('logger'))), $childrenDefintion->getMethodCalls());
    }

    public function testParentWithAddOneArgumentAndReplaceOneArgument()
    {
        $yamlConfig = <<<EVBUFFER_EOF
services:
  logger:
    class: stdClass
  parent:
    arguments: ['one', 'two']
    class: stdClass
  children:
    parent: parent
    class: stdClass
    arguments: 
        1: three
        index_0: one_replaced
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
    
    <services>
        <service id="logger" class="stdClass" />
        <service id="parent" class="stdClass">
            <argument>one</argument>
            <argument>two</argument>
        </service>
        <service id="children" class="stdClass" parent="parent">
            <argument key="1">three</argument>
            <argument key="index_0">one_replaced</argument>
        </service>
    </services>
</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
            $builder->register('logger', \stdClass::class);

            $parent = new Definition(\stdClass::class, array(
                'one',
                'two'
            ));
            $builder->setDefinition('parent', $parent);

            $children = new DefinitionDecorator('parent');
            $children->setClass(\stdClass::class);
            $children->setArguments(array(
                1 => 'three',
                'index_0' => 'one_replaced'
            ));
            $builder->setDefinition('children', $children);
        };

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml' => $xmlConfig,
            'closure' => $closureConfig,
        ));

        $childrenDefintion = $this->containerBuilder->getDefinition('children');

        $this->assertInstanceOf(Definition::class, $childrenDefintion);
        $this->assertEquals(array('one_replaced', 'two', 'three'), $childrenDefintion->getArguments());
    }

    public function testAbstract()
    {
        $yamlConfig = <<<EVBUFFER_EOF
services:
  parent:
    abstract: true
    class: stdClass
    arguments: ['one']
  children:
    parent: parent
    class: stdClass
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="parent" abstract="true" class="stdClass">
            <argument>one</argument>
        </service>
        <service id="children" parent="parent" class="stdClass" />
    </services>    
</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
            $parent = new Definition(\stdClass::class, array('one'));
            $parent->setAbstract(true);
            $builder->setDefinition('parent', $parent);

            $children = new DefinitionDecorator('parent');
            $children->setClass(\stdClass::class);
            $builder->setDefinition('children', $children);
        };

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml' => $xmlConfig,
            'closure' => $closureConfig
        ));

        $this->assertFalse($this->container->has('parent'));
        $this->assertEquals(array('one'), $this->containerBuilder->getDefinition('children')->getArguments());
    }

    public function testAutowiringType()
    {
        $context = <<<EVBUFFER_EOF
namespace Fixture\AutowiringType { 
    interface TransformerInterface { }
    class Rot13Transformer implements TransformerInterface { }
    class UppercaseTransformer implements TransformerInterface { }
    class TwitterClient {
        public function __construct(TransformerInterface #transformer) { }
    }
};
EVBUFFER_EOF;

        eval(str_replace('#', '$', $context));

        $yamlConfig = <<<EVBUFFER_EOF
services:
  rot13_transformer:
    class: Fixture\AutowiringType\Rot13Transformer
    autowiring_types: Fixture\AutowiringType\TransformerInterface
  uppercase_transformer:
    class: Fixture\AutowiringType\UppercaseTransformer
  twitter_client:
    class: Fixture\AutowiringType\TwitterClient
    autowire: true
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="rot13_transformer" class="Fixture\AutowiringType\Rot13Transformer">
            <autowiring-type>Fixture\AutowiringType\TransformerInterface</autowiring-type>
        </service>
        <service id="uppercase_transformer" class="Fixture\AutowiringType\UppercaseTransformer" />
        <service id="twitter_client" class="Fixture\AutowiringType\TwitterClient" autowire="true" />
    </services>    
</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
            $rot13transformer = new Definition('Fixture\AutowiringType\Rot13Transformer');
            $rot13transformer->addAutowiringType('Fixture\AutowiringType\TransformerInterface');
            $builder->setDefinition('rot13_transformer', $rot13transformer);

            $builder->register('uppercase_transformer', 'Fixture\AutowiringType\UppercaseTransformer');

            $twitterClient = new Definition('Fixture\AutowiringType\TwitterClient');
            $twitterClient->setAutowired(true);
            $builder->setDefinition('twitter_client', $twitterClient);
        };

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml' => $xmlConfig,
            'closure' => $closureConfig
        ));
    }

    public function testNotShared()
    {
        $yamlConfig = <<<EVBUFFER_EOF
services:
  fixture:
    class: stdClass
    shared: false
EVBUFFER_EOF;

        $xmlConfig = <<<EVBUFFER_EOF
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="fixture" class="stdClass" shared="false" />
    </services>    
</container>
EVBUFFER_EOF;

        $closureConfig = function(ContainerBuilder $builder) {
            $fixture = new Definition(\stdClass::class);
            $fixture->setShared(false);
            $builder->setDefinition('fixture', $fixture);
        };

        $this->load(array(
            'yaml' => $yamlConfig,
            'xml' => $xmlConfig,
            'closure' => $closureConfig
        ));

        $this->assertNotSame($this->container->get('fixture'), $this->container->get('fixture'));
    }
}