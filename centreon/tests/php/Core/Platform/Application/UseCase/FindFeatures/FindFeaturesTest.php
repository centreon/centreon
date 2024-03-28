<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\Platform\Application\UseCase\FindFeatures;

use Core\Application\Common\UseCase\ErrorResponse;
use Core\Common\Application\FeatureFlagsInterface;
use Core\Platform\Application\UseCase\FindFeatures\FindFeatures;
use Core\Platform\Application\UseCase\FindFeatures\FindFeaturesResponse;
use Exception;

beforeEach(function (): void {
    $this->presenter = new FindFeaturesPresenterStub();
    $this->useCase = new FindFeatures(
        $this->featureFlags = $this->createMock(FeatureFlagsInterface::class),
    );
});

it(
    'should retrieve the "isCloudPlatform" correctly',
    function (bool $isCloudPlatform): void {
        $this->featureFlags->expects($this->once())->method('isCloudPlatform')->willReturn($isCloudPlatform);
        $this->featureFlags->expects($this->once())->method('getAll')->willReturn([]);
        ($this->useCase)($this->presenter);
        expect($this->presenter->data)->toBeInstanceOf(FindFeaturesResponse::class)
            ->and($this->presenter->data->isCloudPlatform)->toBe($isCloudPlatform);
    }
)->with([
    [true],
    [false],
]);

it(
    'should retrieve the "featureFlags" correctly',
    function (array $featureFlags): void {
        $this->featureFlags->expects($this->once())->method('isCloudPlatform')->willReturn(true);
        $this->featureFlags->expects($this->once())->method('getAll')->willReturn($featureFlags);
        ($this->useCase)($this->presenter);
        expect($this->presenter->data)->toBeInstanceOf(FindFeaturesResponse::class)
            ->and($this->presenter->data->featureFlags)->toBe($featureFlags);
    }
)->with([
    [['test_flag_1' => true]],
    [['test_flag_1' => true, 'test_flag_2' => false]],
]);

it(
    'should present an ErrorResponse is something fail',
    function (): void {
        $this->featureFlags->method('isCloudPlatform')->willThrowException(new Exception());
        $this->featureFlags->method('getAll')->willThrowException(new Exception());
        ($this->useCase)($this->presenter);
        expect($this->presenter->data)->toBeInstanceOf(ErrorResponse::class);
    }
);
