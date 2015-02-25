<?php
/**
 * Created by PhpStorm.
 * User: adibox
 * Date: 25/02/15
 * Time: 12:14
 */
namespace Test;

class YamlContainer extends \Model\Test {

    public function runTest()
    {
        $container = $this->getContainer();

        $loader = new \Symfony\Component\DependencyInjection\Loader\YamlFileLoader($container, new \Symfony\Component\Config\FileLocator(__DIR__));

        $loader->load("config/services.yml");
    }
}