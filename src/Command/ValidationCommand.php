<?php
/**
 * base abstract for import based commands where a bunch of file must be collected and
 * done something with them..
 */

namespace Graviton\ImportExport\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Exception\ParseException;
use Webuni\FrontMatter\FrontMatter;
use Webuni\FrontMatter\Document;
use Symfony\Component\Yaml\Parser as YmlParser;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     http://swisscom.ch
 */
class ValidationCommand extends Command
{
    /**
     * @var FrontMatter
     */
    private $frontMatter;

    /**
     * @var YmlParser
     */
    private $ymlParser;

    /**
     * Error array for validation
     * @var array
     */
    private $validationErrors = [];

    /**
     * @param FrontMatter $frontMatter frontmatter parser
     * @param YmlParser   $parser      yaml parser
     */
    public function __construct(
        FrontMatter $frontMatter,
        YmlParser $parser
    ) {
        $this->frontMatter = $frontMatter;
        $this->ymlParser = $parser;
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
            ->setName('graviton:validate:import')
            ->setDescription('Validates data files to check if they can be imported.')
            ->addArgument(
                'file',
                InputArgument::REQUIRED + InputArgument::IS_ARRAY,
                'Directories or files to load'
            );
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface  $input  User input on console
     * @param OutputInterface $output Output of the command
     *
     * @return integer
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $files = $input->getArgument('file');
        $finder =  new Finder();
        $finder = $finder->files();

        /**
         * @param SplFileInfo $file
         * @return bool
         */
        $filter = function (SplFileInfo $file) {
            if (!in_array($file->getExtension(), ['yml','json']) || $file->isDir()) {
                return false;
            }
            return true;
        };

        foreach ($files as $file) {
            if (is_file($file)) {
                $finder->in(dirname($file))->name(basename($file));
            } else {
                $finder->in($file);
            }
        }

        $finder->ignoreDotFiles(true)->filter($filter);

        $this->validateFiles($finder, $output);

        $output->writeln("\n".'<info>Finished</info>');

        if (!empty($this->validationErrors)) {
            $output->writeln("\n".'<error>With: '.count($this->validationErrors).' Errors</error>');
            foreach ($this->validationErrors as $file => $error) {
                $output->writeln('<comment>'.strstr($file, '/initialdata/').': '.$error.'</comment>');
            }
            return -1;
        } else {
            $output->writeln("\n".'<info>You are awesome! No Errors detected.</info>');
        }
        return 0;
    }

    /**
     * Will check and validate initial file information
     *
     * @param Finder          $finder File Finder by SF
     * @param OutputInterface $output Print to command line
     * @return void
     */
    private function validateFiles(Finder $finder, OutputInterface $output)
    {
        $count = $finder->count();
        $output->writeln("\n".'<info>Validation will be done for: '.$count.' files </info>');

        $progress = new ProgressBar($output, $count);

        foreach ($finder->files() as $file) {
            $progress->advance();
            $path = str_replace('//', '/', $file->getPathname());

            // To check file core db import structure or for PUT
            $fileType = strpos($path, '/data/param/') !== false ? 'param' : 'core';

            /** @var Document $doc */
            $doc = $this->frontMatter->parse($file->getContents());
            $docHeader = $doc->getData();

            if ('core' == $fileType) {
                if (!array_key_exists('collection', $docHeader) || empty($docHeader['collection'])) {
                    $this->validationErrors[$path] = 'Core import, header "collection" is required';
                    continue;
                }
            } else {
                if (!array_key_exists('target', $docHeader) || empty($docHeader['target'])) {
                    $this->validationErrors[$path] = 'Param import, header "target" is required';
                    continue;
                }
            }

            $this->parseContent($doc->getContent(), $path, $file->getExtension());
        }
    }

    /**
     * parse contents of a file depending on type
     *
     * @param string $content   contents part of file
     * @param string $path      full path to file
     * @param string $extension File extension json or yml
     *
     * @return void
     */
    protected function parseContent($content, $path, $extension)
    {
        switch ($extension) {
            case 'json':
                $data = json_decode($content);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->validationErrors[$path] = json_last_error_msg();
                }
                break;
            case 'yml':
                try {
                    $data = $this->ymlParser->parse($content);
                } catch (ParseException $e) {
                    $this->validationErrors[$path] = $e->getMessage();
                }
                break;
            default:
                $this->validationErrors[$path] = 'UnknownFileTypeException';
        };

        if (empty($data) && !array_key_exists($path, $this->validationErrors)) {
            $this->validationErrors[$path] = 'Returned empty yml data';
        }
    }
}
