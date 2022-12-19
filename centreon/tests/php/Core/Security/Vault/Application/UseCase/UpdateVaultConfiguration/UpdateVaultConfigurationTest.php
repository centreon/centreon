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

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\{
    ErrorResponse,
    ForbiddenResponse,
    InvalidArgumentResponse,
    NoContentResponse,
    NotFoundResponse
};
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\Vault\Application\Exceptions\VaultConfigurationException;
use Core\Security\Vault\Application\Repository\{
    ReadVaultConfigurationRepositoryInterface,
    ReadVaultRepositoryInterface,
    WriteVaultConfigurationRepositoryInterface
};
use Core\Security\Vault\Application\UseCase\UpdateVaultConfiguration\{
    UpdateVaultConfiguration,
    UpdateVaultConfigurationRequest,
};
use Core\Security\Vault\Domain\Model\{Vault, VaultConfiguration};
use Security\Encryption;

beforeEach(function (): void {
    $this->readVaultConfigurationRepository = $this->createMock(ReadVaultConfigurationRepositoryInterface::class);
    $this->writeVaultConfigurationRepository = $this->createMock(WriteVaultConfigurationRepositoryInterface::class);
    $this->readVaultRepository = $this->createMock(ReadVaultRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
});

it('should present ForbiddenResponse when user is not admin', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $vault = new Vault(1, 'myVaultProvider');

    $encryption = new Encryption();
    $encryption->setFirstKey("myFirstKey");

    $presenter = new UpdateVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new UpdateVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
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
        ->method('exists')
        ->willReturn(false);

    $presenter = new UpdateVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new UpdateVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
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

    $this->readVaultRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $this->readVaultConfigurationRepository
        ->expects($this->any())
        ->method('exists')
        ->willReturn(false);

    $presenter = new UpdateVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new UpdateVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
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

    $this->readVaultRepository
        ->expects($this->any())
        ->method('exists')
        ->willReturn(true);

    $encryption = new Encryption();
    $encryption->setFirstKey("myFirstKey");

    $vault = new Vault(1, 'myVaultProvider');
    $salt = $encryption->generateRandomString(VaultConfiguration::SALT_LENGTH);
    $vaultConfiguration = new VaultConfiguration(
        $encryption,
        2,
        'myVaultConfiguration',
        $vault,
        '127.0.0.2',
        8200,
        'myStorageFolder',
        'myEncryptedRoleId',
        'myEncryptedSecretId',
        $salt
    );
    $this->readVaultConfigurationRepository
        ->expects($this->any())
        ->method('findById')
        ->willReturn($vaultConfiguration);

    $presenter = new UpdateVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new UpdateVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->user
    );

    $invalidName = '';

    $updateVaultConfigurationRequest = new UpdateVaultConfigurationRequest();
    $updateVaultConfigurationRequest->vaultConfigurationId = 1;
    $updateVaultConfigurationRequest->name = $invalidName;
    $updateVaultConfigurationRequest->typeId = 1;
    $updateVaultConfigurationRequest->address = '127.0.0.1';
    $updateVaultConfigurationRequest->port = 8200;
    $updateVaultConfigurationRequest->storage = 'myStorage';
    $updateVaultConfigurationRequest->roleId = 'myRole';
    $updateVaultConfigurationRequest->secretId = 'mySecretId';

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
    'should present InvalidArgumentResponse when update request matches different existing vault configuration with '
        . 'same vault provider',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);

        $vault = new Vault(1, 'myVaultProvider');
        $this->readVaultRepository
            ->expects($this->once())
            ->method('exists')
            ->willReturn(true);

        $encryption = new Encryption();
        $encryption->setFirstKey("myFirstKey");

        $salt = $encryption->generateRandomString(VaultConfiguration::SALT_LENGTH);

        $vaultConfiguration = new VaultConfiguration(
            $encryption,
            2,
            'myVaultConfiguration',
            $vault,
            '127.0.0.2',
            8200,
            'myStorageFolder',
            'myEncryptedRoleId',
            'myEncryptedSecretId',
            $salt
        );
        $this->readVaultConfigurationRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($vaultConfiguration);

        $encryption = new Encryption();
        $encryption->setFirstKey("myFirstKey");

        $existingVaultConfiguration = new VaultConfiguration(
            $encryption,
            1,
            'myExistingVaultConfiguration',
            $vault,
            '127.0.0.1',
            8200,
            'myStorageFolder',
            'myEncryptedRoleId',
            'myEncryptedSecretId',
            $salt
        );

        $this->readVaultConfigurationRepository
            ->expects($this->once())
            ->method('findByAddressAndPortAndStorage')
            ->willReturn($existingVaultConfiguration);

        $updateVaultConfigurationRequest = new UpdateVaultConfigurationRequest();
        $updateVaultConfigurationRequest->vaultConfigurationId = $vaultConfiguration->getId();
        $updateVaultConfigurationRequest->name = $vaultConfiguration->getName();
        $updateVaultConfigurationRequest->typeId = $vault->getId();
        $updateVaultConfigurationRequest->address = $existingVaultConfiguration->getAddress();
        $updateVaultConfigurationRequest->port = $existingVaultConfiguration->getPort();
        $updateVaultConfigurationRequest->storage = $existingVaultConfiguration->getStorage();
        $updateVaultConfigurationRequest->roleId = $vaultConfiguration->getEncryptedRoleId();
        $updateVaultConfigurationRequest->secretId = $vaultConfiguration->getEncryptedSecretId();

        $presenter = new UpdateVaultConfigurationPresenterStub($this->presenterFormatter);
        $useCase = new UpdateVaultConfiguration(
            $this->readVaultConfigurationRepository,
            $this->writeVaultConfigurationRepository,
            $this->readVaultRepository,
            $this->user
        );

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
        ->method('exists')
        ->willThrowException(new \Exception());

    $presenter = new UpdateVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new UpdateVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->user
    );

    $updateVaultConfigurationRequest = new UpdateVaultConfigurationRequest();

    $useCase($presenter, $updateVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(ErrorResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe(
        VaultConfigurationException::impossibleToUpdate()->getMessage()
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
        ->method('exists')
        ->willReturn(true);

    $encryption = new Encryption();
    $encryption->setFirstKey("myFirstKey");

    $vaultConfiguration = new VaultConfiguration(
        $encryption,
        2,
        'myVaultConfiguration',
        $vault,
        '127.0.0.2',
        8201,
        'myStorageFolder',
        'myEncryptedRoleId',
        'myEncryptedSecretId',
        'mySalt'
    );
    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($vaultConfiguration);

    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('findByAddressAndPortAndStorage')
        ->willReturn(null);

    $presenter = new UpdateVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new UpdateVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
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
