<?php

namespace Terablaze\Database\ORM;

use function preg_replace;
use function strpos;
use function strrpos;
use function strtolower;
use function strtoupper;
use function substr;
use function trigger_error;

use const CASE_LOWER;
use const CASE_UPPER;
use const E_USER_DEPRECATED;

/**
 * Naming strategy implementing the underscore naming convention.
 * Converts 'MyEntity' to 'my_entity' or 'MY_ENTITY'.
 *
 *
 * @link    www.doctrine-project.org
 * @since   2.3
 * @author  Fabio B. Silva <fabio.bat.silva@gmail.com>
 */
class UnderscoreNamingStrategy implements NamingStrategyInterface
{
    private const DEFAULT_PATTERN      = '/(?<=[a-z])([A-Z])/';
    private const NUMBER_AWARE_PATTERN = '/(?<=[a-z0-9])([A-Z])/';

    /**
     * @var integer
     */
    private $case;

    /** @var string */
    private $pattern;

    /**
     * Underscore naming strategy construct.
     *
     * @param int $case CASE_LOWER | CASE_UPPER
     */
    public function __construct($case = CASE_LOWER, bool $numberAware = false)
    {
        if (! $numberAware) {
            @trigger_error(
                'Creating ' . self::class . ' without making it number '.
                'aware is deprecated and will be removed in Doctrine ORM 3.0.',
                E_USER_DEPRECATED
            );
        }

        $this->case    = $case;
        $this->pattern = $numberAware ? self::NUMBER_AWARE_PATTERN : self::DEFAULT_PATTERN;
    }

    /**
     * @return integer CASE_LOWER | CASE_UPPER
     */
    public function getCase()
    {
        return $this->case;
    }

    /**
     * Sets string case CASE_LOWER | CASE_UPPER.
     * Alphabetic characters converted to lowercase or uppercase.
     *
     * @param integer $case
     *
     * @return void
     */
    public function setCase($case)
    {
        $this->case = $case;
    }

    /**
     * {@inheritdoc}
     */
    public function classToTableName($className)
    {
        if (strpos($className, '\\') !== false) {
            $className = substr($className, strrpos($className, '\\') + 1);
        }

        return $this->underscore($className);
    }

    /**
     * {@inheritdoc}
     */
    public function propertyToColumnName($propertyName, $className = null)
    {
        return $this->underscore($propertyName);
    }

    /**
     * {@inheritdoc}
     */
    public function embeddedFieldToColumnName(
        $propertyName,
        $embeddedColumnName,
        $className = null,
        $embeddedClassName = null
    ) {
        return $this->underscore($propertyName) . '_' . $embeddedColumnName;
    }

    /**
     * {@inheritdoc}
     */
    public function referenceColumnName()
    {
        return $this->case === CASE_UPPER ?  'ID' : 'id';
    }

    /**
     * {@inheritdoc}
     */
    public function joinColumnName($propertyName, $className = null)
    {
        return $this->underscore($propertyName) . '_' . $this->referenceColumnName();
    }

    /**
     * {@inheritdoc}
     */
    public function joinTableName($sourceEntity, $targetEntity, $propertyName = null)
    {
        return $this->classToTableName($sourceEntity) . '_' . $this->classToTableName($targetEntity);
    }

    /**
     * {@inheritdoc}
     */
    public function joinKeyColumnName($entityName, $referencedColumnName = null)
    {
        return $this->classToTableName($entityName) . '_' .
                ($referencedColumnName ?: $this->referenceColumnName());
    }

    private function underscore(string $string): string
    {
        $string = preg_replace($this->pattern, '_$1', $string);

        if ($this->case === CASE_UPPER) {
            return strtoupper($string);
        }

        return strtolower($string);
    }
}
