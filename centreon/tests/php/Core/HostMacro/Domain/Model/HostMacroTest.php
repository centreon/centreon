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

namespace Tests\Core\HostMacro\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\HostMacro\Domain\Model\HostMacro;

it('should return properly set host macro instance', function (): void {
    $macro = new HostMacro(1, 'macroName', 'macroValue');
    $macro->setIsPassword(true);
    $macro->setDescription('macroDescription');

    expect($macro->getHostId())->toBe(1)
        ->and($macro->getName())->toBe('MACRONAME')
        ->and($macro->getValue())->toBe('macroValue');
});

it('should throw an exception when host macro name is empty', function (): void {
    new HostMacro(1, '', 'macroValue');
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmptyString('HostMacro::name')->getMessage()
);

it('should throw an exception when host macro name is too long', function (): void {
    new HostMacro(1, str_repeat('a', HostMacro::MAX_NAME_LENGTH + 1), 'macroValue');
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('A', HostMacro::MAX_NAME_LENGTH + 1),
        HostMacro::MAX_NAME_LENGTH + 1,
        HostMacro::MAX_NAME_LENGTH,
        'HostMacro::name'
    )->getMessage()
);

it('should throw an exception when host macro value is too long', function (): void {
    new HostMacro(1, 'macroName', str_repeat('a', HostMacro::MAX_VALUE_LENGTH + 1));
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', HostMacro::MAX_VALUE_LENGTH + 1),
        HostMacro::MAX_VALUE_LENGTH + 1,
        HostMacro::MAX_VALUE_LENGTH,
        'HostMacro::value'
    )->getMessage()
);

it('should throw an exception when host macro description is too long', function (): void {
    $macro = new HostMacro(1, 'macroName', 'macroValue');
    $macro->setDescription(str_repeat('a', HostMacro::MAX_DESCRIPTION_LENGTH + 1));
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', HostMacro::MAX_DESCRIPTION_LENGTH + 1),
        HostMacro::MAX_DESCRIPTION_LENGTH + 1,
        HostMacro::MAX_DESCRIPTION_LENGTH,
        'HostMacro::description'
    )->getMessage()
);
