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

use Core\Resources\Infrastructure\API\CountResources\CountResourcesInput;
use Symfony\Component\Validator\Validation;

beforeEach(function () {
    $this->validator = Validation::createValidatorBuilder()
        ->enableAttributeMapping()
        ->getValidator();
});

// search parameter

it('test count resources input validation with no search', function () {
    $input = new CountResourcesInput(null);
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('search parameter is required');
});

it('test count resources input validation with an empty search', function () {
    $input = new CountResourcesInput('',);
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('search parameter is required');
});

it('test count resources input validation with search with an invalid value', function () {
    $input = new CountResourcesInput('toto');
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('search parameter must be a valid JSON');
});

it('test count resources input validation with search with an invalid json', function () {
    $input = new CountResourcesInput('{$and:[]}',);
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('search parameter must be a valid JSON');
});

it('test count resources input validation with search with a valid json', function () {
    $input = new CountResourcesInput('{"$and":[]}');
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(0);
});
