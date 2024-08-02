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

namespace Core\AdditionalConnector\Application\Repository;

use Core\AdditionalConnector\Domain\Model\AdditionalConnector;
use Core\AdditionalConnector\Domain\Model\Type;

interface WriteVaultAdditionalConnectorRepositoryInterface
{
    public function isValidFor(Type $type): bool;

    /**
     * save credentials in vault and return the parameters updated with vaultPaths.
     *
     * @param array<string,mixed> $parameters
     *
     * @throws \Throwable
     *
     * @return array<string,mixed>
     */
    public function saveCredentialInVault(array $parameters): array;

    /**
     * Delete an ACC credentials from vault.
     *
     * @param AdditionalConnector $acc
     *
     * @throws \Throwable
     */
    public function deleteFromVault(AdditionalConnector $acc): void;
}
