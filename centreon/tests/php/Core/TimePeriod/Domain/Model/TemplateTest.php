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

namespace Tests\Core\TimePeriod\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\TimePeriod\Domain\Model\Template;

$emptyAlias = '';

it(
    'should throw an exception if alias is empty',
    function () use ($emptyAlias): void {
        new Template(1, $emptyAlias);
    }
)->throws(
    \InvalidArgumentException::class,
    AssertionException::minLength(
        '',
        0,
        Template::MIN_ALIAS_LENGTH,
        'Template::alias'
    )->getMessage()
);

$badAlias = str_repeat('_', Template::MAX_ALIAS_LENGTH + 1);
it(
    'should throw an exception if alias is too long',
    function () use ($badAlias): void {
        new Template(1, $badAlias);
    }
)->throws(
    \InvalidArgumentException::class,
    AssertionException::maxLength(
        $badAlias,
        mb_strlen($badAlias),
        Template::MAX_ALIAS_LENGTH,
        'Template::alias'
    )->getMessage()
);

$emptyAlias = '     ';
it(
    'should throw an exception if alias consists only of space',
    function () use ($emptyAlias): void {
        new Template(1, $emptyAlias);
    }
)->throws(
    \InvalidArgumentException::class,
    AssertionException::minLength(
        '',
        0,
        Template::MIN_ALIAS_LENGTH,
        'Template::alias'
    )->getMessage()
);

it(
    'should throw an exception if id id less than of 1',
    function (): void {
        new Template(0, 'fake_value');
    }
)->throws(
    \InvalidArgumentException::class,
    AssertionException::min(
        0,
        1,
        'Template::id'
    )->getMessage()
);

it(
    'should apply trim on the alias value',
    function (): void {
        $fakeAlias = 'fake alias ';
        $template = new Template(1, $fakeAlias);
        expect(trim($fakeAlias))->toBe($template->getAlias());
    }
);
