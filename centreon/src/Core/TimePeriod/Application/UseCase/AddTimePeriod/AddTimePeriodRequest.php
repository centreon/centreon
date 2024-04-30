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
     * @param string $alias
     * @param array<array{day: int, time_range:string}> $days
     * @param int[] $templates
     * @param DtoException[] $exceptions
     */
    public function __construct(
        #[Assert\NotBlank()]
        #[Assert\Length(min: 10, max: 20)]
        #[Assert\Type('string')]
        public readonly mixed $name,
        #[Assert\Type('string')]
        public readonly mixed $alias,
        #[Assert\All(
            new Assert\Collection(
            fields: [
                'day' => [
                    new Assert\Type('integer'),
                    new Assert\NotBlank,
                ],
                'time_range' => [
                    new Assert\Type('string'),
                    new Assert\NotBlank,
                ],
            ])
        )]
        public readonly array $days,
        public readonly array $templates = [],
        #[Assert\Valid]
        public readonly array $exceptions = [],
    ) {
    }
}
