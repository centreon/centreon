<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\ServiceCategory\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\ServiceCategory\Domain\Model\ServiceCategory;

beforeEach(function () {
    $this->categoryName = 'service-name';
    $this->categoryAlias = 'service-alias';
});

it('should return properly set service category instance', function () {
    $serviceCategory = new ServiceCategory(1, $this->categoryName, $this->categoryAlias);

    expect($serviceCategory->getId())->toBe(1)
        ->and($serviceCategory->getName())->toBe($this->categoryName)
        ->and($serviceCategory->getAlias())->toBe($this->categoryAlias);
});

it('should trim the fields "name" and "alias"', function (): void {
    $serviceCategory = new ServiceCategory(
        1,
        $nameWithSpaces = '  my-name  ',
        $aliasWithSpaces = '  my-alias  '
    );

    expect($serviceCategory->getName())->toBe(trim($nameWithSpaces))
        ->and($serviceCategory->getAlias())->toBe(trim($aliasWithSpaces));
});

it('should throw an exception when service category name is empty', function () {
    new ServiceCategory(1, '', $this->categoryAlias);
})->throws(
    \Assert\InvalidArgumentException::class,
        AssertionException::minLength('', 0, ServiceCategory::MIN_NAME_LENGTH, 'ServiceCategory::name')
        ->getMessage()
);

it('should throw an exception when service category name is too long', function () {
    new ServiceCategory(1, str_repeat('a', ServiceCategory::MAX_NAME_LENGTH + 1), $this->categoryAlias);
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', ServiceCategory::MAX_NAME_LENGTH + 1),
        ServiceCategory::MAX_NAME_LENGTH + 1,
        ServiceCategory::MAX_NAME_LENGTH,
        'ServiceCategory::name'
    )->getMessage()
);

it('should throw an exception when service category alias is empty', function () {
    new ServiceCategory(1, $this->categoryName, '');
})->throws(
    \Assert\InvalidArgumentException::class,
        AssertionException::minLength('', 0, ServiceCategory::MIN_ALIAS_LENGTH, 'ServiceCategory::alias')
        ->getMessage()
);

it('should throw an exception when service category alias is too long', function () {
    new ServiceCategory(1, $this->categoryName, str_repeat('a', ServiceCategory::MAX_ALIAS_LENGTH + 1));
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', ServiceCategory::MAX_ALIAS_LENGTH + 1),
        ServiceCategory::MAX_ALIAS_LENGTH + 1,
        ServiceCategory::MAX_ALIAS_LENGTH,
        'ServiceCategory::alias'
    )->getMessage()
);
