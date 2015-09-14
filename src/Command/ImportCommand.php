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
use Webuni\FrontMatter\FrontMatter;
use Webuni\FrontMatter\Document;
use Psr\Http\Message\ResponseInterface;

/**
 * @author   List of contributors <https://github.com/libgraviton/import-export/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class ImportCommand extends Command
{
    /**
     * @var Finder
     */
    private $finder;

    /**
     * @var Client
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
     * @param Finder      $finder      symfony/finder instance
     * @param Client      $client      guzzle http client
     * @param FrontMatter $frontMatter frontmatter parser
     * @param Parser      $parser      yaml/json parser
     * @param VarCloner   $cloner      var cloner for dumping reponses
     * @param Dumper      $dumper      dumper for outputing responses
     */
    public function __construct(
        Finder $finder,
        Client $client,
        FrontMatter $frontMatter,
        Parser $parser,
        VarCloner $cloner,
        Dumper $dumper
    ) {
        $this->finder = $finder;
        $this->client = $client;
        $this->frontMatter = $frontMatter;
        $this->parser = $parser;
        $this->cloner = $cloner;
        $this->dumper = $dumper;
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
            ->setName('graviton:import')
            ->setDescription('Import files from a folder or file.')
            ->addOption(
                'rewrite-host',
                'r',
                InputOption::VALUE_OPTIONAL,
                'Replace the value of this option with the <host> value before importing.',
                'http://localhost'
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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $host = $input->getArgument('host');
        $files = $input->getArgument('file');
        $rewriteHost = $input->getOption('rewrite-host');

        $finder = $this->finder->files();

        foreach ($files as $file) {
            if (is_file($file)) {
                $finder = $finder->in(dirname($file))->name(basename($file));
            } else {
                $finder = $finder->in($file);
            }
        }

        try {
            $this->importPaths($finder, $output, $host, $rewriteHost);
        } catch (MissingTargetException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }

    /**
     * @param Finder          $finder      finder primmed with files to import
     * @param OutputInterface $output      output interfac
     * @param string          $host        host to import into
     * @param string          $rewriteHost string to replace with value from $host during loading
     *
     * @return void
     *
     * @throws MissingTargetException
     */
    protected function importPaths(Finder $finder, OutputInterface $output, $host, $rewriteHost)
    {
        $promises = [];
        foreach ($finder as $file) {
            $doc = $this->frontMatter->parse($file->getContents());

            $output->writeln("<info>Loading data from ${file}</info>");

            if (!array_key_exists('target', $doc->getData())) {
                throw new MissingTargetException('Missing target in \'' . $file . '\'');
            }

            $targetUrl = sprintf('%s%s', $host, $doc->getData()['target']);

            $promises[] = $this->importResource($targetUrl, (string) $file, $output, $doc, $host, $rewriteHost);
        }

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
     *
     * @return Promise\Promise
     */
    protected function importResource($targetUrl, $file, OutputInterface $output, Document $doc, $host, $rewriteHost)
    {
        $content = str_replace($rewriteHost, $host, $doc->getContent());

        $data = $this->parseContent($content, $file);

        $promise = $this->client->requestAsync(
            'PUT',
            $targetUrl,
            [
                'json' => $data,
            ]
        );
        $promise->then(
            function (ResponseInterface $response) use ($output) {
                $output->writeln(
                    '<comment>Wrote ' . $response->getHeader('Link')[0] . '</comment>'
                );
            },
            function (RequestException $e) use ($output, $file) {
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
            }
        );
        return $promise;
    }

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
