<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Resources\Application\UseCase\FindResources;

use Centreon\Domain\Monitoring\Icon as LegacyIcon;
use Centreon\Domain\Monitoring\Notes;
use Centreon\Domain\Monitoring\Resource as ResourceEntity;
use Centreon\Domain\Monitoring\ResourceStatus;
use Core\Domain\RealTime\Model\Icon;
use Core\Resources\Application\UseCase\FindResources\Response\IconResponseDto;
use Core\Resources\Application\UseCase\FindResources\Response\NotesResponseDto;
use Core\Resources\Application\UseCase\FindResources\Response\ParentResourceResponseDto;
use Core\Resources\Application\UseCase\FindResources\Response\ResourceResponseDto;
use Core\Resources\Application\UseCase\FindResources\Response\ResourceStatusResponseDto;
use Core\Resources\Application\UseCase\FindResources\Response\SeverityResponseDto;
use Core\Severity\RealTime\Domain\Model\Severity;

final class FindResourcesFactory
{
    /**
     * @param list<ResourceEntity> $resources
     * @param array<string, array<mixed, mixed>> $extraData
     *
     * @return FindResourcesResponse
     */
    public static function createResponse(
        array $resources,
        array $extraData = [],
    ): FindResourcesResponse {
        $response = new FindResourcesResponse();
        $response->extraData = $extraData;

        foreach ($resources as $resource) {
            $parentResource = $resource->getParent();

            $resourceDto = new ResourceResponseDto();
            $resourceDto->resourceId = $resource->getResourceId();
            $resourceDto->uuid = $resource->getUuid();
            $resourceDto->id = $resource->getId();
            $resourceDto->internalId = $resource->getInternalId();
            $resourceDto->name = $resource->getName();
            $resourceDto->type = $resource->getType();
            $resourceDto->fqdn = $resource->getFqdn();
            $resourceDto->alias = $resource->getAlias();
            $resourceDto->hostId = $resource->getHostId();
            $resourceDto->serviceId = $resource->getServiceId();
            $resourceDto->information = $resource->getInformation();
            $resourceDto->isAcknowledged = $resource->getAcknowledged();
            $resourceDto->isInDowntime = $resource->getInDowntime();
            $resourceDto->withActiveChecks = $resource->getActiveChecks();
            $resourceDto->withPassiveChecks = $resource->getPassiveChecks();
            $resourceDto->monitoringServerName = $resource->getMonitoringServerName();
            $resourceDto->shortType = $resource->getShortType();
            $resourceDto->tries = $resource->getTries();
            $resourceDto->status = self::createNullableStatusResponseDto($resource->getStatus());
            $resourceDto->parent = self::createNullableParentResourceResponseDto($parentResource);
            $resourceDto->severity = self::createNullableSeverityResponseDto($resource->getSeverity());
            $resourceDto->icon = self::createNullableIconResponseDto($resource->getIcon());
            $resourceDto->actionUrl = $resource->getLinks()->getExternals()->getActionUrl();
            $resourceDto->notes = self::createNullableNotesResponseDto($resource->getLinks()->getExternals()->getNotes());
            $resourceDto->hasGraphData = $resource->hasGraph();
            $resourceDto->lastCheck = self::createNullableDateTimeImmutable($resource->getLastCheck());
            $resourceDto->lastStatusChange = self::createNullableDateTimeImmutable($resource->getLastStatusChange());

            $response->resources[] = $resourceDto;
        }

        return $response;
    }

    /**
     * @param \DateTimeImmutable|\DateTime|null $date
     *
     * @return ($date is null ? null : \DateTimeImmutable)
     */
    private static function createNullableDateTimeImmutable(null|\DateTimeImmutable|\DateTime $date): ?\DateTimeImmutable
    {
        return match (true) {
            null === $date => null,
            $date instanceof \DateTime => \DateTimeImmutable::createFromMutable($date),
            $date instanceof \DateTimeImmutable => $date,
        };
    }

    /**
     * @param ?Notes $notes
     *
     * @return ($notes is null ? null : NotesResponseDto)
     */
    private static function createNullableNotesResponseDto(?Notes $notes): ?NotesResponseDto
    {
        if (null === $notes) {
            return null;
        }

        $dto = new NotesResponseDto();
        $dto->url = $notes->getUrl();
        $dto->label = $notes->getLabel();

        return $dto;
    }

    /**
     * @param ?ResourceEntity $parentResource
     *
     * @return ($parentResource is null ? null : ParentResourceResponseDto)
     */
    private static function createNullableParentResourceResponseDto(?ResourceEntity $parentResource): ?ParentResourceResponseDto
    {
        if (null === $parentResource) {
            return null;
        }

        $dto = new ParentResourceResponseDto();
        $dto->resourceId = $parentResource->getResourceId();
        $dto->uuid = $parentResource->getUuid();
        $dto->id = $parentResource->getId();
        $dto->name = $parentResource->getName();
        $dto->type = $parentResource->getType();
        $dto->shortType = $parentResource->getShortType();
        $dto->alias = $parentResource->getAlias();
        $dto->fqdn = $parentResource->getFqdn();
        $dto->status = self::createNullableStatusResponseDto($parentResource->getStatus());

        return $dto;
    }

    /**
     * @param ?ResourceStatus $status
     *
     * @return ($status is null ? null : ResourceStatusResponseDto)
     */
    private static function createNullableStatusResponseDto(?ResourceStatus $status): ?ResourceStatusResponseDto
    {
        if (null === $status) {
            return null;
        }

        $dto = new ResourceStatusResponseDto();
        $dto->code = $status->getCode();
        $dto->name = $status->getName();
        $dto->severityCode = $status->getSeverityCode();

        return $dto;
    }

    /**
     * @param ?Severity $severity
     *
     * @return ($severity is null ? null : SeverityResponseDto)
     */
    private static function createNullableSeverityResponseDto(?Severity $severity): ?SeverityResponseDto
    {
        if (null === $severity) {
            return null;
        }

        $dto = new SeverityResponseDto();
        $dto->id = $severity->getId();
        $dto->name = $severity->getName();
        $dto->type = $severity->getType();
        $dto->level = $severity->getLevel();
        $dto->icon = self::createNullableIconResponseDto($severity->getIcon());

        return $dto;
    }

    /**
     * @param LegacyIcon|Icon|null $icon
     *
     * @return ($icon is null ? null : IconResponseDto)
     */
    private static function createNullableIconResponseDto(null|LegacyIcon|Icon $icon): ?IconResponseDto
    {
        if (null === $icon) {
            return null;
        }

        $dto = new IconResponseDto();
        $dto->id = $icon->getId();
        $dto->name = $icon->getName();
        $dto->url = $icon->getUrl();

        return $dto;
    }
}
