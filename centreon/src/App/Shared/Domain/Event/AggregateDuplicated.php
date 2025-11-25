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

namespace App\Shared\Domain\Event;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\AggregateRootId;

/**
 * @template T of AggregateRoot<AggregateRootId>
 */
abstract readonly class AggregateDuplicated implements EventInterface
{
    /**
     * @param T|array<T> $aggregate
     */
    public function __construct(
        public AggregateRoot|array $aggregate,
        public int $creatorId,
        public \DateTimeImmutable $firedAt = new \DateTimeImmutable(),
    ) {
        if (is_array($this->aggregate)) {
            if ($this->aggregate === []) {
                throw new \InvalidArgumentException('Aggregate array cannot be empty');
            }

            $firstType = $this->aggregate[0]::class;
            foreach ($this->aggregate as $item) {
                if (! $item instanceof AggregateRoot) {
                    throw new \InvalidArgumentException('All elements must be instances of AggregateRoot');
                }

                if ($item::class !== $firstType) {
                    throw new \InvalidArgumentException(
                        sprintf('All aggregates must be of the same type. Expected %s, got %s', $firstType, $item::class)
                    );
                }
            }
        }
    }

    public function firedAt(): \DateTimeImmutable
    {
        return $this->firedAt;
    }
}
