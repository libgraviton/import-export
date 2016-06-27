<?php
/**
 * base abstract for import based commands where a bunch of file must be collected and
 * done something with them..
 */

namespace Graviton\ImportExport\Command;

use Graviton\ImportExport\Exception\MissingTargetException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

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
     * @param Finder $finder finder instance
     */
    public function __construct(
        Finder $finder
    ) {
        $this->finder = $finder;
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
}
