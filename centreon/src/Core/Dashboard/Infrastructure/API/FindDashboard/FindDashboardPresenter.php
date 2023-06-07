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

namespace Core\Dashboard\Infrastructure\API\FindDashboard;

use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Dashboard\Application\UseCase\FindDashboard\FindDashboardPresenterInterface;
use Core\Dashboard\Application\UseCase\FindDashboard\FindDashboardResponse;
use Core\Dashboard\Application\UseCase\FindDashboard\Response\PanelResponseDto;
use Core\Dashboard\Application\UseCase\FindDashboard\Response\UserResponseDto;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

final class FindDashboardPresenter extends DefaultPresenter implements FindDashboardPresenterInterface
{
    use PresenterTrait;

    public function presentResponse(ResponseStatusInterface|FindDashboardResponse $data): void
    {
        if ($data instanceof FindDashboardResponse) {
            $this->present([
                'id' => $data->id,
                'name' => $data->name,
                'description' => $this->emptyStringAsNull($data->description),
                'created_by' => $this->userToOptionalArray($data->createdBy),
                'updated_by' => $this->userToOptionalArray($data->updatedBy),
                'created_at' => $this->formatDateToIso8601($data->createdAt),
                'updated_at' => $this->formatDateToIso8601($data->updatedAt),
                'panels' => array_map($this->panelToArray(...), $data->panels),
            ]);
        } else {
            $this->setResponseStatus($data);
        }
    }

    /**
     * @param ?UserResponseDto $dto
     *
     * @return null|array<scalar>
     */
    private function userToOptionalArray(?UserResponseDto $dto): ?array
    {
        return $dto ? [
            'id' => $dto->id,
            'name' => $dto->name,
        ] : null;
    }

    /**
     * @param PanelResponseDto $panel
     *
     * @return array<mixed>
     */
    private function panelToArray(PanelResponseDto $panel): array
    {
        return [
            'id' => $panel->id,
            'name' => $panel->name,
            'layout' => [
                'x' => $panel->layout->posX,
                'y' => $panel->layout->posY,
                'width' => $panel->layout->width,
                'height' => $panel->layout->height,
                'min_width' => $panel->layout->minWidth,
                'min_height' => $panel->layout->minHeight,
            ],
            'widget_type' => $panel->widgetType,
            'widget_settings' => $panel->widgetSettings,
        ];
    }
}
