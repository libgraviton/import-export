<?php
/**
 * dumps data from a mongodb connection to files
 */

namespace Graviton\ImportExport\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;

use Symfony\Component\Console\Input\InputInterface;
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
class CoreExportCommand extends Command
{

    /**
     * @var \MongoClient mongoclient
     */
    private $mongoClient;

    /**
     * @var Filesystem filesystem
     */
    private $fs;

    /**
     * @var JsonSerializer serializer
     */
    private $serializer;

    /**
     * @var FrontMatter frontmatter
     */
    private $frontMatter;

    /**
     * @param \MongoClient   $mongoClient mongoclient
     * @param Filesystem     $fs          symfony filesystem
     * @param JsonSerializer $serializer  json serializer
     * @param FrontMatter    $frontMatter front matter
     */
    public function __construct(
        \MongoClient $mongoClient,
        Filesystem $fs,
        JsonSerializer $serializer,
        FrontMatter $frontMatter
    ) {

        $this->mongoClient = $mongoClient;
        $this->fs = $fs;
        $this->serializer = $serializer;
        $this->frontMatter = $frontMatter;
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
            ->setName('graviton:core:export')
            ->setDescription('Export core resources (from MongoDb) to files')
            ->addArgument(
                'destinationDir',
                InputArgument::REQUIRED,
                'Destination directory for dump structure'
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
        $destinationDir = $input->getArgument('destinationDir');

        if (!$this->fs->exists($destinationDir)) {
            throw new FileNotFoundException(sprintf('Destination "%s" does not exist', $destinationDir));
        }

        if (substr($destinationDir, -1) != DIRECTORY_SEPARATOR) {
            $destinationDir .= DIRECTORY_SEPARATOR;
        }

        foreach ($this->mongoClient->db->listCollections() as $collection) {
            $collectionDestinationDir = $destinationDir.$collection->getName().DIRECTORY_SEPARATOR;

            if (!$this->fs->exists($collectionDestinationDir)) {
                $this->fs->mkdir($collectionDestinationDir);
            }

            $this->dumpCollection($collection, $collectionDestinationDir);
        }
    }

    /**
     * Dumps all objects in a collection
     *
     * @param \MongoCollection $collection     collection
     * @param string           $destinationDir destination dir
     *
     * @return void
     */
    private function dumpCollection(\MongoCollection $collection, $destinationDir)
    {
        foreach ($collection->find() as $record) {
            $this->dumpObject($record, $collection->getName(), $destinationDir);
        }
    }

    /**
     * Dumps a single object
     *
     * @param array  $record         mongodb record
     * @param string $collectionName name of the collection
     * @param string $destinationDir destination dir
     *
     * @return void
     */
    private function dumpObject($record, $collectionName, $destinationDir)
    {
        // make it pretty..
        $content = json_encode(
            json_decode($this->serializer->serialize($record)),
            JSON_PRETTY_PRINT + JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES
        );

        $doc = new Document($content, ['collection' => $collectionName]);
        $fileName = $destinationDir.$record['_id'].'.json';

        $this->fs->dumpFile($fileName, $this->frontMatter->dump($doc));
    }
}
