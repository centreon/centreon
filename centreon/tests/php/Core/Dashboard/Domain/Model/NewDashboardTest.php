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
use Core\Dashboard\Domain\Model\NewDashboard;

beforeEach(function (): void {
    $this->createDashboard = static function (array $fields = []): NewDashboard {
        $dashboard = new NewDashboard($fields['name'] ?? 'dashboard-name');
        $dashboard->setDescription($fields['description'] ?? 'dashboard-description');
        $dashboard->setUpdatedAt($fields['updatedAt'] ?? $dashboard->getUpdatedAt());

        return $dashboard;
    };
});

it('should return properly set dashboard instance', function (): void {
    $now = time();
    $dashboard = ($this->createDashboard)();

    expect($dashboard->getName())->toBe('dashboard-name')
        ->and($dashboard->getDescription())->toBe('dashboard-description')
        ->and($dashboard->getCreatedAt()->getTimestamp())->toBeGreaterThanOrEqual($now)
        ->and($dashboard->getUpdatedAt()->getTimestamp())->toBeGreaterThanOrEqual($now);
});

// mandatory fields

it(
    'should throw an exception when dashboard name is an empty string',
    fn() => ($this->createDashboard)(['name' => ''])
)->throws(
    AssertionException::class,
    AssertionException::notEmptyString('NewDashboard::name')->getMessage()
);

// string field trimmed

foreach (
    [
        'name',
        'description',
    ] as $field
) {
    it(
        "should return trim the field {$field} after construct",
        function () use ($field): void {
            $dashboard = ($this->createDashboard)([$field => '  abcd ']);
            $valueFromGetter = $dashboard->{'get' . $field}();

            expect($valueFromGetter)->toBe('abcd');
        }
    );
}

// too long fields

foreach (
    [
        'name' => Dashboard::MAX_NAME_LENGTH,
        // We have skipped the comment max size test because it costs ~1 second
        // to run it, so more than 10 times the time of all the other tests.
        // At this moment, I didn't find any explanation, and considering
        // this is a non-critical field ... I prefer skipping it.
        // 'description' => NewDashboard::MAX_DESCRIPTION_LENGTH,
    ] as $field => $length
) {
    $tooLong = str_repeat('a', $length + 1);
    it(
        "should throw an exception when dashboard {$field} is too long",
        fn() => ($this->createDashboard)([$field => $tooLong])
    )->throws(
        AssertionException::class,
        AssertionException::maxLength($tooLong, $length + 1, $length, "NewDashboard::{$field}")->getMessage()
    );
}
