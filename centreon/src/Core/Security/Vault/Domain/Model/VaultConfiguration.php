<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Core\Security\Vault\Domain\Model;

use Centreon\Domain\Common\Assertion\Assertion;
use Security\Interfaces\EncryptionInterface;

/**
 * This class represents already existing vault configuration.
 */
class VaultConfiguration
{
    public const MIN_LENGTH = 1;
    public const MAX_LENGTH = 255;
    public const NAME_MAX_LENGTH = 50;
    public const MIN_PORT_VALUE = 1;
    public const MAX_PORT_VALUE = 65535;
    public const SALT_LENGTH = 128;
    public const NAME_VALIDATION_REGEX = '^[a-zA-Z0-9_-/]$';

    private ?string $secretId;
    private ?string $roleId;

    /**
     * @param EncryptionInterface $encryption
     * @param integer $id
     * @param string $name
     * @param Vault $vault
     * @param string $address
     * @param integer $port
     * @param string $storage
     * @param string $encryptedRoleId
     * @param string $encryptedSecretId
     * @param string $salt
     *
     * @throws \Exception
     */
    public function __construct(
        private EncryptionInterface $encryption,
        private int $id,
        private string $name,
        private Vault $vault,
        private string $address,
        private int $port,
        private string $storage,
        private string $encryptedRoleId,
        private string $encryptedSecretId,
        private string $salt
    ) {
        $this->setName($name);
        $this->setAddress($address);
        $this->setPort($port);
        $this->setStorage($storage);
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
     * @return Vault
     */
    public function getVault(): Vault
    {
        return $this->vault;
    }

    /**
     * @return string
     */
    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getStorage(): string
    {
        return $this->storage;
    }

    /**
     * @return string|null
     *
     * @throws \Exception
     */
    public function getRoleId(): ?string
    {
        $this->roleId = $this->encryption->setSecondKey($this->salt)->decrypt($this->encryptedRoleId);
        return $this->roleId;
    }

    /**
     * @return string|null
     *
     * @throws \Exception
     */
    public function getSecretId(): ?string
    {
        $this->secretId = $this->encryption->setSecondKey($this->salt)->decrypt($this->encryptedSecretId);
        return $this->secretId;
    }

    /**
     * @return string
     */
    public function getEncryptedRoleId(): string
    {
        return $this->encryptedRoleId;
    }

    /**
     * @return string
     */
    public function getEncryptedSecretId(): string
    {
        return $this->encryptedSecretId;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        Assertion::minLength($name, self::MIN_LENGTH, 'VaultConfiguration::name');
        Assertion::maxLength($name, self::NAME_MAX_LENGTH, 'VaultConfiguration::name');
        Assertion::regex($name, self::NAME_VALIDATION_REGEX, 'VaultConfiguration::name');
        $this->name = $name;
    }

    /**
     * @param string $address
     */
    public function setAddress(string $address): void
    {
        Assertion::minLength($address, self::MIN_LENGTH, 'VaultConfiguration::address');
        Assertion::ipOrDomain($address, 'VaultConfiguration::address');
        $this->address = $address;
    }

    /**
     * @param int $port
     */
    public function setPort(int $port): void
    {
        Assertion::max($port, self::MAX_PORT_VALUE, 'VaultConfiguration::port');
        Assertion::min($port, self::MIN_PORT_VALUE, 'VaultConfiguration::port');
        $this->port = $port;
    }

    /**
     * @param string $storage
     */
    public function setStorage(string $storage): void
    {
        Assertion::minLength($storage, self::MIN_LENGTH, 'VaultConfiguration::storage');
        Assertion::maxLength($storage, self::NAME_MAX_LENGTH, 'VaultConfiguration::storage');
        $this->storage = $storage;
    }

    /**
     * @param string|null $roleId
     */
    public function setRoleId(?string $roleId): void
    {
        $this->roleId = $roleId;
    }

    /**
     * @param string|null $secretId
     */
    public function setSecretId(?string $secretId): void
    {
        $this->secretId = $secretId;
    }

    /**
     * @param string $roleId
     *
     * @throws \Exception
     */
    public function setNewRoleId(string $roleId): void
    {
        Assertion::minLength($roleId, self::MIN_LENGTH, 'VaultConfiguration::roleId');
        Assertion::maxLength($roleId, self::MAX_LENGTH, 'VaultConfiguration::roleId');
        $this->encryptedRoleId = $this->encryption->setSecondKey($this->salt)->crypt($roleId);
    }

    /**
     * @param string $secretId
     *
     * @throws \Exception
     */
    public function setNewSecretId(string $secretId): void
    {
        Assertion::minLength($secretId, self::MIN_LENGTH, 'VaultConfiguration::secretId');
        Assertion::maxLength($secretId, self::MAX_LENGTH, 'VaultConfiguration::secretId');
        $this->encryptedSecretId = $this->encryption->setSecondKey($this->salt)->crypt($secretId);
    }
}
