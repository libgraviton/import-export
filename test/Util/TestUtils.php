<?php
/**
 * assorted utils for testing
 */

namespace Graviton\ImportExportTest\Util;

use Monolog\Handler\TestHandler;
use Monolog\Logger;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     http://swisscom.ch
 */
class TestUtils
{

    /**
     * get a logger for testing
     *
     * @return Logger
     */
    public static function getTestingLogger()
    {
        $logger = new Logger("test");
        $handler = new TestHandler();
        $logger->pushHandler($handler);
        return $logger;
    }

    /**
     * gets all lines from the handler in one string
     *
     * @param TestHandler $handler handler
     *
     * @return string all lines
     */
    public static function getFullStringFromLog(TestHandler $handler)
    {
        $entries = array_map(
            function ($val) {
                return $val['formatted'];
            },
            $handler->getRecords()
        );
        return implode(PHP_EOL, $entries);
    }
}
