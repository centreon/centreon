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
     *
     * @return FindResourcesResponse
     */
    public static function createResponse(
        array $resources,
    ): FindResourcesResponse {
        $response = new FindResourcesResponse();

        foreach ($resources as $resource) {
            $resourceDto = new ResourceResponseDto();
            $statusDto = $resource->getStatus() !== null ? self::createStatusResponseDto($resource->getStatus()) : null;

            $parentResource = $resource->getParent();
            $parentResourceDto = $parentResource !== null ? self::createParentResourceReponseDto($parentResource) : null;
            $severityDto = $resource->getSeverity() !== null ? self::createSeverityResponseDto($resource->getSeverity()) : null;
            $notesDto = $resource->getLinks()->getExternals()->getNotes() !== null
                ? self::createNotesResponseDto($resource->getLinks()->getExternals()->getNotes())
                : null;

            $resourceDto->uuid = $resource->getUuid();
            $resourceDto->id = $resource->getId();
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
            $resourceDto->status = $statusDto;
            $resourceDto->parent = $parentResourceDto;
            $resourceDto->severity = $severityDto;
            $resourceDto->icon = $resource->getIcon() !== null ? self::createIconResponseDto($resource->getIcon()) : null;
            $resourceDto->actionUrl = $resource->getLinks()->getExternals()->getActionUrl();
            $resourceDto->notes = $notesDto;
            $resourceDto->hasGraphData = $resource->hasGraph();

            $resourceDto->lastCheck = $resource->getLastCheck() !== null
                ? \DateTimeImmutable::createFromMutable($resource->getLastCheck())
                : null;

            $resourceDto->lastStatusChange = $resource->getLastStatusChange() !== null
                ? \DateTimeImmutable::createFromMutable($resource->getLastStatusChange())
                : null;

            $response->resources[] = $resourceDto;
        }

        return $response;
    }

    /**
     * @param Notes $note
     *
     * @return NotesResponseDto
     */
    private static function createNotesResponseDto(Notes $note): NotesResponseDto
    {
        $dto = new NotesResponseDto();
        $dto->url = $note->getUrl();
        $dto->label = $note->getLabel();

        return $dto;
    }

    /**
     * @param ResourceEntity $parentResource
     *
     * @return ParentResourceResponseDto
     */
    private static function createParentResourceReponseDto(ResourceEntity $parentResource): ParentResourceResponseDto
    {
        $dto = new ParentResourceResponseDto();
        $dto->uuid = $parentResource->getUuid();
        $dto->id = $parentResource->getId();
        $dto->name = $parentResource->getName();
        $dto->type = $parentResource->getType();
        $dto->shortType = $parentResource->getShortType();
        $dto->alias = $parentResource->getAlias();
        $dto->fqdn = $parentResource->getFqdn();
        $dto->status = $parentResource->getStatus() !== null
            ? self::createStatusResponseDto($parentResource->getStatus())
            : null;

        return $dto;
    }

    /**
     * @param ResourceStatus $status
     *
     * @return ResourceStatusResponseDto
     */
    private static function createStatusResponseDto(ResourceStatus $status): ResourceStatusResponseDto
    {
        $dto = new ResourceStatusResponseDto();
        $dto->code = $status->getCode();
        $dto->name = $status->getName();
        $dto->severityCode = $status->getSeverityCode();

        return $dto;
    }

    /**
     * @param Severity $severity
     *
     * @return SeverityResponseDto
     */
    private static function createSeverityResponseDto(Severity $severity): SeverityResponseDto
    {
        $dto = new SeverityResponseDto();
        $dto->id = $severity->getId();
        $dto->name = $severity->getName();
        $dto->type = $severity->getType();
        $dto->level = $severity->getLevel();
        $dto->icon = self::createIconResponseDto($severity->getIcon());

        return $dto;
    }

    /**
     * @param LegacyIcon|Icon $icon
     *
     * @return IconResponseDto
     */
    private static function createIconResponseDto(LegacyIcon|Icon $icon): IconResponseDto
    {
        $dto = new IconResponseDto();
        $dto->id = $icon->getId();
        $dto->name = $icon->getName();
        $dto->url = $icon->getUrl();

        return $dto;
    }
}
