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

namespace Core\Common\Application\Repository;

interface WriteVaultRepositoryInterface
{
    public function isVaultConfigured(): bool;

    public function setCustomPath(string $customPath): void;

    /**
     * Update or save secrets and return vault path.
     *
     * @param string|null $uuid
     * @param array<string, int|string> $inserts
     * @param array<string, int|string> $deletes
     *
     * @throws \Throwable
     *
     * @return array<string, string> array of paths
     */
    public function upsert(?string $uuid = null, array $inserts = [], array $deletes = []): array;

    /**
     * Delete secrets.
     *
     * @param string $uuid
     *
     * @throws \Throwable
     */
    public function delete(string $uuid): void;

    /**
     * Add a path to the list of available paths.
     *
     * @param string $path
     */
    public function addAvailablePath(string $path): void;
}
