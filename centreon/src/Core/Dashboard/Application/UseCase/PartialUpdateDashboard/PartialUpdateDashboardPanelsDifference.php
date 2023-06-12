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

namespace Core\Dashboard\Application\UseCase\PartialUpdateDashboard;

use Assert\AssertionFailedException;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\Request\PanelRequestDto;
use Core\Dashboard\Domain\Model\DashboardPanel;
use Core\Dashboard\Domain\Model\NewDashboardPanel;

/**
 * The goal of this class is to know how to make the different repository calls depending on update data.
 */
final class PartialUpdateDashboardPanelsDifference
{
    /** @var array<NewDashboardPanel> */
    private ?array $panelsToCreate = null;

    /** @var array<DashboardPanel> */
    private ?array $panelsToUpdate = null;

    /** @var array<int> */
    private ?array $panelIdsToDelete = null;

    /**
     * @param array<int> $panelIdsFromRepository
     * @param array<PanelRequestDto> $panelsFromRequest
     *
     * @throws DashboardException
     */
    public function __construct(
        private readonly array $panelIdsFromRepository,
        private readonly array $panelsFromRequest
    ) {
        foreach ($this->panelsFromRequest as $dtoPanel) {
            if ($dtoPanel->id && ! \in_array($dtoPanel->id, $this->panelIdsFromRepository, true)) {
                throw DashboardException::errorTryingToUpdateAPanelWhichDoesNotBelongsToTheDashboard();
            }
        }
    }

    /**
     * @throws AssertionFailedException
     *
     * @return list<NewDashboardPanel>
     */
    public function getPanelsToCreate(): array
    {
        if (null === $this->panelsToCreate) {
            $this->panelsToCreate = [];
            foreach ($this->panelsFromRequest as $dtoPanel) {
                if (empty($dtoPanel->id)) {
                    $this->panelsToCreate[] = $this->createNewDashboardPanel($dtoPanel);
                }
            }
        }

        return $this->panelsToCreate;
    }

    /**
     * @throws AssertionFailedException
     *
     * @return list<DashboardPanel>
     */
    public function getPanelsToUpdate(): array
    {
        if (null === $this->panelsToUpdate) {
            $this->panelsToUpdate = [];
            foreach ($this->panelsFromRequest as $dtoPanel) {
                if (\in_array($dtoPanel->id, $this->panelIdsFromRepository, true)) {
                    $this->panelsToUpdate[] = $this->createDashboardPanel($dtoPanel->id, $dtoPanel);
                }
            }
        }

        return $this->panelsToUpdate;
    }

    /**
     * @return array<int>
     */
    public function getPanelIdsToDelete(): array
    {
        if (null === $this->panelIdsToDelete) {
            $this->panelIdsToDelete = [];
            $panelDtoIds = array_map(
                static fn(PanelRequestDto $dtoPanel): ?int => $dtoPanel->id,
                $this->panelsFromRequest
            );
            foreach ($this->panelIdsFromRepository as $id) {
                if (! \in_array($id, $panelDtoIds, true)) {
                    $this->panelIdsToDelete[] = $id;
                }
            }
        }

        return $this->panelIdsToDelete;
    }

    /**
     * @param PanelRequestDto $dtoPanel
     *
     * @throws AssertionFailedException
     *
     * @return NewDashboardPanel
     */
    private function createNewDashboardPanel(PanelRequestDto $dtoPanel): NewDashboardPanel
    {
        $panel = new NewDashboardPanel($dtoPanel->name, $dtoPanel->widgetType);

        $panel->setWidgetSettings($dtoPanel->widgetSettings);
        $panel->setLayoutX($dtoPanel->layout->posX);
        $panel->setLayoutY($dtoPanel->layout->posY);
        $panel->setLayoutWidth($dtoPanel->layout->width);
        $panel->setLayoutHeight($dtoPanel->layout->height);
        $panel->setLayoutMinWidth($dtoPanel->layout->minWidth);
        $panel->setLayoutMinHeight($dtoPanel->layout->minHeight);

        return $panel;
    }

    /**
     * @param int $id
     * @param PanelRequestDto $dtoPanel
     *
     * @throws AssertionFailedException
     *
     * @return DashboardPanel
     */
    private function createDashboardPanel(int $id, PanelRequestDto $dtoPanel): DashboardPanel
    {
        return new DashboardPanel(
            id: $id,
            name: $dtoPanel->name,
            widgetType: $dtoPanel->widgetType,
            widgetSettings: $dtoPanel->widgetSettings,
            layoutX: $dtoPanel->layout->posX,
            layoutY: $dtoPanel->layout->posY,
            layoutWidth: $dtoPanel->layout->width,
            layoutHeight: $dtoPanel->layout->height,
            layoutMinWidth: $dtoPanel->layout->minWidth,
            layoutMinHeight: $dtoPanel->layout->minHeight
        );
    }
}
