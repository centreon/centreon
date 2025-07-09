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

namespace Tests\Core\Resources\Infrastructure\API\CountResources;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Monitoring\ResourceFilter;
use Core\Resources\Infrastructure\API\CountResources\CountResourcesInput;
use Core\Resources\Infrastructure\API\CountResources\CountResourcesRequestTransformer;
use Mockery;

beforeEach(function () {
    $this->filter = Mockery::mock(ResourceFilter::class);
    $this->contact = new Contact();
    $this->contact->setId(1);
});

it('test transform inputs to request to count resources as admin with pagination', function () {
    $this->contact->setAdmin(true);
    $input = new CountResourcesInput('{"$and":[]}',false, 1, 10);
    $request = CountResourcesRequestTransformer::transform($input, $this->filter, $this->contact);
    expect($request->contactId)->toBe(1)
        ->and($request->isAdmin)->toBeTrue()
        ->and($request->allPages)->toBeFalse()
        ->and($request->resourceFilter)->toBe($this->filter);
});

it('test transform inputs to request to count resources as admin without pagination', function () {
    $this->contact->setAdmin(true);
    $input = new CountResourcesInput('{"$and":[]}',true, null, null);
    $request = CountResourcesRequestTransformer::transform($input, $this->filter, $this->contact);
    expect($request->contactId)->toBe(1)
        ->and($request->isAdmin)->toBeTrue()
        ->and($request->allPages)->toBeTrue()
        ->and($request->resourceFilter)->toBe($this->filter);
});

it('test transform inputs to request to count resources as no admin with pagination', function () {
    $this->contact->setAdmin(false);
    $input = new CountResourcesInput('{"$and":[]}',false, 1, 10);
    $request = CountResourcesRequestTransformer::transform($input, $this->filter, $this->contact);
    expect($request->contactId)->toBe(1)
        ->and($request->isAdmin)->toBeFalse()
        ->and($request->allPages)->toBeFalse()
        ->and($request->resourceFilter)->toBe($this->filter);
});

it('test transform inputs to request to count resources as no admin without pagination', function () {
    $this->contact->setAdmin(false);
    $input = new CountResourcesInput('{"$and":[]}',true, null, null);
    $request = CountResourcesRequestTransformer::transform($input, $this->filter, $this->contact);
    expect($request->contactId)->toBe(1)
        ->and($request->isAdmin)->toBeFalse()
        ->and($request->allPages)->toBeTrue()
        ->and($request->resourceFilter)->toBe($this->filter);
});
