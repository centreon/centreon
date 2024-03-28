<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Service\Domain\Model;

use Core\Common\Domain\Comparable;
use Core\Common\Domain\Identifiable;

class TinyService implements Comparable, Identifiable
{
    public function __construct(
        readonly private int $id,
        readonly private string $name,
        readonly private string $hostName,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getHostName(): string
    {
        return $this->hostName;
    }

    public function isEqual(object $object): bool
    {
        return $object instanceof self && $object->getEqualityHash() === $this->getEqualityHash();
    }

    public function getEqualityHash(): string
    {
        return md5($this->getHostName() . '/' . $this->getName());
    }
}
