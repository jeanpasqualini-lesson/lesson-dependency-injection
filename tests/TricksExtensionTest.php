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
class TricksExtensionTest extends \PHPUnit_Framework_TestCase
{
    protected function buildContainer()
    {
        return new ContainerBuilder();
    }

    protected function prepareContainer(ContainerBuilder $containerBuilder)
    {
        $hackExtension  = new Class extends Extension {
            public function load(array $configs, ContainerBuilder $container)
            {
                $extension = new Class Extends Extension {
                    public function getAlias()
                    {
                        return 'first';
                    }
                    public function load(array $configs, ContainerBuilder $container)
                    {
                        // TODO: Implement load() method.
                    }
                };
                $container->registerExtension($extension);
                $container->loadFromExtension('first', array('cat_color' => 'purple'));
            }

            public function getAlias()
            {
                return 'hack';
            }
        };

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

        $containerBuilder->registerExtension($hackExtension);
        $containerBuilder->registerExtension($firstExtension);

        $containerBuilder->getCompilerPassConfig()->setMergePass($mergeExtensionPass);
    }


    public function testRegisterExtension()
    {
        $containerBuilder = $this->buildContainer();
        $this->prepareContainer($containerBuilder);

        $containerBuilder->compile();
        $this->assertEquals(array('cat' => 'purple'), $containerBuilder->getParameterBag()->all());
    }
}