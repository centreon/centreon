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

namespace Core\Metric\Domain\Model\MetricInformation;

class GeneralInformation
{
    public function __construct(
        private readonly int $indexId,
        private readonly int $id,
        private readonly string $name,
        private readonly string $alias,
        private readonly string $unit,
        private readonly bool $isHidden,
        private readonly ?string $hostName,
        private readonly ?string $serviceName,
        private readonly string $legend,
        private readonly bool $isVirtual,
        private readonly bool $isStacked,
        private readonly int $stackingOrder,
    ) {
    }

    public function getIndexId(): int
    {
        return $this->indexId;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function isHidden(): bool
    {
        return $this->isHidden;
    }

    public function getHostName(): ?string
    {
        return $this->hostName;
    }

    public function getServiceName(): ?string
    {
        return $this->serviceName;
    }

    public function getLegend(): string
    {
        return $this->legend;
    }

    public function isVirtual(): bool
    {
        return $this->isVirtual;
    }

    public function isStacked(): bool
    {
        return $this->isStacked;
    }

    public function getStackingOrder(): int
    {
        return $this->stackingOrder;
    }
}
