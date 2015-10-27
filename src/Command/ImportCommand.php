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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper as Dumper;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\BadResponseException;
use Webuni\FrontMatter\FrontMatter;
use Webuni\FrontMatter\Document;
use Psr\Http\Message\ResponseInterface;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class ImportCommand extends ImportCommandAbstract
{

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('graviton:import')
            ->setDescription('Import files from a folder or file.')
            ->addOption(
                'rewrite-host',
                'r',
                InputOption::VALUE_OPTIONAL,
                'Replace the value of this option with the <host> value before importing.',
                'http://localhost'
            )
            ->addOption(
                'sync-requests',
                's',
                InputOption::VALUE_NONE,
                'Send requests synchronously'
            )
            ->addArgument(
                'host',
                InputArgument::REQUIRED,
                'Protocol and host to load data into (ie. https://graviton.nova.scapp.io)'
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
     * @param InputInterface  $input  User input on console
     * @param OutputInterface $output Output of the command
     *
     * @return void
     */
    protected function doImport(Finder $finder, InputInterface $input, OutputInterface $output)
    {
        $host = $input->getArgument('host');
        $rewriteHost = $input->getOption('rewrite-host');
        $sync = $input->getOption('sync-requests');

        $this->importPaths($finder, $output, $host, $rewriteHost, $sync);
    }

    /**
     * @param Finder          $finder      finder primmed with files to import
     * @param OutputInterface $output      output interfac
     * @param string          $host        host to import into
     * @param string          $rewriteHost string to replace with value from $host during loading
     * @param boolean         $sync        send requests syncronously
     *
     * @return void
     *
     * @throws MissingTargetException
     */
    protected function importPaths(Finder $finder, OutputInterface $output, $host, $rewriteHost, $sync = false)
    {
        $promises = [];
        foreach ($finder as $file) {
            $doc = $this->frontMatter->parse($file->getContents());

            $output->writeln("<info>Loading data from ${file}</info>");

            if (!array_key_exists('target', $doc->getData())) {
                throw new MissingTargetException('Missing target in \'' . $file . '\'');
            }

            $targetUrl = sprintf('%s%s', $host, $doc->getData()['target']);

            $promises[] = $this->importResource($targetUrl, (string) $file, $output, $doc, $host, $rewriteHost, $sync);
        }

        echo "hans"; die;

        try {
            Promise\unwrap($promises);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // silently ignored since we already output an error when the promise fails
        }
    }

    /**
     * @param string          $targetUrl   target url to import resource into
     * @param string          $file        path to file being loaded
     * @param OutputInterface $output      output of the command
     * @param Document        $doc         document to load
     * @param string          $host        host to import into
     * @param string          $rewriteHost string to replace with value from $host during loading
     * @param boolean         $sync        send requests syncronously
     *
     * @return Promise\Promise|null
     */
    protected function importResource(
        $targetUrl,
        $file,
        OutputInterface $output,
        Document $doc,
        $host,
        $rewriteHost,
        $sync = false
    ) {
        $content = str_replace($rewriteHost, $host, $doc->getContent());

        $successFunc = function (ResponseInterface $response) use ($output) {
            $output->writeln(
                '<comment>Wrote ' . $response->getHeader('Link')[0] . '</comment>'
            );
        };

        $errFunc = function (RequestException $e) use ($output, $file) {
            $output->writeln(
                '<error>' . str_pad(
                    sprintf(
                        'Failed to write <%s> from \'%s\' with message \'%s\'',
                        $e->getRequest()->getUri(),
                        $file,
                        $e->getMessage()
                    ),
                    140,
                    ' '
                ) . '</error>'
            );
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $this->dumper->dump(
                    $this->cloner->cloneVar(
                        $this->parser->parse($e->getResponse()->getBody(), false, false, true)
                    ),
                    function ($line, $depth) use ($output) {
                        if ($depth > 0) {
                            $output->writeln(
                                '<error>' . str_pad(str_repeat('  ', $depth) . $line, 140, ' ') . '</error>'
                            );
                        }
                    }
                );
            }
        };

        if ($sync === false) {
            $promise = $this->client->requestAsync(
                'PUT',
                $targetUrl,
                [
                    'json' => $this->parseContent($content, $file)
                ]
            );
            $promise->then($successFunc, $errFunc);
        } else {
            $promise = new Promise\Promise;
            try {
                $promise->resolve(
                    $successFunc(
                        $this->client->request(
                            'PUT',
                            $targetUrl,
                            [
                                'json' => $this->parseContent($content, $file),
                            ]
                        )
                    )
                );
            } catch (BadResponseException $e) {
                $promise->resolve(
                    $errFunc($e)
                );
            }
        }
        return $promise;
    }
}
