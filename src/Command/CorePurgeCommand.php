<?php
/**
 * drops all collections in a given mongodb database
 */

namespace Graviton\ImportExport\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Webuni\FrontMatter\FrontMatter;
use Webuni\FrontMatter\Document;
use Zumba\Util\JsonSerializer;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class CorePurgeCommand extends Command
{

    /**
     * @var \MongoClient mongoclient
     */
    private $mongoClient;

    /**
     * @var string
     */
    private $databaseName;

    /**
     * @param \MongoClient $mongoClient  mongoclient
     * @param string       $databaseName database name
     */
    public function __construct(
        \MongoClient $mongoClient,
        $databaseName
    ) {
        $this->mongoClient = $mongoClient;
        $this->databaseName = $databaseName;
        parent::__construct();
    }

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('graviton:core:purge')
            ->setDescription('Purges (removes!) all collections in the database given.')
            ->addArgument(
                'yes',
                InputArgument::REQUIRED,
                'Pass value "yes" if you really want to purge your database'
            );
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface  $input  User input on console
     * @param OutputInterface $output Output of the command
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isSure = $input->getArgument('yes');

        if ($isSure != 'yes') {
            throw new \LogicException('You must pass "yes" as parameter to show that you know what you\'re doing');
        }

        foreach ($this->mongoClient->{$this->databaseName}->listCollections() as $collection) {
            $collectionName = $collection->getName();
            $output->writeln("<info>Dropping collection <${collectionName}></info>");
            $collection->drop();
        }
    }
}