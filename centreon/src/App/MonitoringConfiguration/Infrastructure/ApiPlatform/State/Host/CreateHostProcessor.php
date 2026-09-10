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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\MonitoringConfiguration\Application\Command\CreateHostCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Repository\HostGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CreateHostInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostGroupOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostPollerOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostResource;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<CreateHostInput, HostResource>
 */
final readonly class CreateHostProcessor implements ProcessorInterface
{
    /**
     * @param TransformerInterface<Host, HostResource> $transformer
     */
    public function __construct(
        private CommandBus $commandBus,
        #[Autowire(service: HostResourceTransformer::class)]
        private TransformerInterface $transformer,
        private Security $security,
        private PollerRepository $pollerRepository,
        private HostGroupRepository $hostGroupRepository,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): HostResource
    {
        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);

        // Deduplicated the same way legacy does (AddHost::linkHostGroups()): a client repeating an
        // id is tolerated, not rejected, but must not produce one hostgroup_relation row per
        // repetition.
        $hostGroupIds = new Collection(
            array_map(static fn (int $id): HostGroupId => new HostGroupId($id), array_unique($data->hostGroupIds)),
            HostGroupId::class,
        );

        $command = new CreateHostCommand(
            name: new HostName($data->name),
            address: new HostAddress($data->address),
            pollerId: new PollerId($data->pollerId),
            hostGroupIds: $hostGroupIds,
            creatorId: $credentialUser->credential->userId->value,
            viewerId: $credentialUser->credential->hasUnrestrictedResourceAccess() ? null : $credentialUser->credential->userId,
        );

        $host = $this->commandBus->execute($command);
        Assert::isInstanceOf($host, Host::class);

        $pollerName = $this->pollerRepository->findNamesByIds(new Collection([$host->pollerId], PollerId::class))->toArray();
        $groupNames = $this->hostGroupRepository->findNamesByIds($hostGroupIds)->toArray();

        $groups = [];
        foreach ($host->hostGroupIds as $groupId) {
            if (isset($groupNames[$groupId->value])) {
                $groups[] = new HostGroupOutput($groupId->value, $groupNames[$groupId->value]->value);
            }
        }

        $resource = $this->transformer->transform($host);
        $resource->poller = new HostPollerOutput($host->pollerId->value, $pollerName[$host->pollerId->value]->value ?? '');
        $resource->templates = []; // default value as templates are non mandatory and not handled ATM.
        $resource->groups = $groups;

        return $resource;
    }
}
