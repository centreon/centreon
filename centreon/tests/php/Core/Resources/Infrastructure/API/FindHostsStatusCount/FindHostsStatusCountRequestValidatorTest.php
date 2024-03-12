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

namespace Tests\Core\Resources\Infrastructure\API\FindHostsStatusCount;

use Core\Resources\Infrastructure\API\FindHostsStatusCount\FindHostsStatusCountRequestValidator;

it('should throw an InvalidArgumentException when an invalid request parameter is provided', function () {
    $validator = new FindHostsStatusCountRequestValidator();
    $validator->validateAndRetrieveRequestParameters(['invalid_request_parameters' => ['test','test']]);
})->throws((new \InvalidArgumentException('Request parameter provided not handled'))->getMessage());

it('should throw a InvalidArgumentException when the provided Json is invalid', function () {
    $validator = new FindHostsStatusCountRequestValidator();
    $validator->validateAndRetrieveRequestParameters(['hostgroup_names' => '{;}']);
})->throws((new \InvalidArgumentException(sprintf('Value provided for %s is not correctly formatted. Array expected.', 'hostgroup_names')))->getMessage());

it('should throw a InvalidArgumentException when the Request parameters are not array of strings', function () {
    $validator = new FindHostsStatusCountRequestValidator();
    $validator->validateAndRetrieveRequestParameters(['hostgroup_names' => [3,4,5]]);
})->throws((new \InvalidArgumentException(sprintf('Values provided for %s should only be strings', 'hostgroup_names')))->getMessage());