<?php
/**
 * Created by PhpStorm.
 * User: darkilliant
 * Date: 3/3/15
 * Time: 9:01 AM
 */

namespace Test;


use Model\Test;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;

class ClosureContainer extends Test {
    public function runTest()
    {
        // TODO: Implement runTest() method.

        $loader = new ClosureLoader($this->container);

        $loader->load(function($container)
        {
            (new PhpContainer($container))->runTest();
        });
    }

}