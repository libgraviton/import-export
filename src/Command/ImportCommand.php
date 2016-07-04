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
use Graviton\ImportExport\Service\HttpClient;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper as Dumper;
use GuzzleHttp\Promise;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\Finder\SplFileInfo;
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
     * @var HttpClient
     */
    private $client;

    /**
     * @var FrontMatter
     */
    private $frontMatter;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var VarCloner
     */
    private $cloner;

    /**
     * @var Dumper
     */
    private $dumper;

    /**
     * Count of errors
     * @var array
     */
    private $errors = [];

    /**
     * @param HttpClient  $client      Grv HttpClient guzzle http client
     * @param Finder      $finder      symfony/finder instance
     * @param FrontMatter $frontMatter frontmatter parser
     * @param Parser      $parser      yaml/json parser
     * @param VarCloner   $cloner      var cloner for dumping reponses
     * @param Dumper      $dumper      dumper for outputing responses
     */
    public function __construct(
        HttpClient $client,
        Finder $finder,
        FrontMatter $frontMatter,
        Parser $parser,
        VarCloner $cloner,
        Dumper $dumper
    ) {
        parent::__construct(
            $finder
        );
        $this->client = $client;
        $this->frontMatter = $frontMatter;
        $this->parser = $parser;
        $this->cloner = $cloner;
        $this->dumper = $dumper;
    }

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
                'rewrite-to',
                't',
                InputOption::VALUE_OPTIONAL,
                'String to use as the replacement value for the [REWRITE-HOST] string.',
                '<host>'
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
     * @param Finder          $finder Finder
     * @param InputInterface  $input  User input on console
     * @param OutputInterface $output Output of the command
     *
     * @return void
     */
    protected function doImport(Finder $finder, InputInterface $input, OutputInterface $output)
    {
        $host = $input->getArgument('host');
        $rewriteHost = $input->getOption('rewrite-host');
        $rewriteTo = $input->getOption('rewrite-to');
        if ($rewriteTo === $this->getDefinition()->getOption('rewrite-to')->getDefault()) {
            $rewriteTo = $host;
        }
        $sync = $input->getOption('sync-requests');

        $this->importPaths($finder, $output, $host, $rewriteHost, $rewriteTo, $sync);

        // Error exit
        if (empty($this->errors)) {
            // No errors
            $output->writeln("\n".'<info>No errors</info>');
            $output->writeln('0');
            exit(0);
        } else {
            // Yes, there was errors
            $output->writeln("\n".'<info>There was errors: '.count($this->errors).'</info>');
            foreach ($this->errors as $file => $error) {
                $output->writeln("<error>{$file}: {$error}</error>");
            }
            $output->writeln('1');
            exit(1);
        }
    }

    /**
     * @param Finder          $finder      finder primmed with files to import
     * @param OutputInterface $output      output interfac
     * @param string          $host        host to import into
     * @param string          $rewriteHost string to replace with value from $rewriteTo during loading
     * @param string          $rewriteTo   string to replace value from $rewriteHost with during loading
     * @param boolean         $sync        send requests syncronously
     *
     * @return void
     *
     * @throws MissingTargetException
     */
    protected function importPaths(
        Finder $finder,
        OutputInterface $output,
        $host,
        $rewriteHost,
        $rewriteTo,
        $sync = false
    ) {
        $promises = [];
        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            $doc = $this->frontMatter->parse($file->getContents());

            $output->writeln("<info>Loading data from ${file}</info>");

            if (!array_key_exists('target', $doc->getData())) {
                throw new MissingTargetException('Missing target in \'' . $file . '\'');
            }

            $targetUrl = sprintf('%s%s', $host, $doc->getData()['target']);

            $promises[] = $this->importResource(
                $targetUrl,
                (string) $file,
                $output,
                $doc,
                $rewriteHost,
                $rewriteTo,
                $sync
            );
        }

        try {
            Promise\unwrap($promises);
        } catch (ClientException $e) {
            // silently ignored since we already output an error when the promise fails
        }
    }

    /**
     * @param string          $targetUrl   target url to import resource into
     * @param string          $file        path to file being loaded
     * @param OutputInterface $output      output of the command
     * @param Document        $doc         document to load
     * @param string          $rewriteHost string to replace with value from $host during loading
     * @param string          $rewriteTo   string to replace value from $rewriteHost with during loading
     * @param boolean         $sync        send requests syncronously
     *
     * @return Promise\Promise|null
     */
    protected function importResource(
        $targetUrl,
        $file,
        OutputInterface $output,
        Document $doc,
        $rewriteHost,
        $rewriteTo,
        $sync = false
    ) {
        $content = str_replace($rewriteHost, $rewriteTo, $doc->getContent());
        $uploadFile = $this->validateUploadFile($doc, $file);

        $successFunc = function (ResponseInterface $response) use ($output) {
            $output->writeln(
                '<comment>Wrote ' . $response->getHeader('Link')[0] . '</comment>'
            );
        };

        $errFunc = function (RequestException $e) use ($output, $file) {
            $this->errors[$file] = $e->getMessage();
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

        $data = [
            'json'   => $this->parseContent($content, $file),
            'upload' => $uploadFile
        ];
        $promise = $this->client->requestAsync(
            'PUT',
            $targetUrl,
            $data
        );

        // If there is a file to be uploaded, and it exists in remote, we delete it first.
        // TODO This part, $uploadFile, promise should be removed once Graviton/File service is resolved in new Story. 
        $fileRepeatFunc = false;
        if ($uploadFile) {
            $fileRepeatFunc = function () use ($targetUrl, $successFunc, $errFunc, $output, $file, $data) {
                unset($this->errors[$file]);
                $output->writeln('<info>File deleting: '.$targetUrl.'</info>');
                $deleteRequest = $this->client->requestAsync('DELETE', $targetUrl);
                $insert = function () use ($targetUrl, $successFunc, $errFunc, $output, $data) {
                    $output->writeln('<info>File inserting: '.$targetUrl.'</info>');
                    $promiseInsert = $this->client->requestAsync('PUT', $targetUrl, $data);
                    $promiseInsert->then($successFunc, $errFunc);
                };
                $deleteRequest
                    ->then($insert, $errFunc)->wait();
            };
        }

        $promiseError = $fileRepeatFunc ? $fileRepeatFunc : $errFunc;
        if ($sync) {
            $promise->then($successFunc, $promiseError)->wait();
        } else {
            $promise->then($successFunc, $promiseError);
        }



        return $promise;
    }

    /**
     * parse contents of a file depending on type
     *
     * @param string $content contents part of file
     * @param string $file    full path to file
     *
     * @return mixed
     * @throws UnknownFileTypeException
     * @throws JsonParseException
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
            $data = $this->parser->parse($content);
        } else {
            throw new UnknownFileTypeException($file);
        }

        return $data;
    }

    /**
     * Checks if file exists and return qualified fileName location
     *
     * @param Document $doc        Data source for import data
     * @param string   $originFile Original full filename used toimport
     * @return bool|mixed
     */
    private function validateUploadFile(Document $doc, $originFile)
    {
        $documentData = $doc->getData();

        if (!array_key_exists('file', $documentData)) {
            return false;
        }

        // Find file
        $fileName = dirname($originFile) . DIRECTORY_SEPARATOR . $documentData['file'];
        $fileName = str_replace('//', '/', $fileName);
        if (!file_exists($fileName)) {
            return false;
        }

        return $fileName;
    }
}
