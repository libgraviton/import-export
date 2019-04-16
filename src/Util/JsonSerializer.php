<?php
/**
 * a small extension to the Zumba\Util\JsonSerializer to allow deserializing mongodb legacy driver objects
 *
 * NOTE: this is only needed as we widely use the "mongodb legacy" driver (pecl/mongo) instead of the newer one
 * (pecl/mongodb) - if we once switch to the new driver, we should be able to ditch this.
 */

namespace Graviton\ImportExport\Util;

use Zumba\JsonSerializer\Exception\JsonSerializerException;
use Zumba\JsonSerializer\JsonSerializer as BaseSerializer;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     http://swisscom.ch
 */
class JsonSerializer extends BaseSerializer
{
    /**
     * Convert the serialized array into an object
     *
     * @param mixed $value the value
     * @return object
     * @throws JsonSerializerException
     */
    protected function unserializeObject($value)
    {
        $className = $value[static::CLASS_IDENTIFIER_KEY];

        $obj = false;
        if ($className == 'MongoDate') {
            if (isset($value['usec'])) {
                $obj = new \MongoDate($value['sec'], $value['usec']);
            } else {
                $obj = new \MongoDate($value['sec']);
            }
        } elseif ($className == 'MongoId') {
            $thisId = null;
            if (isset($value['$id'])) {
                $thisId = $value['$id'];
            }
            if (isset($value['objectID']['oid'])) {
                $thisId = $value['objectID']['oid'];
            }

            if (is_null($thisId)) {
                throw new \LogicException('Could not deserialize MongoID instance, could not find $id!');
            }

            $obj = new \MongoId($thisId);
        }

        if ($obj !== false) {
            $this->objectMapping[$this->objectMappingIndex++] = $obj;
            return $obj;
        }

        return parent::unserializeObject($value);
    }

    /**
     * Extract the object data
     *
     * @param object          $value      obj
     * @param ReflectionClass $ref        ref
     * @param array           $properties props
     *
     * @return array
     */
    protected function extractObjectData($value, $ref, $properties)
    {
        $data = array();
        foreach ($properties as $property) {
            try {
                if (class_exists('\MongoDB\BSON\ObjectId') && $value instanceof \MongoDB\BSON\ObjectId) {
                    $data['oid'] = $value->__toString();
                } else {
                    $propRef = $ref->getProperty($property);
                    $propRef->setAccessible(true);
                    $data[$property] = $propRef->getValue($value);
                }
            } catch (\ReflectionException $e) {
                $data[$property] = $value->$property;
            }
        }
        return $data;
    }
}
