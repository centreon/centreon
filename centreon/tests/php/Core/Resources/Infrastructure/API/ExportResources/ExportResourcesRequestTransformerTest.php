<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\Resources\Infrastructure\API\ExportResources;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Monitoring\ResourceFilter;
use Core\Resources\Infrastructure\API\ExportResources\ExportResourcesInput;
use Core\Resources\Infrastructure\API\ExportResources\ExportResourcesRequestTransformer;
use Mockery;

beforeEach(function () {
    $this->filter = Mockery::mock(ResourceFilter::class);
    $this->contact = Mockery::mock(ContactInterface::class);
});

it('test transform inputs to request to export resources with pagination', function () {
    $input = new ExportResourcesInput(
        'csv',
        '0',
        ['status'],
        null,
        '1',
        '100',
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}',
        null
    );
    $request = ExportResourcesRequestTransformer::transform($input, $this->filter, $this->contact);
    expect($request->exportedFormat)->toBe('csv')
        ->and($request->allPages)->toBeFalse()
        ->and($request->maxResults)->toBe(0)
        ->and($request->columns)->toBe(['status'])
        ->and($request->resourceFilter)->toBe($this->filter)
        ->and($request->contact)->toBe($this->contact);
});

it('test transform inputs to request to export resources without pagination', function () {
    $input = new ExportResourcesInput(
        'csv',
        '1',
        ['status'],
        '999',
        null,
        null,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}',
        null
    );
    $request = ExportResourcesRequestTransformer::transform($input, $this->filter, $this->contact);
    expect($request->exportedFormat)->toBe('csv')
        ->and($request->allPages)->toBeTrue()
        ->and($request->maxResults)->toBe(999)
        ->and($request->columns)->toBe(['status'])
        ->and($request->resourceFilter)->toBe($this->filter)
        ->and($request->contact)->toBe($this->contact);
});
