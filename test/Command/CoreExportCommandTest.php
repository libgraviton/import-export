<?php
/**
 * check core export command
 */

namespace Graviton\ImportExport\Tests\Command;

use Graviton\ImportExport\Command\CoreExportCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Webuni\FrontMatter\FrontMatter;
use Graviton\ImportExport\Util\JsonSerializer;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class CoreExportCommandTest extends \PHPUnit_Framework_TestCase
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
    private $destinationDir;

    /**
     * prepare our mocks and cmdtester
     *
     * @return void
     */
    public function setUp()
    {
        $clientMock = $this->getMockBuilder('\MongoClient')->getMock();

        $collectionData = [
            [
                ['_id' => 'Record1', 'name' => 'Fred', 'date' => new \MongoDate(strtotime("1982-02-05 00:00:00"))],
                ['_id' => 'Record2', 'name' => 'Franz']
            ],
            [
                ['_id' => 'Record3', 'name' => 'Hans'],
                ['_id' => 'Record4', 'name' => 'Ilein']
            ]
        ];

        $collectionOne = $this->getMockBuilder('\MongoCollection')->disableOriginalConstructor()->getMock();
        $collectionOne->expects($this->exactly(4))->method('getName')->willReturn('Dude');
        $collectionOne->expects($this->once())->method('find')->willReturn($collectionData[0]);

        $collectionTwo = $this->getMockBuilder('\MongoCollection')->disableOriginalConstructor()->getMock();
        $collectionTwo->expects($this->exactly(4))->method('getName')->willReturn('Dudess');
        $collectionTwo->expects($this->once())->method('find')->willReturn($collectionData[1]);

        // this one is filtered out and gets called differently
        $collectionThree = $this->getMockBuilder('\MongoCollection')->disableOriginalConstructor()->getMock();
        $collectionThree->expects($this->exactly(1))->method('getName')->willReturn('Franz');
        $collectionThree->expects($this->never())->method('find')->willReturn($collectionData[1]);

        $dbMock = $this->getMockBuilder('\MongoDb')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->once())->method('listCollections')->willReturn(
            [$collectionOne, $collectionTwo, $collectionThree]
        );

        $clientMock->db = $dbMock;

        $sut = new CoreExportCommand(
            $clientMock,
            'db',
            new Filesystem(),
            new JsonSerializer(),
            new FrontMatter()
        );

        $app = new Application();
        $app->add($sut);

        $cmd = $app->find('graviton:core:export');

        $this->cmdTester = new CommandTester($cmd);

        $this->fs = new Filesystem();
        $this->destinationDir = __DIR__.DIRECTORY_SEPARATOR.'exportTemp'.DIRECTORY_SEPARATOR;
    }

    /**
     * test the whole procedure by running and checking the resulting files
     *
     * @return void
     */
    public function testResultingFiles()
    {
        if (!$this->fs->exists($this->destinationDir)) {
            $this->fs->mkdir($this->destinationDir);
        }

        $this->cmdTester->execute(
            [
                'destinationDir' => $this->destinationDir,
                '--collection' => 'dud*'
            ]
        );

        // see if directories and files exist and contents..
        $this->assertTrue(is_dir($this->destinationDir.'Dude'));

        $file = $this->destinationDir.'Dude'.DIRECTORY_SEPARATOR.'Record1.json';
        $this->assertFileExists($file);
        $contents = file_get_contents($file);
        $this->assertContains('collection: Dude', $contents);
        $this->assertContains('"_id": "Record1",', $contents);
        $this->assertContains('"name": "Fred",', $contents);
        $this->assertContains('"@type": "MongoDate"', $contents);

        $this->assertFileExists($this->destinationDir.'Dude'.DIRECTORY_SEPARATOR.'Record2.json');

        $this->assertTrue(is_dir($this->destinationDir.'Dudess'));
        $this->assertFileExists($this->destinationDir.'Dudess'.DIRECTORY_SEPARATOR.'Record3.json');
        $this->assertFileExists($this->destinationDir.'Dudess'.DIRECTORY_SEPARATOR.'Record4.json');

        // does the ignored not exist?
        $this->assertFalse(is_dir($this->destinationDir.'Franz'));
    }

    /**
     * remove mess
     *
     * @return void
     */
    public function tearDown()
    {
        $this->fs->remove($this->destinationDir);
    }
}
