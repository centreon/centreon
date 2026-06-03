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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerTokenRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Poller\InstallationCommandResource;
use App\MonitoringConfiguration\Infrastructure\PollerInstallationCommandFactory;
use App\Shared\Domain\Repository\EngineSecretsRepository;

/**
 * @template-implements ProviderInterface<InstallationCommandResource>
 */
final readonly class GetInstallationCommandProvider implements ProviderInterface
{
    public function __construct(
        private PollerRepository $pollerRepository,
        private PollerTokenRepository $pollerTokenRepository,
        private EngineSecretsRepository $engineSecretsRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): InstallationCommandResource
    {
        $rawPollerId = $uriVariables['id'] ?? null;
        $pollerId = new PollerId(is_scalar($rawPollerId) ? (int) $rawPollerId : 0);
        $poller = $this->pollerRepository->get($pollerId);

        $filters = is_array($context['filters'] ?? null) ? $context['filters'] : [];
        $tokenName = $filters['token-name'] ?? null;
        $token = is_string($tokenName) && $tokenName !== ''
            ? $this->pollerTokenRepository->getValidPollerTokenByName($tokenName)
            : $this->pollerTokenRepository->getFirstValidPollerToken();

        $factory = new PollerInstallationCommandFactory(
            $poller,
            $token,
            $this->engineSecretsRepository->getAppSecret(),
            $this->engineSecretsRepository->getSalt(),
            '<CENTRAL_URL>',
        );

        return new InstallationCommandResource(
            installationCommand: $factory->generate(),
        );
    }
}
