<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\Connector\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Connector\Domain\Model\Connector;

it('should return properly set instance', function (): void {
    $connector = new Connector(
        id: 1,
        name: 'connector-name',
        commandLine: 'commandline',
        description: 'some-description',
        isActivated: false,
        commandIds: [12, 23, 45],
    );

    expect($connector->getId())->toBe(1)
        ->and($connector->getName())->toBe('connector-name')
        ->and($connector->getCommandLine())->toBe('commandline')
        ->and($connector->isActivated())->toBe(false)
        ->and($connector->getDescription())->toBe('some-description')
        ->and($connector->getCommandIds())->toBe([12, 23, 45]);
});

it('should throw an exception when ID is < 0', function (): void {
    new Connector(
        id: 0,
        name: 'connector-name',
        commandLine: 'commandline'
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::positiveInt(0, 'Connector::id')->getMessage()
);

it('should throw an exception when name is empty', function (): void {
    new Connector(
        id: 1,
        name: '',
        commandLine: 'commandline',
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmptyString('Connector::name')->getMessage()
);

it('should throw an exception when name is too long', function (): void {
    new Connector(
        id: 1,
        name: str_repeat('a', Connector::NAME_MAX_LENGTH + 1),
        commandLine: 'commandline',
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', Connector::NAME_MAX_LENGTH + 1),
        Connector::NAME_MAX_LENGTH + 1,
        Connector::NAME_MAX_LENGTH,
        'Connector::name'
    )->getMessage()
);

it('should throw an exception when command line is empty', function (): void {
    new Connector(
        id: 1,
        name: 'connector-line',
        commandLine: '',
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmptyString('Connector::commandLine')->getMessage()
);

it('should throw an exception when command line is too long', function (): void {
    new Connector(
        id: 1,
        name: 'connector-name',
        commandLine: str_repeat('a', Connector::COMMAND_MAX_LENGTH + 1),
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', Connector::COMMAND_MAX_LENGTH + 1),
        Connector::COMMAND_MAX_LENGTH + 1,
        Connector::COMMAND_MAX_LENGTH,
        'Connector::commandLine'
    )->getMessage()
);
