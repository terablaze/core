<?php

namespace TeraBlaze\Validation;

use ReflectionException;
use TeraBlaze\Container\Container;
use TeraBlaze\Support\ArrayMethods;
use TeraBlaze\Support\StringMethods;
use TeraBlaze\Translation\Translator;
use TeraBlaze\Validation\Exception\RuleException;
use TeraBlaze\Validation\Exception\ValidationException;
use TeraBlaze\Validation\Rule\Builder\RuleBuilderInterface;
use TeraBlaze\Validation\Rule\RuleInterface;

class Validation implements ValidationInterface
{
    /** @var string[] $rulesNamespaces */
    public static array $rulesNamespaces = ['TeraBlaze\Validation\Rule'];
    public static bool $throwException = true;

    private ?Translator $translator = null;
    private Container $container;

    /** @var RuleInterface[] */
    protected array $resolvedRules = [];

    /** @var array<string, mixed> $data */
    private array $data;
    /** @var array<string, mixed> $rules */
    private array $rules;
    /** @var array<string, mixed> $customMessages */
    private array $customMessages;
    /** @var string[] $customAttributes */
    private array $customAttributes;

    private string $dotPlaceholder;

    /** @var callable[] $after */
    protected array $after = [];

    protected array $validated = [];
    protected array $failed = [];
    protected array $errors = [];

    /**
     * @param Translator $translator
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rules
     * @param array<string, mixed> $customMessages
     * @param array<string, string> $customAttributes
     */
    public function __construct(
        array $data,
        array $rules,
        array $customMessages = [],
        array $customAttributes = []
    )
    {
        $this->dotPlaceholder = StringMethods::random();

        $this->data = $this->parseData($data);
        $this->data = $this->parseData($data);
        $this->rules = $rules;
        $this->customMessages = $customMessages;
        $this->customAttributes = $customAttributes;
        $this->validated = $this->data;
    }

    public function setTranslator(Translator $translator): self
    {
        $this->translator = $translator;
        return $this;
    }

    public function setContainer(Container $container): self
    {
        $this->container = $container;
        return $this;
    }

    /**
     * Parse the data array, converting dots and asterisks.
     *
     * @param array $data
     * @return array
     */
    public function parseData(array $data)
    {
        $newData = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = $this->parseData($value);
            }

            $key = str_replace(
                ['.', '*'],
                [$this->dotPlaceholder, '__asterisk__'],
                $key
            );

            $newData[$key] = $value;
        }

        return $newData;
    }

    /**
     * Replace the placeholders used in data keys.
     *
     * @param array $data
     * @return array
     */
    protected function replacePlaceholders($data)
    {
        $originalData = [];

        foreach ($data as $key => $value) {
            $originalData[$this->replacePlaceholderInString($key)] = is_array($value)
                ? $this->replacePlaceholders($value)
                : $value;
        }

        return $originalData;
    }

    /**
     * Replace the placeholders in the given string.
     *
     * @param string $value
     * @return string
     */
    protected function replacePlaceholderInString(string $value)
    {
        return str_replace(
            [$this->dotPlaceholder, '__asterisk__'],
            ['.', '*'],
            $value
        );
    }

    public function validate(): array
    {
        foreach ($this->rules as $field => $rulesForField) {
            if (is_string($rulesForField)) {
                $rulesForField = explode("|", $rulesForField);
            }
            $bail = reset($rulesForField) == "bail";
            if ($bail) {
                array_shift($rulesForField);
            }
            foreach ($rulesForField as $rule) {
                if (is_a($rule, RuleBuilderInterface::class)) {
                    $rule = $rule->__toString();
                }
                $name = $rule;
                $params = [];

                if (is_string($rule)) {
                    if (str_contains($rule, ':')) {
                        [$name, $params] = explode(':', $rule, 2);
                        $params = explode(',', $params);
                    }
                }

                $processor = $this->getRule($name, $field, $this->data, $params);
                if (method_exists($processor, 'setDatabaseReqs')) {
                    $processor->setDatabaseReqs($this->container);
                }
                if (!$this->doValidate($processor, $field, $name) && $bail) {
                    break;
                }
            }
        }

        if (count($this->errors)) {
            $exception = new ValidationException();
            $exception->setErrors($this->errors);
            if (static::$throwException) {
                throw $exception;
            }
        }

        return [
            'validated' => $this->validated,
            'errors' => $this->errors,
        ];
    }

    /**
     * @param RuleInterface|callable $processor
     * @return bool
     */
    private function doValidate($processor, string $field, string $ruleName): bool
    {
        if ($processor->validate()) {
//            $this->validated[$field] = ArrayMethods::get($this->data, $field);
            return true;
        }
        ArrayMethods::forget($this->validated, $field);
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->failed[] = $ruleName;
        $message = $this->customMessages[$field][$ruleName] ?? $processor->getMessage();
        $this->errors[$field][$ruleName] = (!is_null($this->translator)) ?
            $this->translator->get($message, ['field' => $this->customAttributes[$field] ?? $field]) :
            $message;
        return false;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    /**
     * Determine if the data fails the validation rules.
     *
     * @return bool
     */
    public function fails(): bool
    {
        return !empty($this->failed) || !empty($this->errors);
    }

    /**
     * Get the failed validation rules.
     *
     * @return array
     */
    public function failed(): array
    {
        return $this->failed;
    }

    /**
     * Add an after validation callback.
     *
     * @param callable|string $callback
     * @return $this
     */
    public function after($callback): self
    {
        $this->after[] = function () use ($callback) {
            return $callback($this);
        };

        return $this;
    }

    /**
     * Get all of the validation error messages.
     *
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param string|callable|RuleInterface $rule
     *
     * @return mixed|object|RuleInterface
     * @throws ReflectionException
     * @throws RuleException
     */
    private function getRule($rule, $field, $data, $params)
    {
        if ($rule instanceof RuleInterface) {
            return $this->resolvedRules[get_class($rule)] = $rule;
        }
        if (array_key_exists($rule, $this->resolvedRules)) {
            return $this->resolvedRules[$rule];
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
                    return $this->container->make($ruleClass, [
                        'arguments' => [$field, $data, $params]
                    ], true);
                }
            }
        }

        throw new RuleException(sprintf('Validation rule: %s not found', $rule));
    }
}
