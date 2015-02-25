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
    new \Test\YamlContainer(clone $container)
);

foreach($tests as $test)
{
    echo "Test ::: ".get_class($test).PHP_EOL;

    /** @var \Model\Test $test */

    $containerTest = $test->getContainer();

    foreach($containerTest->findTaggedServiceIds("chat") as $id => $attributes)
    {
        $container->get($id)->test();
    }
}

//$container->compile();

$dumper = new PhpDumper($container);

file_put_contents(__DIR__.DIRECTORY_SEPARATOR."container.php", $dumper->dump());
