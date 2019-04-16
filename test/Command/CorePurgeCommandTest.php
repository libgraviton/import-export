<?php
/**
 * check core purge command
 */

namespace Graviton\ImportExportTest\Command;

use Graviton\ImportExport\Command\CorePurgeCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     http://swisscom.ch
 */
class CorePurgeCommandTest extends TestCase
{

    /**
     * @var CommandTester
     */
    private $cmdTester;

    /**
     * test purge command calls
     *
     * @return void
     */
    public function testCalls()
    {
        $this->expectException('\LogicException');

        $clientMock = $this->getMockBuilder('\MongoClient')->disableOriginalConstructor()->getMock();

        $collection = $this->getMockBuilder('\MongoCollection')->disableOriginalConstructor()->getMock();
        $collection->expects($this->once())->method('drop')->willReturn(true);

        $collectionTwo = $this->getMockBuilder('\MongoCollection')->disableOriginalConstructor()->getMock();
        $collectionTwo->expects($this->once())->method('drop')->willReturn(true);

        $dbMock = $this->getMockBuilder('\MongoDB')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->once())->method('listCollections')->willReturn(
            [$collection, $collectionTwo]
        );

        $clientMock->expects($this->any())
            ->method('selectDB')
            ->with('db')
            ->willReturn($dbMock);

        $sut = new CorePurgeCommand();
        $sut->setClient($clientMock);

        $app = new Application();
        $app->add($sut);

        $cmd = $app->find('graviton:core:purge');

        $this->cmdTester = new CommandTester($cmd);

        $this->cmdTester->execute(
            [
                'yes' => 'yes'
            ]
        );

        // see if exception comes..
        $this->cmdTester->execute(
            [
                'yes' => 'no'
            ]
        );
    }
}
