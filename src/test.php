<?php
/**
 * Created by PhpStorm.
 * User: adibox
 * Date: 25/02/15
 * Time: 10:30
 */
require __DIR__."/../vendor/autoload.php";

use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\ContainerBuilder;

define("ROOT_DIRECTORY", __DIR__);

spl_autoload_register(function($classname)
{
   $path = __DIR__.DIRECTORY_SEPARATOR.str_replace("\\", DIRECTORY_SEPARATOR, $classname).".php";

   if(!file_exists($path)) throw new Exception("class $classname not exist in $path");

   require_once($path);
});

$chats = array();

//$container = new ContainerBuilder();

//$container->setProxyInstantiator(new \Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator());

// Le fait de cloner le container instancier pose un problème avec les parameters qui se retrouve partagé

$tests = array(
    new \Test\PhpContainer(new ContainerBuilder()),
    new \Test\YamlContainer(new ContainerBuilder()),
    new \Test\XmlContainer(new ContainerBuilder()),
    new \Test\ClosureContainer(new ContainerBuilder())
);

$validator = \Symfony\Component\Validator\Validation::createValidator();

$logger = new \Service\logger();

foreach($tests as $test)
{
    $class = new ReflectionClass($test);

    echo "Test ::: ".$class->getShortName().PHP_EOL;

    $containerTest = $test->getContainer();
    $containerTest->setParameter('kernel.root_dir', __DIR__);

    $testExtension = new \Extension\TestExtension();

    /** @var ContainerBuilder $containerTest */
    $containerTest->registerExtension($testExtension);

    /** @var \Model\Test $test */

    $test->runTest();

    //$containerTest->loadFromExtension($testExtension->getAlias());

    $errors = $validator->validate($containerTest, new \Validator\Container());

    if($errors->count() > 0)
    {
        foreach($errors as $error)
        {
            $logger->log(\Psr\Log\LogLevel::ERROR, $error->getMessage());
        }
    }
    else
    {
        $logger->log(\Psr\Log\LogLevel::INFO, $class->getShortName()." test passed [SUCCESS]");
    }

    foreach($containerTest->findTaggedServiceIds("chat") as $id => $attributes)
    {
        if(count($attributes) == 1 && isset($attributes[0]["alias"]))
        {
            if($attributes[0]["alias"] == "synthetic")
            {
                $containerTest->set($id, new \Service\Chat($containerTest->get("logger")));
            }

            $chat = $containerTest->get($id);

            $chat->name = $attributes[0]["alias"];

            $chat->test();
        }
    }

    $containerTest->compile();

    $dumper = new PhpDumper($containerTest);

    $dumper->setProxyDumper(new \Symfony\Bridge\ProxyManager\LazyProxy\PhpDumper\ProxyDumper());

    file_put_contents(__DIR__.'/../'.$class->getShortName().".php", $dumper->dump());
}

