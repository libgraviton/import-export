<?php
/**
 * test our jsonserializer extension
 */

namespace Graviton\ImportExportTest\Util;

use Graviton\ImportExport\Util\JsonSerializer;
use PHPUnit\Framework\TestCase;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     http://swisscom.ch
 */
class JsonSerializerTest extends TestCase
{

    /**
     * see if mongoid/mongodate can be properly unserialized again..
     *
     * @return void
     */
    public function testMongoHandling()
    {
        $origClass = new \stdClass();
        $origClass->id = 'Class';
        $origClass->mongoId = new \MongoId('5a5e05679ca9b2108b5e2ac9');
        $origClass->mongoDate = new \MongoDate();

        $serializer = new JsonSerializer();
        $serialized = $serializer->serialize($origClass);
        $class = $serializer->unserialize($serialized);

        $this->assertEquals($origClass, $class);
    }
}
