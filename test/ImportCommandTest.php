<?php
/**
 * check import command
 */

namespace Graviton\ImportExport\Tests;

use Graviton\ImportExport\Command\ImportCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
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
     * @param string  $host    import target host with protocol
     * @param string  $file    file to import
     * @param string  $path    resulting path from file
     * @param boolean $isError are we testing an error condition
     *
     * @return void
     */
    public function testUploadFile($host, $file, $path, $isError = false)
    {
        $clientMock = $this->getMockBuilder('GuzzleHttp\Client')->getMock();

        $promiseMock = $this->getMock('GuzzleHttp\Promise\Promise');

        $clientMock
            ->method('requestAsync')
            ->will($this->returnValue($promiseMock));

        $responseMock = $this->getMock('Psr\Http\Message\ResponseInterface');

        $responseMock
            ->method('getHeader')
            ->with('Link')
            ->willReturn(['<' . $host . $path . '>; rel="self"']);

        $requestMock = $this->getMock('Psr\Http\Message\RequestInterface');
        $requestMock
            ->method('getUri')
            ->willReturn($host . '/core/app/test');
        

        $exceptionMock = $this->getMockBuilder('GuzzleHttp\Exception\RequestException')
            ->setConstructorArgs(['Client error: 400', $requestMock, $responseMock])
            ->getMock();

        $exceptionMock
            ->method('getRequest')
            ->willReturn($requestMock);

        $promiseMock
            ->method('then')
            ->will(
                $this->returnCallback(
                    function ($ok, $nok) use ($responseMock, $exceptionMock, $isError) {
                        if ($isError) {
                            return $nok($exceptionMock);
                        }
                        $ok($responseMock);
                    }
                )
            );

        $sut = new ImportCommand(
            new Finder(),
            $clientMock,
            new FrontMatter(),
            new Parser(),
            new VarCloner(),
            new Dumper()
        );

        $app = new Application();
        $app->add($sut);

        $cmd = $app->find('g:i');
        $cmdTester = new CommandTester($cmd);
        $cmdTester->execute(
            [
                'command' => $cmd->getName(),
                'host' => 'http://localhost',
                'file' => [
                    $file
                ],
            ],
            [
                'decorated' => true
            ]
        );

        $this->assertContains('Loading ' . $host . $path . ' from ' . $file, $cmdTester->getDisplay());

        if ($isError) {
            $this->assertContains(
                'Failed to write <' . $host . $path . '> from \'' . $file . '\' with message \'Client error: 400\'',
                $cmdTester->getDisplay()
            );
        } else {
            $this->assertContains('Wrote <' . $host . $path . '>; rel="self"', $cmdTester->getDisplay());
        }
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
            'invalid file (server side)' => [
                'http://localhost',
                __DIR__ . '/fixtures/set-01/test.json',
                '/core/app/test',
                true
                
            ]
        ];
    }
}
