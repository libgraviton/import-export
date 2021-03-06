<?php
/**
 * test mongocredential provider
 */

namespace Graviton\ImportExportTest\Util;

use Graviton\ImportExport\Util\MongoCredentialsProvider;
use PHPUnit\Framework\TestCase;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     http://swisscom.ch
 */
class MongoCredentialsProviderTest extends TestCase
{
    /**
     * check fromInput method
     *
     * @dataProvider fromInputProvider
     *
     * @param string $uri connection string
     * @param string $db  expected db name
     *
     * @return void
     */
    public function testMongoHandling($uri, $db)
    {
        $inputMock = $this->createMock('Symfony\Component\Console\Input\InputInterface');
        $inputMock->expects($this->once())
            ->method('getOption')
            ->with('mongodb')
            ->will($this->returnValue($uri));

        $this->assertEquals(['uri' => $uri, 'db' => $db], MongoCredentialsProvider::fromInput($inputMock));
    }

    /**
     * @return array
     */
    public function fromInputProvider()
    {
        return [
            ['mongodb://localhost:27017', 'db'],
            ['mongodb://localhost:27017/', 'db'],
            ['mongodb://localhost:27017/foo', 'foo'],
            ['mongodb://localhost:27017/foo?db=bar', 'bar']
        ];
    }
}
