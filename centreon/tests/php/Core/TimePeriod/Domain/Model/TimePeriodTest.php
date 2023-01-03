<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\TimePeriod\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\TimePeriod\Domain\Model\{
    Day, ExtraTimePeriod, Template, TimePeriod, TimeRange
};

it(
    'should throw an exception if alias is empty',
    function (): void {
        new TimePeriod(1, 'fake_name', '');
    }
)->throws(
    \InvalidArgumentException::class,
    AssertionException::minLength(
        '',
        0,
        TimePeriod::MIN_ALIAS_LENGTH,
        'TimePeriod::alias'
    )->getMessage()
);

it(
    'should throw an exception if alias consists only of space',
    function (): void {
        new TimePeriod(1, 'fake_name', '  ');
    }
)->throws(
    \InvalidArgumentException::class,
    AssertionException::minLength(
        '',
        0,
        TimePeriod::MIN_ALIAS_LENGTH,
        'TimePeriod::alias'
    )->getMessage()
);

it(
    'should throw an exception if name is empty',
    function (): void {
        new TimePeriod(1, '', 'fake_alias');
    }
)->throws(
    \InvalidArgumentException::class,
    AssertionException::minLength(
        '',
        0,
        TimePeriod::MIN_NAME_LENGTH,
        'TimePeriod::name'
    )->getMessage()
);

it(
    'Properties should be equal between constructor and getter',
    function (): void {
        $id = 1;
        $name = ' fake_name ';
        $alias = ' fake_alias ';
        $tp = new TimePeriod($id, $name, $alias);
        expect($tp->getId())->toBe($id);
        expect($tp->getName())->toBe(trim($name));
        expect($tp->getAlias())->toBe(trim($alias));

        $timeRange = new TimeRange('00:00-01:00');

        $extra = [new ExtraTimePeriod(1, 'monday 1', $timeRange)];
        $tp->setExtraTimePeriods($extra);
        expect($tp->getExtraTimePeriods())->toBe($extra);

        $templates = [new Template(1, 'fake_template')];
        $tp->setTemplates($templates);
        expect($tp->getTemplates())->toBe($templates);

        $days = [new Day(1, $timeRange)];
        $tp->setDays($days);
        expect($tp->getDays())->toBe($days);
    }
);

it(
    'should throw an exception if name consists only of space',
    function (): void {
        new TimePeriod(1, '   ', 'fake_alias');
    }
)->throws(
    \InvalidArgumentException::class,
    AssertionException::minLength(
        '',
        0,
        TimePeriod::MIN_NAME_LENGTH,
        'TimePeriod::name'
    )->getMessage()
);

it(
    'should throw an exception should if the given extra periods are not of the right type',
    function (): void {
        $tp = new TimePeriod(1, 'fake_name', 'fake_alias');
        $tp->setExtraTimePeriods([
            new \stdClass()
        ]);
    }
)->throws(
    \TypeError::class
);

it(
    'should throw an exception should if the given templates are not of the right type',
    function (): void {
        $tp = new TimePeriod(1, 'fake_name', 'fake_alias');
        $tp->setTemplates([
            new \stdClass()
        ]);
    }
)->throws(
    \TypeError::class
);

it(
    'should throw an exception should if the given days are not of the right type',
    function (): void {
        $tp = new TimePeriod(1, 'fake_name', 'fake_alias');
        $tp->setDays([
            new \stdClass()
        ]);
    }
)->throws(
    \TypeError::class
);
