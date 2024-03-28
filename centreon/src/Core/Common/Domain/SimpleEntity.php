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

namespace Core\Common\Domain;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

/**
 * This class contain the id and name of any object regardles of their other attributes.
 * It can be used as property of another object.
 */
class SimpleEntity
{
    /**
     * @param int $id
     * @param TrimmedString|null $name
     * @param string $objectName
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $id,
        private readonly ?TrimmedString $name,
        string $objectName,
    )
    {
        Assertion::positiveInt($id, "{$objectName}::id");
        if ($name !== null) {
            Assertion::notEmptyString($name->value, "{$objectName}::name");
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name?->value;
    }
}
