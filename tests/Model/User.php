<?php

namespace Tests\TeraBlaze\Model;

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

//    /**
//     * @column/OneToMany(name="password", type="Tests\Model\Book", mappedBy="")
//     */
//    protected $book;

    /**
     * @column(name="used", type="boolean", nullable=false, default=neh)
     */
    protected $used;

    /**
     * @column(name="awards", type="int", nullable=false, default=200)
     */
    protected $awards;
}