<?php
/**
 * Created by PhpStorm.
 * User: adibox
 * Date: 25/02/15
 * Time: 12:14
 */
namespace Test;


use Symfony\Component\DependencyInjection\Reference;

class PhpContainer extends \Model\Test {

    public function runTest()
    {
        $container = $this->getContainer();

        $container->setParameter("logger.class", 'service\logger');
        $container->setParameter("chat.class", 'service\Chat');

        $container
            ->register("logger", '%logger.class%')
        ;

        $definitionChat = new \Symfony\Component\DependencyInjection\Definition();

        $definitionChat->setClass('service\Chat'); //Fonctionne aussi

        $definitionChat->setClass("%chat.class%");


        $definitionChat->addTag("chat");

        $definitionConstructor = clone $definitionChat;

        $definitionConstructor->setProperty("name", "constructor");

        $definitionConstructor->addArgument(new Reference("logger"));

        $container->setDefinition("definitionConstructor", $definitionConstructor);

        $definitionSetter = clone $definitionChat;

        $definitionSetter->setProperty("name", "setter");

        $definitionSetter->addMethodCall("setLogger", array(new Reference("logger")));

        $container->setDefinition("definitionSetter", $definitionSetter);

        $definitionProperty = clone $definitionChat;

        $definitionProperty->setProperty("name", "property");

        $definitionProperty->setProperty("logger", new Reference("logger"));

        $container->setDefinition("definitionProperty", $definitionProperty);

        $definitionConfigurator = clone $definitionChat;

        $definitionConfigurator->setProperty("name", "configurator");

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

        $definitionFactory->setProperty("name", "factory");

        /**
        $definitionFactory->setFactoryClass('Factory\Chat');

        $definitionFactory->setFactoryMethod('factory');
         */

        $definitionFactory->setFactory('Factory\Chat::factory');

        $definitionFactory->addArgument(new Reference("logger"));

        $container->setDefinition("definitionFactory", $definitionFactory);
    }
}