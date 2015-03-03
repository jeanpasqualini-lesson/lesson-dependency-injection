<?php
/**
 * Created by PhpStorm.
 * User: darkilliant
 * Date: 3/3/15
 * Time: 4:40 AM
 */

namespace Test;


use Model\Test;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class XmlContainer extends Test {

    public function runTest()
    {
        // TODO: Implement runTest() method.

        $container = $this->getContainer();

        $loader = new XmlFileLoader($container, new FileLocator(ROOT_DIRECTORY));

        $loader->load("config/services.xml");
    }

}