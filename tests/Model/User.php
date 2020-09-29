<?php

namespace Tests\Model;

use TeraBlaze\Ripana\ORM\Model;

class User extends Model
{
    /**
     * @column(type="autonumber", name="id")
     * @primary
     */
    public $userId;

    /**
     * @column(name="first_name", type="text", length="100", default="Tom'iwa")
     */
    protected $firstName;

    /**
     * @column(name="last_name", type="text", length="100", default="Ibi`woye")
     */
    protected $lastName;

    /**
     * @column(name="email", type="text", length=100, default="tomiwahq@gmail.com")
     */
    protected $email;

    /**
     * @column/OneToMany(name="password", type="Tests\Model\Book")
     */
    protected $book;
}