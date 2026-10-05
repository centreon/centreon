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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroParentEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\HostMacroInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostMacroOutput;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * Owns the macro wire object in both directions, for every endpoint handling host macros (GetHost,
 * PutHost, PatchHost, CreateHost, the host-template skeleton, the command macros).
 *
 * @implements TransformerInterface<HostMacro, HostMacroOutput>
 */
final readonly class HostMacroTransformer implements TransformerInterface
{
    public function transform(mixed $from): HostMacroOutput
    {
        return new HostMacroOutput(
            id: $from->id?->value,
            name: $from->name->value,
            // A password macro's stored value is a vault reference (or secret) — never echoed (R3).
            value: $from->isPassword ? null : $from->value,
            isPassword: $from->isPassword,
            parent: $from->parent?->value,
        );
    }

    public function toChange(HostMacroInput $input): HostMacroChange
    {
        return new HostMacroChange(
            name: new HostMacroName($input->name),
            value: $input->value,
            isPassword: $input->isPassword,
            id: $input->id !== null ? new HostMacroId($input->id) : null,
            parent: $input->parent !== null ? HostMacroParentEnum::from($input->parent) : null,
        );
    }
}
