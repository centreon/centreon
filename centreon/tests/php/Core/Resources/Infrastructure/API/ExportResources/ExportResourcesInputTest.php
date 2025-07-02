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

use Core\Resources\Infrastructure\API\ExportResources\ExportResourcesInput;
use Symfony\Component\Validator\Validation;

beforeEach(function (): void {
    $this->validator = Validation::createValidatorBuilder()
        ->enableAttributeMapping()
        ->getValidator();
});

// format parameter

it('test export resources input validation with no format', function (): void {
    $input = new ExportResourcesInput(
        null,
        false,
        null,
        null,
        1,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('format parameter is required');
});

it('test export resources input validation with an empty format', function (): void {
    $input = new ExportResourcesInput(
        '',
        false,
        null,
        null,
        1,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(2)
        ->and($errors[0]->getMessage())->toBe('format parameter is required')
        ->and($errors[1]->getMessage())->toBe('format parameter must be one of the following: "csv"');
});

it('test export resources input validation with an invalid type for format', function (): void {
    $input = new ExportResourcesInput(
        0,
        false,
        null,
        null,
        1,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('format parameter must be one of the following: "csv"');
});

it('test export resources input validation with an invalid value for format', function (): void {
    $input = new ExportResourcesInput(
        'pdf',
        false,
        null,
        null,
        1,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('format parameter must be one of the following: "csv"');
});

it('test export resources input validation with a valid format', function (): void {
    $input = new ExportResourcesInput(
        'csv',
        false,
        null,
        null,
        1,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(0);
});

// sort_by parameter

it('test export resources input validation with no sort_by', function (): void {
    $input = new ExportResourcesInput('csv', false, null, null, 1, 100, null, '{"$and":[]}');
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('sort_by parameter is required');
});

it('test export resources input validation with an empty sort_by', function (): void {
    $input = new ExportResourcesInput('csv', false, null, null, 1, 100, '', '{"$and":[]}');
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('sort_by parameter is required');
});

it('test export resources input validation with an invalid value for sort_by', function (): void {
    $input = new ExportResourcesInput('csv', false, null, null, 1, 100, 'toto', '{"$and":[]}');
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('sort_by parameter must be a valid JSON');
});

it('test export resources input validation with sort_by with an invalid json', function (): void {
    $input = new ExportResourcesInput(
        'csv',
        false,
        null,
        null,
        1,
        100,
        '{status_severity_code:"desc",last_status_change:"desc"}',
        '{"$and":[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('sort_by parameter must be a valid JSON');
});

it('test export resources input validation with a valid json for sort_by', function (): void {
    $input = new ExportResourcesInput(
        'csv',
        false,
        null,
        null,
        1,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(0);
});

// search parameter

it('test export resources input validation with no search', function (): void {
    $input = new ExportResourcesInput(
        'csv',
        false,
        null,
        null,
        1,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        null
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('search parameter is required');
});

it('test export resources input validation with an empty search', function (): void {
    $input = new ExportResourcesInput(
        'csv',
        false,
        null,
        null,
        1,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        ''
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('search parameter is required');
});

it('test export resources input validation with search with an invalid value', function (): void {
    $input = new ExportResourcesInput(
        'csv',
        false,
        null,
        null,
        1,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        'toto'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('search parameter must be a valid JSON');
});

it('test export resources input validation with search with an invalid json', function (): void {
    $input = new ExportResourcesInput(
        'csv',
        false,
        null,
        null,
        1,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{$and:[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('search parameter must be a valid JSON');
});

it('test export resources input validation with search with a valid json', function (): void {
    $input = new ExportResourcesInput(
        'csv',
        false,
        null,
        null,
        1,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(0);
});

// all_pages parameter

it('test export resources input validation with no all_pages', function (): void {
    $input = new ExportResourcesInput(
        'csv',
        null,
        null,
        null,
        1,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('all_pages parameter is required');
});

it('test export resources input validation with an empty all_pages', function (): void {
    $input = new ExportResourcesInput(
        'csv',
        '',
        null,
        null,
        1,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('all_pages parameter must be a boolean');
});

it('test export resources input validation with an invalid type for all_pages', function (): void {
    $input = new ExportResourcesInput(
        'csv',
        'toto',
        null,
        null,
        1,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('all_pages parameter must be a boolean');
});

it('test export resources input validation with all_pages equals to false without page and limit', function (): void {
    $input = new ExportResourcesInput(
        'csv',
        false,
        null,
        null,
        null,
        null,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(2)
        ->and($errors[0]->getMessage())->toBe('page parameter is required when all_pages is false')
        ->and($errors[1]->getMessage())->toBe('limit parameter is required when all_pages is false');
});

it('test export resources input validation with all_pages equals to false with page and without limit', function (): void {
    $input = new ExportResourcesInput(
        'csv',
        false,
        null,
        null,
        1,
        null,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('limit parameter is required when all_pages is false');
});

it('test export resources input validation with all_pages equals to false with limit and without page', function (): void {
    $input = new ExportResourcesInput(
        'csv',
        false,
        null,
        null,
        null,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('page parameter is required when all_pages is false');
});

it(
    'test export resources input validation with all_pages equals to false with limit and page with an invalid format for limit',
    function (): void {
        $input = new ExportResourcesInput(
            'csv',
            false,
            null,
            null,
            1,
            'toto',
            '{"status_severity_code":"desc","last_status_change":"desc"}',
            '{"$and":[]}'
        );
        $errors = $this->validator->validate($input);
        expect($errors)->toHaveCount(1)
            ->and($errors[0]->getMessage())->toBe('limit parameter must be an integer');
    }
);

it(
    'test export resources input validation with all_pages equals to false with limit and page with an invalid format for page',
    function (): void {
        $input = new ExportResourcesInput(
            'csv',
            false,
            null,
            null,
            'toto',
            100,
            '{"status_severity_code":"desc","last_status_change":"desc"}',
            '{"$and":[]}'
        );
        $errors = $this->validator->validate($input);
        expect($errors)->toHaveCount(1)
            ->and($errors[0]->getMessage())->toBe('page parameter must be an integer');
    }
);

it(
    'test export resources input validation with all_pages equals to false with limit and page = 0',
    function (): void {
        $input = new ExportResourcesInput(
            'csv',
            false,
            null,
            null,
            0,
            100,
            '{"status_severity_code":"desc","last_status_change":"desc"}',
            '{"$and":[]}'
        );
        $errors = $this->validator->validate($input);
        expect($errors)->toHaveCount(1)
            ->and($errors[0]->getMessage())->toBe('page parameter must be greater than 1');
    }
);

it('test export resources input validation with all_pages equals to false with valid limit and page', function (): void {
    $input = new ExportResourcesInput(
        'csv',
        false,
        null,
        null,
        1,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(0);
});

it(
    'test export resources input validation with all_pages equals to true without pagination and without max_lines',
    function (): void {
        $input = new ExportResourcesInput(
            'csv',
            true,
            null,
            null,
            null,
            null,
            '{"status_severity_code":"desc","last_status_change":"desc"}',
            '{"$and":[]}'
        );
        $errors = $this->validator->validate($input);
        expect($errors)->toHaveCount(1)
            ->and($errors[0]->getMessage())->toBe('max_lines parameter is required when all_pages is true');
    }
);

it(
    'test export resources input validation with all_pages equals to true without pagination and with an invalid max_lines',
    function (): void {
        $input = new ExportResourcesInput(
            'csv',
            true,
            null,
            'toto',
            null,
            null,
            '{"status_severity_code":"desc","last_status_change":"desc"}',
            '{"$and":[]}'
        );
        $errors = $this->validator->validate($input);
        expect($errors)->toHaveCount(1)
            ->and($errors[0]->getMessage())->toBe('max_lines parameter must be an integer');
    }
);

it(
    'test export resources input validation with all_pages equals to true without pagination and with a max_lines greather than 10000',
    function (): void {
        $input = new ExportResourcesInput(
            'csv',
            true,
            null,
            12000,
            null,
            null,
            '{"status_severity_code":"desc","last_status_change":"desc"}',
            '{"$and":[]}'
        );
        $errors = $this->validator->validate($input);
        expect($errors)->toHaveCount(1)
            ->and($errors[0]->getMessage())->toBe('max_lines parameter must be between 1 and 10000');
    }
);

it(
    'test export resources input validation with all_pages equals to true without pagination and with a valid max_lines',
    function (): void {
        $input = new ExportResourcesInput(
            'csv',
            true,
            null,
            100,
            null,
            null,
            '{"status_severity_code":"desc","last_status_change":"desc"}',
            '{"$and":[]}'
        );
        $errors = $this->validator->validate($input);
        expect($errors)->toHaveCount(0);
    }
);

it(
    'test export resources input validation with all_pages equals to true with pagination (should be ignored) and with a valid max_lines',
    function (): void {
        $input = new ExportResourcesInput(
            'csv',
            true,
            null,
            100,
            1,
            100,
            '{"status_severity_code":"desc","last_status_change":"desc"}',
            '{"$and":[]}'
        );
        $errors = $this->validator->validate($input);
        expect($errors)->toHaveCount(0);
    }
);

// columns parameter

it('test export resources input validation with no columns', function (): void {
    $input = new ExportResourcesInput(
        'csv',
        false,
        null,
        null,
        1,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(0);
});

it('test export resources input validation with an empty columns', function (): void {
    $input = new ExportResourcesInput(
        'csv',
        false,
        '',
        null,
        1,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(2)
        ->and($errors[0]->getMessage())->toBe('columns parameter must be an array')
        ->and($errors[1]->getMessage())->toBe('This value should be of type iterable.');
});

it('test export resources input validation with an invalid columns', function (): void {
    $input = new ExportResourcesInput(
        'csv',
        false,
        'toto',
        null,
        1,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(2)
        ->and($errors[0]->getMessage())->toBe('columns parameter must be an array')
        ->and($errors[1]->getMessage())->toBe('This value should be of type iterable.');
});

it('test export resources input validation with columns with an empty value', function (): void {
    $input = new ExportResourcesInput(
        'csv',
        false,
        [''],
        null,
        1,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(2)
        ->and($errors[0]->getMessage())->toBe('columns parameter value must not be empty')
        ->and($errors[1]->getMessage())->toBe(
            'columns parameter must be one of the following: "resource", "status", "parent_resource", "duration", "last_check", "information", "tries", "severity", "notes_url", "action_url", "state", "alias", "parent_alias", "fqdn", "monitoring_server_name", "notification", "checks"'
        );
});

it('test export resources input validation with columns with an invalid value', function (): void {
    $input = new ExportResourcesInput(
        'csv',
        false,
        ['toto'],
        null,
        1,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe(
            'columns parameter must be one of the following: "resource", "status", "parent_resource", "duration", "last_check", "information", "tries", "severity", "notes_url", "action_url", "state", "alias", "parent_alias", "fqdn", "monitoring_server_name", "notification", "checks"'
        );
});

it('test export resources input validation with columns with a valid value', function (): void {
    $input = new ExportResourcesInput(
        'csv',
        false,
        ['status'],
        null,
        1,
        100,
        '{"status_severity_code":"desc","last_status_change":"desc"}',
        '{"$and":[]}'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(0);
});
