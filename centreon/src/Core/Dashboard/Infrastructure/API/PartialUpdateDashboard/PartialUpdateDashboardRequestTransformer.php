<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\Dashboard\Infrastructure\API\PartialUpdateDashboard;

use Core\Common\Application\Type\NoValue;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\PartialUpdateDashboardRequest;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\Request\PanelLayoutRequestDto;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\Request\PanelRequestDto;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\Request\RefreshRequestDto;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\Request\ThumbnailRequestDto;
use Core\Dashboard\Infrastructure\Model\RefreshTypeConverter;
use InvalidArgumentException;

/**
 * Class
 *
 * @class PartialUpdateDashboardTransformer
 * @package Core\Dashboard\Infrastructure\API\PartialUpdateDashboard
 */
abstract readonly class PartialUpdateDashboardRequestTransformer
{
    /**
     * @param PartialUpdateDashboardInput $dashboardInputValidator
     *
     * @throws InvalidArgumentException
     * @return PartialUpdateDashboardRequest
     */
    public static function transform(
        PartialUpdateDashboardInput $dashboardInputValidator
    ): PartialUpdateDashboardRequest {
        $name = empty($dashboardInputValidator->name) ? new NoValue() : $dashboardInputValidator->name;
        $description = $dashboardInputValidator->description === null ? new NoValue(
        ) : $dashboardInputValidator->description;

        return new PartialUpdateDashboardRequest(
            name: $name,
            description: $description,
            panels: self::createPanelDto($dashboardInputValidator),
            refresh: self::createRefreshDto($dashboardInputValidator),
            thumbnail: self::createThumbnailDto($dashboardInputValidator)
        );
    }

    /**
     * @param PartialUpdateDashboardInput $dashboardInputValidator
     *
     * @return ThumbnailRequestDto|NoValue
     */
    private static function createThumbnailDto(
        PartialUpdateDashboardInput $dashboardInputValidator
    ): ThumbnailRequestDto|NoValue {
        if ($dashboardInputValidator->thumbnail === null) {
            return new NoValue();
        }

        return new ThumbnailRequestDto(
            id: isset($dashboardInputValidator->thumbnail['id'])
                ? (int) $dashboardInputValidator->thumbnail['id'] : null,
            directory: $dashboardInputValidator->thumbnail['directory'],
            name: $dashboardInputValidator->thumbnail['name']
        );
    }

    /**
     * @param PartialUpdateDashboardInput $dashboardInputValidator
     *
     * @throws InvalidArgumentException
     * @return RefreshRequestDto|NoValue
     */
    private static function createRefreshDto(
        PartialUpdateDashboardInput $dashboardInputValidator
    ): RefreshRequestDto|NoValue {
        if ($dashboardInputValidator->refresh === null) {
            return new NoValue();
        }

        $refreshInterval = $dashboardInputValidator->refresh['interval']
            ? (int) $dashboardInputValidator->refresh['interval']
            : $dashboardInputValidator->refresh['interval'];

        return new RefreshRequestDto(
            refreshType: RefreshTypeConverter::fromString($dashboardInputValidator->refresh['type']),
            refreshInterval: $refreshInterval
        );
    }

    /**
     * @param PartialUpdateDashboardInput $dashboardInputValidator
     *
     * @return PanelRequestDto[]|NoValue
     */
    private static function createPanelDto(
        PartialUpdateDashboardInput $dashboardInputValidator
    ): array|NoValue {
        if ($dashboardInputValidator->panels === null) {
            return new NoValue();
        }

        $panels = [];

        // We can't send empty arrays in multipart/form-data.
        // The panels[] sent is transformed as array{0: empty-string}
        if (! is_array($dashboardInputValidator->panels[0])) {
            return $panels;
        }

        foreach ($dashboardInputValidator->panels as $panel) {
            $layout = $panel['layout'];
            $panelLayoutRequestDto = new PanelLayoutRequestDto(
                posX: (int) $layout['x'],
                posY: (int) $layout['y'],
                width: (int) $layout['width'],
                height: (int) $layout['height'],
                minWidth: (int) $layout['min_width'],
                minHeight: (int) $layout['min_height'],
            );

            $panelId = $panel['id'] ?? null;
            $panels[] = new PanelRequestDto(
                id: $panelId ? (int) $panelId : $panelId,
                name: $panel['name'],
                layout: $panelLayoutRequestDto,
                widgetType: $panel['widget_type'],
                widgetSettings: ! is_array(
                    json_decode($panel['widget_settings'], true))
                    ? [] : json_decode(
                    $panel['widget_settings'],
                    true
                ),
            );
        }

        return $panels;
    }
}
