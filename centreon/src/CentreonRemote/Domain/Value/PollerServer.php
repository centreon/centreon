<?php

<<<<<<< HEAD
/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

=======
>>>>>>> centreon/dev-21.10.x
namespace CentreonRemote\Domain\Value;

class PollerServer
{
    /**
     * @var int $id the poller id
     */
    private $id;

    /**
     * @var string $name the poller name
     */
    private $name;

    /**
     * @var string $ip the poller ip address
     */
    private $ip;


<<<<<<< HEAD
    /**
     * @return int
     */
    public function getId(): int
=======
    public function getId()
>>>>>>> centreon/dev-21.10.x
    {
        return $this->id;
    }

<<<<<<< HEAD
    /**
     * @param int $id
     */
    public function setId($id): void
=======
    public function setId($id)
>>>>>>> centreon/dev-21.10.x
    {
        $this->id = $id;
    }

    /**
     * Get poller name
     *
     * @return string the poller name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set poller name
     *
     * @param string $name the poller name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

<<<<<<< HEAD
    /**
     * @return string
     */
    public function getIp(): string
=======
    public function getIp()
>>>>>>> centreon/dev-21.10.x
    {
        return $this->ip;
    }

<<<<<<< HEAD
    /**
     * @param string $ip
     */
    public function setIp($ip): void
=======
    public function setIp($ip)
>>>>>>> centreon/dev-21.10.x
    {
        $this->ip = $ip;
    }
}
