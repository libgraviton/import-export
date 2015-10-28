<?php
/**
 * import objects into mongodb with files created by CoreExportCommand
 */

namespace Graviton\ImportExport\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Webuni\FrontMatter\FrontMatter;
use Graviton\ImportExport\Util\JsonSerializer;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class CoreImportCommand extends ImportCommandAbstract
{

    /**
     * @var \MongoClient
     */
    private $client;

    /**
     * @var FrontMatter
     */
    private $frontMatter;

    /**
     * @var JsonSerializer
     */
    private $serializer;

    /**
     * @param \MongoClient   $client      symfony/finder instance
     * @param FrontMatter    $frontMatter frontmatter parser
     * @param JsonSerializer $serializer  serializer
     * @param Finder         $finder      finder
     */
    public function __construct(
        \MongoClient $client,
        FrontMatter $frontMatter,
        JsonSerializer $serializer,
        Finder $finder
    ) {
        parent::__construct($finder);
        $this->client = $client;
        $this->frontMatter = $frontMatter;
        $this->serializer = $serializer;
    }

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('graviton:core:import')
            ->setDescription('Import files from a folder or file generated by graviton:core:export into MongoDB.')
            ->addArgument(
                'file',
                InputArgument::REQUIRED + InputArgument::IS_ARRAY,
                'Directories or files to load'
            );
    }

    /**
     * Executes the current command.
     *
     * @param Finder          $finder Finder
     * @param InputInterface  $input  User input on console
     * @param OutputInterface $output Output of the command
     *
     * @return void
     */
    protected function doImport(Finder $finder, InputInterface $input, OutputInterface $output)
    {
        foreach ($finder as $file) {
            $this->importResource($file);
        }
    }

    /**
     * import a single file into a collection
     *
     * @param File $file file
     *
     * @return void
     */
    private function importResource($file)
    {
        $doc = $this->frontMatter->parse($file->getContents());
        $origDoc = $this->serializer->unserialize($doc->getContent());
        $collectionName = $doc->getData()['collection'];
        $this->client->selectCollection('db', $collectionName)->save($origDoc);
    }
}
