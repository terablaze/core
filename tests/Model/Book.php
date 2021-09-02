<?php

namespace Tests\TeraBlaze\Model;

use TeraBlaze\Ripana\ORM\Model;
use TeraBlaze\Ripana\ORM\NewModel;

class Book extends NewModel
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
     * @column/ManyToOne(name="author", type="\Tests\TeraBlaze\Model\User", length="100", default="Ibi`woye")
     */
    protected $author;
}
