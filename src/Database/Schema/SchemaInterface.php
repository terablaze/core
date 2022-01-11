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

interface SchemaInterface
{
    public function id(string $column = 'id', string $type = 'INT', ?int $length = null): IdField;

    public function bigId(string $column = 'id', string $type = 'BIGINT', ?int $length = null): IdField;

    public function tinyInt(string $column, int $length = 4): IntField;

    public function smallInt(string $column, int $length = 6): IntField;

    public function mediumInt(string $column, int $length = 9): IntField;

    public function int(string $column, int $length = 11): IntField;

    public function bigInt(string $column, int $length = 21): IntField;

    public function bool(string $column): BoolField;

    public function dateTime(string $column): DateTimeField;

    public function date(string $column): DateTimeField;

    public function time(string $column): DateTimeField;

    public function year(string $column): DateTimeField;

    public function timestamp(string $column): IntField;

    public function float(string $column): FloatField;

    public function decimal(string $column, int $precision = 10, int $scale = 2): DecimalField;

    public function string(string $column, int $length = 255): StringField;

    public function text(string $column): TextField;

    public function mediumText(string $column): TextField;

    public function longText(string $column): TextField;

    /**
     * @param string $column
     * @param string[] $values
     * @return EnumField
     */
    public function enum(string $column, array $values): EnumField;

    /**
     * @param string $column
     * @return JsonField
     */
    public function json(string $column): JsonField;

    /**
     * @param string|string[] $column
     * @param string|null $name
     */
    public function primary($column, ?string $name = null): void;

    /**
     * @param string|string[] $column
     * @param string|null $name
     */
    public function index($column, ?string $name = null): void;

    /**
     * @param string|string[] $column
     * @param string|null $name
     */
    public function unique($column, ?string $name = null): void;

    /**
     * @param string|string[] $column
     * @param string|null $name
     */
    public function fullText($column, ?string $name = null): void;

    /**
     * @param string|string[] $columns
     * @param string|null $name
     * @return ForeignKey
     */
    public function foreign($columns, ?string $name = null): ForeignKey;

    /**
     * @param string $from
     * @param string $to
     */
    public function renameColumn(string $from, string $to): void;

    /**
     * @param string $from
     * @param string $to
     */
    public function renameIndex(string $from, string $to): void;

    /**
     * @param string|string[] $column
     * @return $this
     */
    public function dropColumn($column/*, string ...$columns*/): self;

    /**
     * @param string|string[] $index
     * @return $this
     */
    public function dropIndex($index/*, string ...$columns*/): self;

    /**
     * @param string|string[] $key
     * @return $this
     */
    public function dropForeign($key/*, string ...$columns*/): self;

    /**
     * @return void
     */
    public function build(): void;

    /**
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface;

    /**
     * @return string
     */
    public function getTable(): string;

    /**
     * @return static
     */
    public function setType(string $type): self;

    /**
     * @return string
     */
    public function getType(): string;

    /**
     * @return string|null
     */
    public function getRenameTo(): ?string;

    /**
     * @return Field[]
     */
    public function getFields(): array;

    /**
     * @return array<string, string|string[]>
     */
    public function getPrimaries(): array;

    /**
     * @return string[]
     */
    public function getIndexes(): array;

    /**
     * @return string[]
     */
    public function getUniques(): array;

    /**
     * @return string[]
     */
    public function getFullTexts(): array;

    /**
     * @return ForeignKey[]
     */
    public function getForeignKeys(): array;

    /**
     * @return string[]
     */
    public function getRenameColumns(): array;

    /**
     * @return string[]
     */
    public function getRenameIndexes(): array;

    /**
     * @return string[]
     */
    public function getDrops(): array;

    /**
     * @return string[]
     */
    public function getIndexDrops(): array;

    /**
     * @return string[]
     */
    public function getFkDrops(): array;

    public function renameTo(string $to): self;
}
