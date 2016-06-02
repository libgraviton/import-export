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
        $originFileName = array_key_exists('origin', $options) ? $options['origin'] : false;

        if (!$originFileName) {
            return $options;
        }
        
        // Remove un-used param
        unset($options['origin']);

        // Is there a file and a @
        if (!isset($options['json'])
            || !isset($options['json']['file'])
            || !strpos($options['json']['file'], '@') == 0) {
            return $options;
        }

        // Find file
        $fileName = preg_replace('/([^\/]+$)/', substr($options['json']['file'], 1), $originFileName);
        $fileName = str_replace('//', '/', $fileName);
        if (!file_exists($fileName)) {
            return $options;
        }

        // File should only be sent as so.
        unset($options['json']['file']);

        // We send the data in URL
        $options['query'] = ['metadata' =>  json_encode($options['json'])];

        // Guzzle modify header if we send json.
        unset($options['json']);

        // We send file only
        $options['body'] = fopen($fileName, 'r');

        return $options;

    }
}
