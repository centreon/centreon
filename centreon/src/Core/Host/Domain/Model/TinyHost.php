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

declare(strict_types = 1);

namespace Core\Host\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Core\Common\Domain\Comparable;
use Core\Common\Domain\Identifiable;

class TinyHost implements Comparable, Identifiable
{
    /**
     * @param int $id
     * @param string $name
     * @param ?string $alias
     * @param int $monitoringServerId
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $id,
        private string $name,
        private ?string $alias,
        private readonly int $monitoringServerId,
    ) {
        Assertion::positiveInt($this->id, 'Host::id');
        $this->name = trim($this->name);
        Assertion::notEmptyString($this->name, 'Host::name');
        if ($this->alias !== null) {
            $this->alias = trim($this->alias);
            Assertion::notEmptyString($this->alias, 'Host::alias');
        }
        Assertion::positiveInt($this->id, 'Host::monitoringServerId');
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    public function getMonitoringServerId(): int
    {
        return $this->monitoringServerId;
    }

    public function isEqual(object $object): bool
    {
        return $object instanceof self && $object->getEqualityHash() === $this->getEqualityHash();
    }

    public function getEqualityHash(): string
    {
        return md5($this->getName());
    }
}
