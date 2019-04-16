<?php
/**
 * base abstract for import based commands where a bunch of file must be collected and
 * done something with them..
 */

namespace Graviton\ImportExport\Command;

use Graviton\ImportExport\Exception\MissingTargetException;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     http://swisscom.ch
 */
abstract class ImportCommandAbstract extends Command
{
    /**
     * @var Finder
     */
    protected $finder;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param Logger $logger logger
     * @param Finder $finder finder instance
     */
    public function __construct(
        Logger $logger,
        Finder $finder
    ) {
        $this->logger = $logger;
        $this->finder = $finder;
        parent::__construct();
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
        $exitCode = 1;
        try {
            $exitCode = $this->doImport($this->getFinder($input), $input, $output);
        } catch (MissingTargetException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        }
        return $exitCode;
    }

    /**
     * Returns a Finder according to the input params
     *
     * @param InputInterface $input User input on console
     *
     * @return Finder|SplFileInfo[] finder
     */
    protected function getFinder($input)
    {
        $files = $input->getArgument('file');
        $inputFile = $input->getOption('input-file');

        if (empty($files) && is_null($inputFile)) {
            throw new \LogicException('You either need to provide <file> arguments or the --input-file option.');
        }

        $finder = $this->finder->files();

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

        if (is_null($inputFile)) {
            // normal way - file arguments..
            foreach ($files as $file) {
                if (is_file($file)) {
                    $finder->in(dirname($file))->name(basename($file));
                } else {
                    $finder->in($file);
                }
            }
        } else {
            // file list via input file
            if (!file_exists($inputFile)) {
                throw new \LogicException('File '.$inputFile.' does not seem to exist.');
            }

            $fileList = explode(PHP_EOL, file_get_contents($inputFile));

            $fileList = array_filter(
                $fileList,
                function ($val) {
                    if (!empty($val)) {
                        return true;
                    }
                    return false;
                }
            );

            foreach ($fileList as $file) {
                if (is_file($file)) {
                    $finder->in(dirname($file))->name(basename($file));
                } else {
                    $finder->in($file);
                }
            }
        }

        $finder->ignoreDotFiles(true)->filter($filter);

        return $finder;
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
}
