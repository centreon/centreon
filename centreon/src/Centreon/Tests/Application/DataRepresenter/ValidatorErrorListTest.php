<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Centreon\Tests\Application\DataRepresenter;

use Centreon\Application\DataRepresenter\ValidatorErrorList;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator;

class ValidatorErrorListTest extends TestCase
{
    public function testJsonSerialize(): void
    {
        $field = 'input-field';
        $msg1 = 'error N1';
        $msg2 = 'error N2';

        // list of violations
        $errors = [
            new Validator\ConstraintViolation($msg1, null, [], null, $field, null, null, null, null),
            new Validator\ConstraintViolation($msg2, null, [], null, $field, null, null, null, null),
        ];

        // excpected result
        $expected = [
            [
                'field' => $field,
                'messages' => $msg1,
            ],
            [
                'field' => $field,
                'messages' => $msg2,
            ],
        ];

        // load data representer
        $dataRepresenter = new ValidatorErrorList(new Validator\ConstraintViolationList($errors));

        $this->assertEquals($expected, $dataRepresenter->jsonSerialize());
    }
}
