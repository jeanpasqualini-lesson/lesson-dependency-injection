<?php

namespace tests;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * ExtensionTests
 *
 * @author Jean Pasqualini <jpasqualini75@gmail.com>
 * @package tests;
 */
class ExtensionTest extends \PHPUnit_Framework_TestCase
{
    protected function buildContainer()
    {
        return new ContainerBuilder();
    }

    protected function prepareContainer(ContainerBuilder $containerBuilder)
    {
        $firstExtension = new Class extends Extension {
            public function load(array $configs, ContainerBuilder $container)
            {
                $configuration = new Class implements ConfigurationInterface {
                    public function getConfigTreeBuilder()
                    {
                        $treeBuilder = new TreeBuilder();

                        $root = $treeBuilder->root('rara');

                        $root
                            ->children()
                            ->scalarNode('cat_color')->isRequired()->end()
                            ->end();

                        return $treeBuilder;
                    }
                };

                $config = $this->processConfiguration($configuration, $configs);

                $container->setParameter('cat', $config['cat_color']);
            }

            public function getAlias()
            {
                return 'first';
            }
        };

        $secondExtension = new Class extends Extension {
            public function load(array $configs, ContainerBuilder $container)
            {

            }

            public function getAlias()
            {
                return 'second';
            }
        };

        $mergeExtensionPass = new Class extends MergeExtensionConfigurationPass {
            public function process(ContainerBuilder $container)
            {
                foreach($container->getExtensions() as $extension)
                {
                    $container->loadFromExtension($extension->getAlias(), array());
                }

                parent::process($container);
            }
        };

        $containerBuilder->registerExtension($firstExtension);
        $containerBuilder->registerExtension($secondExtension);

        $containerBuilder->getCompilerPassConfig()->setMergePass($mergeExtensionPass);
    }


    public function testRegisterExtension()
    {
        $containerBuilder = $this->buildContainer();
        $this->prepareContainer($containerBuilder);

        $containerBuilder->loadFromExtension('first', array('cat_color' => 'green'));

        $containerBuilder->compile();
        $this->assertEquals(array('cat' => 'green'), $containerBuilder->getParameterBag()->all());
    }
}