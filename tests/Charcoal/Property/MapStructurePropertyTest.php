<?php

namespace Charcoal\Tests\Property;

use \Charcoal\Property\MapStructureProperty;

/**
 * ## TODOs
 * - 2015-03-12:
 */
class MapStructurePropertyTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var MapStructureProperty $obj
     */
    public $obj;

    public function setUp()
    {
        $this->obj = new MapStructureProperty();
    }

    public function testType()
    {
        $obj = $this->obj;
        $this->assertEquals('map-structure', $obj->type());
    }
}