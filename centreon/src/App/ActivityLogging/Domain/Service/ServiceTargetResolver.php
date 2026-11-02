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

namespace App\ActivityLogging\Domain\Service;

use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\ActivityLogging\Domain\ReadModel\ResolvedTarget;
use App\ActivityLogging\Domain\ReadModel\ServiceParentLink;
use App\ActivityLogging\Domain\ReadModel\TargetParent;

/**
 * Resolves the displayed name and parent of a service target.
 *
 * Some writers log a service as "<host name>/<service description>": when the service has a
 * single host, that prefix is moved to the parent (port of the legacy viewLogs.php behaviour).
 */
final readonly class ServiceTargetResolver
{
    private const HOST_SEPARATOR = '/';

    public function resolve(string $serviceName, ?ServiceParentLink $parentLink): ResolvedTarget
    {
        if (! $parentLink instanceof ServiceParentLink) {
            // the relations may have changed since the logs were written
            return new ResolvedTarget($serviceName, null);
        }

        if ($parentLink->type === TargetTypeEnum::Host && count($parentLink->names) === 1) {
            $hostName = array_values($parentLink->names)[0];

            if (
                ($hostName === null && str_contains($serviceName, self::HOST_SEPARATOR))
                || ($hostName !== null && str_contains($serviceName, $hostName . self::HOST_SEPARATOR))
            ) {
                [$hostName, $serviceName] = explode(self::HOST_SEPARATOR, $serviceName, 2);
            }

            return new ResolvedTarget(
                $serviceName,
                $hostName !== null && $hostName !== '' ? new TargetParent(TargetTypeEnum::Host, [$hostName]) : null,
            );
        }

        $names = array_values(array_filter(
            $parentLink->names,
            static fn (?string $name): bool => $name !== null && $name !== '',
        ));

        return new ResolvedTarget(
            $serviceName,
            $names !== [] ? new TargetParent($parentLink->type, $names) : null,
        );
    }
}
