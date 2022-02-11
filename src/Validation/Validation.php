<?php

namespace TeraBlaze\Validation;

use Closure;
use Exception;
use Psr\Http\Message\UploadedFileInterface;
use ReflectionException;
use TeraBlaze\Collection\ArrayCollection;
use TeraBlaze\Collection\Exceptions\InvalidTypeException;
use TeraBlaze\Container\Container;
use TeraBlaze\Support\ArrayMethods;
use TeraBlaze\Support\MessageBag;
use TeraBlaze\Support\StringMethods;
use TeraBlaze\Translation\Translator;
use TeraBlaze\Validation\Exception\RuleException;
use TeraBlaze\Validation\Exception\ValidationException;
use TeraBlaze\Validation\Rule\Builder\RuleBuilderInterface;
use TeraBlaze\Validation\Rule\ClosureValidationRule;
use TeraBlaze\Validation\Rule\NullableRule;
use TeraBlaze\Validation\Rule\RuleInterface;
use TeraBlaze\Validation\Traits\FormatsMessagesTrait;

class Validation implements ValidationInterface
{
    use FormatsMessagesTrait;

    /** @var string[] $rulesNamespaces */
    public static array $rulesNamespaces = ['TeraBlaze\Validation\Rule'];
    public static bool $throwException = true;

    private ?Translator $translator = null;
    private Container $container;

    /**
     * The size related validation rules.
     *
     * @var string[]
     */
    public array $sizeRules = ['size', 'between', 'min', 'max', 'gt', 'lt', 'gte', 'lte'];

    /**
     * The numeric validation rules.
     *
     * @var string[]
     */
    public array $numericRules = ['num', 'number', 'numeric', 'int', 'integer', 'float'];

    /**
     * Indicates if the validator should stop on the first rule failure.
     *
     * @var bool
     */
    protected bool $stopOnFirstFailure = false;

    /** @var array<string, mixed> $data */
    private array $data;
    /** @var array<string, mixed> $rules */
    private array $rules;
    /** @var array<string, mixed> $initialRules */
    private array $initialRules;
    /** @var array<string, mixed> $customMessages */
    private array $customMessages;
    /** @var string[] $implicitFields */
    protected array $implicitFields;
    /** @var string[] $customFields */
    private array $customFields;

    private string $dotPlaceholder;

    /** @var callable[] $after */
    protected array $after = [];

    /** @var array<string, string> $bails */
    protected array $bails = [];

    /** @var array<string, mixed> $rawValidated */
    protected array $rawValidated = [];

    /** @var array<string, mixed> $validated */
    protected array $validated = [];

    /** @var array<string, mixed> $failed */
    protected array $failed = [];

    private ?MessageBag $messages = null;

    /**
     * All the custom replacer extensions.
     *
     * @var array<string, mixed>
     */
    public $replacers = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rules
     * @param array<string, mixed> $customMessages
     * @param array<string, string> $customFields
     * @throws Exception
     */
    public function __construct(
        array $data,
        array $rules,
        array $customMessages = [],
        array $customFields = []
    ) {
        $this->dotPlaceholder = StringMethods::random();

        $this->data = $this->parseData($data);
        $this->implicitFields = [];
        $this->initialRules = $rules;
        $this->customMessages = $customMessages;
        $this->customFields = $customFields;

        $this->setRules($rules);
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
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function parseData(array $data): array
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
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function replacePlaceholders(array $data): array
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
    protected function replacePlaceholderInString(string $value): string
    {
        return str_replace(
            [$this->dotPlaceholder, '__asterisk__'],
            ['.', '*'],
            $value
        );
    }

    /**
     * Get the data under validation.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get the value of a given field.
     *
     * @param string $field
     * @return mixed
     */
    protected function getValue(string $field)
    {
        return ArrayMethods::get($this->data, $field);
    }

    /**
     * Get the validation rules.
     *
     * @return array<string, mixed>
     */
    public function getRules(): array
    {
        return $this->rules;
    }


    /**
     * Determine if the given field has a rule in the given set.
     *
     * @param  string  $field
     * @param  string|array  $rules
     * @return bool
     */
    public function hasRule($field, $rules)
    {
        return ! is_null($this->getRule($field, $rules));
    }

    /**
     * Get a rule and its parameters for a given field.
     *
     * @param  string  $field
     * @param  string|array  $rules
     * @return array|null
     */
    protected function getRule($field, $rules)
    {
        if (! array_key_exists($field, $this->rules)) {
            return null;
        }

        $rules = (array) $rules;

        foreach ($this->rules[$field] as $rule) {
            [$rule, $parameters] = self::parse($rule);

            if (in_array($rule, $rules)) {
                return [$rule, $parameters];
            }
        }
        return null;
    }

    /**
     * Set the validation rules.
     *
     * @param array<string, mixed> $rules
     * @return $this
     * @throws InvalidTypeException
     */
    public function setRules(array $rules): Validation
    {
        $rules = (new ArrayCollection($rules))->mapWithKeys(function ($value, $key) {
            return [str_replace('\.', $this->dotPlaceholder, $key) => $value];
        })->toArray();

        $this->initialRules = $rules;

        $this->rules = [];

        $this->addRules($rules);

        return $this;
    }

    /**
     * Parse the given rules and merge them into current rules.
     *
     * @param array<string, mixed> $rules
     * @return void
     */
    public function addRules(array $rules)
    {
        // The primary purpose of this parser is to expand any "*" rules to the all
        // of the explicit rules needed for the given data. For example the rule
        // names.* would get expanded to names.0, names.1, etc. for this data.
        $response = $this->explode($rules);

        $this->rules = array_merge_recursive(
            $this->rules,
            $response->rules
        );

        $this->implicitFields = array_merge(
            $this->implicitFields,
            $response->implicitFields
        );
    }

    /**
     * @return array<string, mixed>
     * @throws ReflectionException
     * @throws RuleException
     * @throws ValidationException
     */
    public function validate(): array
    {
        $this->messages = new MessageBag();
        $this->validated = [];
        foreach ($this->rules as $field => $rulesForField) {
            if ($this->stopOnFirstFailure && $this->messages->isNotEmpty()) {
                break;
            }
            $bail = reset($rulesForField) == "bail";
            if ($bail) {
                $this->bails[$field] = array_shift($rulesForField);
            }
            $rulesForField = ArrayMethods::clean($rulesForField);
            if ($this->runFieldValidate($rulesForField, $field, $bail)) {
                $this->rawValidated[$field] = ArrayMethods::get($this->data, $field);
            }
        }

        $this->validated = $this->validated();

        if ($this->fails()) {
            $exception = new ValidationException($this);
            if (static::$throwException) {
                throw $exception;
            }
        }

        return [
            'validated' => $this->validated,
            'errors' => $this->messages,
        ];
    }

    /**
     * @param array<string, mixed> $rulesForField
     * @param string $field
     * @param bool $bail
     * @return bool
     * @throws ReflectionException
     * @throws RuleException
     */
    private function runFieldValidate(array $rulesForField, string $field, bool $bail): bool
    {
        if (empty($rulesForField)) {
            return true;
        }
        $fieldPassed = false;
        foreach ($rulesForField as $rule) {
            if (is_a($rule, RuleBuilderInterface::class)) {
                $rule = $rule->__toString();
            }

            [$rule, $params] = static::parse($rule);

            $ruleName = $this->parseRuleName($rule);

            $processor = $this->getRuleInstance($rule, $field, $this->data, $params);
            if ($processor instanceof RuleInterface && method_exists($processor, 'setDatabaseReqs')) {
                $processor->setDatabaseReqs($this->container);
            }
            if ($processor instanceof NullableRule) {
                $data = ArrayMethods::get($this->data, $field);
                if (is_null($data)) {
                    break;
                }
                if ($data instanceof UploadedFileInterface && $data->getError() == UPLOAD_ERR_NO_FILE) {
                    break;
                }
            }

            if (!$this->doValidate($processor, $field, $ruleName, $params) && $bail) {
                break;
            }
        }
        if (! $this->messages->has($this->replacePlaceholderInString($field))) {
            $fieldPassed = true;
        }
        return $fieldPassed;
    }

    /**
     * @param RuleInterface $processor
     * @param string $field
     * @param string $ruleName
     * @param array<int, mixed> $params
     * @return bool
     */
    private function doValidate(RuleInterface $processor, string $field, string $ruleName, array $params): bool
    {
        if ($processor->validate()) {
            return true;
        }
        $this->addFailure($processor, $field, $ruleName, $params);

        return false;
    }

    /**
     * Instruct the validator to stop validating after the first rule failure.
     *
     * @param bool $stopOnFirstFailure
     * @return $this
     */
    public function stopOnFirstFailure($stopOnFirstFailure = true): Validation
    {
        $this->stopOnFirstFailure = $stopOnFirstFailure;

        return $this;
    }

    /**
     * Generate an array of all fields that have messages.
     *
     * @return array<string, mixed>
     * @throws InvalidTypeException
     */
    protected function fieldsThatHaveMessages(): array
    {
        return (new ArrayCollection($this->messages->toArray()))->map(function ($message, $key) {
            return explode('.', $key)[0];
        })->unique()->flip()->all();
    }

    /**
     * Get the fields and values that were validated.
     *
     * @param string|null $field
     * @return mixed
     */
    public function validated(string $field = null)
    {
        return $field ? ArrayMethods::get($this->getValidated(), $field) : $this->getValidated();
    }

    private function getValidated()
    {
        if (!empty($this->validated)) {
            return $this->validated;
        }
        $results = [];

        foreach ($this->rawValidated as $key => $data) {
            $value = dataGet($this->getData(), $key);
            ArrayMethods::set($results, $key, $value);
        }

        $results = $this->replacePlaceholders($results);

        return $this->validated = ArrayMethods::clean($results);
    }

    /**
     * Determine if the data fails the validation rules.
     *
     * @return bool
     */
    public function fails(): bool
    {
        return !empty($this->failed) || $this->messages->isNotEmpty();
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
     * Get the message container for the validator.
     *
     * @return MessageBag
     */
    public function messages(): MessageBag
    {
        if (! $this->messages) {
            $this->validate();
        }

        return $this->messages;
    }

    /**
     * An alternative more semantic shortcut to the message container.
     *
     * @return MessageBag
     */
    public function errors(): MessageBag
    {
        return $this->messages();
    }

    /**
     * Get the messages for the instance.
     *
     * @return MessageBag
     */
    public function getMessageBag(): MessageBag
    {
        return $this->messages();
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
     * @param RuleInterface|Closure|string $rule
     * @param string $field
     * @param array<string, mixed> $data
     * @param array<int, mixed> $params
     * @return mixed|object|ClosureValidationRule|RuleInterface
     * @throws ReflectionException
     * @throws RuleException
     */
    private function getRuleInstance($rule, string $field, array $data, array $params)
    {
        if ($rule instanceof Closure) {
            return new ClosureValidationRule($this, $rule, $field, $data, $params);
        }
        if ($rule instanceof RuleInterface) {
            return $rule;
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
                    $this->container->registerServiceInstance($this);
                    $ruleInstance = $this->container->make($ruleClass, [
                        'arguments' => [$this, $field, $data, $params]
                    ], true);
                    $this->container->removeService($this);
                    return $ruleInstance;
                }
            }
        }

        throw new RuleException(sprintf('Validation rule: %s not found', $rule));
    }

    /**
     * Parse the human-friendly rules into a full rules array for the validator.
     *
     * @param array $rules
     * @return \stdClass
     */
    public function explode($rules)
    {
        $this->implicitFields = [];

        $rules = $this->explodeRules($rules);

        return (object)[
            'rules' => $rules,
            'implicitFields' => $this->implicitFields,
        ];
    }

    /**
     * Explode the rules into an array of explicit rules.
     *
     * @param array $rules
     * @return array
     */
    protected function explodeRules($rules)
    {
        foreach ($rules as $key => $rule) {
            if (StringMethods::contains($key, '*')) {
                $rules = $this->explodeWildcardRules($rules, $key, [$rule]);

                unset($rules[$key]);
            } else {
                $rules[$key] = $this->explodeExplicitRule($rule);
            }
        }

        return $rules;
    }

    /**
     * Explode the explicit rule into an array if necessary.
     *
     * @param mixed $rule
     * @return array
     */
    protected function explodeExplicitRule($rule)
    {
        if (is_string($rule)) {
            return explode('|', $rule);
        } elseif (is_object($rule)) {
            return [$this->prepareRule($rule)];
        }

        return array_map([$this, 'prepareRule'], $rule);
    }

    /**
     * Prepare the given rule for the Validator.
     *
     * @param mixed $rule
     * @return mixed
     */
    protected function prepareRule($rule)
    {
        if (!is_object($rule) || $rule instanceof RuleInterface) {
            return $rule;
        }

        return $rule;
    }

    /**
     * Define a set of rules that apply to each element in an array field.
     *
     * @param array $results
     * @param string $field
     * @param string|array $rules
     * @return array
     */
    protected function explodeWildcardRules($results, $field, $rules)
    {
        $pattern = str_replace('\*', '[^\.]*', preg_quote($field));

        $data = ValidationData::initializeAndGatherData($field, $this->data);

        foreach ($data as $key => $value) {
            if (StringMethods::startsWith($key, $field) || (bool)preg_match('/^' . $pattern . '\z/', $key)) {
                foreach ((array)$rules as $rule) {
                    $this->implicitFields[$field][] = $key;

                    $results = $this->mergeRules($results, $key, $rule);
                }
            }
        }

        return $results;
    }

    /**
     * Merge additional rules into a given field(s).
     *
     * @param array $results
     * @param string|array $field
     * @param string|array $rules
     * @return array
     */
    public function mergeRules($results, $field, $rules = [])
    {
        if (is_array($field)) {
            foreach ((array)$field as $innerField => $innerRules) {
                $results = $this->mergeRulesForField($results, $innerField, $innerRules);
            }

            return $results;
        }

        return $this->mergeRulesForField(
            $results,
            $field,
            $rules
        );
    }

    /**
     * Merge additional rules into a given field.
     *
     * @param array $results
     * @param string $field
     * @param string|array $rules
     * @return array
     */
    protected function mergeRulesForField($results, $field, $rules)
    {
        $merge = head($this->explodeRules([$rules]));

        $results[$field] = array_merge(
            isset($results[$field]) ? $this->explodeExplicitRule($results[$field]) : [],
            $merge
        );

        return $results;
    }

    /**
     * Extract the rule name and parameters from a rule.
     *
     * @param array|string $rule
     * @return array
     */
    public static function parse($rule)
    {
        if ($rule instanceof RuleInterface || $rule instanceof Closure) {
            return [$rule, []];
        }

        if ($rule instanceof RuleBuilderInterface) {
            $rule = (string) $rule;
        }
        if (is_string($rule)) {
            $rule = static::parseStringRule($rule);
        }

        return $rule;
    }

    /**
     * Parse a string based rule.
     *
     * @param string $rule
     * @return array
     */
    protected static function parseStringRule($rule)
    {
        $parameters = [];

        // The format for specifying validation rules and parameters follows an
        // easy {rule}:{parameters} formatting convention. For instance the
        // rule "Max:3" states that the value may only be three letters.
        if (strpos($rule, ':') !== false) {
            [$rule, $parameter] = explode(':', $rule, 2);

            $parameters = static::parseParameters($rule, $parameter);
        }

        return [trim($rule), $parameters];
    }

    /**
     * Parse a parameter list.
     *
     * @param string $rule
     * @param string $parameter
     * @return array
     */
    protected static function parseParameters($rule, $parameter)
    {
        $rule = strtolower($rule);

        if (in_array($rule, ['regex', 'not_regex', 'notregex'], true)) {
            return [$parameter];
        }

        return str_getcsv($parameter);
    }

    /**
     * Add a failed rule and error message to the collection.
     *
     * @param string $field
     * @param string $rule
     * @param array $parameters
     * @return void
     */
    public function addFailure($processor, $field, $rule, $parameters = [])
    {
        $field = $this->replacePlaceholderInString($field);

        $errorMessage = $this->makeReplacements(
            $this->getMessage($processor, $field, $rule),
            $field,
            $rule,
            $parameters
        );

        $this->messages->add($field, $errorMessage);

        if (in_array($rule, ['closure', 'object'])) {
            $this->failed[$field][] = $parameters;
        } else {
            $this->failed[$field][$rule] = $parameters;
        }
    }


    /**
     * Get the explicit keys from a field flattened with dot notation.
     *
     * E.g. 'foo.1.bar.spark.baz' -> [1, 'spark'] for 'foo.*.bar.*.baz'
     *
     * @param string $field
     * @return array
     */
    protected function getExplicitKeys($field)
    {
        $pattern = str_replace('\*', '([^\.]+)', preg_quote($this->getPrimaryField($field), '/'));

        if (preg_match('/^' . $pattern . '/', $field, $keys)) {
            array_shift($keys);

            return $keys;
        }

        return [];
    }

    /**
     * Get the primary field name.
     *
     * For example, if "name.0" is given, "name.*" will be returned.
     *
     * @param string $field
     * @return string
     */
    protected function getPrimaryField($field)
    {
        foreach ($this->implicitFields as $unparsed => $parsed) {
            if (in_array($field, $parsed, true)) {
                return $unparsed;
            }
        }

        return $field;
    }

    /**
     * Replace each field parameter which has an escaped dot with the dot placeholder.
     *
     * @param array $parameters
     * @param array $keys
     * @return array
     */
    protected function replaceDotInParameters(array $parameters)
    {
        return array_map(function ($field) {
            return str_replace('\.', $this->dotPlaceholder, $field);
        }, $parameters);
    }

    /**
     * Replace each field parameter which has asterisks with the given keys.
     *
     * @param array $parameters
     * @param array $keys
     * @return array
     */
    protected function replaceAsterisksInParameters(array $parameters, array $keys)
    {
        return array_map(function ($field) use ($keys) {
            return vsprintf(str_replace('*', '%s', $field), $keys);
        }, $parameters);
    }

    /**
     * Get the size of a value.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function getSize(string $field, $value)
    {
        $hasNumeric = $this->hasRule($field, $this->numericRules);
        if (is_null($value)) {
            return 0;
        }
        if (is_numeric($value) && $hasNumeric) {
            return $value;
        }
        if (is_array($value)) {
            return count($value);
        }
        if ($value instanceof \SplFileInfo || $value instanceof UploadedFileInterface) {
            return $value->getSize() / 1024;
        }

        return StringMethods::length($value);
    }


    /**
     * Register an array of custom validator message replacers.
     *
     * @param array $replacers
     * @return void
     */
    public function addReplacers(array $replacers)
    {
        if ($replacers) {
            $keys = array_map([StringMethods::class, 'snake'], array_keys($replacers));

            $replacers = array_combine($keys, array_values($replacers));
        }

        $this->replacers = array_merge($this->replacers, $replacers);
    }

    /**
     * Register a custom validator message replacer.
     *
     * @param string $rule
     * @param \Closure|string $replacer
     * @return void
     */
    public function addReplacer($rule, $replacer)
    {
        $this->replacers[StringMethods::snake($rule)] = $replacer;
    }

    private function parseRuleName($rule): string
    {
        if ($rule instanceof RuleInterface) {
            return get_class($rule);
        }
        if ($rule instanceof Closure) {
            return 'closure';
        }
        if (!is_string($rule)) {
            return (string)gettype($rule);
        }
        return $rule;
    }
}
