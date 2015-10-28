<?php
/**
 * import json data into graviton
 *
 * Supports importing json data from either a single file or a complete folder of files.
 *
 * The data needs to contain frontmatter to hint where the bits and pieces should go.
 */

namespace Graviton\ImportExport\Command;

use Graviton\ImportExport\Exception\MissingTargetException;
use Graviton\ImportExport\Exception\JsonParseException;
use Graviton\ImportExport\Exception\UnknownFileTypeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Parser;
use Webuni\FrontMatter\FrontMatter;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
abstract class ImportCommandAbstract extends Command
{
    /**
     * @var Finder
     */
    protected $finder;

    /**
     * @var FrontMatter
     */
    protected $frontMatter;

    /**
     * @var Parser
     */
    protected $parser;

    /**
     * @param Finder      $finder      symfony/finder instance
     * @param FrontMatter $frontMatter frontmatter parser
     * @param Parser      $parser      yaml/json parser
     */
    public function __construct(
        Finder $finder,
        FrontMatter $frontMatter,
        Parser $parser
    ) {
        $this->finder = $finder;
        $this->frontMatter = $frontMatter;
        $this->parser = $parser;
        parent::__construct();
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
        $files = $input->getArgument('file');
        $finder = $this->finder->files();

        foreach ($files as $file) {
            if (is_file($file)) {
                $finder = $finder->in(dirname($file))->name(basename($file));
            } else {
                $finder = $finder->in($file);
            }
        }

        try {
            $this->doImport($finder, $input, $output);
        } catch (MissingTargetException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }

    /**
     * the actual command can do his import here..
     *
     * @param Finder          $finder finder
     * @param InputInterface  $input  input
     * @param OutputInterface $output output
     *
     * @return mixed
     */
    abstract protected function doImport(Finder $finder, InputInterface $input, OutputInterface $output);

    /**
     * parse contents of a file depending on type
     *
     * @param string $content contents part of file
     * @param string $file    full path to file
     *
     * @return mixed
     */
    protected function parseContent($content, $file)
    {
        if (substr($file, -5) == '.json') {
            $data = json_decode($content);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new JsonParseException(
                    sprintf(
                        '%s in %s',
                        json_last_error_msg(),
                        $file
                    )
                );
            }
        } elseif (substr($file, -4) == '.yml') {
            $data = $this->parser->parse($content, false, false, true);
        } else {
            throw new UnknownFileTypeException($file);
        }

        return $data;
    }
}
