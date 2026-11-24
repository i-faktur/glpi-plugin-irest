<?php

/**
 * iRest for GLPI: companion plugin of the i-REST application.
 *
 * @copyright 2026 I-Motion SARL
 * @license   GPL-3.0-or-later, see the LICENSE file
 * @link      https://www.i-motion.fr
 */

namespace GlpiPlugin\Irest\Console;

use Glpi\Console\AbstractCommand;
use GlpiPlugin\Irest\Client;
use GlpiPlugin\Irest\ClientException;
use GlpiPlugin\Irest\Config;
use GlpiPlugin\Irest\Installer;
use GlpiPlugin\Irest\Prerequisites;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Installation of i-REST in GLPI from the command line, for the packages. To run as the user of the site.
 *
 * Exit codes: 0 everything is in place, 1 something is missing, 2 waiting for i-REST (no address given), 3 i-REST
 * cannot be reached.
 */
class InstallCommand extends AbstractCommand
{
    /** Everything is in place. */
    private const EXIT_OK = 0;

    /** Something is missing. */
    private const EXIT_MISSING = 1;

    /** The base is in place, waiting for the address of i-REST. */
    private const EXIT_PENDING = 2;

    /** i-REST cannot be reached. */
    private const EXIT_UNREACHABLE = 3;

    /**
     * Declares the command and its options.
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setName('plugins:irest:install');
        $this->setDescription('Create what i-REST needs in GLPI: profile, API user, API client');
        $this->addOption('write', null, InputOption::VALUE_NONE, 'Give the read and write rights (default)');
        $this->addOption('read-only', null, InputOption::VALUE_NONE, 'Give the read rights only (trial without writing)');
        $this->addOption('enable-api', null, InputOption::VALUE_NONE, 'Enable the REST API of GLPI and the login with a user token');
        $this->addOption('generate-token', null, InputOption::VALUE_NONE, 'Generate the application and user tokens and print them once');
        $this->addOption('irest-url', null, InputOption::VALUE_REQUIRED, 'Address of i-REST: saves it and creates the prerequisites of the workflows');
        $this->addOption('irest-key', null, InputOption::VALUE_REQUIRED, 'Key of the API of i-REST');
        $this->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text or json', 'text');
    }

    /**
     * Runs the installation and prints its state.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = (string) $input->getOption('format');
        if (!in_array($format, ['text', 'json'], true) || ($input->getOption('write') && $input->getOption('read-only'))) {
            $output->writeln('<error>The format must be text or json, and --write and --read-only exclude each other.</error>');

            return self::EXIT_MISSING;
        }

        $installer = new Installer();
        $installed = $installer->install((bool) $input->getOption('read-only'));
        if ($input->getOption('enable-api')) {
            $installer->enableApi();
        }
        $result = ['installed' => $installed, 'errors' => $installer->errors];
        if ($input->getOption('generate-token') && $installed) {
            $tokens = $installer->generateTokens();
            if ($tokens === null) {
                $installed = false;
                $result['errors'][] = __('The tokens could not be generated.', 'irest');
            } else {
                $result += $tokens;
            }
        }

        $exit = $installed ? self::EXIT_OK : self::EXIT_MISSING;
        if ($input->getOption('irest-url') !== null) {
            $exit = max($exit, $this->connect($input, $result));
        } elseif ($exit === self::EXIT_OK) {
            $exit = self::EXIT_PENDING;
        }

        $result['states'] = [];
        foreach ($installer->states() ?? [] as $state) {
            $result['states'][$state['code']] = $state['status'];
            if ($exit === self::EXIT_OK && $state['status'] !== 'present') {
                $exit = self::EXIT_MISSING;
            }
        }
        $this->printResult($output, $result, $format);

        return $exit;
    }

    /**
     * Saves the address and key of i-REST, then creates the prerequisites of its workflows.
     *
     * @param array<string, mixed> $result completed with the outcome
     *
     * @return int exit code of this step
     */
    private function connect(InputInterface $input, array &$result): int
    {
        try {
            Config::save((string) $input->getOption('irest-url'), (string) $input->getOption('irest-key'), Config::values()['timeout']);
            $client = Client::fromConfig();
            $client->status();
            $result['prerequisites'] = (new Prerequisites($client))->createMissing();
            $result['irest'] = 'ok';

            return self::EXIT_OK;
        } catch (\InvalidArgumentException) {
            $result['irest'] = 'invalid';
        } catch (ClientException) {
            $result['irest'] = 'unreachable';
        }

        return self::EXIT_UNREACHABLE;
    }

    /**
     * Prints the outcome.
     *
     * @param array<string, mixed> $result
     */
    private function printResult(OutputInterface $output, array $result, string $format): void
    {
        if ($format === 'json') {
            $output->writeln((string) json_encode($result, JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);

            return;
        }
        foreach ($result['states'] as $code => $status) {
            $output->writeln(str_pad((string) $code, 40) . $status, OutputInterface::OUTPUT_RAW);
        }
        foreach ($result['errors'] as $error) {
            $output->writeln('Error: ' . $error, OutputInterface::OUTPUT_RAW);
        }
        if (isset($result['app_token'])) {
            $output->writeln('Application token (shown once): ' . $result['app_token'], OutputInterface::OUTPUT_RAW);
            $output->writeln('User token (shown once): ' . $result['user_token'], OutputInterface::OUTPUT_RAW);
        }
    }
}
