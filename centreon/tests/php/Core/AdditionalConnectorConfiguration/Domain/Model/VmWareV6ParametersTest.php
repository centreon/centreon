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

namespace Core\AdditionalConnectorConfiguration\Application\Validation;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\AdditionalConnectorConfiguration\Domain\Model\VmWareV6Parameters;
use Security\Interfaces\EncryptionInterface;

beforeEach(function (): void {
    $this->encryption = $this->createMock(EncryptionInterface::class);
    $this->parameters = [
        'port' => 4242,
        'vcenters' => [
            [
                'name' => 'my-vcenter',
                'url' => 'http://10.10.10.10/sdk',
                'username' => 'admin',
                'password' => 'pwd',
            ],
        ],
    ];
});

it('should throw an exception when the port is not valid', function (): void {
    $this->parameters['port'] = 9999999999;
    new VmWareV6Parameters($this->encryption, $this->parameters);
})->throws(AssertionException::range(9999999999, 0, 65535, 'parameters.port')->getMessage());

foreach (
    [
        'name',
        'username',
        'password',
        'url',
    ] as $field
) {
    $tooLong = str_repeat('a', VmWareV6Parameters::MAX_LENGTH + 1);
    it(
        "should throw an exception when a vcenter {$field} is too long",
        function () use ($field, $tooLong) : void {
            $this->parameters['vcenters'][0][$field] = $tooLong;
            new VmWareV6Parameters($this->encryption, $this->parameters);
        }
    )->throws(
        AssertionException::maxLength(
            $tooLong,
            VmWareV6Parameters::MAX_LENGTH + 1 ,
            VmWareV6Parameters::MAX_LENGTH,
            "parameters.vcenters[0].{$field}"
        )->getMessage()
    );
}

foreach (
    [
        'name',
        'username',
        'password',
        'url',
    ] as $field
) {
    it(
        "should throw an exception when a vcenter {$field} is too short",
        function () use ($field) : void {
            $this->parameters['vcenters'][0][$field] = '';

            new VmWareV6Parameters($this->encryption, $this->parameters);
        }
    )->throws(
        AssertionException::notEmptyString("parameters.vcenters[0].{$field}")->getMessage()
    );
}

it('should throw an exception when a vcenter URL is invalid',
    function () : void {
        $this->parameters['vcenters'][0]['url'] = 'invalid@xyz';

        new VmWareV6Parameters($this->encryption, $this->parameters);
    }
)->throws(
    AssertionException::urlOrIpOrDomain('invalid@xyz', 'parameters.vcenters[0].url')->getMessage()
);
