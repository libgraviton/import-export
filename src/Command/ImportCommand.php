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
use Graviton\ImportExport\Exception\ParseException;
use Graviton\ImportExport\Exception\UnknownFileTypeException;
use Graviton\ImportExport\Service\HttpClient;
use Monolog\Logger;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper as Dumper;
use GuzzleHttp\Promise;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;
use Webuni\FrontMatter\FrontMatter;
use Webuni\FrontMatter\Document;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  https://opensource.org/licenses/MIT MIT License
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
     * Header basic auth
     * @var string
     */
    private $headerBasicAuth;

    /**
     * Header for custom variables
     * @var array
     */
    private $customHeaders;

    /**
     * @param Logger      $logger      logger
     * @param HttpClient  $client      Grv HttpClient guzzle http client
     * @param Finder      $finder      symfony/finder instance
     * @param FrontMatter $frontMatter frontmatter parser
     * @param Parser      $parser      yaml/json parser
     * @param VarCloner   $cloner      var cloner for dumping reponses
     * @param Dumper      $dumper      dumper for outputing responses
     */
    public function __construct(
        Logger $logger,
        HttpClient $client,
        Finder $finder,
        FrontMatter $frontMatter,
        Parser $parser,
        VarCloner $cloner,
        Dumper $dumper
    ) {
        parent::__construct(
            $logger,
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
            ->addOption(
                'no-overwrite',
                'o',
                InputOption::VALUE_NONE,
                'If set, we will check for record existence and not overwrite existing ones.'
            )
            ->addOption(
                'headers-basic-auth',
                'a',
                InputOption::VALUE_OPTIONAL,
                'Header user:password for Basic auth'
            )
            ->addOption(
                'custom-headers',
                'c',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Custom Header variable(s), -c{key:value} and multiple is optional.'
            )
            ->addOption(
                'input-file',
                'i',
                InputOption::VALUE_REQUIRED,
                'If provided, the list of files to load will be loaded from this file, one file per line.'
            )
            ->addArgument(
                'host',
                InputArgument::REQUIRED,
                'Protocol and host to load data into (ie. https://graviton.nova.scapp.io)'
            )
            ->addArgument(
                'file',
                InputArgument::IS_ARRAY,
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
        $host = $input->getArgument('host');
        $rewriteHost = $input->getOption('rewrite-host');
        $rewriteTo = $input->getOption('rewrite-to');
        $this->headerBasicAuth = $input->getOption('headers-basic-auth');
        $this->customHeaders = $input->getOption('custom-headers');
        if ($rewriteTo === $this->getDefinition()->getOption('rewrite-to')->getDefault()) {
            $rewriteTo = $host;
        }
        $noOverwrite = $input->getOption('no-overwrite');

        $this->importPaths($finder, $output, $host, $rewriteHost, $rewriteTo, $noOverwrite);

        // Error exit
        if (empty($this->errors)) {
            // No errors
            $this->logger->info('No errors');
        } else {
            // Yes, there was errors
            $this->logger->error(
                'There were import errors',
                [
                    'errorCount' => count($this->errors),
                    'errors' => $this->errors
                ]
            );
            $exitCode = 1;
        }
        return $exitCode;
    }

    /**
     * @param Finder          $finder      finder primmed with files to import
     * @param OutputInterface $output      output interfac
     * @param string          $host        host to import into
     * @param string          $rewriteHost string to replace with value from $rewriteTo during loading
     * @param string          $rewriteTo   string to replace value from $rewriteHost with during loading
     * @param boolean         $noOverwrite should we not overwrite existing records?
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
        $noOverwrite = false
    ) {
        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            $doc = $this->frontMatter->parse($file->getContents());

            $this->logger->info("Loading data from ${file}");

            if (!array_key_exists('target', $doc->getData())) {
                throw new MissingTargetException('Missing target in \'' . $file . '\'');
            }

            $targetUrl = sprintf('%s%s', $host, $doc->getData()['target']);

            $this->importResource(
                $targetUrl,
                (string) $file,
                $output,
                $doc,
                $rewriteHost,
                $rewriteTo,
                $noOverwrite
            );
        }
    }

    /**
     * @param string          $targetUrl   target url to import resource into
     * @param string          $file        path to file being loaded
     * @param OutputInterface $output      output of the command
     * @param Document        $doc         document to load
     * @param string          $rewriteHost string to replace with value from $host during loading
     * @param string          $rewriteTo   string to replace value from $rewriteHost with during loading
     * @param boolean         $noOverwrite should we not overwrite existing records?
     *
     * @return Promise\PromiseInterface|null
     */
    protected function importResource(
        $targetUrl,
        $file,
        OutputInterface $output,
        Document $doc,
        $rewriteHost,
        $rewriteTo,
        $noOverwrite = false
    ) {
        $content = str_replace($rewriteHost, $rewriteTo, $doc->getContent());
        $uploadFile = $this->validateUploadFile($doc, $file);

        $data = [
            'json'   => $this->parseContent($content, $file),
            'upload' => $uploadFile,
            'headers'=> []
        ];

        // Authentication or custom headers.
        if ($this->headerBasicAuth) {
            $data['headers']['Authorization'] = 'Basic '. base64_encode($this->headerBasicAuth);
        }
        if ($this->customHeaders) {
            foreach ($this->customHeaders as $headers) {
                list($key, $value) = explode(':', $headers);
                $data['headers'][$key] = $value;
            }
        }
        if (empty($data['headers'])) {
            unset($data['headers']);
        }

        // skip if no overwriting has been requested
        if ($noOverwrite) {
            $response = $this->client->request('GET', $targetUrl, array_merge($data, ['http_errors' => false]));
            if ($response->getStatusCode() == 200) {
                $this->logger->info(
                    sprintf(
                        'Skipping <%s> as "no overwrite" is activated and it does exist.',
                        $targetUrl
                    )
                );
                return;
            }
        }

        try {
            if ($uploadFile) {
                unset($this->errors[$file]);

                // see if file exists..
                $checkRequestData = array_merge($data, ['http_errors' => false]);
                $checkRequestData['headers']['accept'] = 'application/json';
                $response = $this->client->request('GET', $targetUrl, $checkRequestData);

                if ($response->getStatusCode() <> 404) {
                    $response = $this->client->request('DELETE', $targetUrl, array_merge($data, ['http_errors' => false]));
                    $this->logger->info("File deleted: ${targetUrl} (response code " . $response->getStatusCode().")");
                }
            }

            $response = $this->client->request(
                'PUT',
                $targetUrl,
                $data
            );

            $this->logger->info('Wrote ' . $response->getHeader('Link')[0]);
        } catch (\Exception $e) {
            $this->errors[$file] = $e->getMessage();
            $this->logger->error(
                sprintf(
                    'Failed to write <%s> from \'%s\' with message \'%s\'',
                    $e->getRequest()->getUri(),
                    $file,
                    $e->getMessage()
                ),
                ['exception' => $e]
            );
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $this->dumper->dump(
                    $this->cloner->cloneVar(
                        $this->parser->parse($e->getResponse()->getBody(), Yaml::PARSE_OBJECT_FOR_MAP)
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
        }
    }

    /**
     * parse contents of a file depending on type
     *
     * @param string $content contents part of file
     * @param string $file    full path to file
     *
     * @return mixed
     * @throws UnknownFileTypeException
     * @throws ParseException
     */
    protected function parseContent($content, $file)
    {
        if (substr($file, -5) == '.json') {
            $data = json_decode($content);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ParseException(
                    sprintf(
                        '%s in %s',
                        json_last_error_msg(),
                        $file
                    )
                );
            }
        } elseif (substr($file, -4) == '.yml') {
            try {
                $data = $this->parser->parse($content);
            } catch (\Exception $e) {
                throw new ParseException(
                    sprintf(
                        'YAML parse error in file %s, message = %s',
                        $file,
                        $e->getMessage()
                    ),
                    0,
                    $e
                );
            }
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
