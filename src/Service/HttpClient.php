<?php
/**
 * Extending GuzzleHttp client
 */
namespace Graviton\ImportExport\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Promise;

/**
 * Class HttpClient
 * Extends Guzzle client
 *
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class HttpClient extends Client
{
    /** @var string */
    private $url;

    /**
     * Parse Body and if File is found it will find file and send it
     *
     * @param string $method  Request method to be used
     * @param string $uri     Url to where to send data
     * @param array  $options Config params
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function requestAsync($method, $uri = null, array $options = [])
    {
        $this->url = $uri;
        $options = $this->checkFileUploadRequest($options);

        return parent::requestAsync($method, $this->url, $options);
    }

    /**
     * @param array $options Curl data options
     * @return array options
     */
    private function checkFileUploadRequest($options)
    {
        if (!array_key_exists('upload', $options)) {
            return $options;
        }

        $fileName = $options['upload'];
        unset($options['upload']);

        if (!$fileName) {
            return $options;
        }

        // We create a MultiPart form, Graviton decode the "metadata" value.
        $options['multipart'] = [
            [
                'name'     => 'metadata',
                'contents' => json_encode($options['json'])
            ],
            [
                'name'     => 'upload',
                'contents' => fopen($fileName, 'r'),
                'filename' => basename($fileName)
            ]
        ];

        // Guzzle modify header if we send json.
        unset($options['json']);

        return $options;
    }
}
