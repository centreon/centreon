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

namespace Tests\Core\Common\Domain;

use Core\Common\Domain\TrimmedString;

it(
    'should trim a string',
    fn() => expect((new TrimmedString('  Hello World !  '))->value)
        ->toBe('Hello World !')
);

it(
    'should trim a Stringable',
    fn() => expect(
        (new TrimmedString(
            new class implements \Stringable {
                public function __toString(): string
                {
                    return '  Hello World !  ';
                }
            }
        ))->value
    )->toBe('Hello World !')
);

it(
    'should implements a Stringable',
    fn() => expect((string) new TrimmedString('  Hello World !  '))
        ->toBe('Hello World !')
);
