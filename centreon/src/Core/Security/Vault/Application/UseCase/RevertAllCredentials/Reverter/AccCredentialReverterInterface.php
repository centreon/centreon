<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\Security\Vault\Application\UseCase\RevertAllCredentials\Reverter;

use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialDto;

interface AccCredentialReverterInterface
{
    /**
     * @param Type $type
     *
     * @return bool
     */
    public function isValidFor(Type $type): bool;

    /**
     * Build the DTOs for every credential of the given ACC that is currently stored as a vault path.
     * The DTO value holds the vault path (`secret::...`) so the reverter can read the plaintext back.
     *
     * @param Acc $acc
     *
     * @return CredentialDto[]
     */
    public function createCredentialDtos(Acc $acc): array;

    /**
     * Replace the vault path stored on the ACC with the plaintext read back from the vault.
     *
     * @param Acc $acc
     * @param CredentialDto $credential
     * @param string $plaintext
     *
     * @return Acc
     */
    public function revertMigratedCredential(
        Acc $acc,
        CredentialDto $credential,
        string $plaintext,
    ): Acc;
}
