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

namespace Tests\Core\Security\Vault\Domain\Model;

use Assert\InvalidArgumentException;
use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Security\Vault\Domain\Model\NewVaultConfiguration;
use Core\Security\Vault\Domain\Model\Vault;
use Security\Encryption;

$invalidMinLengthString = '';
$invalidMaxLengthString = '';
for ($index = 0; $index <= NewVaultConfiguration::MAX_LENGTH; $index++) {
    $invalidMaxLengthString .= 'a';
}
$invalidNameMaxLengthString = str_repeat('a', NewVaultConfiguration::NAME_MAX_LENGTH + 1);
beforeEach(function (): void {
    $this->encryption = new Encryption();
    $this->encryption->setFirstKey("myFirstKey");
});

it(
    'should throw InvalidArgumentException when vault configuration name is empty',
    function () use ($invalidMinLengthString): void {
        new NewVaultConfiguration(
            $this->encryption,
            '127.0.0.1',
            8200,
            'myStorage',
            'myRoleId',
            'mySecretId',
            $invalidMinLengthString,
        );
    }
)->throws(
    InvalidArgumentException::class,
    AssertionException::minLength(
        $invalidMinLengthString,
        strlen($invalidMinLengthString),
        NewVaultConfiguration::MIN_LENGTH,
        'NewVaultConfiguration::name'
    )->getMessage()
);

it(
    'should throw InvalidArgumentException when vault configuration name exceeds allowed max length',
    function () use ($invalidNameMaxLengthString): void {
        new NewVaultConfiguration(
            $this->encryption,
            '127.0.0.1',
            8200,
            'myStorage',
            'myRoleId',
            'mySecretId',
            $invalidNameMaxLengthString,
        );
    }
)->throws(
    InvalidArgumentException::class,
    AssertionException::maxLength(
        $invalidNameMaxLengthString,
        strlen($invalidNameMaxLengthString),
        NewVaultConfiguration::NAME_MAX_LENGTH,
        'NewVaultConfiguration::name'
    )->getMessage()
);

it(
    'should throw InvalidArgumentException when vault configuration address is empty',
    function () use ($invalidMinLengthString): void {
        new NewVaultConfiguration(
            $this->encryption,
            $invalidMinLengthString,
            8200,
            'myStorage',
            'myRoleId',
            'mySecretId',
            'myVault',
        );
    }
)->throws(
    InvalidArgumentException::class,
    AssertionException::minLength(
        $invalidMinLengthString,
        strlen($invalidMinLengthString),
        NewVaultConfiguration::MIN_LENGTH,
        'NewVaultConfiguration::address'
    )->getMessage()
);

it('should throw AssertionException when vault configuration address is \'._@\'', function (): void {
    new NewVaultConfiguration(
        $this->encryption,
        '._@',
        8200,
        'myStorage',
        'myRoleId',
        'mySecretId',
        'myVault',
    );
})->throws(
    AssertionException::class,
    AssertionException::ipOrDomain('._@', 'NewVaultConfiguration::address')->getMessage()
);

it(
    'should throw InvalidArgumentException when vault configuration port value is lower than allowed range',
    function (): void {
        new NewVaultConfiguration(
            $this->encryption,
            '127.0.0.1',
            0,
            'myStorage',
            'myRoleId',
            'mySecretId',
            'myVault',
        );
    }
)->throws(
    InvalidArgumentException::class,
    AssertionException::min(
        NewVaultConfiguration::MIN_PORT_VALUE - 1,
        NewVaultConfiguration::MIN_PORT_VALUE,
        'NewVaultConfiguration::port'
    )->getMessage()
);

it('should throw InvalidArgumentException when vault configuration port exceeds allowed range', function (): void {
    new NewVaultConfiguration(
        $this->encryption,
        '127.0.0.1',
        NewVaultConfiguration::MAX_PORT_VALUE + 1,
        'myStorage',
        'myRoleId',
        'mySecretId',
        'myVault',
    );
})->throws(
    InvalidArgumentException::class,
    AssertionException::max(
        NewVaultConfiguration::MAX_PORT_VALUE + 1,
        NewVaultConfiguration::MAX_PORT_VALUE,
        'NewVaultConfiguration::port'
    )->getMessage()
);

it(
    'should throw InvalidArgumentException when vault configuration rootPath is empty',
    function () use ($invalidMinLengthString): void {
        new NewVaultConfiguration(
            $this->encryption,
            '127.0.0.1',
            8200,
            $invalidMinLengthString,
            'myRoleId',
            'mySecretId',
            'myVault',
        );
    }
)->throws(
    InvalidArgumentException::class,
    AssertionException::minLength(
        $invalidMinLengthString,
        strlen($invalidMinLengthString),
        NewVaultConfiguration::MIN_LENGTH,
        'NewVaultConfiguration::rootPath'
    )->getMessage()
);

it(
    'should throw InvalidArgumentException when vault configuration rootPath exceeds allowed max length',
    function () use ($invalidNameMaxLengthString): void {
        new NewVaultConfiguration(
            $this->encryption,
            '127.0.0.1',
            8200,
            $invalidNameMaxLengthString,
            'myRoleId',
            'mySecretId',
            'myVault',
        );
    }
)->throws(
    InvalidArgumentException::class,
    AssertionException::maxLength(
        $invalidNameMaxLengthString,
        strlen($invalidNameMaxLengthString),
        NewVaultConfiguration::NAME_MAX_LENGTH,
        'NewVaultConfiguration::rootPath'
    )->getMessage()
);

it(
    'should throw InvalidArgumentException when vault configuration role id is empty',
    function () use ($invalidMinLengthString): void {
        new NewVaultConfiguration(
            $this->encryption,
            '127.0.0.1',
            8200,
            'myStorage',
            $invalidMinLengthString,
            'mySecretId',
            'myVault',
        );
    }
)->throws(
    InvalidArgumentException::class,
    AssertionException::minLength(
        $invalidMinLengthString,
        strlen($invalidMinLengthString),
        NewVaultConfiguration::MIN_LENGTH,
        'NewVaultConfiguration::roleId'
    )->getMessage()
);

it(
    'should throw InvalidArgumentException when vault configuration role id exceeds allowed max length',
    function () use ($invalidMaxLengthString): void {
        new NewVaultConfiguration(
            $this->encryption,
            '127.0.0.1',
            8200,
            'myStorage',
            $invalidMaxLengthString,
            'mySecretId',
            'myVault',
        );
    }
)->throws(
    InvalidArgumentException::class,
    AssertionException::maxLength(
        $invalidMaxLengthString,
        strlen($invalidMaxLengthString),
        NewVaultConfiguration::MAX_LENGTH,
        'NewVaultConfiguration::roleId'
    )->getMessage()
);

it(
    'should throw InvalidArgumentException when vault configuration secret id is empty',
    function () use ($invalidMinLengthString): void {
        new NewVaultConfiguration(
            $this->encryption,
            '127.0.0.1',
            8200,
            'myStorage',
            'myRoleId',
            $invalidMinLengthString,
            'myVault',
        );
    }
)->throws(
    InvalidArgumentException::class,
    AssertionException::minLength(
        $invalidMinLengthString,
        strlen($invalidMinLengthString),
        NewVaultConfiguration::MIN_LENGTH,
        'NewVaultConfiguration::secretId'
    )->getMessage()
);

it(
    'should throw InvalidArgumentException when vault configuration secret id exceeds allowed max length',
    function () use ($invalidMaxLengthString): void {
        new NewVaultConfiguration(
            $this->encryption,
            '127.0.0.1',
            8200,
            'myStorage',
            'myRoleId',
            $invalidMaxLengthString,
            'myVault',
        );
    }
)->throws(
    InvalidArgumentException::class,
    AssertionException::maxLength(
        $invalidMaxLengthString,
        strlen($invalidMaxLengthString),
        NewVaultConfiguration::MAX_LENGTH,
        'NewVaultConfiguration::secretId'
    )->getMessage()
);

it(
    'should return an instance of NewVaultConfiguration when all vault configuration parametes are valid',
    function (): void {
        $newVaultConfiguration = new NewVaultConfiguration(
            $this->encryption,
            '127.0.0.1',
            8200,
            'myStorage',
            'myRoleId',
            'mySecretId',
            'myVault',
        );

        expect($newVaultConfiguration->getName())->toBe('myVault');
        expect($newVaultConfiguration->getAddress())->toBe('127.0.0.1');
        expect($newVaultConfiguration->getPort())->toBe(8200);
        expect($newVaultConfiguration->getRootPath())->toBe('myStorage');
    }
);
