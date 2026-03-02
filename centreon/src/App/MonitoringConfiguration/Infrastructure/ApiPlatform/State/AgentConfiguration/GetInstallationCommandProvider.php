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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\AgentConfiguration;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Domain\Aggregate\AgentConfiguration\AgentConfiguration;
use App\MonitoringConfiguration\Infrastructure\InstallationCommandFactory;
use App\MonitoringConfiguration\Domain\Aggregate\Information\InformationName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Repository\AgentConfigurationRepository;
use App\MonitoringConfiguration\Domain\Repository\InformationRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\AgentConfiguration\InstallationCommandResource;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class GetInstallationCommandProvider implements ProviderInterface
{
    public function __construct(
        private readonly PollerRepository $pollerRepository,
        private readonly InformationRepository $informationRepository,
        private readonly AgentConfigurationRepository $agentConfigurationRepository,
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private readonly bool $isCloudPlatform,
        #[Autowire(env: 'default::ORGANIZATION')]
        private readonly ?string $organization,
        #[Autowire(env: 'default::SITE')]
        private readonly ?string $site,
    ) {
        if ($this->isCloudPlatform && (null === $this->organization || null === $this->site)) {
            throw new \RuntimeException('Organization and site must be provided in cloud platform mode.');
        }
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): InstallationCommandResource
    {
        $pollerId = array_key_exists('pollerId', $uriVariables) ? (int) $uriVariables['pollerId'] : null;
        $pollerId = new PollerId($pollerId);
        $platformVersion = $this->informationRepository->getByName(new InformationName('version'));
        $poller = $this->pollerRepository->withCmaCertificates()->get($pollerId);
        $agentConfiguration = $this->agentConfigurationRepository->getByPollerId($poller->id());
        $installationCommand = new InstallationCommandFactory(
            $poller,
            $agentConfiguration->configuration->getData()['port'] ?? AgentConfiguration::DEFAULT_PORT,
            $this->isCloudPlatform,
            $platformVersion->value->value,
            $this->organization,
            $this->site,
        );
        return new InstallationCommandResource(
            windowsInstallationCommand: $installationCommand->generateCommandForWindows(),
            linuxInstallationCommand: $installationCommand->generateCommandForLinux()
        );
    }
}
