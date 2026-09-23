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
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Repository\HostGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CreateHostInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CreateHostNotificationsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostExtendedInformationsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostGroupOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostIconOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostNotificationsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostPollerOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media\MediaUrlGenerator;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Aggregate\TriStateEnum;
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
     * @param TransformerInterface<?Notifications, ?HostNotificationsOutput> $notificationsTransformer
     */
    public function __construct(
        private CommandBus $commandBus,
        #[Autowire(service: HostResourceTransformer::class)]
        private TransformerInterface $transformer,
        private Security $security,
        private PollerRepository $pollerRepository,
        private HostGroupRepository $hostGroupRepository,
        private MediaRepository $mediaRepository,
        #[Autowire(service: HostNotificationsTransformer::class)]
        private TransformerInterface $notificationsTransformer,
        private MediaUrlGenerator $mediaUrlGenerator,
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private bool $isCloudPlatform = false,
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

        $extendedInformationsInput = $data->extendedInformations;
        $extendedInformations = new ExtendedInformations(
            noteUrl: $extendedInformationsInput?->noteUrl,
            note: $extendedInformationsInput?->note,
            actionUrl: $extendedInformationsInput?->actionUrl,
            iconId: $extendedInformationsInput?->iconId !== null ? new MediaId($extendedInformationsInput->iconId) : null,
            altIcon: $extendedInformationsInput?->altIcon,
            comment: $extendedInformationsInput?->comment,
            geoCoordinates: $extendedInformationsInput?->geoCoordinates !== null
                ? GeoCoordinates::fromString($extendedInformationsInput->geoCoordinates)
                : null,
        );

        // Cloud handles notifications through a different model (MON-204689) and the input
        // validator rejects the block there, so the host simply carries none — and the
        // response omits the key rather than advertising a feature that platform lacks.
        $notifications = $this->isCloudPlatform ? null : $this->buildNotifications($data->notifications);

        $command = new CreateHostCommand(
            name: new HostName($data->name),
            address: new HostAddress($data->address),
            pollerId: new PollerId($data->pollerId),
            hostGroupIds: $hostGroupIds,
            creatorId: $credentialUser->credential->userId->value,
            viewerId: $credentialUser->credential->hasUnrestrictedResourceAccess() ? null : $credentialUser->credential->userId,
            extendedInformations: $extendedInformations,
            notifications: $notifications,
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

        $icon = $this->resolveIcon($host->extendedInformations?->iconId);

        $resource = $this->transformer->transform($host);
        $resource->poller = new HostPollerOutput($host->pollerId->value, $pollerName[$host->pollerId->value]->value ?? '');
        $resource->templates = []; // default value as templates are non mandatory and not handled ATM.
        $resource->groups = $groups;
        $resource->extendedInformations = new HostExtendedInformationsOutput(
            noteUrl: $host->extendedInformations?->noteUrl,
            note: $host->extendedInformations?->note,
            actionUrl: $host->extendedInformations?->actionUrl,
            icon: $icon,
            altIcon: $host->extendedInformations?->altIcon,
            comment: $host->extendedInformations?->comment,
            geoCoordinates: $host->extendedInformations?->geoCoordinates instanceof GeoCoordinates
                ? (string) $host->extendedInformations->geoCoordinates
                : null,
        );

        $resource->notifications = $this->notificationsTransformer->transform($host->notifications);

        return $resource;
    }

    /**
     * Always built on-premise, even for a request carrying no notification block: the columns are
     * written either way (with the Default tri-state), so the response reports what was actually
     * persisted rather than dropping the key.
     */
    private function buildNotifications(?CreateHostNotificationsInput $input): Notifications
    {
        $input ??= new CreateHostNotificationsInput();

        // Deduplicated like the host groups above (and like legacy's own form does through its
        // DELETE/INSERT cycle): a client repeating an id is tolerated, but the relation tables
        // carry no unique index, so a repetition would otherwise become a duplicate row.
        return new Notifications(
            enabled: TriStateEnum::from($input->enabled),
            contactIds: new Collection(
                array_map(static fn (int $id): NotificationContactId => new NotificationContactId($id), array_unique($input->contacts)),
                NotificationContactId::class,
            ),
            contactGroupIds: new Collection(
                array_map(static fn (int $id): ContactGroupId => new ContactGroupId($id), array_unique($input->contactGroups)),
                ContactGroupId::class,
            ),
            options: array_map(HostNotificationsTransformer::optionFromApi(...), $input->options),
            interval: $input->interval,
            periodId: $input->period !== null ? new TimePeriodId($input->period) : null,
            firstDelay: $input->firstDelay,
            recoveryDelay: $input->recoveryDelay,
            contactAdditiveInheritance: $input->contactAdditiveInheritance,
            contactGroupAdditiveInheritance: $input->contactGroupAdditiveInheritance,
        );
    }

    private function resolveIcon(?MediaId $iconId): ?HostIconOutput
    {
        if (! $iconId instanceof MediaId) {
            return null;
        }

        $icon = $this->mediaRepository->findByIds(new Collection([$iconId], MediaId::class))->toArray()[$iconId->value] ?? null;

        return $icon instanceof Media
            ? new HostIconOutput($icon->id()->value, $icon->name->value, $this->mediaUrlGenerator->generate($icon))
            : null;
    }
}
