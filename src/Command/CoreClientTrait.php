<?php
/**
 * mongodb client trait
 */

namespace Graviton\ImportExport\Command;

use Symfony\Component\Console\Input\InputInterface;
use Graviton\ImportExport\Util\MongoCredentialsProvider;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
trait CoreClientTrait
{
    /**
     * @var \MongoClient mongoclient
     */
    protected $client;

    /**
     * @var array
     */
    private $mongoCredentials = ['db' => 'db'];

    /**
     * @param \MongoClient $client new mongodb client
     * @return void
     */
    public function setClient(\MongoClient $client)
    {
        $this->client = $client;
    }

    /**
     * get an authenticated  mongodb client
     *
     * @param InputInterface $input User input on console
     *
     * @return \MongoClient
     */
    protected function getClient(InputInterface $input)
    {
        if ($this->client) {
            return $this->client;
        }
        if ($input->getOption('mongodb')) {
            $mongoCredentials = MongoCredentialsProvider::fromInput($input);
        } else {
            $mongoCredentials = MongoCredentialsProvider::getConnection();
        };
        $this->client = new \MongoClient($mongoCredentials['uri']);
        $this->mongoCredentials = $mongoCredentials;
        return $this->client;
    }

    /**
     * get the connection to a given database
     *
     * @param InputInterface $input input from user
     *
     * @return \MongoDB
     */
    function getDatabase(InputInterface $input)
    {
        return $this->getClient($input)->selectDB($this->mongoCredentials['db']);
    }
}
