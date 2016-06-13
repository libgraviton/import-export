<?php
/**
 * test the file sender (aka uploader) service
 */

namespace Graviton\ImportExport\Tests\Service;

use Graviton\ImportExport\Service\FileSender;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class FileSenderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * checks if FileSender does nothing if nothing is needed
     *
     * @dataProvider requestTypeProvider
     * @covers Graviton\ImportExport\Service\FileSender
     *
     * @param string $type type of request to test
     * @return void
     */
    public function testSenderDoesPlainRequestWithoutUploadField($type)
    {
        $method = 'PUT';
        $uri = 'http://localhost/file/example';
        $options = [];

        $clientMock = $this->getMockBuilder('GuzzleHttp\Client')->getMock();

        $promiseMock = $this->createMock('GuzzleHttp\Promise\Promise');

        $clientMock
            ->method($type)
            ->with($method, $uri, $options)
            ->will($this->returnValue($promiseMock));

        $sut = new FileSender(
            $clientMock
        );
        $this->assertEquals($promiseMock, $sut->$type($method, $uri, $options));
    }

    /**
     * checks if FileSender does nothing if it gets a falsy file path
     *
     * This test was added post-implementation, be sure to check if it is really what
     * you need if you read this.
     *
     * @dataProvider requestTypeProvider
     * @covers Graviton\ImportExport\Service\FileSender
     *
     * @param string $type type of request to test
     * @return void
     */
    public function testSenderDoesPlainRequestWithFalsyUploadField($type)
    {
        $method = 'PUT';
        $uri = 'http://localhost/file/example';
        $options = [
            'upload' => ''    
        ];
        $expectedOptions = [];

        $clientMock = $this->getMockBuilder('GuzzleHttp\Client')->getMock();

        $promiseMock = $this->createMock('GuzzleHttp\Promise\Promise');

        $clientMock
            ->method($type)
            ->with($method, $uri, $expectedOptions)
            ->will($this->returnValue($promiseMock));

        $sut = new FileSender(
            $clientMock
        );
        $this->assertEquals($promiseMock, $sut->$type($method, $uri, $options));
    }

    /**
     * test if FileSender excepts to being called without json in upload cases
     *
     * @dataProvider requestTypeProvider
     * @covers Graviton\ImportExport\Service\FileSender
     *
     * @return void
     */
    public function testExceptsToBeingCalledWithoutJsonDataInUploadCase($type)
    {
        $method = 'PUT';
        $uri = 'http://localhost/file/example';
        $options = [
            'upload' => __DIR__ . '/fixtures/file.txt'
            // look ma no 'json' => '{}'
        ];

        $clientMock = $this->getMockBuilder('GuzzleHttp\Client')->getMock();

        $this->expectException('\RuntimeException');
        $this->expectExceptionMessage('No json option passed');

        $sut = new FileSender(
            $clientMock
        );
        $sut->$type($method, $uri, $options);;
    }


    /**
     * checks if FileSender mangles the options before passing them to the client
     *
     * @dataProvider requestTypeProvider
     * @covers Graviton\ImportExport\Service\FileSender
     *
     * @param string $type type of request to test
     * @return void
     */
    public function testSenderManglesOptionsIfUploadHasBeenPassed($type)
    {
        $method = 'PUT';
        $uri = 'http://localhost/file/example';
        $options = [
            'upload' => __DIR__ . '/fixtures/file.txt',
            'json' => new \stdClass()
        ];
        $expectedOptions = [
            'query' => [
                'metadata' => '{}'
            ],
            'body' => file_get_contents(__DIR__ . '/fixtures/file.txt')
        ];

        $clientMock = $this->getMockBuilder('GuzzleHttp\Client')->getMock();

        $promiseMock = $this->createMock('GuzzleHttp\Promise\Promise');

        $clientMock
            ->method($type)
            ->with($method, $uri, $expectedOptions)
            ->will($this->returnValue($promiseMock));

        $sut = new FileSender(
            $clientMock
        );
        $this->assertEquals($promiseMock, $sut->$type($method, $uri, $options));
    }

    /**
     * @return array
     */
    public function requestTypeProvider()
    {
        return [
            'async' => ['requestAsync'],
            'sync' => ['request'],
        ];
    }
}
