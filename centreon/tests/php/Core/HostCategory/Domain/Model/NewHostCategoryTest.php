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
use Core\HostCategory\Domain\Model\NewHostCategory;

beforeEach(function () {
    $this->categoryName = 'host-name';
    $this->categoryAlias = 'host-alias';
});

it('should return properly set host category instance', function () {
    $hostCategory = new NewHostCategory($this->categoryName, $this->categoryAlias);

    expect($hostCategory->getName())->toBe($this->categoryName)
        ->and($hostCategory->getAlias())->toBe($this->categoryAlias);
});

it('should throw an exception when host category name is empty', function () {
    new NewHostCategory('', $this->categoryAlias);
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmpty('NewHostCategory::name')
        ->getMessage()
);

it('should throw an exception when host category name is too long', function () {
    new NewHostCategory(str_repeat('a', NewHostCategory::MAX_NAME_LENGTH + 1), $this->categoryAlias);
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', NewHostCategory::MAX_NAME_LENGTH + 1),
        NewHostCategory::MAX_NAME_LENGTH + 1,
        NewHostCategory::MAX_NAME_LENGTH,
        'NewHostCategory::name'
    )->getMessage()
);

it('should throw an exception when host category alias is empty', function () {
    new NewHostCategory($this->categoryName, '');
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmpty('NewHostCategory::alias')
        ->getMessage()
);

it('should throw an exception when host category alias is too long', function () {
    new NewHostCategory($this->categoryName, str_repeat('a', NewHostCategory::MAX_ALIAS_LENGTH + 1));
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', NewHostCategory::MAX_ALIAS_LENGTH + 1),
        NewHostCategory::MAX_ALIAS_LENGTH + 1,
        NewHostCategory::MAX_ALIAS_LENGTH,
        'NewHostCategory::alias'
    )->getMessage()
);

it('should throw an exception when host category comment is too long', function () {
    $hostCategory = new NewHostCategory($this->categoryName, $this->categoryAlias);
    $hostCategory->setComment(str_repeat('a', NewHostCategory::MAX_COMMENT_LENGTH + 1));
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', NewHostCategory::MAX_COMMENT_LENGTH + 1),
        NewHostCategory::MAX_COMMENT_LENGTH + 1,
        NewHostCategory::MAX_COMMENT_LENGTH,
        'NewHostCategory::comment'
    )->getMessage()
);
