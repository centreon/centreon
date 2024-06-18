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

class Notification extends NewNotification
{
    /**
     * @param int $id
     * @param string $name
     * @param TimePeriod $timePeriod
     * @param bool $isActivated
     *
     * @throws \Assert\AssertionFailedException
     */
    public function __construct(
        private readonly int $id,
        string $name,
        TimePeriod $timePeriod,
        bool $isActivated = true
    ) {
        Assertion::positiveInt($id, 'Notification::id');

        parent::__construct(
            $name,
            $timePeriod,
            $isActivated
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setIsActivated(bool $isActivated): self
    {
        $this->isActivated = $isActivated;

        return $this;
    }
}
