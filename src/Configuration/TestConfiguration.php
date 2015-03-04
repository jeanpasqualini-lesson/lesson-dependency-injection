<?php
/**
 * Created by PhpStorm.
 * User: darkilliant
 * Date: 3/4/15
 * Time: 8:44 AM
 */

namespace Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class TestConfiguration implements \Symfony\Component\Config\Definition\ConfigurationInterface {
    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();

        $rootNode = $treeBuilder->root("testextension");

        $rootNode
            ->children()
                ->scalarNode("foo")
                    ->isRequired()
                ->end()
                ->scalarNode("bar")
                    ->isRequired()
                ->end()
            ->end()
            ;

        return $treeBuilder;

        // TODO: Implement getConfigTreeBuilder() method.
    }


}