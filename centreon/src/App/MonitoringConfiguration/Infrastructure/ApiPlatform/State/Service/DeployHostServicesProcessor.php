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
use ApiPlatform\State\ProcessorInterface;
use App\MonitoringConfiguration\Application\Command\DeployHostServicesFromTemplatesCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceResource;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Collection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

/**
 * No request body. Answers the created services as a Get does each of them (HTTP 201, a list), or no content when
 * nothing was created (HTTP 204): a Response returned by a processor is sent as it is.
 *
 * @implements ProcessorInterface<null, list<ServiceResource>|Response>
 */
final readonly class DeployHostServicesProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private Security $security,
        private ServiceResourceBuilder $resourceBuilder,
    ) {
    }

    /**
     * @throws HostNotFoundException
     * @return list<ServiceResource>|Response
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array|Response
    {
        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);
        $actor = $credentialUser->credential->actingUser();

        Assert::keyExists($uriVariables, 'hostId');
        $hostId = filter_var($uriVariables['hostId'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($hostId === false) {
            // Beyond the integer range: no host has it, as legacy answers an id it cannot read.
            throw new HostNotFoundException([], 'hostId');
        }

        $services = $this->commandBus->execute(new DeployHostServicesFromTemplatesCommand(
            hostId: new HostId($hostId),
            actor: $actor,
        ));
        Assert::isInstanceOf($services, Collection::class);

        if (count($services) === 0) {
            return new Response(status: Response::HTTP_NO_CONTENT);
        }

        /** @var array<Service> $created the handler's result */
        $created = $services->toArray();

        return array_values(array_map(
            fn (Service $service): ServiceResource => $this->resourceBuilder->build($service, $actor),
            $created,
        ));
    }
}
