<?php

namespace Google\Web_Stories_Dependencies\AmpProject\Cli\Command;

use Google\Web_Stories_Dependencies\AmpProject\Cli\Command;
use Google\Web_Stories_Dependencies\AmpProject\Cli\Options;
use Google\Web_Stories_Dependencies\AmpProject\Exception\Cli\InvalidArgument;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\ErrorCollection;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\TransformationEngine;
/**
 * Optimize AMP HTML markup and return optimized markup.
 *
 * @package ampproject/amp-toolbox
 */
final class Optimize extends \Google\Web_Stories_Dependencies\AmpProject\Cli\Command
{
    /**
     * Name of the command.
     *
     * @var string
     */
    const NAME = 'optimize';
    /**
     * Help text of the command.
     *
     * @var string
     */
    const HELP_TEXT = 'Optimize AMP HTML markup and return optimized markup.';
    /**
     * Register the command.
     *
     * @param Options $options Options instance to register the command with.
     */
    public function register(\Google\Web_Stories_Dependencies\AmpProject\Cli\Options $options)
    {
        $options->registerCommand(self::NAME, self::HELP_TEXT);
        $options->registerArgument('file', "File with unoptimized AMP markup. Use '-' for STDIN.", \true, self::NAME);
    }
    /**
     * Process the command.
     *
     * Arguments and options have been parsed when this is run.
     *
     * @param Options $options Options instance to process the command with.
     *
     * @throws InvalidArgument If the provided file is not readable.
     */
    public function process(\Google\Web_Stories_Dependencies\AmpProject\Cli\Options $options)
    {
        list($file) = $options->getArguments();
        if ($file !== '-' && (!\is_file($file) || !\is_readable($file))) {
            throw \Google\Web_Stories_Dependencies\AmpProject\Exception\Cli\InvalidArgument::forUnreadableFile($file);
        }
        if ($file === '-') {
            $file = 'php://stdin';
        }
        $html = \file_get_contents($file);
        $optimizer = new \Google\Web_Stories_Dependencies\AmpProject\Optimizer\TransformationEngine();
        $errors = new \Google\Web_Stories_Dependencies\AmpProject\Optimizer\ErrorCollection();
        $optimizedHtml = $optimizer->optimizeHtml($html, $errors);
        echo $optimizedHtml . \PHP_EOL;
    }
}
