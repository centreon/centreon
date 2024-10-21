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

namespace Core\Notification\Domain\Model;

use Centreon\Domain\Common\Assertion\Assertion;

class NewNotification
{
    public const MAX_NAME_LENGTH = 255;

    /**
     * @param string $name
     * @param TimePeriod $timePeriod
     * @param bool $isActivated
     *
     * @throws \Assert\AssertionFailedException
     */
    public function __construct(
        protected string $name,
        protected TimePeriod $timePeriod,
        protected bool $isActivated = true
    ) {
        $shortName = (new \ReflectionClass($this))->getShortName();

        $this->name = trim($this->name);
        Assertion::notEmptyString($this->name, "{$shortName}::name");
        Assertion::maxLength($this->name, self::MAX_NAME_LENGTH, "{$shortName}::name");
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTimePeriod(): TimePeriod
    {
        return $this->timePeriod;
    }

    public function isActivated(): bool
    {
        return $this->isActivated;
    }
}
