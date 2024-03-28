<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Resources\Application\UseCase\FindResourcesByParent\Response;

use Core\Resources\Application\UseCase\FindResources\Response\ResourceResponseDto;

final class ResourcesByParentResponseDto
{
    /**
     * @param ResourceResponseDto $parent
     * @param ResourceResponseDto[] $children
     * @param int $total,
     * @param int $totalOK,
     * @param int $totalWarning,
     * @param int $totalCritical,
     * @param int $totalUnknown,
     * @param int $totalPending,
     */
    public function __construct(
        public ResourceResponseDto $parent,
        public array $children = [],
        public int $total = 0,
        public int $totalOK = 0,
        public int $totalWarning = 0,
        public int $totalCritical = 0,
        public int $totalUnknown = 0,
        public int $totalPending = 0,
    ) {
    }
}
