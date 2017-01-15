<?php
/**
 * Created by PhpStorm.
 * User: adibox
 * Date: 25/02/15
 * Time: 12:14
 */
namespace Test;


use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\ExpressionLanguage\Expression;

class PhpContainer extends \Model\Test {

    public function runTest()
    {
        /** @var ContainerBuilder $container */
        $container = $this->getContainer();

        $container->setParameter("logger.class", 'Service\logger');
        $container->setParameter("chat.class", 'Service\Chat');
        $container->setParameter("root", ROOT_DIRECTORY);

        $container
            ->register("logger.file", '%logger.class%')
            ->setFile('%kernel.root_dir%/lib/ColorConsole.php')
        ;

        $container->setAlias("logger", "logger.file");

        $definitionChat = new \Symfony\Component\DependencyInjection\Definition();

        $definitionChat->setClass('Service\Chat'); //Fonctionne aussi

        $definitionChat->setClass("%chat.class%");

        $definitionConstructor = clone $definitionChat;

        $definitionConstructor->addTag("chat", array("alias" => "constructor"));

        $definitionConstructor->addArgument(new Reference("logger"));

        $container->setDefinition("definitionConstructor", $definitionConstructor);

        $definitionSetter = clone $definitionChat;

        $definitionSetter->addTag("chat", array("alias" => "setter"));

        $definitionSetter->addMethodCall("setLogger", array(new Reference("logger")));

        $container->setDefinition("definitionSetter", $definitionSetter);

        $definitionProperty = clone $definitionChat;

        $definitionProperty->addTag("chat", array("alias" => "property"));

        $definitionProperty->setProperty("logger", new Reference("logger"));

        $container->setDefinition("definitionProperty", $definitionProperty);

        $definitionConfigurator = clone $definitionChat;

        $definitionConfigurator->addTag("chat", array("alias" => "configurator"));

// Register configurator with register short
        $container
            ->register("chat.configurator", 'Configurator\Chat')
            ->addArgument(new Reference("logger"))
        ;

        $definitionConfigurator->setConfigurator(array(
            new Reference("chat.configurator"),
            "configure"
        ));

        $container->setDefinition("definitionConfigurator", $definitionConfigurator);

        $definitionFactory = clone $definitionChat;

        $definitionFactory->addTag("chat", array("alias" => "factory"));

        /**
        $definitionFactory->setFactoryClass('Factory\Chat');

        $definitionFactory->setFactoryMethod('factory');
         */

        $definitionFactory->setFactory('Factory\Chat::factory');

        $definitionFactory->addArgument(new Reference("logger"));

        $container->setDefinition("definitionFactory", $definitionFactory);

        $definitionExpression = clone $definitionChat;

        $definitionExpression->addTag("chat", array("alias" => "expression"));
        
        $definitionExpression->setClass("%chat.class%");

        $definitionExpression->addArgument(new Expression("container.get('logger')"));

        $container->setDefinition("definitionExpression", $definitionExpression);

        $definitionSynthetic = new Definition();

        $definitionSynthetic->setSynthetic(true);

        $definitionSynthetic->addTag("chat", array("alias" => "synthetic"));

        $container->setDefinition("definitionSynthetic", $definitionSynthetic);

        $container->loadFromExtension("testextension", array(
            "foo" => 1,
            "bar" => 1
        ));
    }
}