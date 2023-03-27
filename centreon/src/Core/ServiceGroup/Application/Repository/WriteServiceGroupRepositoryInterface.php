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

namespace Core\ServiceGroup\Application\Repository;

use Core\ServiceGroup\Domain\Model\NewServiceGroup;

interface WriteServiceGroupRepositoryInterface
{
    /**
     * Delete a service group.
     *
     * @param int $serviceGroupId
     */
    public function deleteServiceGroup(int $serviceGroupId): void;

    /**
     * @param NewServiceGroup $newServiceGroup
     *
     * @throws \Throwable
     *
     * @return int
     */
    public function add(NewServiceGroup $newServiceGroup): int;
}
