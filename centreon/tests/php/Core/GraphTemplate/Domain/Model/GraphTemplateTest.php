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

namespace Tests\Core\GraphTemplate\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\GraphTemplate\Domain\Model\GraphTemplate;

it('should return properly set instance', function (): void {
    $graphTemplate = new GraphTemplate(
        id: 1,
        name: 'graph template name',
        verticalAxisLabel: 'vertical axis label',
        width: 150,
        height: 250,
        base: 1000,
        gridLowerLimit: 0,
        gridUpperLimit: 115,
        isUpperLimitSizedToMax: false,
        isGraphScaled: true,
        isDefaultCentreonTemplate: true,
    );

    expect($graphTemplate->getId())->toBe(1)
        ->and($graphTemplate->getName())->toBe('graph template name')
        ->and($graphTemplate->getVerticalAxisLabel())->toBe('vertical axis label')
        ->and($graphTemplate->getWidth())->toBe(150)
        ->and($graphTemplate->getHeight())->toBe(250)
        ->and($graphTemplate->getBase())->toBe(1000)
        ->and($graphTemplate->getGridLowerLimit())->toBe(0.0)
        ->and($graphTemplate->getGridUpperLimit())->toBe(115.0)
        ->and($graphTemplate->isUpperLimitSizedToMax())->toBe(false)
        ->and($graphTemplate->isGraphScaled())->toBe(true)
        ->and($graphTemplate->isDefaultCentreonTemplate())->toBe(true);
});

it('should return set upperLimit to null when isUpperLimitSizedToMax is set to true', function (): void {
    $graphTemplate = new GraphTemplate(
        id: 1,
        name: 'graph template name',
        verticalAxisLabel: 'vertical axis label',
        width: 150,
        height: 250,
        gridUpperLimit: 115,
        isUpperLimitSizedToMax: true,
    );

    expect($graphTemplate->getId())->toBe(1)
        ->and($graphTemplate->getGridUpperLimit())->toBe(null)
        ->and($graphTemplate->isUpperLimitSizedToMax())->toBe(true);
});

it('should throw an exception when ID is < 0', function (): void {
    new GraphTemplate(
        id: 0,
        name: 'graph template name',
        verticalAxisLabel: 'vertical axis label',
        width: 150,
        height: 250,
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::positiveInt(0, 'GraphTemplate::id')->getMessage()
);

it('should throw an exception when name is empty', function (): void {
    new GraphTemplate(
        id: 1,
        name: '',
        verticalAxisLabel: 'vertical axis label',
        width: 150,
        height: 250,
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmptyString('GraphTemplate::name')->getMessage()
);

it('should throw an exception when name is too long', function (): void {
    new GraphTemplate(
        id: 1,
        name: str_repeat('a', GraphTemplate::NAME_MAX_LENGTH + 1),
        verticalAxisLabel: 'vertical axis label',
        width: 150,
        height: 250,
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', GraphTemplate::NAME_MAX_LENGTH + 1),
        GraphTemplate::NAME_MAX_LENGTH + 1,
        GraphTemplate::NAME_MAX_LENGTH,
        'GraphTemplate::name'
    )->getMessage()
);

it('should throw an exception when vertical axis label is empty', function (): void {
    new GraphTemplate(
        id: 1,
        name: 'graph template name',
        verticalAxisLabel: '',
        width: 150,
        height: 250,
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmptyString('GraphTemplate::verticalAxisLabel')->getMessage()
);

it('should throw an exception when vertical axis label is too long', function (): void {
    new GraphTemplate(
        id: 1,
        name: 'graph template name',
        verticalAxisLabel: str_repeat('a', GraphTemplate::LABEL_MAX_LENGTH + 1),
        width: 150,
        height: 250,
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', GraphTemplate::LABEL_MAX_LENGTH + 1),
        GraphTemplate::LABEL_MAX_LENGTH + 1,
        GraphTemplate::LABEL_MAX_LENGTH,
        'GraphTemplate::verticalAxisLabel'
    )->getMessage()
);

it('should throw an exception when base is different from 1000 or 1024', function (): void {
    new GraphTemplate(
        id: 1,
        name: 'graph template name',
        verticalAxisLabel: 'vertical axis label',
        width: 150,
        height: 250,
        base: 9999,
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::inArray(9999, [1000, 1024], 'GraphTemplate::base')->getMessage()
);

