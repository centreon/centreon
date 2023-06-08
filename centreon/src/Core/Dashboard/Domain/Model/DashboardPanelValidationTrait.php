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

namespace Core\Dashboard\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

/**
 * This trait exists only here for DRY reasons.
 *
 * It gathers all the guard methods of common fields from {@see DashboardPanel} and {@see NewDashboardPanel} entities.
 */
trait DashboardPanelValidationTrait
{
    /**
     * @param string $name
     *
     * @throws AssertionFailedException
     */
    private function ensureValidName(string $name): void
    {
        $shortName = (new \ReflectionClass($this))->getShortName();
        Assertion::maxLength($name, DashboardPanel::MAX_NAME_LENGTH, $shortName . '::name');
    }

    /**
     * @param string $widgetType
     *
     * @throws AssertionFailedException
     */
    private function ensureValidWidgetType(string $widgetType): void
    {
        $shortName = (new \ReflectionClass($this))->getShortName();
        Assertion::maxLength($widgetType, DashboardPanel::MAX_WIDGET_TYPE_LENGTH, $shortName . '::widgetType');
        Assertion::notEmptyString($widgetType, $shortName . '::widgetType');
    }

    /**
     * @param array<mixed> $widgetSettings
     *
     * @throws AssertionFailedException
     */
    private function ensureValidWidgetSettings(array $widgetSettings): void
    {
        $shortName = (new \ReflectionClass($this))->getShortName();
        Assertion::jsonEncodable(
            $widgetSettings,
            $shortName . '::widgetType',
            DashboardPanel::MAX_WIDGET_SETTINGS_LENGTH
        );
    }

    /**
     * @param int $integer
     * @param string $propertyName
     *
     * @throws AssertionFailedException
     */
    private function ensureValidSmallInteger(int $integer, string $propertyName): void
    {
        $shortName = (new \ReflectionClass($this))->getShortName();
        Assertion::range(
            $integer,
            DashboardPanel::MIN_SMALL_INTEGER,
            DashboardPanel::MAX_SMALL_INTEGER,
            $shortName . '::' . $propertyName
        );
    }
}
