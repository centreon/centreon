<?php
/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 * ...
 */

declare(strict_types=1);

namespace Tests\Core\Dashboard\Infrastructure\API\FindSingleMetric;

use Core\Dashboard\Infrastructure\API\FindSingleMetric\FindSingleMetricInput;
use Symfony\Component\Validator\Validation;

beforeEach(function () {
    $this->validator = Validation::createValidatorBuilder()
        ->enableAttributeMapping()
        ->getValidator();
});

it('fails when hostId is missing', function () {
    $input = new FindSingleMetricInput(
        hostId: null,
        serviceId: 5,
        metricName: 'cpu'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('hostId is required');
});

it('fails when hostId is non-positive', function () {
    $input = new FindSingleMetricInput(
        hostId: 0,
        serviceId: 5,
        metricName: 'cpu'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('hostId must be a positive integer');
});

it('fails when serviceId is missing', function () {
    $input = new FindSingleMetricInput(
        hostId: 1,
        serviceId: null,
        metricName: 'cpu'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('serviceId is required');
});

it('fails when serviceId is non-positive', function () {
    $input = new FindSingleMetricInput(
        hostId: 1,
        serviceId: 0,
        metricName: 'cpu'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('serviceId must be a positive integer');
});

it('fails when metricName is missing', function () {
    $input = new FindSingleMetricInput(
        hostId: 1,
        serviceId: 5,
        metricName: null
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('metricName is required');
});

it('fails when metricName is not a string', function () {
    $input = new FindSingleMetricInput(
        hostId: 1,
        serviceId: 5,
        metricName: 123
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('metricName must be a string');
});

it('fails when metricName is empty', function () {
    $input = new FindSingleMetricInput(
        hostId: 1,
        serviceId: 5,
        metricName: ''
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(2) // One for empty string and one for not null
        ->and($errors[0]->getMessage())->toBe('metricName is required')
        ->and($errors[1]->getMessage())->toBe('metricName cannot be empty');
});

it('passes when all inputs are valid', function () {
    $input = new FindSingleMetricInput(
        hostId: 1,
        serviceId: 5,
        metricName: 'cpu_usage'
    );
    $errors = $this->validator->validate($input);
    expect($errors)->toHaveCount(0);
});
