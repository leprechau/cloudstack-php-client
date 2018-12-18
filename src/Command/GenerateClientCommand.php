<?php declare(strict_types=1);

namespace MyENA\CloudStackClientGenerator\Command;

use MyENA\CloudStackClientGenerator\Generator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class GenerateClientCommand
 * @package MyENA\CloudStackClientGenerator\Command
 */
class GenerateClientCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName($this->generateName('generate-client'))
            ->setDescription('Generate a PHP CloudStack Client based on your current CloudStack implementation')
            ->setHelp(<<<STRING
This command will execute the client generation command.  The generator does the following:

- Executes "listApis" to get list of apis presented by your CloudStack instance
- Executes "listCapabilities" to get version and other information about your CloudStack instance
- Attempts to build a client for you

Example Config: 

STRING
                . rtrim(file_get_contents(__DIR__ . '/../../files/config_prototype.yml')) . "\n");

        $this->addConfigOptions();
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|null
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->initializeConfig($input, $output)) {
            return 1;
        }

        $generator = new Generator($this->log, $this->config, $this->env);
        $generator->generate();

        return 0;
    }
}