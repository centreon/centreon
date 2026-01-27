<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Core\MonitoringServer\Infrastructure\Command;

use Centreon\Domain\Log\LoggerTrait;
use Core\Common\Domain\Exception\RepositoryException;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\MonitoringServer\Application\UseCase\UpdateMonitoringServer\UpdateMonitoringServer;
use Core\MonitoringServer\Application\UseCase\UpdateMonitoringServer\UpdateMonitoringServerRequest;
use Core\MonitoringServer\Infrastructure\Command\CleanEngineBrokerCommandsCommand\ValidMonitoringServerDto;
use Core\MonitoringServer\Model\MonitoringServer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'web:monitoring-server:clean-engine-broker-command',
    description: 'Command to clean syntax of engine and broker syntaxes',
    help: 'This command will check the engine and broker commands of all Monitoring Servers '
        . 'and will fix them if they are not valid.'
        . PHP_EOL . 'If dry run mode is set, it will only list the Monitoring Servers with invalid commands.'
        . PHP_EOL . 'If dry run mode is not set, it will fix the invalid commands.' . PHP_EOL
        . PHP_EOL . 'By default, the command will be updated as follows:'
        . PHP_EOL . ' - Engine Reload Command: will be set to "systemctl reload centengine"'
        . PHP_EOL . ' - Engine Restart Command: will be set to "systemctl restart centengine"'
        . PHP_EOL . ' - Engine Stop Command: will be set to "systemctl stop centengine"'
        . PHP_EOL . ' - Engine Start Command: will be set to "systemctl start centengine"'
        . PHP_EOL . ' - Broker Reload Command: will be set to "systemctl reload cbd"' . PHP_EOL
        . PHP_EOL . 'You can also change those commands manually by using the following regex patterns:'
        . PHP_EOL . "^(?:service\s+[a-zA-Z0-9_-]+\s+(?:start|stop|restart|reload)|"
        . "systemctl\s+(?:start|stop|restart|reload)\s+[a-zA-Z0-9_-]+)$"
)]
final class CleanEngineBrokerCommandsCommand extends Command
{
    use LoggerTrait;

    public function __construct(
        private readonly UpdateMonitoringServer $useCase,
        private readonly ReadMonitoringServerRepositoryInterface $readRepository,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->addOption(
            name: 'dry-run',
            shortcut: 'dr',
            description: 'execute the script in dry run mode to only list '
                . 'the affected Monitoring Server with invalid path'
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $monitoringServers = $this->readRepository->findAll();
        foreach ($monitoringServers as $monitoringServer) {
            $output->writeln('Monitoring Server: ' . $monitoringServer->getName());
            $violations = $this->validator->validate(ValidMonitoringServerDto::fromModel($monitoringServer));
            if ($violations->count() === 0) {
                $output->writeln('No invalid commands found.');
                continue;
            }
            $invalidPropertyPaths = [];
            foreach ($violations as $violation) {
                /** @var ConstraintViolation $violation */
                $invalidPropertyPaths[] = $violation->getPropertyPath();
                $output->writeln(
                    sprintf(
                        'Invalid command: %s - %s',
                        $violation->getPropertyPath(),
                        $violation->getInvalidValue()
                    )
                );
            }
            if ($input->getOption('dry-run')) {
                continue;
            }
            try {
                ($this->useCase)(new UpdateMonitoringServerRequest(
                    id: $monitoringServer->getId(),
                    name: $monitoringServer->getName(),
                    engineRestartCommand: in_array('engineRestartCommand', $invalidPropertyPaths, true)
                        ? MonitoringServer::DEFAULT_ENGINE_RESTART_COMMAND
                        : $monitoringServer->getEngineRestartCommand(),
                    engineReloadCommand: in_array('engineReloadCommand', $invalidPropertyPaths, true)
                        ? MonitoringServer::DEFAULT_ENGINE_RELOAD_COMMAND
                        : $monitoringServer->getEngineReloadCommand(),
                    engineStopCommand: in_array('engineStopCommand', $invalidPropertyPaths, true)
                        ? MonitoringServer::DEFAULT_ENGINE_STOP_COMMAND
                        : $monitoringServer->getEngineStopCommand(),
                    engineStartCommand: in_array('engineStartCommand', $invalidPropertyPaths, true)
                        ? MonitoringServer::DEFAULT_ENGINE_START_COMMAND
                        : $monitoringServer->getEngineStartCommand(),
                    brokerReloadCommand: in_array('brokerReloadCommand', $invalidPropertyPaths, true)
                        ? MonitoringServer::DEFAULT_BROKER_RELOAD_COMMAND
                        : $monitoringServer->getBrokerReloadCommand()
                ));
                $output->writeln('Commands have been correctly cleaned');
            } catch (RepositoryException $ex) {
                $this->error('An error occured while cleaning engine commands', [
                    'exception' => $ex,
                ]);
                $output->writeln('An error occured while cleaning engine commands, please retry');
                continue;
            }
        }

        $input->getOption('dry-run')
            ? $output->writeln('Dry run completed. No changes were made.')
            : $output->writeln('All invalid commands have been cleaned.');

        return Command::SUCCESS;
    }
}
