<?php
/**
 * Extending GuzzleHttp client
 */
namespace Graviton\ImportExport\Service;

use GuzzleHttp\ClientInterface;
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
class FileSender
{
    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @param ClientInterface $client real guzzle client
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Create and send an asynchronous HTTP request sending a file if needed.
     *
     * Use an absolute path to override the base path of the client, or a
     * relative path to append to the base path of the client. The URL can
     * contain the query string as well. Use an array to provide a URL
     * template and additional variables to use in the URL template expansion.
     *
     * @param string              $method  HTTP method
     * @param string|UriInterface $uri     URI object or string.
     * @param array               $options Request options to apply.
     *
     * @return PromiseInterface
     */
    public function requestAsync($method, $uri, array $options = [])
    {
        return $this->client->requestAsync($method, $uri, $this->checkFileUploadRequest($options));
    }

    /**
     * Create and send an HTTP request sending a file as needed.
     *
     * Use an absolute path to override the base path of the client, or a
     * relative path to append to the base path of the client. The URL can
     * contain the query string as well.
     *
     * @param string              $method  HTTP method
     * @param string|UriInterface $uri     URI object or string.
     * @param array               $options Request options to apply.
     *
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function request($method, $uri, array $options = [])
    {
        return $this->client->request($method, $uri, $this->checkFileUploadRequest($options));
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

        if (!array_key_exists('json', $options)) {
            throw new \RuntimeException("No json option passed");
        }

        // We send the data in URL
        $options['query'] = ['metadata' =>  json_encode($options['json'])];

        // Guzzle modify header if we send json.
        unset($options['json']);

        // We send file only
        $options['body'] = fopen($fileName, 'r');

        return $options;
    }
}
