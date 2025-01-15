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

namespace Core\AdditionalConnectorConfiguration\Application\Repository;

use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\AdditionalConnectorConfiguration\Domain\Model\NewAcc;

interface WriteAccRepositoryInterface
{
    /**
     * Create a new additional connector (ACC).
     *
     * @param NewAcc $acc
     *
     * @throws \Throwable
     *
     * @return int
     */
    public function add(NewAcc $acc): int;

    /**
     * Update an additional connector (ACC).
     *
     * @param Acc $acc
     *
     * @throws \Throwable
     */
    public function update(Acc $acc): void;

    /**
     * Delete an additonal connector configuration.
     *
     * @param int $id
     *
     * @throws \Throwable
     */
    public function delete(int $id): void;

    /**
     * Link listed poller to the additional connector (ACC).
     *
     * @param int $accId
     * @param int[] $pollers
     *
     * @throws \Throwable
     */
    public function linkToPollers(int $accId, array $pollers): void;

    /**
     * Unlink all pollers from the additional connector (ACC).
     *
     * @param int $accId
     *
     * @throws \Throwable
     */
    public function removePollers(int $accId): void;
}
