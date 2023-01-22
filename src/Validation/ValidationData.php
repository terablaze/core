<?php

namespace Terablaze\Validation;

use Terablaze\Support\ArrayMethods;
use Terablaze\Support\Helpers;
use Terablaze\Support\StringMethods;

class ValidationData
{
    /**
     * Initialize and gather data for the given field.
     *
     * @param  string  $field
     * @param  array  $masterData
     * @return array
     */
    public static function initializeAndGatherData($field, $masterData)
    {
        $data = ArrayMethods::dot(static::initializeFieldOnData($field, $masterData));

        return array_merge($data, static::extractValuesForWildcards(
            $masterData, $data, $field
        ));
    }

    /**
     * Gather a copy of the field data filled with any missing fields.
     *
     * @param  string  $field
     * @param  array  $masterData
     * @return array
     */
    protected static function initializeFieldOnData($field, $masterData)
    {
        $explicitPath = static::getLeadingExplicitFieldPath($field);

        $data = static::extractDataFromPath($explicitPath, $masterData);

        if (! StringMethods::contains($field, '*') || StringMethods::endsWith($field, '*')) {
            return $data;
        }

        return Helpers::dataSet($data, $field, null, true);
    }

    /**
     * Get all the exact field values for a given wildcard field.
     *
     * @param  array  $masterData
     * @param  array  $data
     * @param  string  $field
     * @return array
     */
    protected static function extractValuesForWildcards($masterData, $data, $field)
    {
        $keys = [];

        $pattern = str_replace('\*', '[^\.]+', preg_quote($field));

        foreach ($data as $key => $value) {
            if ((bool) preg_match('/^'.$pattern.'/', $key, $matches)) {
                $keys[] = $matches[0];
            }
        }

        $keys = array_unique($keys);

        $data = [];

        foreach ($keys as $key) {
            $data[$key] = ArrayMethods::get($masterData, $key);
        }

        return $data;
    }

    /**
     * Extract data based on the given dot-notated path.
     *
     * Used to extract a sub-section of the data for faster iteration.
     *
     * @param  string  $field
     * @param  array  $masterData
     * @return array
     */
    public static function extractDataFromPath($field, $masterData)
    {
        $results = [];

        $value = ArrayMethods::get($masterData, $field, '__missing__');

        if ($value !== '__missing__') {
            ArrayMethods::set($results, $field, $value);
        }

        return $results;
    }

    /**
     * Get the explicit part of the field name.
     *
     * E.g. 'foo.bar.*.baz' -> 'foo.bar'
     *
     * Allows us to not spin through all of the flattened data for some operations.
     *
     * @param  string  $field
     * @return string
     */
    public static function getLeadingExplicitFieldPath($field)
    {
        return rtrim(explode('*', $field)[0], '.') ?: null;
    }
}
