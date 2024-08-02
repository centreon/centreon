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

namespace Core\Resources\Application\UseCase\FindResourcesByParent;

use Core\Resources\Application\UseCase\FindResources\Response\ResourceResponseDto;
use Core\Resources\Application\UseCase\FindResourcesByParent\Response\ResourcesByParentResponseDto;

final class FindResourcesByParentFactory
{
    private const STATUS_CODE_OK = 0,
        STATUS_CODE_WARNING = 1,
        STATUS_CODE_CRITICAL = 2,
        STATUS_CODE_UNKNOWN = 3,
        STATUS_CODE_PENDING = 4;

    /**
     * @param list<ResourceResponseDto> $parents
     * @param list<ResourceResponseDto> $children
     * @param array<string, array<mixed, mixed>> $extraData
     *
     * @return FindResourcesByParentResponse
     */
    public static function createResponse(array $parents, array $children, array $extraData): FindResourcesByParentResponse
    {
        $resources = [];
        foreach ($parents as $parent) {
            $found = null === $parent->id ? [] : self::findChildrenAmongResponse($parent->id, $children);
            $resources[] = new ResourcesByParentResponseDto(
                $parent,
                array_values($found),
                count($found),
                self::countInStatus(self::STATUS_CODE_OK, $found),
                self::countInStatus(self::STATUS_CODE_WARNING, $found),
                self::countInStatus(self::STATUS_CODE_CRITICAL, $found),
                self::countInStatus(self::STATUS_CODE_UNKNOWN, $found),
                self::countInStatus(self::STATUS_CODE_PENDING, $found)
            );
        }

        return new FindResourcesByParentResponse($resources, $extraData);
    }

    /**
     * Return all children of Parent identified by parentId.
     *
     * @param int $parentId
     * @param ResourceResponseDto[] $children
     *
     * @return ResourceResponseDto[]
     */
    private static function findChildrenAmongResponse(int $parentId, array $children): array
    {
        return array_filter(
            $children,
            static fn(ResourceResponseDto $child) => $child->parent?->id === $parentId
        );
    }

    /**
     * Count children in given status.
     *
     * @param int $statusCode
     * @param ResourceResponseDto[] $children
     *
     * @return int
     */
    private static function countInStatus(int $statusCode, array $children): int
    {
        $childrenInStatus = array_filter(
            $children,
            static fn(ResourceResponseDto $resource) => $resource->status?->code === $statusCode
        );

        return count($childrenInStatus);
    }
}
