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
use Core\Resources\Infrastructure\API\CountResources\CountResourcesRequestTransformer;
use Mockery;

beforeEach(function () {
    $this->filter = Mockery::mock(ResourceFilter::class);
    $this->contact = new Contact();
    $this->contact->setId(1);
    $this->contact->setAdmin(true);
});

it('test transform inputs to request to count resources', function () {
    $request = CountResourcesRequestTransformer::transform($this->filter, $this->contact);
    expect($request->contactId)->toBe(1)
        ->and($request->isAdmin)->toBeTrue()
        ->and($request->resourceFilter)->toBe($this->filter);
});
