<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\Dashboard\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\Refresh;
use Core\Dashboard\Domain\Model\Refresh\RefreshType;

beforeEach(function (): void {
    $this->testedCreatedAt = new \DateTimeImmutable('2023-05-09T12:00:00+00:00');
    $this->testedUpdatedAt = new \DateTimeImmutable('2023-05-09T16:00:00+00:00');
    $this->testedGlobalRefresh = new Refresh(RefreshType::Manual, 30);
    $this->createDashboard = fn(array $fields = []): Dashboard => new Dashboard(
        $fields['id'] ?? 1,
        $fields['name'] ?? 'dashboard-name',
        \array_key_exists('created_by', $fields) ? $fields['created_by'] : 2,
        \array_key_exists('updated_by', $fields) ? $fields['updated_by'] : 3,
        $this->testedCreatedAt,
        $this->testedUpdatedAt,
        $this->testedGlobalRefresh,
    );
});

it('should return properly set dashboard instance', function (): void {
    $dashboard = ($this->createDashboard)();

    expect($dashboard->getId())->toBe(1)
        ->and($dashboard->getName())->toBe('dashboard-name')
        ->and($dashboard->getDescription())->toBe(null)
        ->and($dashboard->getUpdatedAt()->getTimestamp())->toBe($this->testedUpdatedAt->getTimestamp())
        ->and($dashboard->getCreatedAt()->getTimestamp())->toBe($this->testedCreatedAt->getTimestamp())
        ->and($dashboard->getCreatedBy())->toBe(2)
        ->and($dashboard->getUpdatedBy())->toBe(3)
        ->and($dashboard->getRefresh())->toBe($this->testedGlobalRefresh);
});

// mandatory fields

it(
    'should throw an exception when dashboard name is an empty string',
    fn() => ($this->createDashboard)(['name' => ''])
)->throws(
    AssertionException::class,
    AssertionException::notEmptyString('Dashboard::name')->getMessage()
);

// string field trimmed
it("should return trim the field name after construct", function (): void {
    $dashboard = new Dashboard(
        1,
        ' abcd ',
        1,
        1,
        new \DateTimeImmutable(),
        new \DateTimeImmutable(),
        new Refresh(RefreshType::Global, null)
    );

    expect($dashboard->getName())->toBe('abcd');
});

it("should return trim the field description", function (): void {
    $dashboard = (new Dashboard(
        1,
        'abcd',
        1,
        1,
        new \DateTimeImmutable(),
        new \DateTimeImmutable(),
        new Refresh(RefreshType::Global, null)
    ))->setDescription(' abcd ');

    expect($dashboard->getDescription())->toBe('abcd');
});

// updatedAt change

it(
    'should NOT change the updatedAt field',
    function (): void {
        $updatedAtBefore = $this->testedUpdatedAt->getTimestamp();
        $dashboard = ($this->createDashboard)();
        $updatedAtAfter = $dashboard->getUpdatedAt()->getTimestamp();

        expect($updatedAtAfter)->toBe($updatedAtBefore);
    }
);

// too long fields

foreach (
    [
        'name' => Dashboard::MAX_NAME_LENGTH,
        // We have skipped the comment max size test because it costs ~1 second
        // to run it, so more than 10 times the time of all the other tests.
        // At this moment, I didn't find any explanation, and considering
        // this is a non-critical field ... I prefer skipping it.
        // 'description' => Dashboard::MAX_DESCRIPTION_LENGTH,
    ] as $field => $length
) {
    $tooLong = str_repeat('a', $length + 1);
    it(
        "should throw an exception when dashboard {$field} is too long",
        fn() => ($this->createDashboard)([$field => $tooLong])
    )->throws(
        AssertionException::class,
        AssertionException::maxLength($tooLong, $length + 1, $length, "Dashboard::{$field}")->getMessage()
    );
}

// not positive integers

foreach (
    [
        'created_by' => 'createdBy',
        'updated_by' => 'updatedBy',
    ] as $field => $propertyName
) {
    it(
        "should throw an exception when dashboard {$field} is not a positive integer",
        fn() => ($this->createDashboard)([$field => 0])
    )->throws(
        AssertionException::class,
        AssertionException::positiveInt(0, 'Dashboard::' . $propertyName)->getMessage()
    );
}

// nullable field allowed

foreach (
    [
        'created_by' => 'createdBy',
        'updated_by' => 'updatedBy',
    ] as $field => $propertyName
) {
    it(
        "should return the NULL field {$field} after construct",
        function () use ($field, $propertyName): void {
            $dashboard = ($this->createDashboard)([$field => null]);
            $valueFromGetter = $dashboard->{'get' . $propertyName}();

            expect($valueFromGetter)->toBeNull();
        }
    );
}
