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

namespace Centreon\Domain\Entity;

class Listener
{
    /** @var int */
    private $event;

    /** @var callable */
    private $action;

    public function __construct(int $event, callable $action)
    {
        $this->event = $event;
        $this->action = $action;
    }

    /**
     * @return int
     */
    public function getEvent(): int
    {
        return $this->event;
    }

    /**
     * @param int $event
     */
    public function setEvent(int $event): void
    {
        $this->event = $event;
    }

    /**
     * @return callable
     */
    public function getAction(): callable
    {
        return $this->action;
    }

    /**
     * @param callable $action
     */
    public function setAction(callable $action): void
    {
        $this->action = $action;
    }
}
