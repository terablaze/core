<?php

namespace TeraBlaze\Ripana\ORM\Column;

use TeraBlaze\Ripana\ORM\Model;

class ManyToOne extends Column
{
    public function getColumn($property)
    {
        $column = $this->propertyMeta['@column/ManyToOne'] ?? $this->propertyMeta['@column\ManyToOne'];
        $referenceType = $column['type'] ?? $this->getFirst($this->propertyMeta, '@type');
        /** @var Model $referenceModel */
        $referenceModel = new $referenceType();

        $referenceProperty = $referenceModel->_getPrimaryColumn();

        $name = $column['name'] ?? $this->getFirst($this->propertyMeta, '@name') ?? $property;
        $type = $referenceProperty['type'];
        $index = !empty($this->propertyMeta['@index']);
        $length = $referenceProperty['length'] ?? $this->getFirst($this->propertyMeta, '@length');
        $default = $column['default'] ?? $this->getFirst($this->propertyMeta, '@default');

        $nullableValue = $column['nullable'] ?? $this->getFirst($this->propertyMeta, '@nullable') ?? true;
        $nullable = in_array(mb_strtolower($nullableValue), Model::falsy) ? false : true;

        return [
            'raw' => $property,
            'name' => $name,
            'primary' => false,
            'type' => $type,
            'length' => $length,
            'index' => $index,
            'default' => $default,
            'nullable' => $nullable,
            'foreignKey' => true,
            'table' => $referenceModel->_getTable(),
            'foreignKeyName' => $referenceProperty['name'],
            'foreignClass' => $column['type'],
        ];
    }
}
