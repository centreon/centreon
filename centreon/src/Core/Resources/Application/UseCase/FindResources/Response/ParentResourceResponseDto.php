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

namespace Core\Resources\Application\UseCase\FindResources\Response;

final class ParentResourceResponseDto
{
    /**
     * @param int|null $resourceId
     * @param string|null $uuid
     * @param int|null $id
     * @param string|null $name
     * @param string|null $type
     * @param string|null $alias
     * @param string|null $fqdn
     * @param ResourceStatusResponseDto|null $status
     * @param string|null $monitoringServerName
     * @param string|null $shortType
     */
    public function __construct(
        public ?int $resourceId = null,
        public ?string $uuid = null,
        public ?int $id = null,
        public ?string $name = null,
        public ?string $type = null,
        public ?string $alias = null,
        public ?string $fqdn = null,
        public ?ResourceStatusResponseDto $status = null,
        public ?string $monitoringServerName = null,
        public ?string $shortType = null,
    ) {
    }
}
