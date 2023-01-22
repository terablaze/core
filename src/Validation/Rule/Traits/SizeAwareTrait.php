<?php

namespace Terablaze\Validation\Rule\Traits;


use Psr\Http\Message\UploadedFileInterface;
use Terablaze\Support\StringMethods;

trait SizeAwareTrait
{
    protected array $messageModifier = ["presence" => "be", "unit" => "characters"];

    private function getSize(string $field, $value): int
    {
        $hasNumeric = $this->validation->hasRule($field, $this->validation->numericRules);
        if (is_null($value)) {
            return 0;
        }
        if (is_numeric($value) && $hasNumeric) {
            $this->messageModifier["unit"] = '';
            return $value;
        }
        if (is_array($value)) {
            $this->messageModifier = ["presence" => "contain", "unit" => "elements"];
            return count($value);
        }
        if ($value instanceof \SplFileInfo || $value instanceof UploadedFileInterface) {
            $this->messageModifier["unit"] = "kilobytes";
            return $value->getSize() / 1024;
        }

        return StringMethods::length($value);
    }
}