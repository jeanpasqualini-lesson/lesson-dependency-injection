<?php
/**
 * Created by PhpStorm.
 * User: darkilliant
 * Date: 3/3/15
 * Time: 6:22 AM
 */

namespace Validator;


use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ContainerValidator extends ConstraintValidator {

    const DEFINITION_CONSTRUCTOR_INCORRECT = "definition constructor incorect";

    const DEFINITION_SETTER_INCORECT = "definition setter incorect";

    const DEFINITION_PROPERTY_INCORECT = "definition property incorect";

    const DEFINITION_CONFIGURATOR_INCORECT = "definction configurator incorect";

    const DEFINITION_FACTORY_INCORECT = "definition factory incorrect";

    const DEFINITION_LOGGER_INCORRECT = "definition logger incorect";

    const PARAMETER_INCORECT = "parameter incorrect";


    private function validateAllDefinition(Definition $definition, $violation)
    {
        if(!$definition->hasTag("chat"))
        {
            $this->context->addViolation($violation." : no tag chat");
        }
        else
        {
            $properties = $definition->getProperties();

            if(count($properties) < 1)
            {
                $this->context->addViolation($violation." : no propertie name setted");
            }
            else
            {
                $class = $definition->getClass();

                if($class != "%chat.class%")
                {
                    $this->context->addViolation($violation." : class is no %chat.class% ");
                }
            }
        }
    }

    private function testReference($value, $equalTo)
    {
        if(!is_object($value)) return false;
        if(!$value instanceof Reference) return false;
        if(!((string) $value) != $equalTo) return false;

        return true;
    }

    private function definitionHasPropertieReference(Definition $definition, $propertieName, $referenceName)
    {
        $properties = $definition->getProperties();

        foreach($properties as $key => $propertie)
        {
            if($key == $propertieName && $propertie instanceof Reference && ((string) $propertie) == $referenceName)
            {
                return true;
            }
        }

        return false;
    }

    private function definitionHasPropertieValue(Definition $definition, $propertieName, $propertieValue)
    {
        $properties = $definition->getProperties();

        foreach($properties as $key => $propertie)
        {
            if($key == $propertieName && $propertie == $propertieValue)
            {
                return true;
            }
        }

        return false;
    }

    private function definitionHasCall(Definition $definition, $method)
    {
        $calls = $definition->getMethodCalls();

        foreach($calls as $call)
        {
            if($call[0] == $method)
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

        if(count($arguments) < 1)
        {
            $this->context->addViolation($violation);
        }

        $this->validateAllDefinition($definition, $violation);
    }

    private function validateDefinitionSetter(Definition $definition, $violation = self::DEFINITION_SETTER_INCORECT)
    {
        $arguments = $definition->getArguments();

        $properties = $definition->getProperties();

        $calls  = $definition->getMethodCalls();

        if(count($calls) < 1)
        {
            $this->context->addViolation($violation." : no called setLogger");
        }
        else
        {
            if(!$this->definitionHasCall($definition, "setLogger"))
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

        if(count($properties) < 2)
        {
            $this->context->addViolation($violation." : no setted propertie logger or name");
        }

        if(!$this->definitionHasPropertieReference($definition, "logger", "logger"))
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

        if(
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

        $this->validateAllDefinition($definition, $violation. " : no configurator chat.configuration attached");
    }

    private function validateDefinitionFactory(Definition $definition, $violation = self::DEFINITION_FACTORY_INCORECT)
    {
        $arguments = $definition->getArguments();

        $properties = $definition->getProperties();

        $factory = $definition->getFactory();

        if($factory === null) $factory = array($definition->getFactoryClass(), $definition->getFactoryMethod());

        if($factory === null || $factory !== array("Factory\\Chat", "factory"))
        {
            $this->context->addViolation($violation);
        }

        $this->validateAllDefinition($definition, $violation." : no factory Factory\\Chat::factory attached");
    }

    private function validateDefinitionLogger(Definition $definition, $violation = self::DEFINITION_LOGGER_INCORRECT)
    {
        $class = $definition->getClass();

        if($class != "%logger.class%")
        {
            $this->context->addViolation($violation." : no class %logger.class% setted");
        }
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
        if(!$value->hasDefinition("definitionConstructor"))
        {
            $this->context->addViolation(self::DEFINITION_CONSTRUCTOR_INCORRECT);
        }
        else
        {
            $this->validateDefinitionConstructor($value->getDefinition("definitionConstructor"));
        }

        if(!$value->hasDefinition("definitionSetter"))
        {
            $this->context->addViolation(self::DEFINITION_SETTER_INCORECT);
        }
        else
        {
            $this->validateDefinitionSetter($value->getDefinition("definitionSetter"));
        }

        if(!$value->hasDefinition("definitionProperty"))
        {
            $this->context->addViolation(self::DEFINITION_PROPERTY_INCORECT);
        }
        else
        {
            $this->validateDefinitionProperty($value->getDefinition("definitionProperty"));
        }

        if(!$value->hasDefinition("definitionConfigurator"))
        {
            $this->context->addViolation(self::DEFINITION_CONFIGURATOR_INCORECT);
        }
        else
        {
            $this->validateDefinitionConfigurator($value->getDefinition("definitionConfigurator"));
        }

        if(!$value->hasDefinition("definitionFactory"))
        {
            $this->context->addViolation(self::DEFINITION_FACTORY_INCORECT);
        }
        else
        {
            $this->validateDefinitionFactory($value->getDefinition("definitionFactory"));
        }

        if(!$value->hasDefinition("logger"))
        {
            $this->context->addViolation(self::DEFINITION_LOGGER_INCORRECT);
        }
        else
        {
            $this->validateDefinitionLogger($value->getDefinition("logger"));
        }

        if(!$value->hasParameter("chat.class"))
        {
            $this->context->addViolation(self::PARAMETER_INCORECT. " : parameter chat.class not setted with value Service\\Chat");
        }
        elseif($value->getParameter("chat.class") != "Service\\Chat")
        {
            $this->context->addViolation(self::PARAMETER_INCORECT. " : parameter chat.class not setted with value Service\\Chat");
        }


        if(!$value->hasParameter("logger.class"))
        {
            $this->context->addViolation(self::PARAMETER_INCORECT. " : parameter logger.class not setted with value Service\\logger");
        }
        elseif($value->getParameter("logger.class") != "Service\\logger")
        {
            $this->context->addViolation(self::PARAMETER_INCORECT. " : parameter logger.class not setted with value Service\\logger");
        }

        // TODO: Implement validate() method.
    }

}