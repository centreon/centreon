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

declare(strict_types = 1);

namespace Tests\Core\Host\Domain\Model;

use Core\Common\Domain\SimpleEntity;
use Core\Common\Domain\TrimmedString;
use Core\Host\Domain\Model\SmallHost;

beforeEach(function (): void {
    $this->parameters = [
        'id' => 1,
        'name' => new TrimmedString('name'),
        'alias' => new TrimmedString('alias'),
        'ipAddress' => new TrimmedString('127.0.0.1'),
        'normalCheckInterval' => 1,
        'retryCheckInterval' => 2,
        'isActivated' => true,
        'monitoringServer' => new SimpleEntity(1, new TrimmedString('Central'), 'host'),
        'checkTimePeriod' => new SimpleEntity(1, new TrimmedString('24x7'), 'host'),
        'notificationTimePeriod' => new SimpleEntity(1, new TrimmedString('24x7'), 'host'),
        'severity' => new SimpleEntity(1, new TrimmedString('severity_name'), 'host'),
    ];
});

it('should throw an exception when the id property is not a positive number', function (): void {
    $this->parameters['id'] = 0;
    new SmallHost(...$this->parameters);
})->expectException(\Assert\AssertionFailedException::class);

it('should throw an exception when the name property is empty', function (): void {
    $this->parameters['name'] = new TrimmedString('');
    new SmallHost(...$this->parameters);
})->expectException(\Assert\AssertionFailedException::class);

it('should throw an exception when the ipAddress property is empty', function (): void {
    $this->parameters['ipAddress'] = new TrimmedString('');
    new SmallHost(...$this->parameters);
})->expectException(\Assert\AssertionFailedException::class);

it('should throw an exception when the normalCheckInterval property is not a positive number', function (): void {
    $this->parameters['normalCheckInterval'] = -1;
    new SmallHost(...$this->parameters);
})->expectException(\Assert\AssertionFailedException::class);

it('should throw an exception when the retryCheckInterval property is not a positive number greater than 1', function (): void {
    $this->parameters['retryCheckInterval'] = 0;
    new SmallHost(...$this->parameters);
})->expectException(\Assert\AssertionFailedException::class);
