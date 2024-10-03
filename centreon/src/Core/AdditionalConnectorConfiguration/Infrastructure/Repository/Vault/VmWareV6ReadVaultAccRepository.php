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

namespace Core\AdditionalConnectorConfiguration\Infrastructure\Repository\Vault;

use Core\AdditionalConnectorConfiguration\Application\Repository\ReadVaultAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\AccParametersInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type;
use Core\AdditionalConnectorConfiguration\Domain\Model\VmWareV6Parameters;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\UseCase\VaultTrait;
use Core\Common\Infrastructure\Repository\AbstractVaultRepository;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Security\Interfaces\EncryptionInterface;

/**
 * @phpstan-import-type _VmWareV6Parameters from VmWareV6Parameters
 */
class VmWareV6ReadVaultAccRepository implements ReadVaultAccRepositoryInterface
{
    use VaultTrait;

    public function __construct(
        private readonly EncryptionInterface $encryption,
        private readonly ReadVaultRepositoryInterface $readVaultRepository,
    )
    {
        $this->readVaultRepository->setCustomPath(AbstractVaultRepository::ACC_VAULT_PATH);
    }

    /**
     * @inheritDoc
     */
    public function isValidFor(Type $type): bool
    {
        return $type === Type::VMWARE_V6;
    }

    /**
     * @inheritDoc
     */
    public function getCredentialsFromVault(AccParametersInterface $parameters): AccParametersInterface
    {
        if (false === $this->readVaultRepository->isVaultConfigured()) {
            return $parameters;
        }

        /** @var _VmWareV6Parameters $data */
        $data = $parameters->getData();
        $vaultPath = null;

        foreach ($data['vcenters'] as $vcenter) {
            if (str_starts_with($vcenter['username'], VaultConfiguration::VAULT_PATH_PATTERN)) {
                $vaultPath = $vcenter['username'];

                break;
            }
            if (str_starts_with($vcenter['password'], VaultConfiguration::VAULT_PATH_PATTERN)) {
                $vaultPath = $vcenter['password'];

                break;
            }
        }

        if ($vaultPath === null) {
            return $parameters;
        }

        $vaultDatas = $this->readVaultRepository->findFromPath($vaultPath);

        foreach ($data['vcenters'] as $index => $vcenter) {
            if (in_array($vcenter['name']. '_username', array_keys($vaultDatas), true)) {
                $data['vcenters'][$index]['username'] = $vaultDatas[$vcenter['name'] . '_username'];
                $data['vcenters'][$index]['password'] = $vaultDatas[$vcenter['name'] . '_password'];
            }
        }

        return new VmWareV6Parameters($this->encryption, $data);
    }
}
