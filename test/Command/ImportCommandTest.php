<?php
/**
 * check import command
 */

namespace Graviton\ImportExportTest\Command;

use Graviton\ImportExport\Command\ImportCommand;
use Graviton\ImportExportTest\Util\TestUtils;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper as Dumper;
use Webuni\FrontMatter\FrontMatter;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class ImportCommandTest extends TestCase
{
    /**
     * String Http Client Class.
     */
    const CLIENT = 'Graviton\ImportExport\Service\HttpClient';

    /**
     * @dataProvider uploadFileProvider
     *
     * @param string $host import target host with protocol
     * @param string $file file to import
     * @param string $path resulting path from file
     *
     * @return void
     */
    public function testUploadFile($host, $file, $path)
    {
        $clientMock = $this->getMockBuilder(self::CLIENT)->getMock();

        $responseMock = $this->createMock('Psr\Http\Message\ResponseInterface');

        $responseMock
            ->method('getHeader')
            ->with('Link')
            ->willReturn(['<' . $host . $path . '>; rel="self"']);

        $clientMock
            ->method('request')
            ->will($this->returnValue($responseMock));

        $logger = TestUtils::getTestingLogger();

        $sut = new ImportCommand(
            $logger,
            $clientMock,
            new Finder(),
            new FrontMatter(),
            new Parser(),
            new VarCloner(),
            new Dumper()
        );

        $cmdTester = $this->getTester($sut, $file);
        $display = TestUtils::getFullStringFromLog($logger->getHandlers()[0]);

        $this->assertContains('Loading data from ' . $file, $display);
        $this->assertContains('Wrote <' . $host . $path . '>; rel="self"', $display);
        $this->assertEquals(0, $cmdTester->getStatusCode());
    }

    /**
     * @return array[]
     */
    public function uploadFileProvider()
    {
        return [
            'basic valid file' => [
                'http://localhost',
                __DIR__ . '/fixtures/set-01/test-2.json',
                '/core/app/test',
            ],
            'basic valid image file' => [
                'http://localhost',
                __DIR__ . '/fixtures/file',
                '/core/app/test',
            ],
        ];
    }

    /**
     * @dataProvider errorFileProvider
     *
     * @param string $host   import target host with protocol
     * @param string $file   file to import
     * @param array  $errors errors to check for (check valid case if none given)
     *
     * @return void
     */
    public function testErrorFile($host, $file, $errors = [])
    {
        $clientMock = $this->getMockBuilder(self::CLIENT)->getMock();

        $responseMock = $this->createMock('Psr\Http\Message\ResponseInterface');

        $responseMock
            ->method('getBody')
            ->willReturn(json_encode((object) ["message" => "invalid"]));
        $responseMock
            ->method('getHeader')
            ->with('Link')
            ->willReturn(['<' . $host . $file . '>; rel="self"']);

        $requestMock = $this->createMock('Psr\Http\Message\RequestInterface');
        $requestMock
            ->method('getUri')
            ->willReturn($host . '/core/app/test');

        $exceptionMock = $this->getMockBuilder('GuzzleHttp\Exception\RequestException')
            ->setConstructorArgs(['Client error: 400', $requestMock, $responseMock])
            ->getMock();

        $exceptionMock
            ->method('getRequest')
            ->willReturn($requestMock);

        $exceptionMock
            ->method('getResponse')
            ->willReturn($responseMock);

        $clientMock
            ->method('request')
            ->willThrowException($exceptionMock);

        $logger = TestUtils::getTestingLogger();

        $sut = new ImportCommand(
            $logger,
            $clientMock,
            new Finder(),
            new FrontMatter(),
            new Parser(),
            new VarCloner(),
            new Dumper()
        );

        $cmdTester = $this->getTester($sut, $file);
        $display = TestUtils::getFullStringFromLog($logger->getHandlers()[0]);

        $this->assertContains('Loading data from ' . $file, $display);
        foreach ($errors as $error) {
            $this->assertContains(
                $error,
                $display.' - '.$cmdTester->getDisplay()
            );
        }
        $this->assertEquals(1, $cmdTester->getStatusCode());
    }

    /**
     * @return array[]
     */
    public function errorFileProvider()
    {
        return [
            'invalid file (server side)' => [
                'http://localhost',
                __DIR__ . '/fixtures/set-01/test.json',
                [
                    'Failed to write <http://localhost/core/app/test> from \'' .
                    __DIR__ . '/fixtures/set-01/test.json\' with message \'Client error: 400\'',
                    '"message": "invalid"',
                ],
            ],
            'missing target in file (user error)' => [
                'http://localhost',
                __DIR__ . '/fixtures/set-01/test-3.json',
                [
                    'Missing target in \'' . __DIR__ . '/fixtures/set-01/test-3.json\'',
                ],
            ]
        ];
    }

    /**
     * test rewriting of contents with --rewrite-host
     *
     * @return void
     */
    public function testRewrite()
    {
        $responseMock = $this->createMock('Psr\Http\Message\ResponseInterface');

        $responseMock
            ->method('getHeader')
            ->with('Link')
            ->willReturn(['<http://example.com/core/module/test>; rel="self"']);

        $clientMock = $this->getMockBuilder(self::CLIENT)->getMock();

        $clientMock
            ->method('request')
            ->with(
                $this->equalTo('PUT'),
                $this->equalTo('http://example.com/core/module/test'),
                $this->equalTo(
                    [
                        'json' => 'http://example.com/core/app/test',
                        'upload' => false
                    ]
                )
            )
            ->will($this->returnValue($responseMock));

        $logger = TestUtils::getTestingLogger();

        $sut = new ImportCommand(
            $logger,
            $clientMock,
            new Finder(),
            new FrontMatter(),
            new Parser(),
            new VarCloner(),
            new Dumper()
        );

        $cmdTester = $this->getTester(
            $sut,
            __DIR__ . '/fixtures/set-01/test-4.json',
            [
                'host' => 'http://example.com',
                '--rewrite-host' => 'http://localhost'
            ]
        );

        $display = TestUtils::getFullStringFromLog($logger->getHandlers()[0]);

        $this->assertContains('Wrote <http://example.com/core/module/test>; rel="self"', $display);
        $this->assertEquals(0, $cmdTester->getStatusCode());
    }

    /**
     * @param ImportCommand $sut  command under test
     * @param string        $file file to load
     * @param array         $args additional arguments
     *
     * @return CommandTester
     */
    private function getTester(ImportCommand $sut, $file, array $args = [])
    {
        $app = new Application();
        $app->add($sut);

        $cmd = $app->find('g:i');
        $cmdTester = new CommandTester($cmd);
        $cmdTester->execute(
            array_merge(
                [
                    'command' => $cmd->getName(),
                    'host' => 'http://localhost',
                    'file' => [
                        $file
                    ],
                ],
                $args
            ),
            [
                'decorated' => true,
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );
        return $cmdTester;
    }
}
