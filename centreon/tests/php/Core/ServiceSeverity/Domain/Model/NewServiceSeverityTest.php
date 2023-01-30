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

namespace Tests\Core\ServiceSeverity\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\ServiceSeverity\Domain\Model\NewServiceSeverity;

beforeEach(function (): void {
    $this->severityName = 'service-name';
    $this->severityAlias = 'service-alias';
    $this->level = 1;
    $this->iconId = 2;
});

it('should return properly set service severity instance', function (): void {
    $serviceSeverity = new NewServiceSeverity($this->severityName, $this->severityAlias, $this->level, $this->iconId);

    expect($serviceSeverity->getName())->toBe($this->severityName)
        ->and($serviceSeverity->getAlias())->toBe($this->severityAlias);
});

// it('should trim the fields "name" and "alias"', function (): void {
//     $serviceSeverity = new NewServiceSeverity(
//         $nameWithSpaces = '  my-name  ',
//         $aliasWithSpaces = '  my-alias  ',
//         $this->level,
//         $this->iconId
//     );

//     expect($serviceSeverity->getName())->toBe(trim($nameWithSpaces))
//         ->and($serviceSeverity->getAlias())->toBe(trim($aliasWithSpaces));
// });

it('should throw an exception when service severity name is empty', function (): void {
    new NewServiceSeverity('', $this->severityAlias, $this->level, $this->iconId);
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmpty('NewServiceSeverity::name')
        ->getMessage()
);

it('should throw an exception when service severity name is too long', function (): void {
    new NewServiceSeverity(
        str_repeat('a', NewServiceSeverity::MAX_NAME_LENGTH + 1),
        $this->severityAlias,
        $this->level,
        $this->iconId
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', NewServiceSeverity::MAX_NAME_LENGTH + 1),
        NewServiceSeverity::MAX_NAME_LENGTH + 1,
        NewServiceSeverity::MAX_NAME_LENGTH,
        'NewServiceSeverity::name'
    )->getMessage()
);

it('should throw an exception when service severity alias is empty', function (): void {
    new NewServiceSeverity($this->severityName, '', $this->level, $this->iconId);
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmpty('NewServiceSeverity::alias')
        ->getMessage()
);

it('should throw an exception when service severity alias is too long', function (): void {
    new NewServiceSeverity(
        $this->severityName,
        str_repeat('a', NewServiceSeverity::MAX_ALIAS_LENGTH + 1),
        $this->level,
        $this->iconId
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', NewServiceSeverity::MAX_ALIAS_LENGTH + 1),
        NewServiceSeverity::MAX_ALIAS_LENGTH + 1,
        NewServiceSeverity::MAX_ALIAS_LENGTH,
        'NewServiceSeverity::alias'
    )->getMessage()
);

it('should throw an exception when service severity level is too high', function (): void {
    $serviceSeverity = new NewServiceSeverity(
        $this->severityName,
        $this->severityAlias,
        NewServiceSeverity::MAX_LEVEL_VALUE + 1,
        $this->iconId
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::max(
        NewServiceSeverity::MAX_LEVEL_VALUE + 1,
        NewServiceSeverity::MAX_LEVEL_VALUE,
        'NewServiceSeverity::level'
    )->getMessage()
);

it('should throw an exception when service severity level is too low', function (): void {
    $serviceSeverity = new NewServiceSeverity(
        $this->severityName,
        $this->severityAlias,
        NewServiceSeverity::MIN_LEVEL_VALUE - 1,
        $this->iconId
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::min(
        NewServiceSeverity::MIN_LEVEL_VALUE - 1,
        NewServiceSeverity::MIN_LEVEL_VALUE,
        'NewServiceSeverity::level'
    )->getMessage()
);

