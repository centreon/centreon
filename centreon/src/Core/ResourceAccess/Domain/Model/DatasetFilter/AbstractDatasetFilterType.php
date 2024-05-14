<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\ResourceAccess\Domain\Model\DatasetFilter;

abstract class AbstractDatasetFilterType implements DatasetFilterTypeInterface
{
    protected string $name = '';

    /** @var string[] */
    protected array $possibleChildren = [];

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function isValidFor(string $name): bool
    {
        return $this->name === $name;
    }

    /**
     * @inheritDoc
     */
    public function getPossibleChildren(): array
    {
        return $this->possibleChildren;
    }

    /**
     * @inheritDoc
     */
    public function canResourceIdsBeEmpty(): bool
    {
        return false;
    }
}

