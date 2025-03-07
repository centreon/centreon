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

namespace Core\Domain\Configuration\Notification\Model;

class NotifiedContact
{
    /**
     * @param int $id
     * @param string $name
     * @param string $alias
     * @param string $email
     * @param HostNotification|null $hostNotification
     * @param ServiceNotification|null $serviceNotification
     */
    public function __construct(
        private int $id,
        private string $name,
        private string $alias,
        private string $email,
        private ?HostNotification $hostNotification,
        private ?ServiceNotification $serviceNotification,
    ) {
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return HostNotification|null
     */
    public function getHostNotification(): ?HostNotification
    {
        return $this->hostNotification;
    }

    /**
     * @return ServiceNotification|null
     */
    public function getServiceNotification(): ?ServiceNotification
    {
        return $this->serviceNotification;
    }
}
