<?php

namespace TeraBlaze\Database\ORM\Column;

use TeraBlaze\Database\ORM\LegacyModel;

class OneToMany extends Column
{
    public function getColumn($property)
    {
        $column = $this->propertyMeta['@column/OneToMany'] ?? $this->propertyMeta['@column\OneToMany'];
        $referenceType = $column['type'] ?? $this->getFirst($this->propertyMeta, '@type');
        /** @var LegacyModel $referenceModel */
        $referenceModel = new $referenceType();

        $referenceProperty = $referenceModel->getPrimaryColumn();

        $name = $column['name'] ?? $this->getFirst($this->propertyMeta, '@name') ?? $property;
        $type = $referenceProperty['type'];
        $index = !empty($this->propertyMeta['@index']);
        $length = $referenceProperty['length'] ?? $this->getFirst($this->propertyMeta, '@length');
        $default = $column['default'] ?? $this->getFirst($this->propertyMeta, '@default');
        $nullableValue = $column['nullable'] ?? $this->getFirst($this->propertyMeta, '@nullable') ?? true;
        $nullable = in_array(mb_strtolower($nullableValue), LegacyModel::falsy) ? false : true;

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
            'table' => $referenceModel->getTable(),
            'foreignKeyName' => $referenceProperty['name'],
            'foreignClass' => $column['type'],
        ];
    }
}
