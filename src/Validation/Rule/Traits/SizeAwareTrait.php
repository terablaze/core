<?php

namespace TeraBlaze\Validation\Rule\Traits;


use Psr\Http\Message\UploadedFileInterface;
use TeraBlaze\Support\StringMethods;

trait SizeAwareTrait
{
    protected array $messageModifier = ["presence" => "be", "unit" => "characters"];

    private function getSize($value): int
    {
        if (is_null($value)) {
            return 0;
        }
        if (is_numeric($value)) {
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