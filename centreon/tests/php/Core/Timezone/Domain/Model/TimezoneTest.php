<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\Timezone\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Timezone\Domain\Model\Timezone;

beforeEach(function (): void {
    $this->name = 'host-name';
    $this->offset = '+05:00';
    $this->dayligthSavingTimeOffset = '+06:00';
});

it('should return properly set timezone instance', function (): void {
    $timezone = new Timezone(1, $this->name, $this->offset, $this->dayligthSavingTimeOffset);

    expect($timezone->getId())->toBe(1)
        ->and($timezone->getName())->toBe($this->name)
        ->and($timezone->getOffset())->toBe($this->offset)
        ->and($timezone->getDaylightSavingTimeOffset())->toBe($this->dayligthSavingTimeOffset);
});

it('should throw an exception when timezone name is empty', function (): void {
    new Timezone(1, '', $this->offset, $this->dayligthSavingTimeOffset);
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmptyString('Timezone::name')->getMessage()
);

it('should throw an exception when timezone offset format is not respected', function (): void {
    new Timezone(1, $this->name, 'aaa', $this->dayligthSavingTimeOffset);
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::matchRegex('aaa', '/^[-+][0-9]{2}:[0-9]{2}$/', "Timezone::offset")->getMessage()
);

it('should throw an exception when timezone daylightSavingTimeOffset format is not respected', function (): void {
    new Timezone(1, $this->name, $this->offset, 'aaa');
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::matchRegex('aaa', '/^[-+][0-9]{2}:[0-9]{2}$/', "Timezone::daylightSavingTimeOffset")->getMessage()
);
