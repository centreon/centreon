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

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Exception\InvalidOptionsException;
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

final readonly class PartialUpdateDashboardInput
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
     *
     * @throws ConstraintDefinitionException
     * @throws InvalidArgumentException
     * @throws InvalidOptionsException
     * @throws LogicException
     * @throws MissingOptionsException
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
    ) {}
}
