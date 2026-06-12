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

namespace App\AiConfiguration\Domain\Repository;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Exception\AiProviderNotFoundException;
use App\AiConfiguration\Domain\Repository\Criteria\AiProviderCriteria;

interface AiProviderRepository
{
    /**
     * @throws AiProviderNotFoundException
     */
    public function getById(AiProviderId $id): AiProvider;

    public function findOneByName(AiProviderName $name): ?AiProvider;

    public function findActivated(): ?AiProvider;

    /**
     * @return \IteratorAggregate<int, AiProvider>&\Countable
     */
    public function findAll(?AiProviderCriteria $criteria): \IteratorAggregate&\Countable;

    public function countAll(): int;

    public function add(AiProvider $aiProvider): void;

    public function update(AiProvider ...$aiProviders): void;

    public function delete(AiProvider $aiProvider): void;
}
