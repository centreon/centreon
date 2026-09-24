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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command;

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Repository\CommandResourceCount;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\ListCommandResource;
use App\Shared\Infrastructure\TransformerInterface;
use Webmozart\Assert\Assert;

/**
 * Takes the linked resource count already resolved for the whole page: see
 * {@see ListCommandResourceListTransformer}.
 *
 * @phpstan-type ExtraDataTypeAlias array{linkedResourceCount?: CommandResourceCount}
 *
 * @implements TransformerInterface<Command, ListCommandResource, ExtraDataTypeAlias>
 */
final readonly class ListCommandResourceTransformer implements TransformerInterface
{
    public function transform(mixed $from, array $extraData = []): ListCommandResource
    {
        Assert::keyExists($extraData, 'linkedResourceCount');
        $count = $extraData['linkedResourceCount'];

        return new ListCommandResource(
            id: $from->id()->value,
            name: $from->name->value,
            type: $from->type->name,
            commandLine: $from->commandLine->value,
            isActivated: $from->isActivated ?? false,
            isFromMonitoringConnector: $from->isFromMonitoringConnector,
            usedHostsCount: $count->usedHosts,
            usedHostTemplatesCount: $count->usedHostTemplates,
            usedServicesCount: $count->usedServices,
            usedServiceTemplatesCount: $count->usedServiceTemplates,
        );
    }
}
