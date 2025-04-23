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
use Core\AdditionalConnectorConfiguration\Application\Exception\AccException;
use Core\AdditionalConnectorConfiguration\Application\UseCase\AddAcc\AddAccRequest;

beforeEach(function (): void {
    $this->validator = new VmWareV6DataValidator();

    $this->request = new AddAccRequest();
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

it('should throw an exception when the vcenters have duplicate names', function (): void {
    $this->request->parameters['vcenters'][] = $this->request->parameters['vcenters'][0];

    $this->validator->validateParametersOrFail($this->request);
})->throws(AccException::duplicatesNotAllowed('parameters.vcenters[].name')->getMessage());
