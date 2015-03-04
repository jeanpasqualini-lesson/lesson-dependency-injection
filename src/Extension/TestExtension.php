<?php
/**
 * Created by PhpStorm.
 * User: darkilliant
 * Date: 3/4/15
 * Time: 8:02 AM
 */

namespace Extension;


use Configuration\TestConfiguration;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class TestExtension extends Extension {
    /**
     * Loads a specific configuration.
     *
     * @param array $config An array of configuration values
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @throws \InvalidArgumentException When provided tag is not defined in this extension
     *
     * @api
     */
    public function load(array $config, ContainerBuilder $container)
    {
        $configuration = new TestConfiguration();

        $processor = new Processor();

        $processor->processConfiguration($configuration, $config);
        // TODO: Implement load() method.
    }


    public function getAlias()
    {
        return "testextension";
    }
}