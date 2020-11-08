<?php

namespace TeraBlaze\Ripana\ORM\Column;

use TeraBlaze\Ripana\ORM\Model;

class OneToOne extends Column
{
    public function getColumn($property)
    {
        $column = $this->propertyMeta['@column/OneToOne'] ?? $this->propertyMeta['@column\OneToOne'];
        $referenceType = $column['type'] ?? $this->getFirst($this->propertyMeta, '@type');
        /** @var Model $referenceModel */
        $referenceModel = new $referenceType();

        $referenceProperty = $referenceModel->getPrimaryColumn();

        $name = $column['name'] ?? $this->getFirst($this->propertyMeta, '@name') ?? $property;
        $type = $referenceProperty['type'];
        $index = !empty($this->propertyMeta['@index']);
        $validate = !empty($this->propertyMeta['@validate']) ? $this->propertyMeta['@validate'] : false;
        $length = $referenceProperty['length'] ?? $this->getFirst($this->propertyMeta, '@length');
        $default = $column['default'] ?? $this->getFirst($this->propertyMeta, '@default');
        $label = $column['label'] ?? $this->getFirst($this->propertyMeta, '@label');

        $nullableValue = $column['nullable'] ?? $this->getFirst($this->propertyMeta, '@nullable') ?? true;
        $nullable = in_array(mb_strtolower($nullableValue), Model::falsy) ? false : true;

        return [
            'raw' => $property,
            'name' => $name,
            'primary' => false,
            'type' => $type,
            'length' => $length,
            'index' => $index,
            'validate' => $validate,
            'label' => $label,
            'default' => $default,
            'nullable' => $nullable,
            'foreignKey' => true,
            'table' => $referenceModel->getTable(),
            'foreignKeyName' => $referenceProperty['name'],
            'foreignClass' => $column['type'],
        ];
    }
}
