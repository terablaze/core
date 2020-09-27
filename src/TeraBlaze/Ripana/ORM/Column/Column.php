<?php

namespace TeraBlaze\Ripana\ORM\Column;

use TeraBlaze\Ripana\ORM\Model;

class Column
{
    public $propertyMeta;

    public function __construct($propertyMeta)
    {
        $this->propertyMeta = $propertyMeta;
    }

    public function getColumn($property)
    {
        $primary = !empty($this->propertyMeta['@primary']);
        $index = !empty($this->propertyMeta['@index']);
        $readwrite = !empty($this->propertyMeta['@readwrite']);
        $read = !empty($this->propertyMeta['@read']) || $readwrite;
        $write = !empty($this->propertyMeta['@write']) || $readwrite;
        $validate = !empty($this->propertyMeta['@validate']) ? $this->propertyMeta['@validate'] : false;

        $column = $this->propertyMeta['@column'];
        $name = $column['name'] ?? $this->getFirst($this->propertyMeta, '@name') ?? $property;
        $type = $column['type'] ?? $this->getFirst($this->propertyMeta, '@type');
        $length = $column['length'] ?? $this->getFirst($this->propertyMeta, '@length');
        $default = $column['default'] ?? $this->getFirst($this->propertyMeta, '@default');
        $label = $column['label'] ?? $this->getFirst($this->propertyMeta, '@label');

        $nullableValue = $column['nullable'] ?? $this->getFirst($this->propertyMeta, '@nullable') ?? true;
        $nullable = in_array(mb_strtolower($nullableValue), Model::falsy) ? false : true;

        return [
            'raw' => $property,
            'name' => $name,
            'primary' => $primary,
            'type' => $type,
            'length' => $length,
            'index' => $index,
            'read' => $read,
            'write' => $write,
            'validate' => $validate,
            'label' => $label,
            'default' => $default,
            'nullable' => $nullable,
        ];
    }

    protected function getFirst(array $array, string $key)
    {
        if (!empty($array[$key]) && sizeof($array[$key]) == 1) {
            return $array[$key][0];
        }
        return null;
    }
}
