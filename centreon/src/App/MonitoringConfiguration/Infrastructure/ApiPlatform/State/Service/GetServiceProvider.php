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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Exception\ServiceNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\ServiceRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceResource;
use App\Security\Infrastructure\Security\CredentialUser;
use Symfony\Bundle\SecurityBundle\Security;
use Webmozart\Assert\Assert;

/**
 * Returns the whole form of one service, built by ServiceResourceBuilder. A service the actor cannot see
 * (outside their access, linked to a host group, a template) answers like a missing one: no existence leak.
 *
 * @implements ProviderInterface<ServiceResource>
 */
final readonly class GetServiceProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private ServiceRepository $serviceRepository,
        private ServiceResourceBuilder $resourceBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ServiceResource
    {
        Assert::integer($uriVariables['id']);
        $serviceId = new ServiceId($uriVariables['id']);

        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);

        $actor = $credentialUser->credential->actingUser();
        $service = $this->serviceRepository->findOne($serviceId, $actor);
        if (! $service instanceof Service) {
            throw new ServiceNotFoundException([$serviceId->value], 'id');
        }

        return $this->resourceBuilder->build($service, $actor);
    }
}
