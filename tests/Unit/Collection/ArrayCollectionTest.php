<?php

namespace Tests\TeraBlaze\Unit\Collection;

use PHPUnit\Framework\TestCase;
use TeraBlaze\Collection\ArrayCollection;
use TeraBlaze\Collection\Exceptions\InvalidTypeException;
use TeraBlaze\Collection\Exceptions\TypeException;

class ArrayCollectionTest extends TestCase
{
    public function testCanBeCreated(): void
    {
        $data = ["TeraBlaze", "is", "great"];

        $arrayCollection = new ArrayCollection($data);
        $this->assertInstanceOf(ArrayCollection::class, $arrayCollection);

        $arrayCollection2 = new ArrayCollection($data, 'string');
        $this->assertInstanceOf(ArrayCollection::class, $arrayCollection2);

        $arrayCollection3 = new ArrayCollection([
            new TestClass(),
            new TestClass(),
            new TestClass(),
        ], TestClass::class);
        $this->assertInstanceOf(ArrayCollection::class, $arrayCollection3);
    }

    public function testExceptionThrownWithInvalidTypeConstraint(): void
    {
        $this->expectException(InvalidTypeException::class);
        $data = ["TeraBlaze", "is", "great"];
        new ArrayCollection($data, 'invalid');
    }

    public function testExceptionThrownWhenDataInvalid(): void
    {
        $data = ["TeraBlaze", 1, "great"];
        $this->expectException(TypeException::class);
        $arrayCollection = new ArrayCollection($data, 'string');
        $this->assertInstanceOf(ArrayCollection::class, $arrayCollection);
    }

    public function testAll(): void
    {
        $data = ["TeraBlaze", "is", "great"];
        $arrayCollection = new ArrayCollection($data, 'string');
        $this->assertEquals($data, $arrayCollection->all());
        $this->assertIsArray($arrayCollection->toArray());
    }

    public function testGetFirstAndLast(): void
    {
        $data = ["TeraBlaze", 1, "is", 2, "great", 3];
        $arrayCollection = new ArrayCollection($data);

        $this->assertEquals("TeraBlaze", $arrayCollection->first());

        $callbackFirst = $arrayCollection->first(fn ($item) => is_int($item));
        $this->assertEquals(1, $callbackFirst);

        $this->assertEquals(3, $arrayCollection->last());

        $callbackLast = $arrayCollection->last(fn ($item) => is_string($item));
        $this->assertEquals("great", $callbackLast);
    }

    public function testKey(): void
    {
        $data = ["TeraBlaze", 1, "is", 2, "great", 3];
        $arrayCollection = new ArrayCollection($data);

        $this->assertEquals("TeraBlaze", $arrayCollection->current());
        $this->assertEquals(0, $arrayCollection->key());
        $this->assertEquals(1, $arrayCollection->next());

        $this->assertEquals(1, $arrayCollection->current());
        $this->assertEquals(1, $arrayCollection->key());
        $this->assertEquals("is", $arrayCollection->next());

        $data2 = ["framework" => "TeraBlaze", "author" => "tomiwahq", "source" => "opensource"];
        $arrayCollection2 = new ArrayCollection($data2);

        $this->assertEquals("TeraBlaze", $arrayCollection2->current());
        $this->assertEquals("framework", $arrayCollection2->key());
        $this->assertEquals("tomiwahq", $arrayCollection2->next());

        $this->assertEquals("tomiwahq", $arrayCollection2->current());
        $this->assertEquals("author", $arrayCollection2->key());
        $this->assertEquals("opensource", $arrayCollection2->next());
    }

    public function testRemoveByKey(): void
    {
        $data = ["TeraBlaze", 1, "is", 2, "great", 3];
        $arrayCollection = new ArrayCollection($data);
        $this->assertNull($arrayCollection->remove("tom"));
        $this->assertEquals($data, $arrayCollection->all());
        $this->assertEquals("is", $arrayCollection->remove(2));
        unset($data[2]);
        $this->assertEquals($data, $arrayCollection->all());

        $data2 = ["framework" => "TeraBlaze", "author" => "tomiwahq", "source" => "opensource"];
        $arrayCollection2 = new ArrayCollection($data2);
        $this->assertNull($arrayCollection2->remove("tom"));
        $this->assertEquals($data2, $arrayCollection2->all());
        $this->assertEquals("tomiwahq", $arrayCollection2->remove("author"));
        $this->assertEquals(["framework" => "TeraBlaze", "source" => "opensource"], $arrayCollection2->all());

    }

    public function testRemoveElement(): void
    {
        $data = ["framework" => "TeraBlaze", "author" => "tomiwahq", "source" => "opensource"];
        $arrayCollection = new ArrayCollection($data);
        $this->assertFalse($arrayCollection->removeElement("Non-existent"));
        $this->assertEquals($data, $arrayCollection->all());

        $this->assertTrue($arrayCollection->removeElement("opensource"));
        $this->assertEquals(["framework" => "TeraBlaze", "author" => "tomiwahq"], $arrayCollection->all());
    }
}