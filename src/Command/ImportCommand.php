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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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

        $finder = $this->finder->files();

        foreach ($input->getArgument('file') as $file) {
            if (is_file($file)) {
                $finder = $finder->in(dirname($file))->name(basename($file));
            } else {
                $finder = $finder->in($file);
            }
        }

        foreach ($finder as $file) {
            $doc = $this->frontMatter->parse($file->getContents());

            if (!$doc->getData()['target']) {
                throw new MissingTargetException($file);
            }

            $targetUrl = sprintf('%s%s', $host, $doc->getData()['target']);

            $promises[] = $this->importResource($targetUrl, (string) $file, $output, $doc);
        }
        try {
            Promise\unwrap($promises);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // silently ignored since we already output an error when the promise fails
        }
    }

    /**
     * @param string          $targetUrl target url to import resource into
     * @param string          $file      path to file being loaded
     * @param OutputInterface $output    output of the command
     * @param Document        $doc       document to load
     *
     * @return Promise/Promise
     */
    protected function importResource($targetUrl, $file, OutputInterface $output, Document $doc)
    {
        $output->writeln("<info>Loading ${targetUrl} from ${file}</info>");
        $promise = $this->client->requestAsync(
            'PUT',
            $targetUrl,
            [
                'json' => $this->parser->parse($doc->getContent(), false, false, true)
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
}
