<?php
/**
 * check core import command
 */

namespace Graviton\ImportExportTest\Command;

use Graviton\ImportExport\Command\CoreImportCommand;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Webuni\FrontMatter\FrontMatter;
use Graviton\ImportExport\Util\JsonSerializer;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     http://swisscom.ch
 */
class CoreImportCommandTest extends TestCase
{

    /**
     * @var CommandTester
     */
    private $cmdTester;

    /**
     * @var Filesystem
     */
    private $fs;

    /**
     * @var string
     */
    private $sourceDir;

    /**
     * @var array
     */
    private $saves = [];

    /**
     * prepare our mocks and cmdtester
     *
     * @return void
     */
    public function setUp()
    {
        $clientMock = $this->getMockBuilder('\MongoClient')->disableOriginalConstructor()->getMock();

        $collectionMock = $this->getMockBuilder('\MongoCollection')->disableOriginalConstructor()->getMock();
        $collectionMock->method('save')->will($this->returnCallback(array($this,'saveCollectionCallback')));

        $dbMock = $this->getMockBuilder('\MongoDB')->disableOriginalConstructor()->getMock();
        $dbMock->method('selectCollection')->willReturn($collectionMock);

        $clientMock->expects($this->any())
            ->method('selectDB')
            ->with('db')
            ->willReturn($dbMock);

        $sut = new CoreImportCommand(
            new Logger("test"),
            new FrontMatter(),
            new JsonSerializer(),
            new Finder()
        );
        $sut->setClient($clientMock);

        $app = new Application();
        $app->add($sut);

        $cmd = $app->find('graviton:core:import');

        $this->cmdTester = new CommandTester($cmd);

        $this->fs = new Filesystem();
        $this->sourceDir = __DIR__.DIRECTORY_SEPARATOR.'fixtures'.DIRECTORY_SEPARATOR.'core-import';
    }

    /**
     * param callback to collect what mongo would have saved
     *
     * @param array $param data
     *
     * @return bool always true
     */
    public function saveCollectionCallback($param)
    {
        $this->saves[] = $param;
        return true;
    }

    /**
     * test the whole procedure by running and checking the resulting files
     *
     * @return void
     */
    public function testResultingFiles()
    {
        $this->cmdTester->execute(
            [
                'file' => [$this->sourceDir]
            ]
        );

        $this->assertCount(4, $this->saves);

        // sort by id..
        $saveIds = [];
        foreach ($this->saves as $key => $record) {
            $saveIds[$key] = $record['_id'];
        }

        array_multisort($saveIds, SORT_ASC, $this->saves);

        // ids..
        $this->assertSame('Record1', $this->saves[0]['_id']);
        $this->assertSame('Record2', $this->saves[1]['_id']);
        $this->assertSame('Record3', $this->saves[2]['_id']);
        $this->assertSame('Record4', $this->saves[3]['_id']);

        // mongodate?
        $this->assertTrue(($this->saves[0]['date'] instanceof \MongoDate));

        // fail in output?
        $this->assertContains(
            'Error in <' . $this->sourceDir . '/Dudess/Invalid.json>: Invalid JSON to unserialize.',
            $this->cmdTester->getDisplay()
        );
        $this->assertEquals(1, $this->cmdTester->getStatusCode());
    }
}
