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

namespace App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @phpstan-type MacroRowTypeAlias array{name: string, value: string, is_password: string|int, description: string|null}
 * @phpstan-type RowTypeAlias array{service_id: int|string, service_description: string, host_id: int, macros: list<MacroRowTypeAlias>}
 *
 * @implements TransformerInterface<RowTypeAlias, Service>
 */
final readonly class DbalServiceTransformer implements TransformerInterface
{
    public function transform(mixed $from): Service
    {
        return new Service(
            id: new ServiceId((int) $from['service_id']),
            name: new ServiceName($from['service_description']),
            hostId: new HostId($from['host_id']),
            macros: array_map($this->createMacro(...), $from['macros']),
        );
    }

    /**
     * @param MacroRowTypeAlias $row
     */
    private function createMacro(array $row): ServiceMacro
    {
        // Stored as the full engine form ($_SERVICE<NAME>$, see ServiceMacroName::toStorageName());
        // strip the '$_SERVICE' prefix and trailing '$' to get back the short name the VO expects.
        $shortName = mb_substr($row['name'], 9, -1);

        return new ServiceMacro(
            name: new ServiceMacroName($shortName),
            value: $row['value'],
            isPassword: (bool) $row['is_password'],
            description: $row['description'],
        );
    }
}
