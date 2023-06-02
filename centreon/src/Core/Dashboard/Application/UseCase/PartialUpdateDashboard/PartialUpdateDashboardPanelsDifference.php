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
    private readonly array $panelsToCreate;

    /** @var array<DashboardPanel> */
    private readonly array $panelsToUpdate;

    /** @var array<int> */
    private readonly array $panelIdsToDelete;

    /**
     * @param array<int> $panelIdsFromRepository
     * @param array<PanelRequestDto> $panelsFromRequest
     *
     * @throws DashboardException|AssertionFailedException
     */
    public function __construct(array $panelIdsFromRepository, array $panelsFromRequest)
    {
        [$this->panelsToCreate, $this->panelsToUpdate, $this->panelIdsToDelete]
            = $this->compute($panelIdsFromRepository, $panelsFromRequest);
    }

    /**
     * @return array<NewDashboardPanel>
     */
    public function getPanelsToCreate(): array
    {
        return $this->panelsToCreate;
    }

    /**
     * @return array<DashboardPanel>
     */
    public function getPanelsToUpdate(): array
    {
        return $this->panelsToUpdate;
    }

    /**
     * @return array<int>
     */
    public function getPanelIdsToDelete(): array
    {
        return $this->panelIdsToDelete;
    }

    /**
     * @param array<int> $panelIdsFromRepository
     * @param array<PanelRequestDto> $panelsFromRequest
     *
     * @throws DashboardException|AssertionFailedException
     *
     * @return array{
     *     array<NewDashboardPanel>,
     *     array<DashboardPanel>,
     *     array<int>
     * }
     */
    private function compute(array $panelIdsFromRepository, array $panelsFromRequest): array
    {
        $panelsToCreate = [];
        $panelsToUpdate = [];
        $panelIdsToDelete = [];

        $panelsFromRequestById = [];
        foreach ($panelsFromRequest as $dtoPanel) {
            if (empty($dtoPanel->id)) {
                $panelsToCreate[] = $this->createNewDashboardPanel($dtoPanel);
                continue;
            }

            $panelsFromRequestById[$dtoPanel->id] = $dtoPanel;
            if (! \in_array($dtoPanel->id, $panelIdsFromRepository, true)) {
                throw DashboardException::errorTryingToUpdateAPanelWhichDoesNotBelongsToTheDashboard();
            }
        }

        foreach ($panelIdsFromRepository as $id) {
            if (\array_key_exists($id, $panelsFromRequestById)) {
                $panelsToUpdate[] = $this->createDashboardPanel($id, $panelsFromRequestById[$id]);
            } else {
                $panelIdsToDelete[] = $id;
            }
        }

        return [$panelsToCreate, $panelsToUpdate, $panelIdsToDelete];
    }

    /**
     * @param PanelRequestDto $dtoPanel
     *
     * @throws AssertionFailedException
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
