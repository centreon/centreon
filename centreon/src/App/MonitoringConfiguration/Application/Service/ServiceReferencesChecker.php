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

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostState;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityName;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\GraphTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\MediaNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceSeverityNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TimePeriodNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\UnlicensedServiceTemplatesException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\GraphTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\Security\Domain\Aggregate\ActingUser;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Domain\Collection;

/**
 * The authoritative check of the resources a partial update of a service refers to, run by the command handler
 * after the same rules ran, earlier, on the input. It catches what changed in between.
 *
 * A resource the actor cannot access is reported like one that does not exist, so a restricted actor cannot probe
 * for what they cannot see. Service templates, graph templates, time periods, icons and commands are not scoped.
 */
final readonly class ServiceReferencesChecker
{
    public function __construct(
        private HostRepository $hostRepository,
        private ServiceTemplateRepository $serviceTemplateRepository,
        private ServiceTemplateLicenseGuard $templateLicenseGuard,
        private ServiceSeverityRepository $serviceSeverityRepository,
        private GraphTemplateRepository $graphTemplateRepository,
        private TimePeriodRepository $timePeriodRepository,
        private MediaRepository $mediaRepository,
        private CommandRepository $commandRepository,
        private AccessScopeResolver $accessScopes,
    ) {
    }

    /**
     * @throws HostNotFoundException
     *
     * @return HostState the activation and the poller of the host
     */
    public function getAccessibleHost(HostId $hostId, ActingUser $actor): HostState
    {
        $host = $this->hostRepository->findState($hostId, $actor);
        if (! $host instanceof HostState) {
            throw new HostNotFoundException([$hostId->value], 'hostId');
        }

        return $host;
    }

    /**
     * The template the service already has is kept, even one it could no longer be given.
     *
     * @throws ServiceTemplateNotFoundException
     * @throws UnlicensedServiceTemplatesException
     */
    public function assertTemplateAllowed(ServiceTemplateId $templateId, ?ServiceTemplateId $currentTemplateId): void
    {
        $names = $this->serviceTemplateRepository->findNamesByIds(new Collection([$templateId], ServiceTemplateId::class));
        if (! isset($names->toArray()[$templateId->value])) {
            throw new ServiceTemplateNotFoundException($templateId->value);
        }

        $this->templateLicenseGuard->assertLicensed($templateId, $currentTemplateId);
    }

    /**
     * @throws ServiceSeverityNotFoundException
     */
    public function assertSeverityAccessible(ServiceSeverityId $severityId, ActingUser $actor): void
    {
        if (
            ! $this->serviceSeverityRepository->findNameById($severityId) instanceof ServiceSeverityName
            || ! $this->accessScopes->serviceSeverities($actor)->allows($severityId)
        ) {
            throw new ServiceSeverityNotFoundException($severityId->value);
        }
    }

    /**
     * @throws GraphTemplateNotFoundException
     */
    public function assertGraphTemplateExists(GraphTemplateId $graphTemplateId): void
    {
        $names = $this->graphTemplateRepository->findNamesByIds(new Collection([$graphTemplateId], GraphTemplateId::class));
        if (! isset($names->toArray()[$graphTemplateId->value])) {
            throw new GraphTemplateNotFoundException($graphTemplateId->value);
        }
    }

    /**
     * @throws TimePeriodNotFoundException
     */
    public function assertTimePeriodExists(TimePeriodId $timePeriodId): void
    {
        if (! $this->timePeriodRepository->existsOne($timePeriodId)) {
            throw new TimePeriodNotFoundException($timePeriodId->value);
        }
    }

    /**
     * @throws MediaNotFoundException
     */
    public function assertMediaExists(MediaId $mediaId): void
    {
        if (! $this->mediaRepository->existsOne($mediaId)) {
            throw new MediaNotFoundException($mediaId->value);
        }
    }

    /**
     * @throws CommandNotFoundException
     */
    public function assertCommandExists(CommandId $commandId): void
    {
        $this->commandRepository->getById($commandId);
    }

    /**
     * A command of another type is refused as one that does not exist.
     *
     * @throws CommandNotFoundException
     */
    public function getCheckCommand(CommandId $commandId): Command
    {
        $command = $this->commandRepository->getById($commandId);
        if ($command->type !== CommandTypeEnum::Check) {
            throw new CommandNotFoundException(['id' => $commandId->value], 'This command does not exist or is not a check command.');
        }

        return $command;
    }
}
