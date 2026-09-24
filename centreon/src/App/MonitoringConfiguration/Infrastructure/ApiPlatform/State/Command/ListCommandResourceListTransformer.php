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
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\ListCommandResource;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Counts the linked resources of the whole list in one query, then delegates each command to
 * {@see ListCommandResourceTransformer}.
 *
 * @phpstan-import-type ExtraDataTypeAlias from ListCommandResourceTransformer
 *
 * @implements TransformerInterface<list<Command>, list<ListCommandResource>>
 */
final readonly class ListCommandResourceListTransformer implements TransformerInterface
{
    /**
     * @param TransformerInterface<Command, ListCommandResource, ExtraDataTypeAlias> $itemTransformer
     */
    public function __construct(
        private CommandRepository $commandRepository,
        #[Autowire(service: ListCommandResourceTransformer::class)]
        private TransformerInterface $itemTransformer,
    ) {
    }

    public function transform(mixed $from, array $extraData = []): array
    {
        if ($from === []) {
            return [];
        }

        $counts = $this->commandRepository->countLinkedResources(array_map(
            static fn (Command $command): CommandId => $command->id(),
            $from,
        ));

        return array_map(
            fn (Command $command): ListCommandResource => $this->itemTransformer->transform(
                $command,
                ['linkedResourceCount' => $counts[$command->id()->value]],
            ),
            $from,
        );
    }
}
