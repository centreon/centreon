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

class NewDashboardPanel
{
    use DashboardPanelValidationTrait;

    private string $name;

    private string $widgetType;

    /** @var array<mixed> */
    private array $widgetSettings = [];

    private int $layoutX = 0;

    private int $layoutY = 0;

    private int $layoutWidth = 0;

    private int $layoutHeight = 0;

    private int $layoutMinWidth = 0;

    private int $layoutMinHeight = 0;

    /**
     * @param string $name
     * @param string $widgetType
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        string $name,
        string $widgetType
    ) {
        $this->setName($name);
        $this->setWidgetType($widgetType);
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

    /**
     * @param string $name
     *
     * @throws AssertionFailedException
     */
    public function setName(string $name): void
    {
        $this->name = trim($name);
        $this->ensureValidName($this->name);
    }

    /**
     * @param string $widgetType
     *
     * @throws AssertionFailedException
     */
    public function setWidgetType(string $widgetType): void
    {
        $this->widgetType = trim($widgetType);
        $this->ensureValidWidgetType($this->widgetType);
    }

    /**
     * @param array<mixed> $widgetSettings
     *
     * @throws AssertionFailedException
     */
    public function setWidgetSettings(array $widgetSettings): void
    {
        $this->widgetSettings = $widgetSettings;
        $this->ensureValidWidgetSettings($this->widgetSettings);
    }

    /**
     * @param int $layoutX
     *
     * @throws AssertionFailedException
     */
    public function setLayoutX(int $layoutX): void
    {
        $this->layoutX = $layoutX;
        $this->ensureValidSmallInteger($this->layoutX, 'layoutX');
    }

    /**
     * @param int $layoutY
     *
     * @throws AssertionFailedException
     */
    public function setLayoutY(int $layoutY): void
    {
        $this->layoutY = $layoutY;
        $this->ensureValidSmallInteger($this->layoutY, 'layoutY');
    }

    /**
     * @param int $layoutWidth
     *
     * @throws AssertionFailedException
     */
    public function setLayoutWidth(int $layoutWidth): void
    {
        $this->layoutWidth = $layoutWidth;
        $this->ensureValidSmallInteger($this->layoutWidth, 'layoutWidth');
    }

    /**
     * @param int $layoutHeight
     *
     * @throws AssertionFailedException
     */
    public function setLayoutHeight(int $layoutHeight): void
    {
        $this->layoutHeight = $layoutHeight;
        $this->ensureValidSmallInteger($this->layoutHeight, 'layoutHeight');
    }

    /**
     * @param int $layoutMinWidth
     *
     * @throws AssertionFailedException
     */
    public function setLayoutMinWidth(int $layoutMinWidth): void
    {
        $this->layoutMinWidth = $layoutMinWidth;
        $this->ensureValidSmallInteger($this->layoutMinWidth, 'layoutMinWidth');
    }

    /**
     * @param int $layoutMinHeight
     *
     * @throws AssertionFailedException
     */
    public function setLayoutMinHeight(int $layoutMinHeight): void
    {
        $this->layoutMinHeight = $layoutMinHeight;
        $this->ensureValidSmallInteger($this->layoutMinHeight, 'layoutMinHeight');
    }
}
