<?php
/**
 * import objects into mongodb with files created by CoreExportCommand
 */

namespace Graviton\ImportExport\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
    use CoreClientTrait;

    /**
     * @var FrontMatter
     */
    private $frontMatter;

    /**
     * @var JsonSerializer
     */
    private $serializer;

    /**
     * @var array
     */
    private $errorStack = [];

    /**
     * @param FrontMatter    $frontMatter frontmatter parser
     * @param JsonSerializer $serializer  serializer
     * @param Finder         $finder      finder
     */
    public function __construct(
        FrontMatter $frontMatter,
        JsonSerializer $serializer,
        Finder $finder
    ) {
        parent::__construct($finder);
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
            ->addOption(
                'mongodb',
                null,
                InputOption::VALUE_REQUIRED,
                'MongoDB connection URL.'
            )
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
     * @return integer
     */
    protected function doImport(Finder $finder, InputInterface $input, OutputInterface $output)
    {
        $exitCode = 0;
        foreach ($finder as $file) {
            $this->importResource($file, $input, $output);
        }

        if (!empty($this->errorStack)) {
            $output->writeln('<error>Errors orcurred during load!</error>');
            foreach ($this->errorStack as $errorMessage) {
                $output->writeln($errorMessage);
            }
            $exitCode = 1;
        }
        return $exitCode;
    }

    /**
     * import a single file into a collection
     *
     * @param SplFileInfo     $file   file
     * @param InputInterface  $input  User input on console
     * @param OutputInterface $output Output of the command
     *
     * @return integer
     */
    private function importResource(\SplFileInfo $file, InputInterface $input, OutputInterface $output)
    {
        $doc = $this->frontMatter->parse($file->getContents());
        $origDoc = $this->serializer->unserialize($doc->getContent());

        if (is_null($origDoc)) {
            $errorMessage = "<error>Could not deserialize file <${file}></error>";
            $output->writeln($errorMessage);
            array_push($this->errorStack, $errorMessage);
        } else {
            try {
                $collectionName = $doc->getData()['collection'];
                $this->getDatabase($input)->selectCollection($collectionName)->save($origDoc);
                $output->writeln("<info>Imported <${file}> to <${collectionName}></info>");
            } catch (\Exception $e) {
                $errorMessage = "<error>Error in <${file}>: ".$e->getMessage()."</error>";
                $output->writeln($errorMessage);
                $this->errorStack[] = $errorMessage;
            }
        }
    }
}
