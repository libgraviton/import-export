<?php
/**
 * check core import command
 */

namespace Graviton\ImportExport\Tests\Command;

use Graviton\ImportExport\Command\CoreImportCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Webuni\FrontMatter\FrontMatter;
use Graviton\ImportExport\Util\JsonSerializer;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class CoreImportCommandTest extends \PHPUnit_Framework_TestCase
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
        $clientMock = $this->getMockBuilder('\MongoClient')->getMock();

        $collection = $this->getMockBuilder('\MongoCollection')->disableOriginalConstructor()->getMock();
        $collection->method('save')->will($this->returnCallback(array($this,'saveCollectionCallback')));
        $clientMock->method('selectCollection')->willReturn($collection);

        $sut = new CoreImportCommand(
            $clientMock,
            new FrontMatter(),
            new JsonSerializer(),
            new Finder()
        );

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

        // ids..
        $this->assertSame('Record1', $this->saves[0]['_id']);
        $this->assertSame('Record2', $this->saves[1]['_id']);
        $this->assertSame('Record3', $this->saves[2]['_id']);
        $this->assertSame('Record4', $this->saves[3]['_id']);

        // mongodate?
        $this->assertTrue(($this->saves[0]['date'] instanceof \MongoDate));

        // fail in output?
        $this->assertContains(
            'Could not deserialize file <' . $this->sourceDir . '/Dudess/Invalid.json>',
            $this->cmdTester->getDisplay()
        );
    }
}