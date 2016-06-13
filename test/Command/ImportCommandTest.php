<?php
/**
 * check import command
 */

namespace Graviton\ImportExport\Tests\Command;

use Graviton\ImportExport\Command\ImportCommand;
use Graviton\ImportExport\Service\FileSender;
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
class ImportCommandTest extends \PHPUnit_Framework_TestCase
{
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
        $clientMock = $this->getMockBuilder('GuzzleHttp\Client')->getMock();

        $promiseMock = $this->createMock('GuzzleHttp\Promise\Promise');

        $clientMock
            ->method('requestAsync')
            ->will($this->returnValue($promiseMock));

        $responseMock = $this->createMock('Psr\Http\Message\ResponseInterface');

        $responseMock
            ->method('getHeader')
            ->with('Link')
            ->willReturn(['<' . $host . $path . '>; rel="self"']);

        $promiseMock
            ->method('then')
            ->will(
                $this->returnCallback(
                    function ($ok) use ($responseMock) {
                        $ok($responseMock);
                    }
                )
            );

        $sut = new ImportCommand(
            $clientMock,
            new Finder(),
            new FrontMatter(),
            new Parser(),
            new VarCloner(),
            new Dumper(),
            new FileSender($clientMock)
        );

        $cmdTester = $this->getTester($sut, $file);

        $this->assertContains('Loading data from ' . $file, $cmdTester->getDisplay());
        $this->assertContains('Wrote <' . $host . $path . '>; rel="self"', $cmdTester->getDisplay());
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
        ];
    }

    /**
     * @return array[]
     */
    public function uploadImageFileProvider()
    {
        return [
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
        $clientMock = $this->getMockBuilder('GuzzleHttp\Client')->getMock();

        $promiseMock = $this->createMock('GuzzleHttp\Promise\Promise');

        $clientMock
            ->method('requestAsync')
            ->will($this->returnValue($promiseMock));

        $responseMock = $this->createMock('Psr\Http\Message\ResponseInterface');

        $responseMock
            ->method('getBody')
            ->willReturn(json_encode((object) ["message" => "invalid"]));

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

        $promiseMock
            ->method('then')
            ->will(
                $this->returnCallback(
                    function ($ok, $nok) use ($exceptionMock) {
                        return $nok($exceptionMock);
                    }
                )
            );

        $sut = new ImportCommand(
            $clientMock,
            new Finder(),
            new FrontMatter(),
            new Parser(),
            new VarCloner(),
            new Dumper(),
            new FileSender($clientMock)
        );

        $cmdTester = $this->getTester($sut, $file);

        $this->assertContains('Loading data from ' . $file, $cmdTester->getDisplay());
        foreach ($errors as $error) {
            $this->assertContains(
                $error,
                $cmdTester->getDisplay()
            );
        }
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
        $clientMock = $this->getMockBuilder('GuzzleHttp\Client')->getMock();

        $promiseMock = $this->createMock('GuzzleHttp\Promise\Promise');

        $clientMock
            ->method('requestAsync')
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
            ->will($this->returnValue($promiseMock));

        $responseMock = $this->createMock('Psr\Http\Message\ResponseInterface');

        $responseMock
            ->method('getHeader')
            ->with('Link')
            ->willReturn(['<http://example.com/core/module/test>; rel="self"']);

        $promiseMock
            ->method('then')
            ->will(
                $this->returnCallback(
                    function ($ok) use ($responseMock) {
                        $ok($responseMock);
                    }
                )
            );

        $sut = new ImportCommand(
            $clientMock,
            new Finder(),
            new FrontMatter(),
            new Parser(),
            new VarCloner(),
            new Dumper(),
            new FileSender($clientMock)
        );

        $cmdTester = $this->getTester(
            $sut,
            __DIR__ . '/fixtures/set-01/test-4.json',
            [
                'host' => 'http://example.com',
                '--rewrite-host' => 'http://localhost'
            ]
        );
        $this->assertContains('Wrote <http://example.com/core/module/test>; rel="self"', $cmdTester->getDisplay());
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
