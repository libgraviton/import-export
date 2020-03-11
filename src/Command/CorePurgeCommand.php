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

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     http://swisscom.ch
 */
class CorePurgeCommand extends Command
{
    use CoreClientTrait;

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('graviton:core:purge')
            ->setDescription(
                'Purges (removes!) all collections in a given database or only the resources '.
                'that contain a specified recordOrigin value.'
            )
            ->addOption(
                'mongodb',
                null,
                InputOption::VALUE_REQUIRED,
                'MongoDB connection URL.'
            )
            ->addOption(
                'recordOrigin',
                null,
                InputOption::VALUE_OPTIONAL,
                'Value of recordOrigin field of values to purge. '.
                'Passing this makes the command only remove value from a given recordOrigin'
            )
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
        $recordOrigin = $input->getOption('recordOrigin');

        if ($isSure != 'yes') {
            throw new \LogicException('You must pass "yes" as parameter to show that you know what you\'re doing');
        }

        foreach ($this->getDatabase($input)->listCollections() as $collection) {
            if ($recordOrigin === null) {
                $this->purgeCollection($output, $collection);
            } else {
                $this->purgeResourcesByOrigin($output, $recordOrigin, $collection);
            }
        }

        return 0;
    }

    /**
     * purge all connections from a database
     *
     * @param OutputInterface  $output     Output of the command
     * @param \MongoCollection $collection Collection to purge
     *
     * @return void
     */
    private function purgeCollection(OutputInterface $output, \MongoCollection $collection)
    {
        $collectionName = $collection->getName();
        $output->writeln("<info>Dropping collection <${collectionName}></info>");
        $collection->drop();
    }

    /**
     * purge all connections from a database
     *
     * @param OutputInterface  $output     Output of the command
     * @param \MongoCollection $collection Collection to purge
     * @param string           $origin     Value of recordOrigin field to purge
     *
     * @return void
     */
    private function purgeResourcesByOrigin(OutputInterface $output, \MongoCollection $collection, $origin)
    {
        $collectionName = $collection->getName();
        $output->writeln(
            "<info>Dropping resources with recordOrigin <${origin}> from collection <${collectionName}></info>"
        );
        $collection->remove(['recordOrigin' => $origin]);
    }
}
