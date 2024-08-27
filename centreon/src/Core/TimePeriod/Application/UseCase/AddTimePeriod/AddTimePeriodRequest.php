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

namespace Core\TimePeriod\Application\UseCase\AddTimePeriod;

use Symfony\Component\Validator\Constraints as Assert;

final class AddTimePeriodRequest
{
    /**
     * @param mixed $name
     * @param mixed $alias
     * @param array<array{day:int, time_range:string}>|null $days
     * @param array<int>|null $templates
     * @param array<array{day_range:string, time_range:string}>|null $exceptions
     */
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Type('string')]
        #[Assert\Length(min: 1, max: 200)]
        public readonly mixed $name,
        #[Assert\NotNull]
        #[Assert\Type('string')]
        #[Assert\Length(min: 1, max: 200)]
        public readonly mixed $alias,
        #[Assert\NotNull]
        #[Assert\Type('array')]
        #[Assert\All([
            new Assert\Collection(
                fields: [
                    'day' => [
                        new Assert\NotNull(),
                        new Assert\Type('integer'),
                    ],
                    'time_range' => [
                        new Assert\NotNull(),
                        new Assert\Type('string'),
                    ],
                ],
            ),
        ])]
        public readonly mixed $days,
        #[Assert\NotNull]
        #[Assert\Type('array')]
        #[Assert\All(
            new Assert\Type('integer'),
        )]
        public readonly mixed $templates,
        #[Assert\NotNull]
        #[Assert\Type('array')]
        #[Assert\All([
            new Assert\Collection(
                fields: [
                    'day_range' => [
                        new Assert\NotNull(),
                        new Assert\Type('string'),
                    ],
                    'time_range' => [
                        new Assert\NotNull(),
                        new Assert\Type('string'),
                    ],
                ],
            ),
        ])]
        public readonly mixed $exceptions = [],
    ) {
    }

    public function toDto(): AddTimePeriodDto
    {
        return new AddTimePeriodDto(
            is_string($this->name) ? $this->name : '',
            is_string($this->alias) ? $this->alias : '',
            array_map(
                fn (array $day): array => ['day' => $day['day'], 'time_range' => $day['time_range']],
                is_array($this->days) ? $this->days : []
            ),
            is_array($this->templates) ? $this->templates : [],
            array_map(
                fn (array $exception): array => [
                    'day_range' => $exception['day_range'],
                    'time_range' => $exception['time_range'],
                ],
                is_array($this->exceptions) ? $this->exceptions : []
            ),
        );
    }
}
