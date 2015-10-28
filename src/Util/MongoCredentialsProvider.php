<?php
/**
 * small helper to read our VCAP_SERVICES in cf
 */

namespace Graviton\ImportExport\Util;

use Flow\JSONPath\JSONPath;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class MongoCredentialsProvider
{

    /**
     * gets connection params from ENV if available
     *
     * @return array connection params
     */
    public static function getConnection()
    {
        // defaults
        $ret = ['uri' => null, 'db' => 'db'];

        if (isset($_ENV['VCAP_SERVICES'])) {
            $path = new JSONPath(json_decode($_ENV['VCAP_SERVICES'], true));
            $ret['uri'] = $path->find('$..mongodb[0].*.uri')->first();
            $ret['db'] = $path->find('$..mongodb[0].*.database')->first();
        }

        return $ret;
    }
}
