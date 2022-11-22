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

namespace Tests\Core\Security\Vault\Application\UseCase\UpdateVaultConfiguration;

use Assert\InvalidArgumentException;
use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\{ErrorResponse,
    ForbiddenResponse,
    InvalidArgumentResponse,
    NoContentResponse,
    NotFoundResponse};
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\Vault\Application\Exceptions\VaultConfigurationException;
use Core\Security\Vault\Application\Repository\{ReadVaultConfigurationRepositoryInterface,
    ReadVaultRepositoryInterface,
    WriteVaultConfigurationRepositoryInterface};
use Core\Security\Vault\Application\UseCase\UpdateVaultConfiguration\{UpdateVaultConfiguration,
    UpdateVaultConfigurationRequest,
    VaultConfigurationFactory};
use Core\Security\Vault\Domain\Model\{Vault, VaultConfiguration};

beforeEach(function (): void {
    $this->readVaultConfigurationRepository = $this->createMock(ReadVaultConfigurationRepositoryInterface::class);
    $this->writeVaultConfigurationRepository = $this->createMock(WriteVaultConfigurationRepositoryInterface::class);
    $this->readVaultRepository = $this->createMock(ReadVaultRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->factory = $this->createMock(VaultConfigurationFactory::class);
    $this->user = $this->createMock(ContactInterface::class);
});

it('should present ForbiddenResponse when user is not admin', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

        $vault = new Vault(1, 'myVaultProvider');

        $vaultConfiguration = new VaultConfiguration(
            1,
            'myConf',
            $vault,
            '127.0.0.1',
            8200,
            'myStorage',
            'myRoleId',
            'mySecretId',
            'mySalt'
        );

        $presenter = new UpdateVaultConfigurationPresenterStub($this->presenterFormatter);
        $useCase = new UpdateVaultConfiguration(
            $this->readVaultConfigurationRepository,
            $this->writeVaultConfigurationRepository,
            $this->readVaultRepository,
            $this->factory,
            $this->user
        );

        $updateVaultConfigurationRequest = new UpdateVaultConfigurationRequest();

        $useCase($presenter, $updateVaultConfigurationRequest);

        expect($presenter->getResponseStatus())->toBeInstanceOf(ForbiddenResponse::class);
        expect($presenter->getResponseStatus()?->getMessage())->toBe('Only admin user can create vault configuration');
});

it('should present NotFoundResponse when vault provider does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readVaultRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(null);

    $presenter = new UpdateVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new UpdateVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->factory,
        $this->user
    );

    $updateVaultConfigurationRequest = new UpdateVaultConfigurationRequest();
    $updateVaultConfigurationRequest->vaultConfigurationId = 1;
    $updateVaultConfigurationRequest->name = 'myVault';
    $updateVaultConfigurationRequest->typeId = 3;
    $updateVaultConfigurationRequest->address = '127.0.0.1';
    $updateVaultConfigurationRequest->port = 8200;
    $updateVaultConfigurationRequest->storage = 'myStorage';
    $updateVaultConfigurationRequest->roleId = 'myRole';
    $updateVaultConfigurationRequest->secretId = 'mySecretId';

    $useCase($presenter, $updateVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(NotFoundResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe(
        (new NotFoundResponse('Vault provider'))->getMessage()
    );
});

it('should present NotFoundResponse when vault configuration does not exist for a given id', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $vault = new Vault(1, 'myVaultProvider');
    $this->readVaultRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($vault);

    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(null);

    $presenter = new UpdateVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new UpdateVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->factory,
        $this->user
    );

    $updateVaultConfigurationRequest = new UpdateVaultConfigurationRequest();
    $updateVaultConfigurationRequest->vaultConfigurationId = 1;
    $updateVaultConfigurationRequest->name = 'myVault';
    $updateVaultConfigurationRequest->typeId = 3;
    $updateVaultConfigurationRequest->address = '127.0.0.1';
    $updateVaultConfigurationRequest->port = 8200;
    $updateVaultConfigurationRequest->storage = 'myStorage';
    $updateVaultConfigurationRequest->roleId = 'myRole';
    $updateVaultConfigurationRequest->secretId = 'mySecretId';

    $useCase($presenter, $updateVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(NotFoundResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe(
        (new NotFoundResponse('Vault configuration'))->getMessage()
    );
});

it('should present InvalidArgumentResponse when one parameter is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $vault = new Vault(1, 'myVaultProvider');
    $this->readVaultRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($vault);

    $vaultConfiguration = new VaultConfiguration(
        2,
        'myVaultConfiguration',
        $vault,
        '127.0.0.2',
        8201,
        'myStorageFolder',
        'myRoleId',
        'mySecretId',
        'mySalt'
    );
    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($vaultConfiguration);

    $presenter = new UpdateVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new UpdateVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->factory,
        $this->user
    );

    $invalidName = '';

    $updateVaultConfigurationRequest = new UpdateVaultConfigurationRequest();
    $updateVaultConfigurationRequest->vaultConfigurationId = 1;
    $updateVaultConfigurationRequest->name = $invalidName;
    $updateVaultConfigurationRequest->typeId = 3;
    $updateVaultConfigurationRequest->address = '127.0.0.1';
    $updateVaultConfigurationRequest->port = 8200;
    $updateVaultConfigurationRequest->storage = 'myStorage';
    $updateVaultConfigurationRequest->roleId = 'myRole';
    $updateVaultConfigurationRequest->secretId = 'mySecretId';

    $this->factory
        ->expects($this->once())
        ->method('create')
        ->with($updateVaultConfigurationRequest)
        ->willThrowException(
            new InvalidArgumentException(
                AssertionException::minLength(
                    $invalidName,
                    strlen($invalidName),
                    VaultConfiguration::MIN_LENGTH,
                    'VaultConfiguration::name'
                )->getMessage(),
                1
            )
        );

    $useCase($presenter, $updateVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(InvalidArgumentResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe(
        AssertionException::minLength(
            $invalidName,
            strlen($invalidName),
            VaultConfiguration::MIN_LENGTH,
            'VaultConfiguration::name'
        )->getMessage()
    );
});

it(
    'should present InvalidArgumentResponse when update request matches different existing vault configuration',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);

        $vault = new Vault(1, 'myVaultProvider');
        $this->readVaultRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($vault);

        $vaultConfiguration = new VaultConfiguration(
            2,
            'myVaultConfiguration',
            $vault,
            '127.0.0.2',
            8201,
            'myStorageFolder',
            'myRoleId',
            'mySecretId',
            'mySalt'
        );
        $this->readVaultConfigurationRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($vaultConfiguration);

        $presenter = new UpdateVaultConfigurationPresenterStub($this->presenterFormatter);
        $useCase = new UpdateVaultConfiguration(
            $this->readVaultConfigurationRepository,
            $this->writeVaultConfigurationRepository,
            $this->readVaultRepository,
            $this->factory,
            $this->user
        );

        $updateVaultConfigurationRequest = new UpdateVaultConfigurationRequest();
        $updateVaultConfigurationRequest->vaultConfigurationId = 1;
        $updateVaultConfigurationRequest->name = 'myVaultConfigurationName';
        $updateVaultConfigurationRequest->typeId = 1;
        $updateVaultConfigurationRequest->address = '127.0.0.1';
        $updateVaultConfigurationRequest->port = 8200;
        $updateVaultConfigurationRequest->storage = 'myStorage';
        $updateVaultConfigurationRequest->roleId = 'myRole';
        $updateVaultConfigurationRequest->secretId = 'mySecretId';

        $sameVaultConfiguration = new VaultConfiguration(
            3,
            'mySameVaultConfiguration',
            $vault,
            '127.0.0.1',
            8200,
            'myStorage',
            'myRole',
            'mySecretId',
            'mySalt'
        );
        $this->readVaultConfigurationRepository
            ->expects($this->once())
            ->method('findByAddressAndPortAndStorage')
            ->willReturn($sameVaultConfiguration);

        $updateVaultConfigurationRequest = new UpdateVaultConfigurationRequest();
        $useCase($presenter, $updateVaultConfigurationRequest);

        expect($presenter->getResponseStatus())->toBeInstanceOf(InvalidArgumentResponse::class);
        expect($presenter->getResponseStatus()?->getMessage())->toBe(
            VaultConfigurationException::configurationExists()->getMessage()
        );
    }
);

it('should present ErrorResponse when an unhandled error occurs', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readVaultRepository
        ->expects($this->once())
        ->method('findById')
        ->willThrowException(new \Exception());

    $presenter = new UpdateVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new UpdateVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->factory,
        $this->user
    );

    $updateVaultConfigurationRequest = new UpdateVaultConfigurationRequest();

    $useCase($presenter, $updateVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(ErrorResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe(
        VaultConfigurationException::impossibleToCreate()->getMessage()
    );
});

it('should present NoContentResponse when vault configuration is created with success', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $vault = new Vault(1, 'myVaultProvider');
    $this->readVaultRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($vault);

    $vaultConfiguration = new VaultConfiguration(
        2,
        'myVaultConfiguration',
        $vault,
        '127.0.0.2',
        8201,
        'myStorageFolder',
        'myRoleId',
        'mySecretId',
        'mySalt'
    );
    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($vaultConfiguration);

    $presenter = new UpdateVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new UpdateVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->factory,
        $this->user
    );

    $updateVaultConfigurationRequest = new UpdateVaultConfigurationRequest();
    $updateVaultConfigurationRequest->vaultConfigurationId = 1;
    $updateVaultConfigurationRequest->name = 'myVaultConfigurationName';
    $updateVaultConfigurationRequest->typeId = 1;
    $updateVaultConfigurationRequest->address = '127.0.0.1';
    $updateVaultConfigurationRequest->port = 8200;
    $updateVaultConfigurationRequest->storage = 'myStorage';
    $updateVaultConfigurationRequest->roleId = 'myRole';
    $updateVaultConfigurationRequest->secretId = 'mySecretId';

    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('findByAddressAndPortAndStorage')
        ->willReturn(null);

    $useCase($presenter, $updateVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});
