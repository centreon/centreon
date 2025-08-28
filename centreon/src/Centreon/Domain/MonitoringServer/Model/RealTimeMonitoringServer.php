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

declare(strict_types=1);

namespace Centreon\Domain\MonitoringServer\Model;

use Centreon\Domain\Common\Assertion\Assertion;

/**
 * This class is designed to represent a RealTime Monitoring Server.
 *
 * @package Centreon\Domain\MonitoringServer\Model
 */
class RealTimeMonitoringServer
{
    public const MAX_NAME_LENGTH = 255;
    public const MIN_NAME_LENGTH = 1;
    public const MAX_ADDRESS_LENGTH = 128;
    public const MAX_DESCRIPTION_LENGTH = 128;
    public const MAX_VERSION_LENGTH = 16;

    /** @var int defines the Monitoring Server id */
    private $id;

    /** @var string defines a short name for the Monitoring Server */
    private $name;

    /** @var string|null Defines the IP address of the Monitoring Server */
    private $address;

    /** @var string|null Defines a short description of the Monitoring Server */
    private $description;

    /** @var int|null Defines the last time when Monitoring Server was alive (timestamp) */
    private $lastAlive;

    /** @var bool Defines whether or not the Monitoring Server is running */
    private $isRunning = false;

    /** @var string|null Defines the version of the Monitoring Server (Centreon Engine version) */
    private $version;

    /**
     * @param int $id ID of the Monitoring Server
     * @param string $name Name of the Monitoring Server
     * @throws \Assert\AssertionFailedException
     */
    public function __construct(int $id, string $name)
    {
        $this->setId($id);
        $this->setName($name);
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return RealTimeMonitoringServer
     */
    public function setId(int $id): RealTimeMonitoringServer
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @throws \Assert\AssertionFailedException
     * @return RealTimeMonitoringServer
     */
    public function setName(string $name): RealTimeMonitoringServer
    {
        Assertion::minLength($name, self::MIN_NAME_LENGTH, 'RealTimeMonitoringServer::name');
        Assertion::maxLength($name, self::MAX_NAME_LENGTH, 'RealTimeMonitoringServer::name');
        $this->name = $name;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getAddress(): ?string
    {
        return $this->address;
    }

    /**
     * @param string|null $address
     * @throws \Assert\AssertionFailedException
     * @return RealTimeMonitoringServer
     */
    public function setAddress(?string $address): RealTimeMonitoringServer
    {
        if ($address !== null) {
            Assertion::maxLength($address, self::MAX_ADDRESS_LENGTH, 'RealTimeMonitoringServer::address');
        }
        $this->address = $address;

        return $this;
    }

    /**
     * @return int
     */
    public function getLastAlive(): int
    {
        return $this->lastAlive;
    }

    /**
     * @param int|null $lastAlive
     * @return RealTimeMonitoringServer
     */
    public function setLastAlive(?int $lastAlive): RealTimeMonitoringServer
    {
        $this->lastAlive = $lastAlive;

        return $this;
    }

    /**
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->isRunning;
    }

    /**
     * @param bool $running
     * @return RealTimeMonitoringServer
     */
    public function setRunning(bool $running): RealTimeMonitoringServer
    {
        $this->isRunning = $running;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * @param string|null $version
     * @throws \Assert\AssertionFailedException
     * @return RealTimeMonitoringServer
     */
    public function setVersion(?string $version): RealTimeMonitoringServer
    {
        if ($version !== null) {
            Assertion::maxLength($version, self::MAX_VERSION_LENGTH, 'RealTimeMonitoringServer::version');
        }
        $this->version = $version;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string|null $description
     * @throws \Assert\AssertionFailedException
     * @return RealTimeMonitoringServer
     */
    public function setDescription(?string $description): RealTimeMonitoringServer
    {
        if ($description !== null) {
            Assertion::maxLength($description, self::MAX_DESCRIPTION_LENGTH, 'RealTimeMonitoringServer::description');
        }
        $this->description = $description;

        return $this;
    }
}
