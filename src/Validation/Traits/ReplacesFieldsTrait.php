<?php

namespace TeraBlaze\Validation\Traits;

use TeraBlaze\Support\ArrayMethods;

trait ReplacesFieldsTrait
{
    /**
     * Replace all place-holders for the accepted_if rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceAcceptedIf($message, $field, $rule, $parameters)
    {
        $parameters[1] = $this->getDisplayableValue($parameters[0], ArrayMethods::get($this->data, $parameters[0]));

        $parameters[0] = $this->getDisplayableField($parameters[0]);

        return str_replace([':other', ':value'], $parameters, $message);
    }

    /**
     * Replace all place-holders for the declined_if rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceDeclinedIf($message, $field, $rule, $parameters)
    {
        $parameters[1] = $this->getDisplayableValue($parameters[0], ArrayMethods::get($this->data, $parameters[0]));

        $parameters[0] = $this->getDisplayableField($parameters[0]);

        return str_replace([':other', ':value'], $parameters, $message);
    }

    /**
     * Replace all place-holders for the between rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceBetween($message, $field, $rule, $parameters)
    {
        return str_replace([':min', ':max'], $parameters, $message);
    }

    /**
     * Replace all place-holders for the date_format rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceDateFormat($message, $field, $rule, $parameters)
    {
        return str_replace(':format', $parameters[0], $message);
    }

    /**
     * Replace all place-holders for the different rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceDifferent($message, $field, $rule, $parameters)
    {
        return $this->replaceSame($message, $field, $rule, $parameters);
    }

    /**
     * Replace all place-holders for the digits rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceDigits($message, $field, $rule, $parameters)
    {
        return str_replace(':digits', $parameters[0], $message);
    }

    /**
     * Replace all place-holders for the digits (between) rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceDigitsBetween($message, $field, $rule, $parameters)
    {
        return $this->replaceBetween($message, $field, $rule, $parameters);
    }

    /**
     * Replace all place-holders for the min rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceMin($message, $field, $rule, $parameters)
    {
        return str_replace(':min', $parameters[0], $message);
    }

    /**
     * Replace all place-holders for the max rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceMax($message, $field, $rule, $parameters)
    {
        return str_replace(':max', $parameters[0], $message);
    }

    /**
     * Replace all place-holders for the multiple_of rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceMultipleOf($message, $field, $rule, $parameters)
    {
        return str_replace(':value', $parameters[0] ?? '', $message);
    }

    /**
     * Replace all place-holders for the in rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceIn($message, $field, $rule, $parameters)
    {
        foreach ($parameters as &$parameter) {
            $parameter = $this->getDisplayableValue($field, $parameter);
        }

        return str_replace(':values', implode(', ', $parameters), $message);
    }

    /**
     * Replace all place-holders for the not_in rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceNotIn($message, $field, $rule, $parameters)
    {
        return $this->replaceIn($message, $field, $rule, $parameters);
    }

    /**
     * Replace all place-holders for the in_array rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceInArray($message, $field, $rule, $parameters)
    {
        return str_replace(':other', $this->getDisplayableField($parameters[0]), $message);
    }

    /**
     * Replace all place-holders for the mimetypes rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceMime($message, $field, $rule, $parameters)
    {
        return str_replace(':values', implode(', ', $parameters), $message);
    }

    /**
     * Replace all place-holders for the mimes rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceExt($message, $field, $rule, $parameters)
    {
        return str_replace(':values', implode(', ', $parameters), $message);
    }

    /**
     * Replace all place-holders for the required_with rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceRequiredWith($message, $field, $rule, $parameters)
    {
        return str_replace(':values', implode(' / ', $this->getFieldList($parameters)), $message);
    }

    /**
     * Replace all place-holders for the required_with_all rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceRequiredWithAll($message, $field, $rule, $parameters)
    {
        return $this->replaceRequiredWith($message, $field, $rule, $parameters);
    }

    /**
     * Replace all place-holders for the required_without rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceRequiredWithout($message, $field, $rule, $parameters)
    {
        return $this->replaceRequiredWith($message, $field, $rule, $parameters);
    }

    /**
     * Replace all place-holders for the required_without_all rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceRequiredWithoutAll($message, $field, $rule, $parameters)
    {
        return $this->replaceRequiredWith($message, $field, $rule, $parameters);
    }

    /**
     * Replace all place-holders for the size rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceSize($message, $field, $rule, $parameters)
    {
        return str_replace(':size', $parameters[0], $message);
    }

    /**
     * Replace all place-holders for the gt rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceGt($message, $field, $rule, $parameters)
    {
        if (is_null($value = $this->getValue($parameters[0]))) {
            return str_replace(':value', $this->getDisplayableField($parameters[0]), $message);
        }

        return str_replace(':value', $this->getSize($field, $value), $message);
    }

    /**
     * Replace all place-holders for the lt rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceLt($message, $field, $rule, $parameters)
    {
        if (is_null($value = $this->getValue($parameters[0]))) {
            return str_replace(':value', $this->getDisplayableField($parameters[0]), $message);
        }

        return str_replace(':value', $this->getSize($field, $value), $message);
    }

    /**
     * Replace all place-holders for the gte rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceGte($message, $field, $rule, $parameters)
    {
        if (is_null($value = $this->getValue($parameters[0]))) {
            return str_replace(':value', $this->getDisplayableField($parameters[0]), $message);
        }

        return str_replace(':value', $this->getSize($field, $value), $message);
    }

    /**
     * Replace all place-holders for the lte rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceLte($message, $field, $rule, $parameters)
    {
        if (is_null($value = $this->getValue($parameters[0]))) {
            return str_replace(':value', $this->getDisplayableField($parameters[0]), $message);
        }

        return str_replace(':value', $this->getSize($field, $value), $message);
    }

    /**
     * Replace all place-holders for the required_if rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceRequiredIf($message, $field, $rule, $parameters)
    {
        $parameters[1] = $this->getDisplayableValue($parameters[0], ArrayMethods::get($this->data, $parameters[0]));

        $parameters[0] = $this->getDisplayableField($parameters[0]);

        return str_replace([':other', ':value'], $parameters, $message);
    }

    /**
     * Replace all place-holders for the required_unless rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceRequiredUnless($message, $field, $rule, $parameters)
    {
        $other = $this->getDisplayableField($parameters[0]);

        $values = [];

        foreach (array_slice($parameters, 1) as $value) {
            $values[] = $this->getDisplayableValue($parameters[0], $value);
        }

        return str_replace([':other', ':values'], [$other, implode(', ', $values)], $message);
    }

    /**
     * Replace all place-holders for the prohibited_if rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceProhibitedIf($message, $field, $rule, $parameters)
    {
        $parameters[1] = $this->getDisplayableValue($parameters[0], ArrayMethods::get($this->data, $parameters[0]));

        $parameters[0] = $this->getDisplayableField($parameters[0]);

        return str_replace([':other', ':value'], $parameters, $message);
    }

    /**
     * Replace all place-holders for the prohibited_unless rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceProhibitedUnless($message, $field, $rule, $parameters)
    {
        $other = $this->getDisplayableField($parameters[0]);

        $values = [];

        foreach (array_slice($parameters, 1) as $value) {
            $values[] = $this->getDisplayableValue($parameters[0], $value);
        }

        return str_replace([':other', ':values'], [$other, implode(', ', $values)], $message);
    }

    /**
     * Replace all place-holders for the prohibited_with rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceProhibits($message, $field, $rule, $parameters)
    {
        return str_replace(':other', implode(' / ', $this->getFieldList($parameters)), $message);
    }

    /**
     * Replace all place-holders for the same rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceSame($message, $field, $rule, $parameters)
    {
        return str_replace(':other', $this->getDisplayableField($parameters[0]), $message);
    }

    /**
     * Replace all place-holders for the before rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceBefore($message, $field, $rule, $parameters)
    {
        if (! strtotime($parameters[0])) {
            return str_replace(':date', $this->getDisplayableField($parameters[0]), $message);
        }

        return str_replace(':date', $this->getDisplayableValue($field, $parameters[0]), $message);
    }

    /**
     * Replace all place-holders for the before_or_equal rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceBeforeOrEqual($message, $field, $rule, $parameters)
    {
        return $this->replaceBefore($message, $field, $rule, $parameters);
    }

    /**
     * Replace all place-holders for the after rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceAfter($message, $field, $rule, $parameters)
    {
        return $this->replaceBefore($message, $field, $rule, $parameters);
    }

    /**
     * Replace all place-holders for the after_or_equal rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceAfterOrEqual($message, $field, $rule, $parameters)
    {
        return $this->replaceBefore($message, $field, $rule, $parameters);
    }

    /**
     * Replace all place-holders for the date_equals rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceDateEquals($message, $field, $rule, $parameters)
    {
        return $this->replaceBefore($message, $field, $rule, $parameters);
    }

    /**
     * Replace all place-holders for the ends_with rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceEndsWith($message, $field, $rule, $parameters)
    {
        foreach ($parameters as &$parameter) {
            $parameter = $this->getDisplayableValue($field, $parameter);
        }

        return str_replace(':values', implode(', ', $parameters), $message);
    }

    /**
     * Replace all place-holders for the starts_with rule.
     *
     * @param  string  $message
     * @param  string  $field
     * @param  string  $rule
     * @param  array  $parameters
     * @return string
     */
    protected function replaceStartsWith($message, $field, $rule, $parameters)
    {
        foreach ($parameters as &$parameter) {
            $parameter = $this->getDisplayableValue($field, $parameter);
        }

        return str_replace(':values', implode(', ', $parameters), $message);
    }
}
