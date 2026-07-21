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
use App\MonitoringConfiguration\Domain\Aggregate\AgentConfiguration\CmaConfigurationParameters;
use App\MonitoringConfiguration\Domain\Aggregate\PlatformMetadata\PlatformMetadataName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Repository\AgentConfigurationRepository;
use App\MonitoringConfiguration\Domain\Repository\PlatformMetadataRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\AgentConfiguration\InstallationCommandResource;
use App\MonitoringConfiguration\Infrastructure\InstallationCommandFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @template-implements ProviderInterface<InstallationCommandResource>
 */
final readonly class GetInstallationCommandProvider implements ProviderInterface
{
    public function __construct(
        private PollerRepository $pollerRepository,
        private PlatformMetadataRepository $informationRepository,
        private AgentConfigurationRepository $agentConfigurationRepository,
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private bool $isCloudPlatform,
        #[Autowire(env: 'default::ORGANIZATION')]
        private ?string $organization,
        #[Autowire(env: 'default::SITE')]
        private ?string $site,
    ) {
        if ($this->isCloudPlatform && ($this->organization === null || $this->site === null)) {
            throw new \RuntimeException('Organization and site must be provided in cloud platform mode.');
        }
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): InstallationCommandResource
    {
        $rawPollerId = $uriVariables['pollerId'] ?? null;
        $pollerId = new PollerId(is_scalar($rawPollerId) ? (int) $rawPollerId : 0);
        $platformVersion = $this->informationRepository->getByName(new PlatformMetadataName('version'));
        $poller = $this->pollerRepository->withCmaCertificates()->get($pollerId);
        $agentConfiguration = $this->agentConfigurationRepository->getByPollerId($poller->id());

        Assert::isInstanceOf(
            $agentConfiguration->configuration,
            CmaConfigurationParameters::class,
            'Installation commands are only available for CMA configurations.'
        );

        $configData = $agentConfiguration->configuration->getData();
        Assert::true(
            ($configData[CmaConfigurationParameters::PARAM_AGENT_INITIATED] ?? false) === true,
            'Installation commands require agent-initiated connection mode.'
        );

        $portData = $configData['port'] ?? null;
        $installationCommand = new InstallationCommandFactory(
            $poller,
            is_scalar($portData) ? (int) $portData : AgentConfiguration::DEFAULT_PORT,
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
