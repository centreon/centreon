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

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostCheckCommandOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostCheckOptionsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostMacroOutput;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * The macros are not read from CheckOptions but from $extraData: the response lists every macro
 * the host effectively has, its own and those it inherits, as resolved by the caller.
 *
 * @phpstan-type ExtraDataTypeAlias array{commands?: array<int, Command>, macros?: list<HostMacro>}
 *
 * @implements TransformerInterface<CheckOptions, HostCheckOptionsOutput, ExtraDataTypeAlias>
 */
final readonly class HostCheckOptionsOutputTransformer implements TransformerInterface
{
    /**
     * @param TransformerInterface<HostMacro, HostMacroOutput> $macroTransformer
     */
    public function __construct(
        #[Autowire(service: HostMacroTransformer::class)]
        private TransformerInterface $macroTransformer,
    ) {
    }

    public function transform(mixed $from, array $extraData = []): HostCheckOptionsOutput
    {
        Assert::keyExists($extraData, 'commands');
        Assert::keyExists($extraData, 'macros');

        $command = $from->checkCommandId !== null ? $extraData['commands'][$from->checkCommandId->value] ?? null : null;

        return new HostCheckOptionsOutput(
            $command instanceof Command ? new HostCheckCommandOutput($command->id()->value, $command->name->value) : null,
            $from->args,
            array_map($this->macroTransformer->transform(...), $extraData['macros']),
        );
    }
}
