<?php

namespace TeraBlaze\Validation\Rule\Traits;


use Psr\Http\Message\UploadedFileInterface;
use TeraBlaze\Support\StringMethods;

trait SizeAwareTrait
{
    protected array $messageModifier = ["presence" => "be", "unit" => "characters"];

    private function getSize(): int
    {
        if (is_null($this->value)) {
            return 0;
        }
        if (is_numeric($this->value)) {
            $this->messageModifier["unit"] = '';
            return $this->value;
        }
        if (is_array($this->value)) {
            $this->messageModifier = ["presence" => "contain", "unit" => "elements"];
            return count($this->value);
        }
        if ($this->value instanceof \SplFileInfo || $this->value instanceof UploadedFileInterface) {
            $this->messageModifier["unit"] = "kilobytes";
            return $this->value->getSize() / 1024;
        }

        return StringMethods::length($this->value);
    }
}