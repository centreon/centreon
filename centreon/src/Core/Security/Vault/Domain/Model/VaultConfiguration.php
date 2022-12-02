<?php

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

declare(strict_types=1);

namespace Core\Security\Vault\Domain\Model;

use Security\Interfaces\EncryptionInterface;

/**
 * This class represents already existing vault configuration.
 */
class VaultConfiguration
{
    private int $id;
    private EncryptionInterface $encryption;
    private string $name;
    private Vault $vault;
    private string $address;
    private int $port;
    private string $storage;
    private ?string $secretId;
    private ?string $roleId;
    private string $salt;
    private string $encryptedRoleId;
    private string $encryptedSecretId;

    /**
     * @param EncryptionInterface $encryption
     * @param int $id
     * @param string $name
     * @param Vault $vault
     * @param string $address
     * @param int $port
     * @param string $storage
     * @param string $salt
     * @param string $encryptedRoleId
     * @param string $encryptedSecretId
     *
     * @throws \Exception
     */
    public function __construct(
        EncryptionInterface $encryption,
        int $id,
        string $name,
        Vault $vault,
        string $address,
        int $port,
        string $storage,
        string $salt,
        string $encryptedRoleId,
        string $encryptedSecretId
    ) {
        $this->id = $id;
        $this->encryption = $encryption;
        $this->name = $name;
        $this->vault = $vault;
        $this->address = $address;
        $this->port = $port;
        $this->storage = $storage;
        $this->encryptedSecretId = $encryptedSecretId;
        $this->encryptedRoleId = $encryptedRoleId;
        $this->salt = $salt;
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
    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @return string|null
     * @throws \Exception
     */
    public function getRoleId(): ?string
    {
        return $this->encryption->setSecondKey($this->salt)->decrypt($this->encryptedRoleId);
    }

    /**
     * @return string|null
     * @throws \Exception
     */
    public function getSecretId(): ?string
    {
        return $this->encryption->setSecondKey($this->salt)->decrypt($this->encryptedSecretId);
    }

    /**
     * @return string
     */
    public function getStorage(): string
    {
        return $this->storage;
    }

    /**
     * @return Vault
     */
    public function getVault(): Vault
    {
        return $this->vault;
    }

    /**
     * @param string|null $secretId
     * @throws \Exception
     */
    public function setNewSecretId(?string $secretId): void
    {
        $this->secretId = $this->encryption->setSecondKey($this->salt)->crypt($secretId);
    }

    /**
     * @param string|null $roleId
     * @throws \Exception
     */
    public function setNewRoleId(?string $roleId): void
    {
        $this->roleId = $this->encryption->setSecondKey($this->salt)->crypt($roleId);
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
        $this->name = $name;
    }

    /**
     * @param string $address
     */
    public function setAddress(string $address): void
    {
        $this->address = $address;
    }

    /**
     * @param int $port
     */
    public function setPort(int $port): void
    {
        $this->port = $port;
    }

    /**
     * @param string $storage
     */
    public function setStorage(string $storage): void
    {
        $this->storage = $storage;
    }

    /**
     * @param string|null $secretId
     */
    public function setSecretId(?string $secretId): void
    {
        $this->secretId = $secretId;
    }

    /**
     * @param string|null $roleId
     */
    public function setRoleId(?string $roleId): void
    {
        $this->roleId = $roleId;
    }
}
