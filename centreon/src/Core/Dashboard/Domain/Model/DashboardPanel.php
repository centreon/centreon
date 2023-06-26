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
use Core\Dashboard\Domain\Model\Validation\DashboardPanelValidationTrait;

class DashboardPanel
{
    use DashboardPanelValidationTrait;
    public const MAX_NAME_LENGTH = 200;
    public const MAX_WIDGET_TYPE_LENGTH = 200;
    public const MAX_WIDGET_SETTINGS_LENGTH = 65535;
    public const MIN_SMALL_INTEGER = -32768;
    public const MAX_SMALL_INTEGER = 32767;

    private readonly string $name;

    private readonly string $widgetType;

    /**
     * @param int $id
     * @param string $name
     * @param string $widgetType
     * @param array<mixed> $widgetSettings
     * @param int $layoutX
     * @param int $layoutY
     * @param int $layoutWidth
     * @param int $layoutHeight
     * @param int $layoutMinWidth
     * @param int $layoutMinHeight
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $id,
        string $name,
        string $widgetType,
        private readonly array $widgetSettings,
        private readonly int $layoutX,
        private readonly int $layoutY,
        private readonly int $layoutWidth,
        private readonly int $layoutHeight,
        private readonly int $layoutMinWidth,
        private readonly int $layoutMinHeight,
    ) {
        $this->name = trim($name);
        $this->widgetType = trim($widgetType);

        $this->ensureValidName($this->name);
        $this->ensureValidWidgetType($this->widgetType);
        $this->ensureValidWidgetSettings($this->widgetSettings);
        $this->ensureValidSmallInteger($this->layoutX, 'layoutX');
        $this->ensureValidSmallInteger($this->layoutY, 'layoutY');
        $this->ensureValidSmallInteger($this->layoutWidth, 'layoutWidth');
        $this->ensureValidSmallInteger($this->layoutHeight, 'layoutHeight');
        $this->ensureValidSmallInteger($this->layoutMinWidth, 'layoutMinWidth');
        $this->ensureValidSmallInteger($this->layoutMinHeight, 'layoutMinHeight');
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getWidgetType(): string
    {
        return $this->widgetType;
    }

    /**
     * @return array<mixed>
     */
    public function getWidgetSettings(): array
    {
        return $this->widgetSettings;
    }

    public function getLayoutX(): int
    {
        return $this->layoutX;
    }

    public function getLayoutY(): int
    {
        return $this->layoutY;
    }

    public function getLayoutWidth(): int
    {
        return $this->layoutWidth;
    }

    public function getLayoutHeight(): int
    {
        return $this->layoutHeight;
    }

    public function getLayoutMinWidth(): int
    {
        return $this->layoutMinWidth;
    }

    public function getLayoutMinHeight(): int
    {
        return $this->layoutMinHeight;
    }
}
