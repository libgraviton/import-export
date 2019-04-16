<?php
/**
 * small factory to get a logger class
 */

namespace Graviton\ImportExport\Util;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RavenHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Raven_Client;
use Raven_ErrorHandler;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     http://swisscom.ch
 */
class LoggerFactory
{

    /**
     * gets a logger instance
     *
     * @param OutputInterface|null $output output interface
     *
     * @return Logger a logger
     * @throws \Exception
     */
    public static function getInstance(OutputInterface $output = null)
    {
        $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
        $sentryClient = new Raven_Client(
            null,
            [
                'app_path' => __DIR__.'/../../',
                'tags' => ['application' => 'import-export']
            ]
        );

        $monologFormatter = new LineFormatter(null, 'Y-m-d H:i:sO');

        if ($output instanceof OutputInterface) {
            $mainHandler = new ConsoleHandler($output, Logger::INFO);
        } else {
            $mainHandler = new StreamHandler('php://stdout', Logger::INFO);
        }

        $mainHandler->setFormatter($monologFormatter);

        $logger = new Logger('app');
        $logger->pushHandler($mainHandler);
        $logger->pushHandler(new RavenHandler($sentryClient, Logger::WARNING));

        $errorHandler = new Raven_ErrorHandler($sentryClient);
        $errorHandler->registerExceptionHandler();
        $errorHandler->registerErrorHandler();
        $errorHandler->registerShutdownFunction();

        return $logger;
    }
}
