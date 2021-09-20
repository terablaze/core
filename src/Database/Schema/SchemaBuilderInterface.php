<?php

namespace TeraBlaze\Database\Schema;

use TeraBlaze\Database\Schema\Field\BoolField;
use TeraBlaze\Database\Schema\Field\DateTimeField;
use TeraBlaze\Database\Schema\Field\FloatField;
use TeraBlaze\Database\Schema\Field\IdField;
use TeraBlaze\Database\Schema\Field\IntField;
use TeraBlaze\Database\Schema\Field\StringField;
use TeraBlaze\Database\Schema\Field\TextField;

interface SchemaBuilderInterface
{
    public function bool(string $name): BoolField;

    public function dateTime(string $name): DateTimeField;

    public function float(string $name): FloatField;

    public function id(string $name = 'id'): IdField;

    public function int(string $name): IntField;

    public function bigInt(string $name): IntField;

    public function string(string $name): StringField;

    public function text(string $name): TextField;

    public function execute();

    public function dropColumn(string $name): self;
}
