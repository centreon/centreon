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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service;

use App\MonitoringConfiguration\Application\Command\PatchServiceCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\PatchServiceInput;
use App\Security\Domain\Aggregate\ActingUser;
use App\Shared\Domain\NoValue;
use App\Shared\Infrastructure\ApiPlatform\RequestPayload;
use Webmozart\Assert\Assert;

/**
 * Builds the command of a partial update from the validated Input DTO and the keys that were really sent: a key
 * left out becomes {@see NoValue}, anything else replaces the value.
 */
final readonly class PatchServiceCommandFactory
{
    public function create(
        ServiceId $id,
        ActingUser $actor,
        PatchServiceInput $input,
        RequestPayload $payload,
    ): PatchServiceCommand {
        return new PatchServiceCommand(
            id: $id,
            actor: $actor,
            activated: $this->provided($payload, 'activated', fn (): bool => $this->required($input->activated)),
        );
    }

    /**
     * @template T
     *
     * @param \Closure(): T $value
     *
     * @return T|NoValue
     */
    private function provided(RequestPayload $payload, string $key, \Closure $value): mixed
    {
        return $payload->has($key) ? $value() : new NoValue();
    }

    /**
     * Once a key is provided, the property that cannot be null is not: the constraint on the Input DTO has already
     * refused it.
     *
     * @template T
     *
     * @param T|null $value
     *
     * @return T
     */
    private function required(mixed $value): mixed
    {
        Assert::notNull($value);

        return $value;
    }
}
