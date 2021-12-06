<?php

namespace TeraBlaze\Validation;

use TeraBlaze\Container\Container;
use TeraBlaze\Support\StringMethods;
use TeraBlaze\Validation\Exception\RuleException;
use TeraBlaze\Validation\Exception\ValidationException;
use TeraBlaze\Validation\Rule\RuleInterface;

class Validator implements ValidatorInterface
{
    /** @var RuleInterface[] */
    protected array $rules = [];

    private Container $container;

    public static $rulesNamespaces = [];

    public static $sessionName = "validation_errors";

    public static $throwException = false;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function addRule(string $alias, RuleInterface $rule): self
    {
        $this->rules[$alias] = $rule;
        return $this;
    }

    public function validate(array $data, array $rules, array $messages = []): array
    {
        $sessionName = static::$sessionName;
        $errors = [];

        foreach ($rules as $field => $rulesForField) {
            if (is_string($rulesForField)) {
                $rulesForField = explode("|", $rulesForField);
            }
            $bail = reset($rulesForField) == "bail";
            if ($bail) {
                array_shift($rulesForField);
            }
            foreach ($rulesForField as $rule) {
                $name = $rule;
                $params = [];

                if (is_string($rule)) {
                    if (str_contains($rule, ':')) {
                        [$name, $params] = explode(':', $rule);
                        $params = explode(',', $params);
                    }
                }

                $processor = $this->getRule($name);
                $this->addRule($name, $processor);

                if (!$processor->validate($data[$field], $field, $params)) {
                    if (!isset($errors[$field])) {
                        $errors[$field] = [];
                    }

                    $errors[$field][$name] = $messages[$field][$name]
                        ?? $processor->getMessage($data[$field], $field, $params);
                    if ($bail) {
                        break;
                    }
                }
            }
        }

        if (count($errors)) {
            $exception = new ValidationException();
            $exception->setErrors($errors);
            $exception->setSessionName($sessionName);
            if (static::$throwException) {
                throw $exception;
            }
            if (request()->hasFlash()) {
                flash()->flashNow($sessionName, $errors);
            }
        } else {
            if (request()->hasFlash()) {
                flash()->getFlash($sessionName);
            }
        }

        return [
            'data' => array_intersect_key($data, $rules),
            'errors' => $errors,
        ];
    }

    private function getRule($rule): RuleInterface
    {
        if ($rule instanceof RuleInterface) {
            return $this->rules[get_class($rule)] = $rule;
        }
        if (array_key_exists($rule, $this->rules)) {
            return $this->rules[$rule];
        }
        if (is_string($rule)) {
            $ruleClasses[] = $rule;
            $ruleClasses[] = ucfirst($rule);
            $ruleClasses[] = StringMethods::studly("{$rule}_rule");
            $ruleClasses[] = "{$rule}Rule";
            $ruleClasses[] = ucfirst($rule) . "Rule";
            
            foreach (self::$rulesNamespaces as $rulesNamespace) {
                $ruleClasses[] = $rulesNamespace . "\\$rule";
                $ruleClasses[] = $rulesNamespace . "\\" . ucfirst($rule);
                $ruleClasses[] = $rulesNamespace . "\\" . StringMethods::studly("{$rule}_rule");
                $ruleClasses[] = $rulesNamespace . "\\{$rule}Rule";
                $ruleClasses[] = $rulesNamespace . "\\" . ucfirst($rule) . "Rule";
            }

            $ruleClasses = array_unique($ruleClasses);

            foreach ($ruleClasses as $ruleClass) {
                if (class_exists($ruleClass) && is_a($ruleClass, RuleInterface::class, true)) {
                    return $this->container->make($ruleClass);
                }
            }
        }

        $ruleString = is_object($rule) ? get_class($rule) : (string) $rule;
        throw new RuleException(sprintf('Validation rule: %s not found', $ruleString));
    }
}
