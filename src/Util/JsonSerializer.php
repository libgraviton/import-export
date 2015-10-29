<?php
/**
 * a small extension to the Zumba\Util\JsonSerializer to allow deserializing mongodb legacy driver objects
 *
 * NOTE: this is only needed as we widely use the "mongodb legacy" driver (pecl/mongo) instead of the newer one
 * (pecl/mongodb) - if we once switch to the new driver, we should be able to ditch this.
 */

namespace Graviton\ImportExport\Util;

use Zumba\Util\JsonSerializer as BaseSerializer;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class JsonSerializer extends BaseSerializer
{
    /**
     * Convert the serialized array into an object
     *
     * @param aray $value
     * @return object
     * @throws Zumba\Exception\JsonSerializerException
     */
    protected function unserializeObject($value) {
        $className = $value[static::CLASS_IDENTIFIER_KEY];

        if ($className == 'MongoDate') {
            $obj = new \MongoDate($value['sec'], $value['usec']);
            $this->objectMapping[$this->objectMappingIndex++] = $obj;
            return $obj;
        }

        if ($className == 'MongoId') {
            $obj = new \MongoId($value['$id']);
            $this->objectMapping[$this->objectMappingIndex++] = $obj;
            return $obj;
        }

        return parent::unserializeObject($value);
    }
}
