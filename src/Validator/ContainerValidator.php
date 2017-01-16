<?php
/**
 * Created by PhpStorm.
 * User: darkilliant
 * Date: 3/3/15
 * Time: 6:22 AM
 */

namespace Validator;


use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ContainerValidator extends ConstraintValidator {

    const DEFINITION_CONSTRUCTOR_INCORRECT = "definition constructor incorect";

    const DEFINITION_SETTER_INCORECT = "definition setter incorect";

    const DEFINITION_PROPERTY_INCORECT = "definition property incorect";

    const DEFINITION_CONFIGURATOR_INCORECT = "definction configurator incorect";

    const DEFINITION_FACTORY_INCORECT = "definition factory incorrect";

    const DEFINITION_LOGGER_INCORRECT = "definition logger incorect";

    const DEFINITION_LOGGERFILE_INCORECT = "definition loggerfile incorect";

    const DEFINITION_EXPRESSION_INCORECT = "definition expression incorect";

    const DEFINITION_SYNTHETIC_INCORECT = "definition synthetic incorect";

    const PARAMETER_INCORECT = "parameter incorrect";

    private $chats;

    /**
     * @param string $violation
     */
    private function validateAllDefinition(Definition $definition, $violation)
    {
        if (!$definition->hasTag("chat"))
        {
            $this->context->addViolation($violation." : no tag chat");
        } else
        {
                $class = $definition->getClass();

                if ($class != "%chat.class%")
                {
                    $this->context->addViolation($violation." : class is no %chat.class% ");
                }
        }
    }

    private function testReference($value, $equalTo)
    {
        if (!is_object($value)) return false;
        if (!$value instanceof Reference) return false;
        if (!((string) $value) != $equalTo) return false;

        return true;
    }

    /**
     * @param string $propertieName
     * @param string $referenceName
     */
    private function definitionHasPropertieReference(Definition $definition, $propertieName, $referenceName)
    {
        $properties = $definition->getProperties();

        foreach ($properties as $key => $propertie)
        {
            if ($key == $propertieName && $propertie instanceof Reference && ((string) $propertie) == $referenceName)
            {
                return true;
            }
        }

        return false;
    }

    private function definitionHasPropertieValue(Definition $definition, $propertieName, $propertieValue)
    {
        $properties = $definition->getProperties();

        foreach ($properties as $key => $propertie)
        {
            if ($key == $propertieName && $propertie == $propertieValue)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $method
     */
    private function definitionHasCall(Definition $definition, $method)
    {
        $calls = $definition->getMethodCalls();

        foreach ($calls as $call)
        {
            if ($call[0] == $method)
            {
                return true;
            }
        }

        return false;
    }


    private function validateDefinitionConstructor(Definition $definition, $violation = self::DEFINITION_CONSTRUCTOR_INCORRECT)
    {
        $arguments = $definition->getArguments();

        $properties = $definition->getProperties();

        if (count($arguments) < 1)
        {
            $this->context->addViolation($violation);
        }

        $this->validateAllDefinition($definition, $violation);
    }

    private function validateDefinitionSynthetic(Definition $definition, $violation = self::DEFINITION_SYNTHETIC_INCORECT)
    {
        if (!$definition->isSynthetic())
        {
            $this->context->addViolation($violation);
        }
    }

    private function validateDefinitionSetter(Definition $definition, $violation = self::DEFINITION_SETTER_INCORECT)
    {
        $arguments = $definition->getArguments();

        $properties = $definition->getProperties();

        $calls = $definition->getMethodCalls();

        if (count($calls) < 1)
        {
            $this->context->addViolation($violation." : no called setLogger");
        } else
        {
            if (!$this->definitionHasCall($definition, "setLogger"))
            {
                $this->context->addViolation($violation." : no called setLogger");
            }
        }

        $this->validateAllDefinition($definition, $violation);
    }

    private function validateDefinitionProperty(Definition $definition, $violation = self::DEFINITION_PROPERTY_INCORECT)
    {
        $arguments = $definition->getArguments();

        $properties = $definition->getProperties();

        if (count($properties) < 1)
        {
            $this->context->addViolation($violation." : no setted propertie logger or name");
        }

        if (!$this->definitionHasPropertieReference($definition, "logger", "logger"))
        {
            $this->context->addViolation($violation." : no setted propertie logger");
        }

        $this->validateAllDefinition($definition, $violation);
    }

    private function validateDefinitionConfigurator(Definition $definition, $violation = self::DEFINITION_CONFIGURATOR_INCORECT)
    {
        $arguments = $definition->getArguments();

        $properties = $definition->getProperties();

        $configurator = $definition->getConfigurator();

        if (
            $configurator === null
            ||
            !is_array($configurator)
            ||
            count($configurator) != 2
            ||
            !$configurator[0] instanceof Reference
            ||
            !is_string($configurator[1])
            ||
            ((string) $configurator[0]) != "chat.configurator"
            ||
            $configurator[1] != "configure"
        )
        {
            $this->context->addViolation($violation);
        }

        $this->validateAllDefinition($definition, $violation." : no configurator chat.configuration attached");
    }

    private function validateDefinitionFactory(Definition $definition, $violation = self::DEFINITION_FACTORY_INCORECT)
    {
        $arguments = $definition->getArguments();

        $properties = $definition->getProperties();

        $factory = $definition->getFactory();

        if ($factory === null) $factory = array($definition->getFactoryClass(), $definition->getFactoryMethod());

        if ($factory === null || $factory !== array("Factory\\Chat", "factory"))
        {
            $this->context->addViolation($violation);
        }

        $this->validateAllDefinition($definition, $violation." : no factory Factory\\Chat::factory attached");
    }

    private function validateAliasLogger(Alias $alias, $violation = self::DEFINITION_LOGGER_INCORRECT)
    {
        if ((string) $alias != "logger.file")
        {
            $this->context->addViolation($violation." : no alias setted");
        }
    }

    private function validateDefinitionLoggerFile(Definition $definition, $violation = self::DEFINITION_LOGGER_INCORRECT)
    {
        $class = $definition->getClass();

        $file = $definition->getFile();

        if ($class != "%logger.class%")
        {
            $this->context->addViolation($violation." : no class %logger.class% setted");
        }
        elseif ($file != "%kernel.root_dir%/lib/ColorConsole.php")
        {
            $this->context->addViolation($violation." : no file 'lib/ColorConsole.php' attached");
        }
    }

    private function validateDefinitionExpression(Definition $definition, $violation = self::DEFINITION_EXPRESSION_INCORECT)
    {
        $arguments = $definition->getArguments();

        if (count($arguments) != 1)
        {
            $this->context->addViolation($violation);
        } else
        {
            if (!$arguments[0] instanceof Expression || ((string) $arguments[0]) != "container.get('logger')")
            {
                $this->context->addViolation($violation);
            }
        }

    }


    private function getAvailablesChats()
    {
        return array(
            "constructor",
            "setter",
            "property",
            "configurator",
            "factory",
            "expression",
            "synthetic"
        );
    }

    private function hasChat($name)
    {
        return in_array($name, array_keys($this->chats));
    }

    /**
     * @param string $name
     *
     * @return Definition
     */
    private function getChat($name)
    {
        if (!$this->hasChat($name))
        {
            throw new \LogicException("chat $name not defined");
        }

        return $this->chats[$name];
    }

    private function setChats(ContainerBuilder $container)
    {
        $chats = array();

        foreach ($container->getDefinitions() as $key => $definition)
        {
            if ($definition->hasTag("chat"))
            {
                $tag = $definition->getTag("chat");

                if (count($tag) == 1 && isset($tag[0]["alias"]))
                {
                    $chats[$tag[0]["alias"]] = $definition;
                }
            }
        }

        $this->chats = $chats;
    }

    /**
     * Checks if the passed value is valid.
     *
     * @param ContainerBuilder $value The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     *
     * @api
     */
    public function validate($value, Constraint $constraint)
    {
        $this->setChats($value);

        if (!$this->hasChat("constructor"))
        {
            $this->context->addViolation(self::DEFINITION_CONSTRUCTOR_INCORRECT);
        } else
        {
            $this->validateDefinitionConstructor($this->getChat("constructor"));
        }

        if (!$this->hasChat("setter"))
        {
            $this->context->addViolation(self::DEFINITION_SETTER_INCORECT);
        } else
        {
            $this->validateDefinitionSetter($this->getChat("setter"));
        }

        if (!$this->hasChat("property"))
        {
            $this->context->addViolation(self::DEFINITION_PROPERTY_INCORECT);
        } else
        {
            $this->validateDefinitionProperty($this->getChat("property"));
        }

        if (!$this->hasChat("configurator"))
        {
            $this->context->addViolation(self::DEFINITION_CONFIGURATOR_INCORECT);
        } else
        {
            $this->validateDefinitionConfigurator($this->getChat("configurator"));
        }

        if (!$this->hasChat("factory"))
        {
            $this->context->addViolation(self::DEFINITION_FACTORY_INCORECT);
        } else
        {
            $this->validateDefinitionFactory($this->getChat("factory"));
        }

        if (!$this->hasChat("expression"))
        {
            $this->context->addViolation(self::DEFINITION_EXPRESSION_INCORECT);
        } else
        {
            $this->validateDefinitionExpression($this->getChat("expression"));
        }

        if (!$this->hasChat("synthetic"))
        {
            $this->context->addViolation(self::DEFINITION_SYNTHETIC_INCORECT);
        } else
        {
            $this->validateDefinitionSynthetic($this->getChat("synthetic"));
        }

        if (!$value->hasAlias("logger"))
        {
            $this->context->addViolation(self::DEFINITION_LOGGER_INCORRECT);
        } else
        {
            $this->validateAliasLogger($value->getAlias("logger"));
        }

        if (!$value->hasDefinition("logger.file"))
        {
            $this->context->addViolation(self::DEFINITION_LOGGERFILE_INCORECT);
        } else
        {
            $this->validateDefinitionLoggerFile($value->getDefinition("logger.file"));
        }

        if (!$value->hasParameter("chat.class"))
        {
            $this->context->addViolation(self::PARAMETER_INCORECT." : parameter chat.class not setted with value Service\\Chat");
        }
        elseif ($value->getParameter("chat.class") != "Service\\Chat")
        {
            $this->context->addViolation(self::PARAMETER_INCORECT." : parameter chat.class not setted with value Service\\Chat");
        }


        if (!$value->hasParameter("logger.class"))
        {
            $this->context->addViolation(self::PARAMETER_INCORECT." : parameter logger.class not setted with value Service\\logger");
        }
        elseif ($value->getParameter("logger.class") != "Service\\logger")
        {
            $this->context->addViolation(self::PARAMETER_INCORECT." : parameter logger.class not setted with value Service\\logger");
        }

        if (!$value->hasParameter("root"))
        {
            $this->context->addViolation(self::PARAMETER_INCORECT." : parameter root not setted with ".ROOT_DIRECTORY);
        }
        elseif ($value->getParameter("root") != ROOT_DIRECTORY)
        {
            $this->context->addViolation(self::PARAMETER_INCORECT." : parameter root not setted with ".ROOT_DIRECTORY);
        }

        // TODO: Implement validate() method.
    }

}