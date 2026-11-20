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

namespace App\AiConfiguration\Domain\Exception;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;

/**
 * The API token could not be written to, or removed from, the vault: nothing was persisted.
 */
final class VaultWriteFailedException extends \RuntimeException
{
    public static function forCreation(\Throwable $previous): self
    {
        return new self(
            'The AI provider configuration was not created: its API token could not be written to the vault.',
            previous: $previous,
        );
    }

    public static function forUpdate(AiProviderId $aiProviderId, \Throwable $previous): self
    {
        return new self(
            sprintf('AI provider configuration %d was not updated: its API token could not be written to the vault.', $aiProviderId->value),
            previous: $previous,
        );
    }

    public static function forDeletion(AiProviderId $aiProviderId, \Throwable $previous): self
    {
        return new self(
            sprintf('AI provider configuration %d was not deleted: its API token could not be removed from the vault.', $aiProviderId->value),
            previous: $previous,
        );
    }
}
