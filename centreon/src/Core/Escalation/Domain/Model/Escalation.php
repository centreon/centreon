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

declare(strict_types=1);

namespace Core\Escalation\Domain\Model;

use Centreon\Domain\Common\Assertion\Assertion;

class Escalation
{
    public const MAX_LENGTH_NAME = 255;

    public function __construct(
        private readonly int $id,
        private string $name,
        // NOTE: minimal implementation for current needs as november 2023
    ) {
        $className = (new \ReflectionClass($this))->getShortName();
        $this->name = trim($name);

        Assertion::positiveInt($this->id, "{$className}::id");
        Assertion::notEmptyString($this->name, "{$className}::name");
        Assertion::maxLength($name, self::MAX_LENGTH_NAME, "{$className}::name");
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
