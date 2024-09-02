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

namespace Core\AdditionalConnectorConfiguration\Domain\Model\VmWareV6;

use Centreon\Domain\Common\Assertion\Assertion;
use Centreon\Domain\Common\Assertion\AssertionException;
use Core\AdditionalConnectorConfiguration\Domain\Model\AccParametersInterface;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Security\Interfaces\EncryptionInterface;

/**
 * @phpstan-type _VmWareV6Parameters array{
 *      port:int,
 *      vcenters:array<array{name:string,url:string,username:string,password:string}>
 *  }
 *  @phpstan-type _VmWareV6ParametersWithoutCredentials array{
 *      port:int,
 *      vcenters:array<array{name:string,url:string,username:null,password:null}>
 *  }
 */
class VmWareV6Parameters implements AccParametersInterface
{
    public const MAX_LENGTH = 255;
    private const SECOND_KEY = 'additional_connector_configuration_vmware_v6';

    /** @var _VmWareV6Parameters */
    private array $parameters;

    /**
     * @param EncryptionInterface $encryption
     * @param array<string,mixed> $parameters
     * @param bool $isEncrypted
     *
     * @throws AssertionException
     */
    public function __construct(
        private readonly EncryptionInterface $encryption,
        array $parameters,
        private readonly bool $isEncrypted = false
    ){
        /** @var _VmWareV6Parameters $parameters */
        Assertion::range($parameters['port'], 0, 65535, 'parameters.port');
        foreach ($parameters['vcenters'] as $index => $vcenter) {
            // Validate min length
            Assertion::notEmptyString($vcenter['name'], "parameters.vcenters[{$index}].name");
            Assertion::notEmptyString($vcenter['username'], "parameters.vcenters[{$index}].username");
            Assertion::notEmptyString($vcenter['password'], "parameters.vcenters[{$index}].password");
            Assertion::notEmptyString($vcenter['url'], "parameters.vcenters[{$index}].url");

            // Validate max length
            Assertion::maxLength($vcenter['name'], self::MAX_LENGTH, "parameters.vcenters[{$index}].name");
            Assertion::maxLength($vcenter['username'], self::MAX_LENGTH, "parameters.vcenters[{$index}].username");
            Assertion::maxLength($vcenter['password'], self::MAX_LENGTH, "parameters.vcenters[{$index}].password");
            Assertion::maxLength($vcenter['url'], self::MAX_LENGTH, "parameters.vcenters[{$index}].url");

            // Validate specific format
            Assertion::urlOrIpOrDomain($vcenter['url'], "parameters.vcenters[{$index}].url");
        }
        $this->parameters = $parameters;

        $this->encryption->setSecondKey(self::SECOND_KEY);
    }

    /**
     * @inheritDoc
     *
     * @return VmWareV6Parameters
     */
    public static function update(
        EncryptionInterface $encryption,
        AccParametersInterface $currentObj,
        array $newDatas
    ): self
    {
        /** @var _VmWareV6Parameters|_VmWareV6ParametersWithoutCredentials $newDatas */
        /** @var _VmWareV6Parameters $parameters */
        $parameters = $currentObj->getDecryptedData();

        foreach ($newDatas['vcenters'] as $index => $vcenter) {
            $newDatas['vcenters'][$vcenter['name']] = $vcenter;
            unset($newDatas['vcenters'][$index]);
        }

        $parameters['port'] = $newDatas['port'];
        foreach ($parameters['vcenters'] as $index => $vcenter) {
            // Remove vcenter
            if (! array_key_exists($vcenter['name'], $newDatas['vcenters'])) {
                unset($parameters['vcenters'][$index]);

                continue;
            }

            // Update vcenter
            $updatedVcenter = $newDatas['vcenters'][$vcenter['name']];
            $updatedVcenter['username'] ??= $vcenter['username'];
            $updatedVcenter['password'] ??= $vcenter['password'];

            $parameters['vcenters'][$index] = $updatedVcenter;
            unset($newDatas['vcenters'][$vcenter['name']]);
        }
        // Add new vcenter
        if ([] !== $newDatas['vcenters']) {
            foreach ($newDatas['vcenters'] as $newVcenter) {
                $parameters['vcenters'][] = $newVcenter;
            }
        }
        $parameters['vcenters'] = array_values($parameters['vcenters']);

        return new self($encryption, $parameters);
    }

    /**
     * @inheritDoc
     *
     * @return _VmWareV6Parameters
     */
    public function getData(): array
    {
        return $this->parameters;
    }

    public function isEncrypted(): bool
    {
        return $this->isEncrypted;
    }

    /**
     * @inheritDoc
     *
     * @return _VmWareV6Parameters
     */
    public function getEncryptedData(): array
    {
        if (true === $this->isEncrypted) {
            return $this->parameters;
        }

        $parameters = $this->parameters;

        foreach ($parameters['vcenters'] as $index => $vcenter) {
            $parameters['vcenters'][$index]['username'] = str_starts_with(
                $vcenter['username'],
                VaultConfiguration::VAULT_PATH_PATTERN
            ) ? $vcenter['username'] : $this->encryption->crypt($vcenter['username']);
            $parameters['vcenters'][$index]['password'] = str_starts_with(
                $vcenter['password'],
                VaultConfiguration::VAULT_PATH_PATTERN
            ) ? $vcenter['password'] : $this->encryption->crypt($vcenter['password']);
        }

        return $parameters;
    }

    /**
     * @inheritDoc
     *
     * @return _VmWareV6Parameters
     */
    public function getDecryptedData(): array
    {
        if (false === $this->isEncrypted) {
            return $this->parameters;
        }

        $parameters = $this->parameters;

        foreach ($parameters['vcenters'] as $index => $vcenter) {
            $parameters['vcenters'][$index]['username'] = str_starts_with(
                $vcenter['username'],
                VaultConfiguration::VAULT_PATH_PATTERN
            ) ? $vcenter['username'] : $this->encryption->decrypt($vcenter['username']) ?? '';
            $parameters['vcenters'][$index]['password'] = str_starts_with(
                $vcenter['password'],
                VaultConfiguration::VAULT_PATH_PATTERN
            ) ? $vcenter['password'] : $this->encryption->decrypt($vcenter['password']) ?? '';
        }

        return $parameters;
    }

    /**
     * @inheritDoc
     *
     * @return _VmWareV6ParametersWithoutCredentials
     */
    public function getDataWithoutCredentials(): array
    {
        $parameters = $this->parameters;

        foreach ($parameters['vcenters'] as $index => $vcenter) {
            $parameters['vcenters'][$index]['username'] = null;
            $parameters['vcenters'][$index]['password'] = null;
        }

        /** @var _VmWareV6ParametersWithoutCredentials $parameters */
        return $parameters;
    }
}
