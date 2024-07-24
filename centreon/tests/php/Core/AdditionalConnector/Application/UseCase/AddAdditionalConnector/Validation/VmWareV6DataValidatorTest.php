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

namespace Core\AdditionalConnector\Application\UseCase\AddAdditionalConnector\Validation;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\AdditionalConnector\Application\Exception\AdditionalConnectorException;
use Core\AdditionalConnector\Application\UseCase\AddAdditionalConnector\AddAdditionalConnectorRequest;

beforeEach(function (): void {
    $this->validator = new VmWareV6DataValidator();

    $this->request = new AddAdditionalConnectorRequest();
    $this->request->name = 'my-ACC';
    $this->request->type = 'vmware_v6';
    $this->request->description = null;
    $this->request->pollers = [1];
    $this->request->parameters = [
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

it('should throw an exception when the vcenters have duplicated names', function (): void {
    $this->request->parameters['vcenters'][] = $this->request->parameters['vcenters'][0];

    $this->validator->validateParametersOrFail($this->request);
})->throws(AdditionalConnectorException::duplicatesNotAllowed('parameters.vcenters[].name')->getMessage());

it('should throw an exception when the port is not valid', function (): void {
    $this->request->parameters['port'] = 9999999999;

    $this->validator->validateParametersOrFail($this->request);
})->throws(AssertionException::range(9999999999, 0, 65535, 'parameters.port')->getMessage());

foreach (
    [
        'name',
        'username',
        'password',
        'url',
    ] as $field
) {
    $tooLong = str_repeat('a', VmWareV6DataValidator::MAX_LENGTH + 1);
    it(
        "should throw an exception when a vcenter {$field} is too long",
        function () use ($field, $tooLong) : void {
            $this->request->parameters['vcenters'][0][$field] = $tooLong;

            $this->validator->validateParametersOrFail($this->request);
        }
    )->throws(
        AssertionException::maxLength(
            $tooLong,
            VmWareV6DataValidator::MAX_LENGTH + 1 ,
            VmWareV6DataValidator::MAX_LENGTH,
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
            $this->request->parameters['vcenters'][0][$field] = '';

            $this->validator->validateParametersOrFail($this->request);
        }
    )->throws(
        AssertionException::notEmptyString("parameters.vcenters[0].{$field}")->getMessage()
    );
}

it('should throw an exception when a vcenter url is invalid',
    function () : void {
        $this->request->parameters['vcenters'][0]['url'] = 'invalid@xyz';

        $this->validator->validateParametersOrFail($this->request);
    }
)->throws(
    AssertionException::urlOrIpOrDomain('invalid@xyz', 'parameters.vcenters[0].url')->getMessage()
);
