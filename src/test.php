<?php
/**
 * Created by PhpStorm.
 * User: adibox
 * Date: 25/02/15
 * Time: 10:30
 */
require "../vendor/autoload.php";

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

$container = new ContainerBuilder();

$tests = array(
    new \Test\PhpContainer(clone $container),
    new \Test\YamlContainer(clone $container),
    new \Test\XmlContainer(clone $container)
);

$validator = \Symfony\Component\Validator\Validation::createValidator();

$logger = new \Service\logger();

foreach($tests as $test)
{
    $class = new ReflectionClass($test);

    echo "Test ::: ".$class->getShortName().PHP_EOL;

    /** @var \Model\Test $test */

    $test->runTest();

    $containerTest = $test->getContainer();

    foreach($containerTest->findTaggedServiceIds("chat") as $id => $attributes)
    {
        $containerTest->get($id)->test();

        $dumper = new PhpDumper($containerTest);
    }

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

    file_put_contents(__DIR__.DIRECTORY_SEPARATOR."Dumped".DIRECTORY_SEPARATOR.$class->getShortName().".php", $dumper->dump());
}

