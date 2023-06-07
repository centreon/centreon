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
use Core\Dashboard\Domain\Model\NewDashboardPanel;

beforeEach(function (): void {
    $this->createPanel = function (array $fields = []): NewDashboardPanel {
        $panel = new NewDashboardPanel(
            name: $fields['name'] ?? 'panel-name',
            widgetType: $fields['widgetType'] ?? 'widget-type'
        );

        $panel->setWidgetSettings($fields['widgetSettings'] ?? ['foo' => 'bar']);
        $panel->setLayoutX($fields['layoutX'] ?? 1);
        $panel->setLayoutY($fields['layoutY'] ?? 2);
        $panel->setLayoutWidth($fields['layoutWidth'] ?? 3);
        $panel->setLayoutHeight($fields['layoutHeight'] ?? 4);
        $panel->setLayoutMinWidth($fields['layoutMinWidth'] ?? 5);
        $panel->setLayoutMinHeight($fields['layoutMinHeight'] ?? 6);

        return $panel;
    };
});

it('should return properly set dashboard panel instance', function (): void {
    $panel = ($this->createPanel)();

    expect($panel->getName())->toBe('panel-name')
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
        'name',
        'widgetType',
    ] as $field
) {
    it(
        "should throw an exception when dashboard panel {$field} is an empty string",
        fn() => ($this->createPanel)([$field => ''])
    )->throws(
        AssertionException::class,
        AssertionException::notEmptyString("NewDashboardPanel::{$field}")->getMessage()
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
        AssertionException::maxLength($tooLong, $length + 1, $length, "NewDashboardPanel::{$field}")->getMessage()
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
        AssertionException::range($min - 1, $min, $max, 'NewDashboardPanel::' . $field)->getMessage()
    );
    it(
        "should throw an exception when dashboard panel {$field} is too high",
        fn() => ($this->createPanel)([$field => $max + 1])
    )->throws(
        AssertionException::class,
        AssertionException::range($max + 1, $min, $max, 'NewDashboardPanel::' . $field)->getMessage()
    );
}
