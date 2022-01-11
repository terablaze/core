<?php

namespace TeraBlaze\Database\Schema;

use TeraBlaze\Database\Connection\ConnectionInterface;
use TeraBlaze\Database\Schema\Field\BoolField;
use TeraBlaze\Database\Schema\Field\DateTimeField;
use TeraBlaze\Database\Schema\Field\DecimalField;
use TeraBlaze\Database\Schema\Field\EnumField;
use TeraBlaze\Database\Schema\Field\Field;
use TeraBlaze\Database\Schema\Field\FloatField;
use TeraBlaze\Database\Schema\Field\IdField;
use TeraBlaze\Database\Schema\Field\IntField;
use TeraBlaze\Database\Schema\Field\JsonField;
use TeraBlaze\Database\Schema\Field\StringField;
use TeraBlaze\Database\Schema\Field\TextField;
use TeraBlaze\Support\ArrayMethods;

abstract class AbstractSchema implements SchemaInterface
{
    public ConnectionInterface $connection;

    protected string $table;
    protected string $type;
    protected ?string $renameTo;

    /** @var Field[] $fields */
    protected array $fields = [];

    /** @var array<string, mixed> */
    protected array $primaries = [];
    /** @var array<string, mixed> */
    protected array $indexes = [];
    /** @var array<string, mixed> */
    protected array $uniques = [];
    /** @var array<string, mixed> */
    protected array $fullTexts = [];
    /** @var ForeignKey[] */
    protected array $foreignKeys = [];

    /** @var string[] */
    protected array $renameColumns = [];
    /** @var string[] */
    protected array $renameIndexes = [];

    /** @var string[] */
    protected array $drops = [];
    /** @var string[] */
    protected array $indexDrops = [];
    /** @var string[] */
    protected array $fkDrops = [];

    public function __construct(ConnectionInterface $connection, string $table)
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function id(string $column = 'id', string $type = 'INT', ?int $length = null): IdField
    {
        return $this->fields[] = new IdField($column, $type, $length);
    }

    public function bigId(string $column = 'id', string $type = 'BIGINT', ?int $length = null): IdField
    {
        return $this->fields[] = new IdField($column, $type, $length);
    }

    public function tinyInt(string $column, int $length = 4): IntField
    {
        return $this->fields[] = new IntField($column, 'TINYINT', $length);
    }

    public function smallInt(string $column, int $length = 6): IntField
    {
        return $this->fields[] = new IntField($column, 'SMALLINT', $length);
    }

    public function mediumInt(string $column, int $length = 9): IntField
    {
        return $this->fields[] = new IntField($column, 'MEDIUMINT', $length);
    }

    public function int(string $column, int $length = 11): IntField
    {
        return $this->fields[] = new IntField($column, 'INT', $length);
    }

    public function bigInt(string $column, int $length = 21): IntField
    {
        return $this->fields[] = new IntField($column, 'BIGINT', $length);
    }

    public function bool(string $column): BoolField
    {
        return $this->fields[] = new BoolField($column);
    }

    public function dateTime(string $column): DateTimeField
    {
        return $this->fields[] = new DateTimeField($column, "DATETIME");
    }

    public function date(string $column): DateTimeField
    {
        return $this->fields[] = new DateTimeField($column, "DATE");
    }

    public function time(string $column): DateTimeField
    {
        return $this->fields[] = new DateTimeField($column, "TIME");
    }

    public function year(string $column): DateTimeField
    {
        return $this->fields[] = new DateTimeField($column, "YEAR");
    }

    public function timestamp(string $column): IntField
    {
        return $this->fields[] = new IntField($column, 'BIGINT', 21);
    }

    public function float(string $column): FloatField
    {
        return $this->fields[] = new FloatField($column);
    }

    public function decimal(string $column, int $precision = 10, int $scale = 2): DecimalField
    {
        return $this->fields[] = new DecimalField($column, $precision, $scale);
    }

    public function string(string $column, int $length = 255): StringField
    {
        return $this->fields[] = new StringField($column, "VARCHAR", $length);
    }

    public function text(string $column): TextField
    {
        return $this->fields[] = new TextField($column, "TEXT");
    }

    public function mediumText(string $column): TextField
    {
        return $this->fields[] = new TextField($column, "MEDIUMTEXT");
    }

    public function longText(string $column): TextField
    {
        return $this->fields[] = new TextField($column, "LONGTEXT");
    }

    public function enum(string $column, array $values): EnumField
    {
        return $this->fields[] = new EnumField($column, $values);
    }

    public function json(string $column): JsonField
    {
        return $this->fields[] = new JsonField($column);
    }

    public function primary($column, ?string $name = null): void
    {
        $columns = ArrayMethods::wrap($column);
        $this->primaries['name'] = $this->primaries['name'] ?? $name;
        $this->primaries['columns'] = array_merge($this->primaries['columns'] ?? [], ArrayMethods::wrap($columns));
    }

    public function index($column, ?string $name = null): void
    {
        $columns = ArrayMethods::wrap($column);
        $this->indexes[$name ?? "INDEX_" . implode('_', $columns)] = $columns;
    }

    public function unique($column, ?string $name = null): void
    {
        $columns = ArrayMethods::wrap($column);
        $this->uniques[$name ?? "UNIQUE_" . implode('_', $columns)] = $columns;
    }

    public function fullText($column, ?string $name = null): void
    {
        $columns = ArrayMethods::wrap($column);
        $this->fullTexts[$name ?? "FULLTEXT_" . implode('_', $columns)] = $columns;
    }

    public function foreign($columns, ?string $name = null): ForeignKey
    {
        return $this->foreignKeys[] = new ForeignKey($columns, $name);
    }

    public function renameColumn(string $from, string $to): void
    {
        $this->renameColumns[$from] = $to;
    }

    public function renameIndex(string $from, string $to): void
    {
        $this->renameIndexes[$from] = $to;
    }

    /**
     * @param string|string[] $column
     * @return $this
     */
    public function dropColumn($column/*, string ...$columns*/): self
    {
        $columns = is_array($column) ? $column : func_get_args();
        foreach ($columns as $column) {
            $this->drops[] = $column;
        }
        return $this;
    }

    public function dropIndex($index = null/*, string ...$columns*/): self
    {
        $indexes = is_array($index) ? $index : func_get_args();
        foreach ($indexes as $index) {
            $this->indexDrops[] = $index;
        }
        return $this;
    }

    public function dropForeign($key = null/*, string ...$columns*/): self
    {
        $keys = is_array($key) ? $key : func_get_args();
        foreach ($keys as $key) {
            $this->fkDrops[] = $key;
        }
        return $this;
    }

    /**
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @return static
     */
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string|null
     */
    public function getRenameTo(): ?string
    {
        return $this->renameTo;
    }

    /**
     * @return Field[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return string[]
     */
    public function getPrimaries(): array
    {
        return $this->primaries;
    }

    /**
     * @return string[]
     */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    /**
     * @return string[]
     */
    public function getUniques(): array
    {
        return $this->uniques;
    }

    /**
     * @return string[]
     */
    public function getFullTexts(): array
    {
        return $this->fullTexts;
    }

    /**
     * @return ForeignKey[]
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    /**
     * @return string[]
     */
    public function getRenameColumns(): array
    {
        return $this->renameColumns;
    }

    /**
     * @return string[]
     */
    public function getRenameIndexes(): array
    {
        return $this->renameIndexes;
    }

    /**
     * @return string[]
     */
    public function getDrops(): array
    {
        return $this->drops;
    }

    /**
     * @return string[]
     */
    public function getIndexDrops(): array
    {
        return $this->indexDrops;
    }

    /**
     * @return string[]
     */
    public function getFkDrops(): array
    {
        return $this->fkDrops;
    }

    public function renameTo(string $to): self
    {
        $this->renameTo = $to;
        return $this;
    }
}
