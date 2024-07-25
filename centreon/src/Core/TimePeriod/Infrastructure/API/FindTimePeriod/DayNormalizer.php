<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

declare(strict_types = 1);

namespace Core\TimePeriod\Infrastructure\API\FindTimePeriod;

use Core\TimePeriod\Domain\Model\Day;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class DayNormalizer implements NormalizerInterface
{
    public function __construct(private readonly ObjectNormalizer $normalizer)
    {
    }

    /**
     * @param Day $object
     * @param string|null $format
     * @param array<string, mixed> $context
     *
     * @throws ExceptionInterface
     *
     * @return array<string, mixed>|\ArrayObject<int, mixed>|bool|float|int|string|null
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): float|int|\ArrayObject|bool|array|string|null
    {
        $data = $this->normalizer->normalize($object, $format, $context);
        if (is_array($data) && array_key_exists('time_range', $data)) {
            $data['time_range'] = (string) $object->getTimeRange();
        }

        return $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof Day;
    }
}
