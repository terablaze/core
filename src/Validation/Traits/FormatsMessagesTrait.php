<?php

namespace Terablaze\Validation\Traits;

use Closure;
use SplFileInfo;
use Terablaze\Psr7\UploadedFile;
use Terablaze\Support\ArrayMethods;
use Terablaze\Support\StringMethods;
use Terablaze\Validation\Rule\RuleInterface;
use Terablaze\Validation\Validation;

trait FormatsMessagesTrait
{
    use ReplacesFieldsTrait;

    /**
     * Get the validation message for a field and rule.
     *
     * @param RuleInterface $processor
     * @param $field
     * @param $rule
     * @return array|string|null
     */
    protected function getMessage(RuleInterface $processor, $field, $rule)
    {
        $inlineMessage = $this->getInlineMessage($field, $rule);

        // First we will retrieve the custom message for the validation rule if one
        // exists. If a custom validation message is being used we'll return the
        // custom message, otherwise we'll keep searching for a valid message.
        if (!is_null($inlineMessage)) {
            return $inlineMessage;
        }

        $lowerRule = StringMethods::snake($rule);

        $customMessage = $this->getCustomMessageFromTranslator(
            $customKey = "validation.custom.{$field}.{$lowerRule}"
        );

        // First we check for a custom defined validation message for the field
        // and rule. This allows the developer to specify specific messages for
        // only some fields and rules that need to get specially formed.
        if ($customMessage !== $customKey) {
            return $customMessage;
        }

        // If the rule being validated is a "size" rule, we will need to gather the
        // specific error message for the type of attribute being validated such
        // as a number, file or string which all have different message types.
        $key = $this->getMessageKey($rule, $field, $lowerRule);

        if ($key !== ($value = $this->translator->get($key))) {
            return $value;
        }

        return $this->getFromLocalArray($field, $lowerRule) ?: $processor->getMessage();
    }

    /**
     * Get the proper inline error message for standard and size rules.
     *
     * @param string $field
     * @param string $rule
     * @return string|null
     */
    protected function getInlineMessage($field, $rule)
    {
        $inlineEntry = $this->getFromLocalArray($field, StringMethods::snake($rule));

        return is_array($inlineEntry) ?
            $inlineEntry[$this->getFieldType($field)] :
            $inlineEntry;
    }

    /**
     * Get the inline message for a rule if it exists.
     *
     * @param string $field
     * @param string $lowerRule
     * @param array|null $source
     * @return string|null
     */
    protected function getFromLocalArray($field, $lowerRule)
    {
        $source = $this->customMessages;

        $keys = ["{$field}.{$lowerRule}", $lowerRule];

        // First we will check for a custom message for a field specific rule
        // message for the fields, then we will check for a general custom line
        // that is not field specific. If we find either we'll return it.
        foreach ($keys as $key) {
            foreach (array_keys($source) as $sourceKey) {
                if (strpos($sourceKey, '*') !== false) {
                    $pattern = str_replace('\*', '([^.]*)', preg_quote($sourceKey, '#'));

                    if (preg_match('#^' . $pattern . '\z#u', $key) === 1) {
                        return $source[$sourceKey];
                    }

                    continue;
                }

                if (StringMethods::is($sourceKey, $key)) {
                    return $source[$sourceKey];
                }
            }
        }
        return null;
    }

    /**
     * Get the custom error message from the translator.
     *
     * @param string $key
     * @return string
     */
    protected function getCustomMessageFromTranslator($key)
    {
        if (($message = $this->translator->get($key)) !== $key) {
            return $message;
        }

        // If an exact match was not found for the key, we will collapse all of these
        // messages and loop through them and try to find a wildcard match for the
        // given key. Otherwise, we will simply return the key's value back out.
        $shortKey = preg_replace(
            '/^validation\.custom\./', '', $key
        );

        return $this->getWildcardCustomMessages(ArrayMethods::dot(
            (array)$this->translator->get('validation.custom')
        ), $shortKey, $key);
    }

    /**
     * Check the given messages for a wildcard key.
     *
     * @param array $messages
     * @param string $search
     * @param string $default
     * @return string
     */
    protected function getWildcardCustomMessages($messages, $search, $default)
    {
        foreach ($messages as $key => $message) {
            if ($search === $key || (StringMethods::contains($key, ['*']) && StringMethods::is($key, $search))) {
                return $message;
            }
        }

        return $default;
    }



    /**
     * @param $rule
     * @param $field
     * @param string $lowerRule
     * @return string
     */
    protected function getMessageKey($rule, $field, string $lowerRule): string
    {
        if (in_array($rule, $this->sizeRules)) {
            // There are three different types of size validations. The field may be
            // either a number, file, or string so we will check a few things to know
            // which type of value it is and return the correct line for that type.
            $type = $this->getFieldType($field);

            return "validation.{$lowerRule}.{$type}";
        }

        // Finally, if no developer specified messages have been set, and no other
        // special messages apply for this rule, we will just pull the default
        // messages out of the translator service for this validation rule.
        return "validation.{$lowerRule}";
    }

    /**
     * Get the data type of the given field.
     *
     * @param string $field
     * @return string
     */
    protected function getFieldType($field)
    {
        if ($this->hasRule($field, $this->numericRules)) {
            return 'numeric';
        } elseif (is_array($this->getValue($field))) {
            return 'array';
        } elseif ($this->getValue($field) instanceof UploadedFile || $this->getValue($field) instanceof SplFileInfo) {
            return 'file';
        }
        return 'string';
    }

    /**
     * Replace all error message place-holders with actual values.
     *
     * @param string $message
     * @param string $field
     * @return string
     */
    public function makeReplacements($message, $field, $rule, $parameters)
    {
        $message = $this->replaceFieldPlaceholder(
            $message, $this->getDisplayableField($field)
        );

        $message = $this->replaceInputPlaceholder($message, $field);

        if (isset($this->replacers[StringMethods::snake($rule)])) {
            return $this->callReplacer($message, $field, StringMethods::snake($rule), $parameters, $this);
        }
        if (method_exists($this, $replacer = "replace" . StringMethods::studly($rule))) {
            return $this->$replacer($message, $field, $rule, $parameters);
        }

        return $message;
    }

    /**
     * Get the displayable name of the field.
     *
     * @param string $field
     * @return string
     */
    public function getDisplayableField($field)
    {
        $primaryField = $this->getPrimaryField($field);

        $expectedFields = $field != $primaryField
            ? [$field, $primaryField] : [$field];

        foreach ($expectedFields as $name) {
            // The developer may dynamically specify the array of custom fields on this
            // validator instance. If the field exists in this array it is used over
            // the other ways of pulling the field name for this given fields.
            if (isset($this->customFields[$name])) {
                return $this->customFields[$name];
            }

            // We allow for a developer to specify language lines for any field in this
            // application, which allows flexibility for displaying a unique displayable
            // version of the field name instead of the name used in an HTTP POST.
            if ($line = $this->getFieldFromTranslations($name)) {
                return $line;
            }
        }

        // When no language line has been specified for the field and it is also
        // an implicit field we will display the raw field's name and not
        // modify it with any of these replacements before we display the name.
        if (isset($this->implicitFields[$primaryField])) {
            return $field;
        }

        return str_replace('_', ' ', StringMethods::snake($field));
    }

    /**
     * Get the given field from the field translations.
     *
     * @param string $name
     * @return string
     */
    protected function getFieldFromTranslations($name)
    {
        return ArrayMethods::get($this->translator->get('validation.fields'), $name);
    }

    /**
     * Replace the :field placeholder in the given message.
     *
     * @param string $message
     * @param string $value
     * @return string
     */
    protected function replaceFieldPlaceholder($message, $value)
    {
        return str_replace(
            [':field', ':FIELD', ':Field'],
            [$value, StringMethods::upper($value), StringMethods::ucfirst($value)],
            $message
        );
    }

    /**
     * Replace the :input placeholder in the given message.
     *
     * @param string $message
     * @param string $field
     * @return string
     */
    protected function replaceInputPlaceholder($message, $field)
    {
        $actualValue = $this->getValue($field);

        if (is_scalar($actualValue) || is_null($actualValue)) {
            $message = str_replace(':input', $this->getDisplayableValue($field, $actualValue), $message);
        }

        return $message;
    }

    /**
     * Get the displayable name of the value.
     *
     * @param string $field
     * @param mixed $value
     * @return string
     */
    public function getDisplayableValue($field, $value)
    {
        if (isset($this->customValues[$field][$value])) {
            return $this->customValues[$field][$value];
        }

        $key = "validation.values.{$field}.{$value}";

        if (($line = $this->translator->get($key)) !== $key) {
            return $line;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'empty';
        }

        return (string)$value;
    }

    /**
     * Transform an array of fields to their displayable form.
     *
     * @param array $values
     * @return array
     */
    protected function getFieldList(array $values)
    {
        $fields = [];

        // For each field in the list we will simply get its displayable form as
        // this is convenient when replacing lists of parameters like some of the
        // replacement functions do when formatting out the validation message.
        foreach ($values as $key => $value) {
            $fields[$key] = $this->getDisplayableField($value);
        }

        return $fields;
    }

    /**
     * Call a custom validator message replacer.
     *
     * @param string $message
     * @param string $field
     * @param string $rule
     * @param array $parameters
     * @param Validation $validation
     * @return string|null
     */
    protected function callReplacer($message, $field, $rule, $parameters, $validation)
    {
        $callback = $this->replacers[$rule];

        if ($callback instanceof Closure) {
            return $callback(...func_get_args());
        }
        if (is_string($callback)) {
            return $this->callClassBasedReplacer($callback, $message, $field, $rule, $parameters, $validation);
        }
        return null;
    }

    /**
     * Call a class based validator message replacer.
     *
     * @param string $callback
     * @param string $message
     * @param string $field
     * @param string $rule
     * @param array $parameters
     * @param Validation $validation
     * @return string
     */
    protected function callClassBasedReplacer($callback, $message, $field, $rule, $parameters, $validation)
    {
        [$class, $method] = StringMethods::parseCallback($callback, 'replace');

        return $this->container->make($class)->{$method}(...array_slice(func_get_args(), 1));
    }
}
