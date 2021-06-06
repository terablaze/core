<?php

namespace Tests\TeraBlaze\Model;

use TeraBlaze\Ripana\ORM\Model;

class User extends Model
{
    /**
     * @column(type="autonumber", name="id")
     * @primary
     */
    public int $userId;

    /**
     * @column(name="first_name", type="text", length="100", default="Tom'iwa")
     */
    public string $firstName;

    /**
     * @column(name="last_name", type="text", length="100", default="Ibi`woye")
     */
    public string $lastName;

    /**
     * @column(name="email", type="text", length=100, default="tomiwahq@gmail.com")
     */
    protected string $email;

//    /**
//     * @column/OneToMany(name="password", type="Tests\Model\Book", mappedBy="")
//     */
//    protected $book;

    /**
     * @column(name="used", type="boolean", nullable=false, default=neh)
     */
    protected bool $used;

    /**
     * @column(name="awards", type="int", nullable=false, default=200)
     */
    protected int $awards;

    /**
     * @column(name="created_on", type="datetime", nullable=false, default="NOW()")
     */
    protected \DateTime $createdOn;

    /**
     * @return int
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * @param int $userId
     * @return User
     */
    public function setUserId(int $userId): User
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * @return string
     */
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    /**
     * @param string $firstName
     * @return User
     */
    public function setFirstName(string $firstName): User
    {
        $this->firstName = $firstName;
        return $this;
    }

    /**
     * @return string
     */
    public function getLastName(): string
    {
        return $this->lastName;
    }

    /**
     * @param string $lastName
     * @return User
     */
    public function setLastName(string $lastName): User
    {
        $this->lastName = $lastName;
        return $this;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param string $email
     * @return User
     */
    public function setEmail(string $email): User
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @return bool
     */
    public function isUsed(): bool
    {
        return $this->used;
    }

    /**
     * @param bool $used
     * @return User
     */
    public function setUsed(bool $used): User
    {
        $this->used = $used;
        return $this;
    }

    /**
     * @return int
     */
    public function getAwards(): int
    {
        return $this->awards;
    }

    /**
     * @param int $awards
     * @return User
     */
    public function setAwards(int $awards): User
    {
        $this->awards = $awards;
        return $this;
    }
}
