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

namespace Core\Security\Vault\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Security\Interfaces\EncryptionInterface;

/**
 * This class represents vault configuration being created.
 */
class NewVaultConfiguration
{
    public const MIN_LENGTH = 1;
    public const MAX_LENGTH = 255;
    public const NAME_MAX_LENGTH = 50;
    public const MIN_PORT_VALUE = 1;
    public const MAX_PORT_VALUE = 65535;
    public const SALT_LENGTH = 128;
    public const NAME_VALIDATION_REGEX = '/^[\w+\-\/]+$/';
    public const DEFAULT_NAME = 'hashicorp_vault';

    protected string $encryptedSecretId;

    protected string $encryptedRoleId;

    protected string $salt;

    /**
     * @param EncryptionInterface $encryption
     * @param string $address
     * @param int $port
     * @param string $rootPath
     * @param string $roleId
     * @param string $secretId
     * @param string $name
     *
     * @throws AssertionFailedException
     * @throws \Exception
     */
    public function __construct(
        protected EncryptionInterface $encryption,
        protected string $address,
        protected int $port,
        protected string $rootPath,
        private string $roleId,
        private string $secretId,
        protected string $name = self::DEFAULT_NAME
    ) {
        Assertion::minLength($name, self::MIN_LENGTH, 'NewVaultConfiguration::name');
        Assertion::maxLength($name, self::NAME_MAX_LENGTH, 'NewVaultConfiguration::name');
        Assertion::regex($name, self::NAME_VALIDATION_REGEX, 'VaultConfiguration::name');
        Assertion::minLength($address, self::MIN_LENGTH, 'NewVaultConfiguration::address');
        Assertion::ipOrDomain($address, 'NewVaultConfiguration::address');
        Assertion::max($port, self::MAX_PORT_VALUE, 'NewVaultConfiguration::port');
        Assertion::min($port, self::MIN_PORT_VALUE, 'NewVaultConfiguration::port');
        Assertion::minLength($rootPath, self::MIN_LENGTH, 'NewVaultConfiguration::rootPath');
        Assertion::maxLength($rootPath, self::NAME_MAX_LENGTH, 'NewVaultConfiguration::rootPath');
        Assertion::regex($rootPath, self::NAME_VALIDATION_REGEX, 'VaultConfiguration::name');
        Assertion::minLength($this->roleId, self::MIN_LENGTH, 'NewVaultConfiguration::roleId');
        Assertion::maxLength($this->roleId, self::MAX_LENGTH, 'NewVaultConfiguration::roleId');
        Assertion::minLength($this->secretId, self::MIN_LENGTH, 'NewVaultConfiguration::secretId');
        Assertion::maxLength($this->secretId, self::MAX_LENGTH, 'NewVaultConfiguration::secretId');
        $this->salt = $this->encryption->generateRandomString(self::SALT_LENGTH);
        $this->encryptedSecretId = $this->encrypt($this->secretId, $this->salt);
        $this->encryptedRoleId = $this->encrypt($this->roleId, $this->salt);
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
    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    /**
     * @throws \Exception
     *
     * @return string
     */
    public function getRoleId(): string
    {
        return $this->roleId;
    }

    /**
     * @throws \Exception
     *
     * @return string
     */
    public function getSecretId(): string
    {
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
     * @return string
     */
    public function getSalt(): string
    {
        return $this->salt;
    }

    /**
     * @param string $unencrypted
     * @param string $salt
     *
     * @throws \Exception
     *
     * @return string
     */
    private function encrypt(string $unencrypted, string $salt): string
    {
        return $this->encryption
            ->setSecondKey($salt)
            ->crypt($unencrypted);
    }
}
