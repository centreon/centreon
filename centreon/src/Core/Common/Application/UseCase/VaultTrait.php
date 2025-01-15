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

namespace Core\Common\Application\UseCase;

use Core\Security\Vault\Domain\Model\VaultConfiguration;

trait VaultTrait
{
    private ?string $uuid = null;

    /**
     * Parse the vault path and find the UUID.
     *
     * example:
     * Given $value = 'secret::vault_name::path/to/secret/xxxxxxx-xx-xxx-xxxxx::vault_key'
     * Then 'xxxxxxx-xx-xxx-xxxxx' will be extracted.
     *
     * @param string $value
     *
     * @return string|null
     */
    private function getUuidFromPath(string $value): ?string
    {
        if (
            preg_match(
                '/' . VaultConfiguration::UUID_EXTRACTION_REGEX . '/',
                $value,
                $matches
            )
        ) {
            return $matches[2];
        }

        return null;
    }

    private function isAVaultPath(string $value): bool
    {
        return str_starts_with($value, VaultConfiguration::VAULT_PATH_PATTERN);
    }
}
