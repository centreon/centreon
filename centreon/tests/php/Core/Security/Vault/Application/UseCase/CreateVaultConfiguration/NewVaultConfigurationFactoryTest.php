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

namespace Tests\Core\Security\Vault\Application\UseCase\CreateVaultConfiguration;

use Core\Security\Vault\Application\Repository\ReadVaultRepositoryInterface;
use Core\Security\Vault\Application\UseCase\CreateVaultConfiguration\{
    CreateVaultConfigurationRequest,
    NewVaultConfigurationFactory
};
use Core\Security\Vault\Domain\Model\{NewVaultConfiguration, Vault};
use Security\Encryption;

beforeEach(function (): void {
    $this->readVaultRepository = $this->createMock(ReadVaultRepositoryInterface::class);
});

it(
    'should return an instance of NewVaultConfiguration when a valid request is passed to create method',
    function (): void {
        $encryption = new Encryption();
        $vault = new Vault(1, 'myVaultProvider');
        $this->readVaultRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($vault);

        $factory = new NewVaultConfigurationFactory(
            $encryption->setFirstKey('myFirstKey'),
            $this->readVaultRepository
        );
        $createVaultConfigurationRequest = new CreateVaultConfigurationRequest();
        $createVaultConfigurationRequest->name = 'myVault';
        $createVaultConfigurationRequest->typeId = 1;
        $createVaultConfigurationRequest->address = '127.0.0.1';
        $createVaultConfigurationRequest->port = 8200;
        $createVaultConfigurationRequest->rootPath = 'myStorage';
        $createVaultConfigurationRequest->roleId = 'myRoleId';
        $createVaultConfigurationRequest->secretId = 'mySecretId';

        $newVaultConfiguration = $factory->create($createVaultConfigurationRequest);

        expect($newVaultConfiguration)->toBeInstanceOf(NewVaultConfiguration::class);
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

    $factory = new NewVaultConfigurationFactory($encryption, $this->readVaultRepository);
    $createVaultConfigurationRequest = new CreateVaultConfigurationRequest();
    $createVaultConfigurationRequest->name = 'myVault';
    $createVaultConfigurationRequest->typeId = 1;
    $createVaultConfigurationRequest->address = '127.0.0.1';
    $createVaultConfigurationRequest->port = 8200;
    $createVaultConfigurationRequest->rootPath = 'myStorage';
    $createVaultConfigurationRequest->roleId = 'myRoleId';
    $createVaultConfigurationRequest->secretId = 'mySecretId';

    $newVaultConfiguration = $factory->create($createVaultConfigurationRequest);

    $encryption = $encryption->setSecondKey($newVaultConfiguration->getSalt());

    $roleId = $encryption->decrypt($newVaultConfiguration->getEncryptedRoleId());
    $secretId = $encryption->decrypt($newVaultConfiguration->getEncryptedSecretId());

    expect($roleId)->toBe($createVaultConfigurationRequest->roleId);
    expect($secretId)->toBe($createVaultConfigurationRequest->secretId);
});
