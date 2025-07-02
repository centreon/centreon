<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\Dashboard\Infrastructure\API\FindSingleMetric;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class FindSingleMetricInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'hostId is required')]
        #[Assert\Positive(message: 'hostId must be a positive integer')]
        public mixed $hostId,

        #[Assert\NotBlank(message: 'serviceId is required')]
        #[Assert\Positive(message: 'serviceId must be a positive integer')]
        public mixed $serviceId,

        #[Assert\NotBlank(message: 'metricName is required')]
        #[Assert\Type('string', message: 'metricName must be a string')]
        #[Assert\Length(min: 1, minMessage: 'metricName cannot be empty')]
        public mixed $metricName,
    ) {}
}
