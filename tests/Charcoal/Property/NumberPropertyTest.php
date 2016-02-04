<?php

namespace Charcoal\Tests\Property;

use \Charcoal\Property\NumberProperty;

/**
 *
 */
class NumberPropertyTest extends \PHPUnit_Framework_TestCase
{
    /**
    * @var NumberProperty $obj
    */
    public $obj;

    public function setUp()
    {
        $this->obj = new NumberProperty();
    }

    public function testType()
    {
        $obj = $this->obj;
        $this->assertEquals('number', $obj->type());
    }
}