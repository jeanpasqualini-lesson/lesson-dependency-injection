<?php

namespace tests;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * ExtensionTests
 *
 * @author Jean Pasqualini <jpasqualini75@gmail.com>
 * @package tests;
 */
class MergeContainerTest extends \PHPUnit_Framework_TestCase
{
    public function testMerge()
    {
        $mainContainer = new ContainerBuilder();

        $firstContainer = new ContainerBuilder();
        $firstContainer->setParameter('cat', array('red'));
        $firstContainer->setParameter('dog', array('yellow'));

        $secondContainer = new ContainerBuilder();
        $secondContainer->setParameter('cat', array('blue'));

        $mainContainer->merge($firstContainer);
        $mainContainer->merge($secondContainer);

        $this->assertEquals(array('cat' => array('blue'), 'dog' => array('yellow')), $mainContainer->getParameterBag()->all());
    }
}