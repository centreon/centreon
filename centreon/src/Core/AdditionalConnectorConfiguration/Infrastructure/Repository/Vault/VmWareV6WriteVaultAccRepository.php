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

use Core\AdditionalConnectorConfiguration\Application\Repository\WriteVaultAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\AdditionalConnectorConfiguration\Domain\Model\AccParametersInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type;
use Core\AdditionalConnectorConfiguration\Domain\Model\VmWareV6Parameters;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Application\UseCase\VaultTrait;
use Core\Common\Infrastructure\Repository\AbstractVaultRepository;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Security\Interfaces\EncryptionInterface;

/**
 * @phpstan-import-type _VmWareV6Parameters from VmWareV6Parameters
 */
class VmWareV6WriteVaultAccRepository implements WriteVaultAccRepositoryInterface
{
    use VaultTrait;

    public function __construct(
        private readonly EncryptionInterface $encryption,
        private readonly WriteVaultRepositoryInterface $writeVaultRepository,
    )
    {
        $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::ACC_VAULT_PATH);
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
    public function saveCredentialInVault(AccParametersInterface $parameters): AccParametersInterface
    {
        if (false === $this->writeVaultRepository->isVaultConfigured()) {
            return $parameters;
        }

        /** @var _VmWareV6Parameters $data */
        $data = $parameters->getData();
        $inserts = [];

        foreach ($data['vcenters'] as $vcenter) {
            $inserts[$vcenter['name'] . '_username'] = $vcenter['username'];
            $inserts[$vcenter['name'] . '_password'] = $vcenter['password'];
        }

        $vaultPaths = $this->writeVaultRepository->upsert(null, $inserts);

        foreach ($data['vcenters'] as $index => $vcenter) {
            if (in_array($vcenter['name']. '_username', array_keys($vaultPaths), true)) {
                $data['vcenters'][$index]['username'] = $vaultPaths[$vcenter['name'] . '_username'];
                $data['vcenters'][$index]['password'] = $vaultPaths[$vcenter['name'] . '_password'];
            }
        }

        return new VmWareV6Parameters($this->encryption, $data);
    }

    /**
     * @inheritDoc
     */
    public function deleteFromVault(Acc $acc): void
    {
        if (false === $this->writeVaultRepository->isVaultConfigured()) {
            return;
        }

        /** @var _VmWareV6Parameters $parameters */
        $parameters = $acc->getParameters()->getData();
        $vaultPath = null;
        foreach ($parameters['vcenters'] as $vcenter) {
            if (str_starts_with($vcenter['password'], VaultConfiguration::VAULT_PATH_PATTERN) === true) {
                $vaultPath = $vcenter['password'];

                break;
            }
        }

        if (null !== $vaultPath && null !== $uuid = $this->getUuidFromPath($vaultPath)) {
            $this->writeVaultRepository->delete($uuid);
        }
    }
}
