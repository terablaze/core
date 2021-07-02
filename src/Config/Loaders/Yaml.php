<?php

namespace TeraBlaze\Config\Loaders;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml as YamlParser;
use TeraBlaze\Config\Exception\InvalidFileException;

class Yaml extends Loader
{
    /**
     * Retrieve the contents of a .yaml file and convert it to an array of
     * configuration options.
     *
     * @throws InvalidFileException
     *
     * @return array<string, mixed> Array of configuration options
     */
    public function getArray(): array
    {
        $yamlContent = file_get_contents($this->context);
        if (false === $yamlContent) {
            throw new InvalidFileException('Unable to load YAML file at ' . $this->context);
        }

        try {
            $parsed = YamlParser::parse($yamlContent);
        } catch (ParseException $e) {
            throw new InvalidFileException($e->getMessage());
        }

        if (! is_array($parsed)) {
            throw new InvalidFileException('Unable to parse invalid YAML file at ' . $this->context);
        }

        return $parsed;
    }
}
