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

namespace Core\Macro\Application\Repository;

use Core\Macro\Domain\Model\Macro;

/**
 * This interface is designed to read macros for both hosts and host templates.
 */
interface ReadHostMacroRepositoryInterface
{
    /**
     * Find macros by host (or host template) IDs.
     *
     * @param int[] $hostIds
     *
     * @throws \Throwable
     *
     * @return Macro[]
     */
    public function findByHostIds(array $hostIds): array;

    /**
     * Find macros by host (or host template) ID.
     *
     * @param int $hostId
     *
     * @throws \Throwable
     *
     * @return Macro[]
     */
    public function findByHostId(int $hostId): array;

    /**
     * Find password macros.
     *
     * @throws \Throwable
     *
     * @return Macro[]
     */
    public function findPasswords(): array;
}
