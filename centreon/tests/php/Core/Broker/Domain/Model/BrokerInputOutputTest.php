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

namespace Tests\Core\Broker\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Broker\Domain\Model\BrokerInputOutput;
use Core\Broker\Domain\Model\Type;

beforeEach(function (): void {
    $this->name = 'my-output-test';
    $this->type = new Type(33, 'lua');
    $this->parameters = [
        'path' => 'some/path/file',
    ];
});

it('should return properly set broker output instance', function (): void {
    $output = new BrokerInputOutput(1, 'output', $this->type, $this->name, $this->parameters);

    expect($output->getId())->toBe(1)
        ->and($output->getName())->toBe($this->name)
        ->and($output->getType()->name)->toBe($this->type->name)
        ->and($output->getParameters())->toBe($this->parameters);
});

it('should throw an exception when broker output ID is invalid', function (): void {
    new BrokerInputOutput(-1, 'output', $this->type, $this->name, $this->parameters);
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::min(-1, 0, 'BrokerInputOutput::id')->getMessage()
);

it('should throw an exception when broker output name is empty', function (): void {
    new BrokerInputOutput(1, 'output', $this->type, '', $this->parameters);
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmptyString('BrokerInputOutput::name')->getMessage()
);

it('should throw an exception when broker output name is too long', function (): void {
    new BrokerInputOutput(
        1,
        'output',
        $this->type,
        str_repeat('a', BrokerInputOutput::NAME_MAX_LENGTH + 1),
        $this->parameters
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', BrokerInputOutput::NAME_MAX_LENGTH + 1),
        BrokerInputOutput::NAME_MAX_LENGTH + 1,
        BrokerInputOutput::NAME_MAX_LENGTH,
        'BrokerInputOutput::name'
    )->getMessage()
);
