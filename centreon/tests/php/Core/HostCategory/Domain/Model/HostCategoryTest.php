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

namespace Tests\Core\HostCategory\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\HostCategory\Domain\Model\HostCategory;

beforeEach(function () {
    $this->categoryName = 'host-name';
    $this->categoryAlias = 'host-alias';
});

it('should return properly set host category instance', function () {
    $hostCategory = new HostCategory(1, $this->categoryName, $this->categoryAlias);

    expect($hostCategory->getId())->toBe(1)
        ->and($hostCategory->getName())->toBe($this->categoryName)
        ->and($hostCategory->getAlias())->toBe($this->categoryAlias);
});

it('should throw an exception when host category name is empty', function () {
    new HostCategory(1, '', $this->categoryAlias);
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmpty('HostCategory::name')
        ->getMessage()
);

it('should throw an exception when host category name is too long', function () {
    new HostCategory(1, str_repeat('a', HostCategory::MAX_NAME_LENGTH + 1), $this->categoryAlias);
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', HostCategory::MAX_NAME_LENGTH + 1),
        HostCategory::MAX_NAME_LENGTH + 1,
        HostCategory::MAX_NAME_LENGTH,
        'HostCategory::name'
    )->getMessage()
);

it('should throw an exception when host category alias is empty', function () {
    new HostCategory(1, $this->categoryName, '');
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmpty('HostCategory::alias')
        ->getMessage()
);

it('should throw an exception when host category alias is too long', function () {
    new HostCategory(1, $this->categoryName, str_repeat('a', HostCategory::MAX_ALIAS_LENGTH + 1));
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', HostCategory::MAX_ALIAS_LENGTH + 1),
        HostCategory::MAX_ALIAS_LENGTH + 1,
        HostCategory::MAX_ALIAS_LENGTH,
        'HostCategory::alias'
    )->getMessage()
);

it('should throw an exception when host category comment is too long', function () {
    $hostCategory = new HostCategory(1, $this->categoryName, $this->categoryAlias);
    $hostCategory->setComment(str_repeat('a', HostCategory::MAX_COMMENT_LENGTH + 1));
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', HostCategory::MAX_COMMENT_LENGTH + 1),
        HostCategory::MAX_COMMENT_LENGTH + 1,
        HostCategory::MAX_COMMENT_LENGTH,
        'HostCategory::comment'
    )->getMessage()
);
