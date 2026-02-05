<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\AgentConfiguration\Infrastructure\Command;

use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Application\UseCase\CreateHostForAgentConfiguration\CreateHostForAgentConfiguration;
use Core\AgentConfiguration\Application\UseCase\CreateHostForAgentConfiguration\CreateHostForAgentConfigurationRequest;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Application\Repository\WriteHostRepositoryInterface;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'agent-configuration:host:create', description: 'Create a host and deploy services for an agent configuration')]
final readonly class CreateHostForAgentConfigurationCommand
{
    public function __construct(
        private readonly ReadAgentConfigurationRepositoryInterface $agentConfigurationRepository,
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly WriteHostRepositoryInterface $writeHostRepository,
        private readonly ReadServiceRepositoryInterface $readServiceRepository,
        private readonly ReadServiceTemplateRepositoryInterface $readServiceTemplateRepository,
        private readonly WriteServiceRepositoryInterface $writeServiceRepository,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument] int $pollerId,
        #[Argument] string $hostName,
        #[Argument] string $address,
        #[Argument] string $templateName,
    ): int {
        try {
            $request = new CreateHostForAgentConfigurationRequest(
                pollerId: $pollerId,
                hostName: $hostName,
                address: $address,
                templateName: $templateName,
            );

            $useCase = new CreateHostForAgentConfiguration(
                agentConfigurationRepository: $this->agentConfigurationRepository,
                readHostRepository: $this->readHostRepository,
                readHostTemplateRepository: $this->readHostTemplateRepository,
                writeHostRepository: $this->writeHostRepository,
                readServiceRepository: $this->readServiceRepository,
                readServiceTemplateRepository: $this->readServiceTemplateRepository,
                writeServiceRepository: $this->writeServiceRepository,
            );

            $return = ($useCase)($request);

            if ($return['success'] === true) {
                $io->success([
                    $return['message'],
                    $return['details'],
                ]);

                return Command::SUCCESS;
            }

            $io->error([
                $return['message'],
                $return['details'],
            ]);

        } catch (\InvalidArgumentException $ex) {
            $io->error([
                'Host creation process failed',
                sprintf('Reason: Invalid argument (%s)', $ex->getMessage()),
            ]);

        } catch (\Throwable $ex) {
            $io->error([
                'Host creation process failed',
                sprintf('Reason: Unexpected error: %s', $ex->getMessage()),
            ]);
        }

        return Command::FAILURE;
    }
}
