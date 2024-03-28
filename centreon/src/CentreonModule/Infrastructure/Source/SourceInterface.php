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

namespace CentreonModule\Infrastructure\Source;

use CentreonModule\Infrastructure\Entity\Module;

interface SourceInterface
{
    public function initInfo(): void;

    /**
     * @param string|null $search
     * @param bool|null $installed
     * @param bool|null $updated
     *
     * @return array<int,Module>
     */
    public function getList(?string $search = null, ?bool $installed = null, ?bool $updated = null): array;

    public function getDetail(string $id): ?Module;

    public function install(string $id): ?Module;

    public function update(string $id): ?Module;

    public function remove(string $id): void;

    public function createEntityFromConfig(string $configFile): Module;

    public function isEligible(
        Module $entity,
        ?string $search = null,
        ?bool $installed = null,
        ?bool $updated = null
    ): bool;
}
