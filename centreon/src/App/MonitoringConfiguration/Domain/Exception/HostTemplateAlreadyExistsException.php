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

namespace App\MonitoringConfiguration\Domain\Exception;

use App\Shared\Domain\Exception\AggregateAlreadyExistsException;

/**
 * Hosts and host templates share one name space.
 */
final class HostTemplateAlreadyExistsException extends AggregateAlreadyExistsException
{
    public static function forName(string $name, ?\Throwable $previous = null): self
    {
        return new self(['name' => $name], 'A host or host template already uses this name.', previous: $previous);
    }

    public static function forExhaustedSuffixes(string $sourceName, int $maxSuffix, ?\Throwable $previous = null): self
    {
        return new self(
            ['name' => $sourceName],
            sprintf('Every name from "%1$s_1" to "%1$s_%2$d" is already used by a host or host template.', $sourceName, $maxSuffix),
            previous: $previous,
        );
    }
}
