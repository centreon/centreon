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
use Core\Dashboard\Domain\Model\DashboardPanel;

beforeEach(function (): void {
    $this->createPanel = fn(array $fields = []): DashboardPanel => new DashboardPanel(
        id: $fields['id'] ?? 123,
        name: $fields['name'] ?? 'panel-name',
        widgetType: $fields['widgetType'] ?? 'widget-type',
        widgetSettings: $fields['widgetSettings'] ?? ['foo' => 'bar'],
        layoutX: $fields['layoutX'] ?? 1,
        layoutY: $fields['layoutY'] ?? 2,
        layoutWidth: $fields['layoutWidth'] ?? 3,
        layoutHeight: $fields['layoutHeight'] ?? 4,
        layoutMinWidth: $fields['layoutMinWidth'] ?? 5,
        layoutMinHeight: $fields['layoutMinHeight'] ?? 6,
    );
});

it('should return properly set dashboard panel instance', function (): void {
    $panel = ($this->createPanel)();

    expect($panel->getId())->toBe(123)
        ->and($panel->getName())->toBe('panel-name')
        ->and($panel->getWidgetType())->toBe('widget-type')
        ->and($panel->getWidgetSettings())->toBe(['foo' => 'bar'])
        ->and($panel->getLayoutX())->toBe(1)
        ->and($panel->getLayoutY())->toBe(2)
        ->and($panel->getLayoutWidth())->toBe(3)
        ->and($panel->getLayoutHeight())->toBe(4)
        ->and($panel->getLayoutMinWidth())->toBe(5)
        ->and($panel->getLayoutMinHeight())->toBe(6);
});

// mandatory fields

foreach (
    [
        'widgetType',
    ] as $field
) {
    it(
        "should throw an exception when dashboard panel {$field} is an empty string",
        fn() => ($this->createPanel)([$field => ''])
    )->throws(
        AssertionException::class,
        AssertionException::notEmptyString("DashboardPanel::{$field}")->getMessage()
    );
}

// string field trimmed

foreach (
    [
        'name',
        'widgetType',
    ] as $field
) {
    it(
        "should return trim the field {$field} after construct",
        function () use ($field): void {
            $dashboard = ($this->createPanel)([$field => '  abcd ']);
            $valueFromGetter = $dashboard->{'get' . $field}();

            expect($valueFromGetter)->toBe('abcd');
        }
    );
}

// too long fields

foreach (
    [
        'name' => DashboardPanel::MAX_NAME_LENGTH,
        'widgetType' => DashboardPanel::MAX_WIDGET_TYPE_LENGTH,
    ] as $field => $length
) {
    $tooLong = str_repeat('a', $length + 1);
    it(
        "should throw an exception when dashboard panel {$field} is too long",
        fn() => ($this->createPanel)([$field => $tooLong])
    )->throws(
        AssertionException::class,
        AssertionException::maxLength($tooLong, $length + 1, $length, "DashboardPanel::{$field}")->getMessage()
    );
}

// not in range integers

foreach (
    [
        'layoutX' => [DashboardPanel::MIN_SMALL_INTEGER, DashboardPanel::MAX_SMALL_INTEGER],
        'layoutY' => [DashboardPanel::MIN_SMALL_INTEGER, DashboardPanel::MAX_SMALL_INTEGER],
        'layoutWidth' => [DashboardPanel::MIN_SMALL_INTEGER, DashboardPanel::MAX_SMALL_INTEGER],
        'layoutHeight' => [DashboardPanel::MIN_SMALL_INTEGER, DashboardPanel::MAX_SMALL_INTEGER],
        'layoutMinWidth' => [DashboardPanel::MIN_SMALL_INTEGER, DashboardPanel::MAX_SMALL_INTEGER],
        'layoutMinHeight' => [DashboardPanel::MIN_SMALL_INTEGER, DashboardPanel::MAX_SMALL_INTEGER],
    ] as $field => [$min, $max]
) {
    it(
        "should throw an exception when dashboard panel {$field} is too low",
        fn() => ($this->createPanel)([$field => $min - 1])
    )->throws(
        AssertionException::class,
        AssertionException::range($min - 1, $min, $max, 'DashboardPanel::' . $field)->getMessage()
    );
    it(
        "should throw an exception when dashboard panel {$field} is too high",
        fn() => ($this->createPanel)([$field => $max + 1])
    )->throws(
        AssertionException::class,
        AssertionException::range($max + 1, $min, $max, 'DashboardPanel::' . $field)->getMessage()
    );
}
