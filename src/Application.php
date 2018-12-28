<?php declare(strict_types=1);

namespace MyENA\CloudStackClientGenerator;

use MyENA\CloudStackClientGenerator\Command\BuildCommand;
use MyENA\CloudStackClientGenerator\Command\GenerateClientCommand;
use MyENA\CloudStackClientGenerator\Command\GenerateEventMapCommand;
use Symfony\Component\Console\Application as BaseApplication;

/**
 * Class Application
 * @package MyENA\CloudStackClientGenerator
 */
class Application extends BaseApplication
{
    /**
     * @return array
     */
    protected function getDefaultCommands()
    {
        $commands = [
            new GenerateClientCommand(),
        ];

        if (!(bool)getenv('PHP_CLOUDSTACK_GENERATOR_PHAR')) {
            $commands[] = new BuildCommand();
            $commands[] = new GenerateEventMapCommand();
        }
        return array_merge(
            parent::getDefaultCommands(),
            $commands
        );
    }
}