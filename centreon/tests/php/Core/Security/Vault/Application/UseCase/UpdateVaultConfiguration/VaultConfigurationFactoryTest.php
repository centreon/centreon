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

namespace Tests\Core\Security\Vault\Application\UseCase\UpdateVaultConfiguration;

use Security\Encryption;
use Core\Security\Vault\Domain\Model\Vault;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Core\Security\Vault\Application\Repository\ReadVaultRepositoryInterface;
use Core\Security\Vault\Application\UseCase\UpdateVaultConfiguration\VaultConfigurationFactory;
use Core\Security\Vault\Application\UseCase\UpdateVaultConfiguration\UpdateVaultConfigurationRequest;

beforeEach(function (): void {
    $this->readVaultRepository = $this->createMock(ReadVaultRepositoryInterface::class);
});

it(
    'should return an instance of VaultConfiguration when a valid request is passed to create method',
    function (): void {
        $encryption = new Encryption();
        $vault = new Vault(1, 'myVaultProvider');
        $this->readVaultRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($vault);

        $factory = new VaultConfigurationFactory(
            $encryption->setFirstKey('myFirstKey'),
            $this->readVaultRepository
        );

        $updateVaultConfigurationRequest = new UpdateVaultConfigurationRequest();
        $updateVaultConfigurationRequest->vaultConfigurationId = 1;
        $updateVaultConfigurationRequest->name = 'myVault';
        $updateVaultConfigurationRequest->typeId = 1;
        $updateVaultConfigurationRequest->address = '127.0.0.1';
        $updateVaultConfigurationRequest->port = 8200;
        $updateVaultConfigurationRequest->storage = 'myStorage';
        $updateVaultConfigurationRequest->roleId = 'myRoleId';
        $updateVaultConfigurationRequest->secretId = 'mySecretId';

        $vaultConfiguration = $factory->create($updateVaultConfigurationRequest);

        expect($vaultConfiguration)->toBeInstanceOf(VaultConfiguration::class);
    }
);

it('should encrypt roleId and secretId correctly', function (): void {
    $encryption = new Encryption();
    $encryption = $encryption->setFirstKey('myFirstKey');

    $vault = new Vault(1, 'myVaultProvider');
    $this->readVaultRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($vault);

    $factory = new VaultConfigurationFactory($encryption, $this->readVaultRepository);
    $updateVaultConfigurationRequest = new UpdateVaultConfigurationRequest();
    $updateVaultConfigurationRequest->vaultConfigurationId = 1;
    $updateVaultConfigurationRequest->name = 'myVault';
    $updateVaultConfigurationRequest->typeId = 1;
    $updateVaultConfigurationRequest->address = '127.0.0.1';
    $updateVaultConfigurationRequest->port = 8200;
    $updateVaultConfigurationRequest->storage = 'myStorage';
    $updateVaultConfigurationRequest->roleId = 'myRoleId';
    $updateVaultConfigurationRequest->secretId = 'mySecretId';

    $vaultConfiguration = $factory->create($updateVaultConfigurationRequest);

    $encryption = $encryption->setSecondKey($vaultConfiguration->getSalt());

    $roleId = $encryption->decrypt($vaultConfiguration->getRoleId());
    $secretId = $encryption->decrypt($vaultConfiguration->getSecretId());

    expect($roleId)->toBe($updateVaultConfigurationRequest->roleId);
    expect($secretId)->toBe($updateVaultConfigurationRequest->secretId);
});
