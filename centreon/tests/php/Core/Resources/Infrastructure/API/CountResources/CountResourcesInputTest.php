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
    $input = new CountResourcesInput(null, false, 1, 10);
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('search parameter is required');
});

it('test count resources input validation with an empty search', function () {
    $input = new CountResourcesInput('',false, 1, 10);
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('search parameter is required');
});

it('test count resources input validation with search with an invalid value', function () {
    $input = new CountResourcesInput('toto', false, 1, 10);
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('search parameter must be a valid JSON');
});

it('test count resources input validation with search with an invalid json', function () {
    $input = new CountResourcesInput('{$and:[]}',false, 1, 10);
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('search parameter must be a valid JSON');
});

it('test count resources input validation with search with a valid json', function () {
    $input = new CountResourcesInput('{"$and":[]}',false, 1, 10);
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(0);
});

// all_pages parameter without limit and page

it('test count resources input validation with no all_pages', function () {
    $input = new CountResourcesInput('{"$and":[]}', null, null, null);
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('all_pages parameter is required');
});

it('test count resources input validation with an empty all_pages', function () {
    $input = new CountResourcesInput('{"$and":[]}','', null, null);
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('all_pages parameter must be a boolean');
});

it('test count resources input validation with all_pages with an invalid value', function () {
    $input = new CountResourcesInput('{"$and":[]}', 'toto', null, null);
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('all_pages parameter must be a boolean');
});

it('test count resources input validation with a correct all_pages', function () {
    $input = new CountResourcesInput('{"$and":[]}',true, null, null);
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(0);
});

// all_pages parameter with limit and page

it('test count resources input validation for pagination with no page', function () {
    $input = new CountResourcesInput('{"$and":[]}',false, null, 10);
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('page parameter is required when all_pages is false');
});

it('test count resources input validation for pagination with an empty page', function () {
    $input = new CountResourcesInput('{"$and":[]}',false, '', 10);
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('page parameter is required when all_pages is false');
});

it('test count resources input validation for pagination with page with invalid type', function () {
    $input = new CountResourcesInput('{"$and":[]}',false, 'toto', 10);
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('page parameter must be an integer');
});

it('test count resources input validation for pagination with page lower than 1', function () {
    $input = new CountResourcesInput('{"$and":[]}',false, 0, 10);
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('page parameter must be greater than 1');
});

it('test count resources input validation for pagination with no limit', function () {
    $input = new CountResourcesInput('{"$and":[]}',false, 1, null);
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('limit parameter is required when all_pages is false');
});

it('test count resources input validation for pagination with an empty limit', function () {
    $input = new CountResourcesInput('{"$and":[]}',false, 1, '');
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(2)
        ->and($errors[0]->getMessage())->toBe('limit parameter is required when all_pages is false')
        ->and($errors[1]->getMessage())->toBe('limit parameter must be an integer');
});

it('test count resources input validation for pagination with limit with invalid type', function () {
    $input = new CountResourcesInput('{"$and":[]}',false, 1, 'toto');
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('limit parameter must be an integer');
});

it('test count resources input validation for pagination with success', function () {
    $input = new CountResourcesInput('{"$and":[]}',false, 1, 10);
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(0);
});
