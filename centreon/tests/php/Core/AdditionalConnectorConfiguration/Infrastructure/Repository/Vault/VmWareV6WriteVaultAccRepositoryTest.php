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

use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type;
use Core\AdditionalConnectorConfiguration\Domain\Model\VmWareV6\VmWareV6Parameters;
use Core\AdditionalConnectorConfiguration\Infrastructure\Repository\Vault\VmWareV6WriteVaultAccRepository;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Security\Interfaces\EncryptionInterface;

beforeEach(function (): void {
    $this->encryption = $this->createMock(EncryptionInterface::class);
    $this->writeVaultRepository = $this->createMock(WriteVaultRepositoryInterface::class);
    $this->writeVaultRepository->method('isVaultConfigured')->willReturn(true);

    $this->repository = new VmWareV6WriteVaultAccRepository(
        $this->encryption,
        $this->writeVaultRepository,
    );
});

it('should be valid only for the vmware_v6 type', function (): void {
    expect($this->repository->isValidFor(Type::VMWARE_V6))->toBeTrue();
});

it('should push every plaintext password to vault under a single UUID', function (): void {
    $parameters = new VmWareV6Parameters($this->encryption, [
        'port' => 443,
        'vcenters' => [
            [
                'id' => null,
                'name' => 'vc1',
                'url' => 'http://10.0.0.1/sdk',
                'username' => 'admin1',
                'password' => 'pwd-1',
            ],
            [
                'id' => null,
                'name' => 'vc2',
                'url' => 'http://10.0.0.2/sdk',
                'username' => 'admin2',
                'password' => 'pwd-2',
            ],
        ],
    ]);

    $vaultBase = 'secret::my_vault::configuration/additionalConnectorConfigurations/uuid-1';
    $this->writeVaultRepository
        ->expects($this->once())
        ->method('upsert')
        ->with(null, ['vc1_password' => 'pwd-1', 'vc2_password' => 'pwd-2'])
        ->willReturn([
            'vc1_password' => $vaultBase . '::vc1_password',
            'vc2_password' => $vaultBase . '::vc2_password',
        ]);

    $result = $this->repository->saveCredentialInVault($parameters)->getData();

    expect($result['vcenters'][0]['password'])->toBe($vaultBase . '::vc1_password')
        ->and($result['vcenters'][1]['password'])->toBe($vaultBase . '::vc2_password');
});

it(
    'should skip existing vault paths and only push plaintext passwords',
    function (): void {
        $existingPath = 'secret::my_vault::configuration/additionalConnectorConfigurations/uuid-1::vc1_password';
        $newPath = 'secret::my_vault::configuration/additionalConnectorConfigurations/uuid-2::vc2_password';

        $parameters = new VmWareV6Parameters($this->encryption, [
            'port' => 443,
            'vcenters' => [
                [
                    'id' => 1,
                    'name' => 'vc1',
                    'url' => 'http://10.0.0.1/sdk',
                    'username' => 'admin1',
                    'password' => $existingPath,
                ],
                [
                    'id' => null,
                    'name' => 'vc2',
                    'url' => 'http://10.0.0.2/sdk',
                    'username' => 'admin2',
                    'password' => 'pwd-2',
                ],
            ],
        ]);

        $this->writeVaultRepository
            ->expects($this->once())
            ->method('upsert')
            ->with(null, ['vc2_password' => 'pwd-2'])
            ->willReturn(['vc2_password' => $newPath]);

        $result = $this->repository->saveCredentialInVault($parameters)->getData();

        expect($result['vcenters'][0]['password'])->toBe($existingPath)
            ->and($result['vcenters'][1]['password'])->toBe($newPath);
    }
);

it('should not call upsert when there is nothing to insert', function (): void {
    $existingPath = 'secret::my_vault::configuration/additionalConnectorConfigurations/uuid-1::vc1_password';

    $parameters = new VmWareV6Parameters($this->encryption, [
        'port' => 443,
        'vcenters' => [[
            'id' => 1,
            'name' => 'vc1',
            'url' => 'http://10.0.0.1/sdk',
            'username' => 'admin',
            'password' => $existingPath,
        ]],
    ]);

    $this->writeVaultRepository->expects($this->never())->method('upsert');

    expect($this->repository->saveCredentialInVault($parameters)->getData()['vcenters'][0]['password'])
        ->toBe($existingPath);
});

it('should delete every unique UUID referenced by the ACC vcenters', function (): void {
    $acc = new Acc(
        id: 1,
        name: 'acc',
        type: Type::VMWARE_V6,
        createdBy: 1,
        updatedBy: 1,
        createdAt: new \DateTimeImmutable(),
        updatedAt: new \DateTimeImmutable(),
        parameters: new VmWareV6Parameters($this->encryption, [
            'port' => 443,
            'vcenters' => [
                [
                    'id' => 1,
                    'name' => 'vc1',
                    'url' => 'http://10.0.0.1/sdk',
                    'username' => 'admin1',
                    'password' => 'secret::my_vault::configuration/additionalConnectorConfigurations/uuid-A::vc1_password',
                ],
                [
                    'id' => 2,
                    'name' => 'vc2',
                    'url' => 'http://10.0.0.2/sdk',
                    'username' => 'admin2',
                    'password' => 'secret::my_vault::configuration/additionalConnectorConfigurations/uuid-A::vc2_password',
                ],
                [
                    'id' => 3,
                    'name' => 'vc3',
                    'url' => 'http://10.0.0.3/sdk',
                    'username' => 'admin3',
                    'password' => 'secret::my_vault::configuration/additionalConnectorConfigurations/uuid-B::vc3_password',
                ],
            ],
        ]),
    );

    $deleted = [];
    $this->writeVaultRepository
        ->expects($this->exactly(2))
        ->method('delete')
        ->willReturnCallback(function (string $uuid) use (&$deleted): void {
            $deleted[] = $uuid;
        });

    $this->repository->deleteFromVault($acc);

    expect($deleted)->toBe(['uuid-A', 'uuid-B']);
});

it('should not call delete when no vcenter has a vault path', function (): void {
    $acc = new Acc(
        id: 1,
        name: 'acc',
        type: Type::VMWARE_V6,
        createdBy: 1,
        updatedBy: 1,
        createdAt: new \DateTimeImmutable(),
        updatedAt: new \DateTimeImmutable(),
        parameters: new VmWareV6Parameters($this->encryption, [
            'port' => 443,
            'vcenters' => [[
                'id' => 1,
                'name' => 'vc1',
                'url' => 'http://10.0.0.1/sdk',
                'username' => 'admin',
                'password' => 'plaintext',
            ]],
        ]),
    );

    $this->writeVaultRepository->expects($this->never())->method('delete');

    $this->repository->deleteFromVault($acc);
});
