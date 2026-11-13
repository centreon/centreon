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

namespace App\MonitoringConfiguration\Application\Service;

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostState;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\InvalidServiceNameException;
use App\MonitoringConfiguration\Domain\Exception\InvalidServiceTemplateAliasException;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
use App\Security\Domain\Aggregate\ActingUser;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Creates on a host one enabled service per service template of its host template chain, named after the
 * template's alias, unless a service directly linked to the host already has that name, compared as on any service
 * creation (case ignored, see {@see ServiceRepository::isNameUsedOnHost()}). It opens no
 * transaction and fires no event: it must be reached through
 * {@see \App\MonitoringConfiguration\Application\Command\DeployHostServicesFromTemplatesCommand}, whose
 * handler fires the events and whose bus transaction (a savepoint when nested) makes the deployment all or nothing.
 */
final readonly class HostServicesDeployer
{
    public function __construct(
        private HostRepository $hostRepository,
        private ServiceTemplateRepository $serviceTemplateRepository,
        private ServiceRepository $serviceRepository,
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private bool $isCloudPlatform = false,
    ) {
    }

    /**
     * @throws HostNotFoundException
     * @throws InvalidServiceTemplateAliasException
     *
     * @return Collection<Service> the created services, in the order of the templates
     */
    public function deploy(HostId $hostId, ActingUser $actor): Collection
    {
        // First, templates or not: a host outside the actor's scope reads as not found.
        $host = $this->hostRepository->findState($hostId, $actor);
        if (! $host instanceof HostState) {
            throw new HostNotFoundException([$hostId->value], 'hostId');
        }

        // Every name is validated before the first write: the activity log and the ACL cache are written on
        // another connection, outside the configuration transaction.
        $candidates = array_map(
            fn (ServiceTemplate $template): array => [$template, $this->nameFrom($template)],
            $this->serviceTemplateRepository->findDeployableOnHost($hostId)->toArray(),
        );

        $services = new Collection([], Service::class);
        foreach ($candidates as [$template, $name]) {
            // Checked after each write, so a name the deployment already gave is skipped too.
            if ($this->serviceRepository->isNameUsedOnHost($name, $hostId)) {
                continue;
            }

            $service = new Service(
                id: null,
                name: $name,
                hostId: $hostId,
                hostActivated: $host->activated,
                hostPollerId: $host->pollerId,
                activated: true,
                templateId: $template->id(),
                notifications: $this->isCloudPlatform ? null : $this->defaultNotifications(),
                serviceGroupIds: $template->serviceGroupIds,
            );
            $this->serviceRepository->add($service);
            $services->add($service);
        }

        return $services;
    }

    /**
     * @throws InvalidServiceTemplateAliasException
     */
    private function nameFrom(ServiceTemplate $template): ServiceName
    {
        try {
            return ServiceName::fromUserInput($template->alias ?? '');
        } catch (InvalidServiceNameException $exception) {
            throw InvalidServiceTemplateAliasException::fromInvalidName($template->id()->value, $exception);
        }
    }

    /**
     * The block a service created through the API gets when none is sent, so that the created service reads the
     * same as when it is fetched afterwards.
     */
    private function defaultNotifications(): ServiceNotifications
    {
        return new ServiceNotifications(
            enabled: TriStateEnum::UseDefault,
            contactIds: new Collection([], NotificationContactId::class),
            contactGroupIds: new Collection([], ContactGroupId::class),
        );
    }
}
