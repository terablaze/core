<?php

namespace TeraBlaze\Database\Schema;

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

interface SchemaBuilderInterface
{
    public function id(string $column = 'id', int $length = 11): IdField;

    public function bigId(string $column = 'id', int $length = 21): IdField;

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

    public function float(string $column, int $precision = 10, int $scale = 2): FloatField;

    public function decimal(string $column, int $precision = 10, int $scale = 2): DecimalField;

    public function string(string $column, int $length = 255): StringField;

    public function text(string $column): TextField;

    public function mediumText(string $column): TextField;

    public function longText(string $column): TextField;

    public function enum(string $column, array $values): EnumField;

    public function json(string $column): JsonField;

    public function index(array $columns, $name = null): void;

    public function unique(array $columns, $name = null): void;

    public function fullText(array $columns, $name = null): void;

    public function foreign($column, ?string $name = null): ForeignKey;

    public function renameColumn(string $from, string $to): void;

    public function renameIndex(string $from, string $to): void;

    public function dropColumn($column = null/*, string ...$columns*/): self;

    public function dropIndex($index = null/*, string ...$columns*/): self;

    public function dropForeign($key = null/*, string ...$columns*/): self;

    public function build();
}
