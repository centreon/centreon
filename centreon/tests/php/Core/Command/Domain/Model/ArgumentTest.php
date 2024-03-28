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

namespace Tests\Core\Command\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Command\Domain\Model\Argument;
use Core\Common\Domain\TrimmedString;

it('should return properly set argument instance', function (): void {
    $argument = new Argument(new TrimmedString('ARG1'), new TrimmedString('argDescription'));

    expect($argument->getName())->toBe('ARG1')
        ->and($argument->getDescription())->toBe('argDescription');
});

it('should throw an exception when name is empty', function (): void {
    new Argument(new TrimmedString(''), new TrimmedString('argDescription'));
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmptyString('Argument::name')->getMessage()
);

it('should throw an exception when name is too long', function (): void {
    new Argument(
        new TrimmedString(str_repeat('a', Argument::NAME_MAX_LENGTH + 1)),
        new TrimmedString('argDescription')
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', Argument::NAME_MAX_LENGTH + 1),
        Argument::NAME_MAX_LENGTH + 1,
        Argument::NAME_MAX_LENGTH,
        'Argument::name'
    )->getMessage()
);

it('should throw an exception when name does not respect format', function (): void {
    new Argument(
        new TrimmedString('test'),
        new TrimmedString('argDescription')
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::matchRegex(
        'test',
        '/^ARG\d+$/',
        'Argument::name'
    )->getMessage()
);

it('should throw an exception when description is too long', function (): void {
    new Argument(
        new TrimmedString('ARG1'),
        new TrimmedString(str_repeat('a', Argument::DESCRIPTION_MAX_LENGTH + 1))
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', Argument::DESCRIPTION_MAX_LENGTH + 1),
        Argument::DESCRIPTION_MAX_LENGTH + 1,
        Argument::DESCRIPTION_MAX_LENGTH,
        'Argument::description'
    )->getMessage()
);
