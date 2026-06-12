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

namespace Tests\App\AiConfiguration\Infrastructure\Double;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Exception\AiProviderNotFoundException;
use App\AiConfiguration\Domain\Repository\AiProviderRepository;
use App\AiConfiguration\Domain\Repository\Criteria\AiProviderCriteria;
use App\Shared\Domain\Aggregate\AggregateRoot;
use Countable;
use IteratorAggregate;

final class FakeAiProviderRepository implements AiProviderRepository
{
    /** @var array<int, AiProvider> */
    public array $aiProviders = [];

    private int $nextId = 1;

    public function getById(AiProviderId $id): AiProvider
    {
        return $this->aiProviders[$id->value]
            ?? throw new AiProviderNotFoundException(['id' => $id->value]);
    }

    public function findOneByName(AiProviderName $name): ?AiProvider
    {
        foreach ($this->aiProviders as $aiProvider) {
            if ($aiProvider->name->value === $name->value) {
                return $aiProvider;
            }
        }

        return null;
    }

    public function findActivated(): ?AiProvider
    {
        foreach ($this->aiProviders as $aiProvider) {
            if ($aiProvider->isActivated) {
                return $aiProvider;
            }
        }

        return null;
    }

    public function findAll(?AiProviderCriteria $criteria): IteratorAggregate&Countable
    {
        return new \ArrayObject(array_values($this->aiProviders));
    }

    public function countAll(): int
    {
        return \count($this->aiProviders);
    }

    public function add(AiProvider $aiProvider): void
    {
        $id = $this->nextId++;

        $reflection = new \ReflectionProperty(AggregateRoot::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($aiProvider, new AiProviderId($id));

        $this->aiProviders[$id] = $aiProvider;
    }

    public function update(AiProvider ...$aiProviders): void
    {
        foreach ($aiProviders as $aiProvider) {
            $this->aiProviders[$aiProvider->id()->value] = $aiProvider;
        }
    }

    public function delete(AiProvider $aiProvider): void
    {
        unset($this->aiProviders[$aiProvider->id()->value]);
    }
}
