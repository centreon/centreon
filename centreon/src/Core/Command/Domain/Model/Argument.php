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

namespace Core\Command\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Core\Common\Domain\TrimmedString;

class Argument {
    public const NAME_MAX_LENGTH = 255;
    public const DESCRIPTION_MAX_LENGTH = 255;

    /**
     * @param TrimmedString $name
     * @param TrimmedString $description
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly TrimmedString $name,
        private readonly TrimmedString $description,
    ) {
        Assertion::notEmptyString($name->value, 'Argument::name');
        Assertion::maxLength($name->value, self::NAME_MAX_LENGTH, 'Argument::name');
        Assertion::regex($name->value, '/^ARG\d+$/', 'Argument::name');

        Assertion::maxLength($description->value, self::DESCRIPTION_MAX_LENGTH, 'Argument::description');
    }

    public function getName(): string
    {
        return $this->name->value;
    }

    public function getDescription(): string
    {
        return $this->description->value;
    }
}
