<?php

namespace Tests\Model;

use TeraBlaze\Ripana\ORM\Model;

class Book extends Model
{
    /**
     * @column(type="autonumber", name="id")
     * @primary
     */
    public $bookId;

    /**
     * @column(name="name", type="text", length="100", default="Tom'iwa")
     */
    protected $name;

    /**
     * @column(name="last_name", type="text", length="100", default="Ibi`woye")
     */
    protected $author;
}