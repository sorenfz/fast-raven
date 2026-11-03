<?php

namespace FastRaven\Tests\Components\Data;

use PHPUnit\Framework\TestCase;
use FastRaven\Components\Data\ConditionList;
use FastRaven\Components\Data\Condition;

class ConditionListTest extends TestCase
{
    public function testNewCreatesEmptyList(): void
    {
        $list = ConditionList::new();
        $this->assertEmpty($list->getRawData());
    }

    public function testNewWithItems(): void
    {
        $c1 = Condition::equals("a", 1);
        $c2 = Condition::equals("b", 2);
        
        $list = ConditionList::new([$c1, "invalid", $c2]); // "invalid" should be ignored

        $this->assertCount(2, $list->getRawData());
        $this->assertSame($c1, $list->get(0));
        $this->assertSame($c2, $list->get(1));
    }

    public function testAddAndGet(): void
    {
        $list = ConditionList::new();
        $c = Condition::equals("a", 1);
        
        $list->add($c);
        
        $this->assertCount(1, $list->getRawData());
        $this->assertSame($c, $list->get(0));
    }

    public function testRemove(): void
    {
        $c1 = Condition::equals("a", 1);
        $c2 = Condition::equals("b", 2);
        $list = ConditionList::new([$c1, $c2]);

        $list->remove(0);

        $this->assertNull($list->get(0));
        $this->assertSame($c2, $list->get(1));
    }

    public function testMerge(): void
    {
        $c1 = Condition::equals("a", 1);
        $c2 = Condition::equals("b", 2);
        
        $list1 = ConditionList::new([$c1]);
        $list2 = ConditionList::new([$c2]);

        $list1->merge($list2);

        $this->assertCount(2, $list1->getRawData());
        $this->assertSame($c1, $list1->get(0));
        $this->assertSame($c2, $list1->get(1));
    }

    public function testGetAllLeftValues(): void
    {
        $list = ConditionList::new([
            Condition::equals("col1", 1),
            Condition::greaterThan("col2", 5)
        ]);

        $lefts = $list->getAllLeftValues();
        
        $this->assertEquals(["col1", "col2"], $lefts);
    }

    public function testGetAllRightValues(): void
    {
        $list = ConditionList::new([
            Condition::equals("col1", 10),
            Condition::greaterThan("col2", 50)
        ]);

        $rights = $list->getAllRightValues();
        
        $this->assertEquals([10, 50], $rights);
    }

    public function testGetBindValuesFlattensAndSkipsLiterals(): void
    {
        $list = ConditionList::new([
            Condition::equals("a", 1),
            Condition::in("b", [2, 3]),
            Condition::isNull("c"),
            Condition::between("d", 4, 5),
            Condition::equals("e", true)
        ]);

        $this->assertEquals([1, 2, 3, 4, 5, 1], $list->getBindValues());
        $this->assertEquals([], ConditionList::new()->getBindValues());
    }
}
