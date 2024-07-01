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

namespace Tests\Core\Broker\Application\UseCase\AddBrokerInputOutput;

use Core\Broker\Application\Exception\BrokerException;
use Core\Broker\Application\Repository\ReadBrokerRepositoryInterface;
use Core\Broker\Application\UseCase\AddBrokerInputOutput\BrokerInputOutputValidator;
use Core\Broker\Domain\Model\BrokerInputOutputField;

beforeEach(function (): void {
    $this->validator = new BrokerInputOutputValidator(
        $this->readBrokerRepository = $this->createMock(ReadBrokerRepositoryInterface::class),
    );

    $this->outputFields = [
        'mandatory-field' => new BrokerInputOutputField(1, 'mandatory-field', 'text', null, null, true, false, null, []),
        'optional-field' => new BrokerInputOutputField(2, 'optional-field', 'text', null, null, false, false, null, []),
        'integer-field' => new BrokerInputOutputField(3, 'integer-field', 'int', null, null, false, false, null, []),
        'password-field' => new BrokerInputOutputField(4, 'password-field', 'password', null, null, false, false, null, []),
        'select-field' => new BrokerInputOutputField(5, 'select-field', 'select', null, null, false, false, 'a', ['a', 'b', 'c']),
        'radio-field' => new BrokerInputOutputField(6, 'radio-field', 'radio', null, null, false, false, 'A', ['A', 'B', 'C']),
        'multiselect-field' => [
            'subfield' => new BrokerInputOutputField(3, 'subfield', 'multiselect', null, null, false, false, null, ['X', 'Y', 'Z']),
        ],
        'group-field' => [
            'field-A' => new BrokerInputOutputField(3, 'field-A', 'text', null, null, false, false, null, []),
            'field-B' => new BrokerInputOutputField(3, 'field-B', 'text', null, null, false, false, null, []),
        ],
    ];

    $this->outputValues = [
        'mandatory-field' => 'mandatory-test',
        'optional-field' => 'optional-test',
        'integer-field' => 12,
        'password-field' => 'password-test',
        'select-field' => 'a',
        'radio-field' => 'B',
        'multiselect-field_subfield' => ['X', 'Z'],
        'group-field' => [
            [
                'field-A' => 'value-A1',
                'field-B' => 'value B1',
            ],
            [
                'field-A' => 'value-A2',
                'field-B' => 'value-B2',
            ],
        ],
    ];
});

it('throws an exception when broker ID is invalid', function (): void {
    $this->readBrokerRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validator->brokerIsValidOrFail(1);
})->throws(
    BrokerException::class,
    BrokerException::notFound(1)->getMessage()
);

it('throws an exception when mandatory field is missing', function (): void {
    $this->validator->validateParameters($this->outputFields, []);
})->throws(
    BrokerException::class,
    BrokerException::missingParameter('mandatory-field')->getMessage()
);

it('throws an exception when mandatory field is empty', function (): void {
    $this->validator->validateParameters($this->outputFields, ['mandatory-field' => '']);
})->throws(
    BrokerException::class,
    BrokerException::missingParameter('mandatory-field')->getMessage()
);

it('throws an exception when mandatory field is null', function (): void {
    $this->validator->validateParameters($this->outputFields, ['mandatory-field' => null]);
})->throws(
    BrokerException::class,
    BrokerException::missingParameter('mandatory-field')->getMessage()
);

it('throws an exception when optional field is missing', function (): void {
    $this->validator->validateParameters(
        fields: ['optional-field' => $this->outputFields['optional-field']],
        values: []
    );
})->throws(
    BrokerException::class,
    BrokerException::missingParameter('optional-field')->getMessage()
);

it('throws an exception when select field value is not in the allowed values', function (): void {
    $this->validator->validateParameters(
        fields: ['select-field' => $this->outputFields['select-field']],
        values: ['select-field' => 'invalid-value']
    );
})->throws(
    BrokerException::class,
    BrokerException::invalidParameter('select-field', 'invalid-value')->getMessage()
);

it('throws an exception when radio field value is not in the allowed values', function (): void {
    $this->validator->validateParameters(
        fields: ['radio-field' => $this->outputFields['radio-field']],
        values: ['radio-field' => 'invalid-value']
    );
})->throws(
    BrokerException::class,
    BrokerException::invalidParameter('radio-field', 'invalid-value')->getMessage()
);

it('throws an exception when multiselect field value is not an array', function (): void {
    $this->validator->validateParameters(
        fields: ['multiselect-field' => $this->outputFields['multiselect-field']],
        values: ['multiselect-field_subfield' => 'invalid-test']
    );
})->throws(
    BrokerException::class,
    BrokerException::invalidParameterType('multiselect-field_subfield', 'invalid-test')->getMessage()
);

it('throws an exception when multiselect field value is not in the allowed values', function (): void {
    $this->validator->validateParameters(
        fields: ['multiselect-field' => $this->outputFields['multiselect-field']],
        values: ['multiselect-field_subfield' => ['invalid-test']]
    );
})->throws(
    BrokerException::class,
    BrokerException::invalidParameter('multiselect-field_subfield', 'invalid-test')->getMessage()
);

it('throws an exception when group field does not contain expected subfield', function (): void {
    $this->validator->validateParameters(
        fields: ['group-field' => $this->outputFields['group-field']],
        values: ['group-field' => [['unknownField' => 'azerty']]]
    );
})->throws(
    BrokerException::class,
    BrokerException::missingParameter('group-field[].field-A')->getMessage()
);

it('does not throw an exception when optional field is empty', function (): void {
    $this->validator->validateParameters(
        fields: ['optional-field' => $this->outputFields['optional-field']],
        values: ['optional-field' => '']
    );

    expect(true)->toBe(true);
});

it('does not throw an exception when optional field is null', function (): void {
    $this->validator->validateParameters(
        fields: ['optional-field' => $this->outputFields['optional-field']],
        values: ['optional-field' => null]
    );

    expect(true)->toBe(true);
});

it('does not throw an exception when a multiselect field has expected format', function (): void {
    $this->validator->validateParameters(
        fields: ['multiselect-field' => $this->outputFields['multiselect-field']],
        values: ['multiselect-field_subfield' => $this->outputValues['multiselect-field_subfield']]
    );

    expect(true)->toBe(true);
});

it('does not throw an exception when a group field has expected format', function (): void {
    $this->validator->validateParameters(
        fields: ['group-field' => $this->outputFields['group-field']],
        values: ['group-field' => $this->outputValues['group-field']]
    );

    expect(true)->toBe(true);
});

it('does not throw an exception when a group field is empty', function (): void {
    $this->validator->validateParameters(
        fields: ['group-field' => $this->outputFields['group-field']],
        values: ['group-field' => []]
    );

    expect(true)->toBe(true);
});
