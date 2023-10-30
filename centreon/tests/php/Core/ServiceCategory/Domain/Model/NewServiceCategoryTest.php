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
use Core\ServiceCategory\Domain\Model\NewServiceCategory;

beforeEach(function () {
    $this->categoryName = 'service-name';
    $this->categoryAlias = 'service-alias';
});

it('should return properly set service category instance', function () {
    $serviceCategory = new NewServiceCategory($this->categoryName, $this->categoryAlias);

    expect($serviceCategory->getName())->toBe($this->categoryName)
        ->and($serviceCategory->getAlias())->toBe($this->categoryAlias);
});

it('should trim the fields "name" and "alias"', function (): void {
    $serviceCategory = new NewServiceCategory(
        $nameWithSpaces = '  my-name  ',
        $aliasWithSpaces = '  my-alias  '
    );

    expect($serviceCategory->getName())->toBe(trim($nameWithSpaces))
        ->and($serviceCategory->getAlias())->toBe(trim($aliasWithSpaces));
});

it('should throw an exception when service category name is empty', function () {
    new NewServiceCategory('', $this->categoryAlias);
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::minLength('', 0, NewServiceCategory::MIN_NAME_LENGTH, 'NewServiceCategory::name')
        ->getMessage()
);

it('should throw an exception when service category name is too long', function () {
    new NewServiceCategory(str_repeat('a', NewServiceCategory::MAX_NAME_LENGTH + 1), $this->categoryAlias);
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', NewServiceCategory::MAX_NAME_LENGTH + 1),
        NewServiceCategory::MAX_NAME_LENGTH + 1,
        NewServiceCategory::MAX_NAME_LENGTH,
        'NewServiceCategory::name'
    )->getMessage()
);

it('should throw an exception when service category alias is empty', function () {
    new NewServiceCategory($this->categoryName, '');
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::minLength('', 0, NewServiceCategory::MIN_ALIAS_LENGTH, 'NewServiceCategory::alias')
        ->getMessage()
);

it('should throw an exception when service category alias is too long', function () {
    new NewServiceCategory($this->categoryName, str_repeat('a', NewServiceCategory::MAX_ALIAS_LENGTH + 1));
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', NewServiceCategory::MAX_ALIAS_LENGTH + 1),
        NewServiceCategory::MAX_ALIAS_LENGTH + 1,
        NewServiceCategory::MAX_ALIAS_LENGTH,
        'NewServiceCategory::alias'
    )->getMessage()
);
