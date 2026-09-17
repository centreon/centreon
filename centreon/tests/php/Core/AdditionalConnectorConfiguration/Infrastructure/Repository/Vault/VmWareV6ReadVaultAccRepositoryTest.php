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

namespace Tests\Core\AdditionalConnectorConfiguration\Infrastructure\Repository\Vault;

use Core\AdditionalConnectorConfiguration\Domain\Model\Type;
use Core\AdditionalConnectorConfiguration\Domain\Model\VmWareV6\VmWareV6Parameters;
use Core\AdditionalConnectorConfiguration\Infrastructure\Repository\Vault\VmWareV6ReadVaultAccRepository;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Security\Interfaces\EncryptionInterface;

beforeEach(function (): void {
    $this->encryption = $this->createMock(EncryptionInterface::class);
    $this->readVaultRepository = $this->createMock(ReadVaultRepositoryInterface::class);
    $this->readVaultRepository->method('isVaultConfigured')->willReturn(true);

    $this->repository = new VmWareV6ReadVaultAccRepository(
        $this->encryption,
        $this->readVaultRepository,
    );
});

it('should be valid only for the vmware_v6 type', function (): void {
    expect($this->repository->isValidFor(Type::VMWARE_V6))->toBeTrue();
});

it('should return parameters unchanged when vault is not configured', function (): void {
    $readVaultRepository = $this->createMock(ReadVaultRepositoryInterface::class);
    $readVaultRepository->method('isVaultConfigured')->willReturn(false);
    $repository = new VmWareV6ReadVaultAccRepository($this->encryption, $readVaultRepository);

    $parameters = new VmWareV6Parameters($this->encryption, [
        'port' => 443,
        'vcenters' => [[
            'id' => 1,
            'name' => 'vc1',
            'url' => 'http://10.0.0.1/sdk',
            'username' => 'admin',
            'password' => 'plaintext',
        ]],
    ]);

    expect($repository->getCredentialsFromVault($parameters))->toBe($parameters);
});

it('should return parameters unchanged when no vcenter has a vault path', function (): void {
    $parameters = new VmWareV6Parameters($this->encryption, [
        'port' => 443,
        'vcenters' => [[
            'id' => 1,
            'name' => 'vc1',
            'url' => 'http://10.0.0.1/sdk',
            'username' => 'admin',
            'password' => 'plaintext',
        ]],
    ]);

    $this->readVaultRepository->expects($this->never())->method('findFromPath');

    expect($this->repository->getCredentialsFromVault($parameters))->toBe($parameters);
});

it(
    'should restore plaintext passwords for every vcenter whose _password key exists in vault',
    function (): void {
        $vaultPath = 'secret::my_vault::configuration/additionalConnectorConfigurations/uuid-1::vc1_password';
        $parameters = new VmWareV6Parameters($this->encryption, [
            'port' => 443,
            'vcenters' => [
                [
                    'id' => 1,
                    'name' => 'vc1',
                    'url' => 'http://10.0.0.1/sdk',
                    'username' => 'admin1',
                    'password' => $vaultPath,
                ],
                [
                    'id' => 2,
                    'name' => 'vc2',
                    'url' => 'http://10.0.0.2/sdk',
                    'username' => 'admin2',
                    'password' => 'secret::my_vault::configuration/additionalConnectorConfigurations/uuid-1::vc2_password',
                ],
            ],
        ]);

        $this->readVaultRepository
            ->expects($this->once())
            ->method('findFromPath')
            ->with($vaultPath)
            ->willReturn([
                'vc1_password' => 'pwd-1',
                'vc2_password' => 'pwd-2',
            ]);

        $result = $this->repository->getCredentialsFromVault($parameters)->getData();

        expect($result['vcenters'][0]['password'])->toBe('pwd-1')
            ->and($result['vcenters'][1]['password'])->toBe('pwd-2')
            ->and($result['vcenters'][0]['username'])->toBe('admin1')
            ->and($result['vcenters'][1]['username'])->toBe('admin2');
    }
);

it(
    'should keep the vault path unchanged for a vcenter whose _password key is missing from vault',
    function (): void {
        $vaultPath = 'secret::my_vault::configuration/additionalConnectorConfigurations/uuid-1::vc1_password';
        $danglingPath = 'secret::my_vault::configuration/additionalConnectorConfigurations/uuid-1::dangling_password';
        $parameters = new VmWareV6Parameters($this->encryption, [
            'port' => 443,
            'vcenters' => [
                [
                    'id' => 1,
                    'name' => 'vc1',
                    'url' => 'http://10.0.0.1/sdk',
                    'username' => 'admin1',
                    'password' => $vaultPath,
                ],
                [
                    'id' => 2,
                    'name' => 'renamed-vc',
                    'url' => 'http://10.0.0.2/sdk',
                    'username' => 'admin2',
                    'password' => $danglingPath,
                ],
            ],
        ]);

        $this->readVaultRepository
            ->method('findFromPath')
            ->willReturn(['vc1_password' => 'pwd-1']);

        $result = $this->repository->getCredentialsFromVault($parameters)->getData();

        expect($result['vcenters'][0]['password'])->toBe('pwd-1')
            ->and($result['vcenters'][1]['password'])->toBe($danglingPath);
    }
);
