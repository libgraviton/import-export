<?php
/**
 * check core purge command
 */

namespace Graviton\ImportExport\Tests\Command;

use Graviton\ImportExport\Command\CorePurgeCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class CorePurgeCommandTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var CommandTester
     */
    private $cmdTester;

    /**
     * test purge command calls
     *
     * @expectedException \LogicException
     * @return void
     */
    public function testCalls()
    {
        $clientMock = $this->getMockBuilder('\MongoClient')->disableOriginalConstructor()->getMock();

        $collection = $this->getMockBuilder('\MongoCollection')->disableOriginalConstructor()->getMock();
        $collection->expects($this->once())->method('drop')->willReturn(true);

        $collectionTwo = $this->getMockBuilder('\MongoCollection')->disableOriginalConstructor()->getMock();
        $collectionTwo->expects($this->once())->method('drop')->willReturn(true);

        $dbMock = $this->getMockBuilder('\MongoDb')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->once())->method('listCollections')->willReturn(
            [$collection, $collectionTwo]
        );

        $clientMock->db = $dbMock;

        $sut = new CorePurgeCommand(
            'db'
        );
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
