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

use Core\Common\Application\Type\NoValue;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\Request\PanelLayoutRequestDto;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\Request\PanelRequestDto;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\Request\RefreshRequestDto;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\Request\ThumbnailRequestDto;
use Core\Dashboard\Infrastructure\Model\RefreshTypeConverter;
use Symfony\Component\Validator\Constraints as Assert;

final class PartialUpdateDashboardRequest
{
    /**
     * @param string|null $name
     * @param string|null $description
     * @param array{type: string, interval: int} $refresh
     * @param array{id?: int, name: string, directory: string} $thumbnail
     * @param array<array{
     *    id?: ?int,
     *    name: string,
     *    layout: array{
     *        x: int,
     *        y: int,
     *        width: int,
     *        height: int,
     *        min_width: int,
     *        min_height: int
     *    },
     *    widget_type: string,
     *    widget_settings: string,
     *}> $panels
     */
    public function __construct(
        #[Assert\Type('string')]
        #[Assert\Length(min: 1, max: 200)]
        public mixed $name = null,
        #[Assert\Type('string')]
        public mixed $description = null,
        #[Assert\Collection(
            fields: [
                'type' => [
                    new Assert\NotNull(),
                    new Assert\Type('string'),
                    new Assert\Choice(['global', 'manual']),
                ],
                'interval' => new Assert\Optional([
                    new Assert\Type('numeric'),
                    new Assert\Positive(),
                ]),
            ]
        )]
        public mixed $refresh = null,
        #[Assert\Collection(
            fields: [
                'id' => new Assert\Optional([
                    new Assert\Type('numeric'),
                    new Assert\Positive(),
                ]),
                'name' => [
                    new Assert\NotNull(),
                    new Assert\Type('string'),
                    new Assert\Length(min: 1, max: 255),
                ],
                'directory' => [
                    new Assert\NotNull(),
                    new Assert\Type('string'),
                    new Assert\Length(min: 1, max: 255),
                ],
            ],
        )]
        public mixed $thumbnail = null,
        #[Assert\AtLeastOneOf([
            new Assert\Count(max: 1),
            new Assert\Sequentially([
                new Assert\Type('array'),
                new Assert\All(
                    new Assert\Collection(
                        fields: [
                            'id' => new Assert\Optional([
                                new Assert\When(
                                    expression: 'value !== "null"',
                                    constraints: [new Assert\Type('numeric'), new Assert\Positive()]
                                ),
                            ]),
                            'name' => [
                                new Assert\NotNull(),
                                new Assert\Type('string'),
                            ],
                            'layout' => new Assert\Collection(
                                fields: [
                                    'x' => [
                                        new Assert\NotNull(),
                                        new Assert\Type('numeric'),
                                    ],
                                    'y' => [
                                        new Assert\NotNull(),
                                        new Assert\Type('numeric'),
                                    ],
                                    'width' => [
                                        new Assert\NotNull(),
                                        new Assert\Type('numeric'),
                                    ],
                                    'height' => [
                                        new Assert\NotNull(),
                                        new Assert\Type('numeric'),
                                    ],
                                    'min_width' => [
                                        new Assert\NotNull(),
                                        new Assert\Type('numeric'),
                                    ],
                                    'min_height' => [
                                        new Assert\NotNull(),
                                        new Assert\Type('numeric'),
                                    ],
                                ]
                            ),
                            'widget_type' => [
                                new Assert\NotNull(),
                                new Assert\Type('string'),
                            ],
                            'widget_settings' => [
                                new Assert\NotNull(),
                                new Assert\Type('string'),
                                new Assert\Json(),
                            ],
                        ]
                    )
                ),
            ]),
        ])]
        public mixed $panels = null,
    ) {
    }

    public function toDto(): PartialUpdateDashboardRequestDto
    {
        $name = empty($this->name) ? new NoValue() : $this->name;
        $description = $this->description === null ? new NoValue() : $this->description;

        return new PartialUpdateDashboardRequestDto(
            name: $name,
            description: $description,
            panels: $this->createPanelDto(),
            refresh: $this->createRefreshDto(),
            thumbnail: $this->createThumbnailDto()
        );
    }

    /**
     * @return ThumbnailRequestDto|NoValue
     */
    private function createThumbnailDto(): ThumbnailRequestDto|NoValue
    {
        if ($this->thumbnail === null) {
            return new NoValue();
        }

        return new ThumbnailRequestDto(
            id: isset($this->thumbnail['id']) ? (int) $this->thumbnail['id'] : null,
            directory: $this->thumbnail['directory'],
            name: $this->thumbnail['name']
        );
    }

    /**
     * @return RefreshRequestDto|NoValue
     */
    private function createRefreshDto(): RefreshRequestDto|NoValue
    {
        if ($this->refresh === null) {
            return new NoValue();
        }

        $refreshInterval = $this->refresh['interval'] ? (int) $this->refresh['interval'] : $this->refresh['interval'];

        return new RefreshRequestDto(
            refreshType: RefreshTypeConverter::fromString($this->refresh['type']),
            refreshInterval: $refreshInterval
        );
    }

    /**
     * @return PanelRequestDto[]|NoValue
     */
    private function createPanelDto(): array|NoValue
    {
        if ($this->panels === null) {
            return new NoValue();
        }

        $panels = [];

        // We can't send empty arrays in multipart/form-data.
        // The panels[] sent is transformed as array{0: empty-string}
        if (! \is_array($this->panels[0])) {
            return $panels;
        }

        foreach ($this->panels as $panel) {
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
                widgetSettings: ! is_array(json_decode($panel['widget_settings'], true)) ? [] : json_decode($panel['widget_settings'], true),
            );
        }

        return $panels;
    }
}
