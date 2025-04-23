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

declare(strict_types=1);

namespace Core\TimePeriod\Infrastructure\API\FindTimePeriod;

use ArrayObject;
use Core\TimePeriod\Domain\Model\TimePeriod;
use Core\TimePeriod\Domain\Rules\TimePeriodRuleStrategyInterface;
use DateTimeImmutable;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Traversable;

class TimePeriodNormalizer implements NormalizerInterface
{
    /** @var TimePeriodRuleStrategyInterface[] */
    private array $strategies;

    /**
     * @param ObjectNormalizer $normalizer
     * @param Traversable<TimePeriodRuleStrategyInterface> $strategies
     */
    public function __construct(
        private readonly ObjectNormalizer $normalizer,
        Traversable $strategies
    ) {
        $this->strategies = iterator_to_array($strategies);
    }

    /**
     * @param TimePeriod $object
     * @param string|null $format
     * @param array<string, mixed> $context
     *
     * @throws ExceptionInterface
     *
     * @return array<string, mixed>|ArrayObject<int, mixed>|bool|float|int|string|null
     */
    public function normalize(
        mixed $object,
        ?string $format = null,
        array $context = []
    ): float|int|ArrayObject|bool|array|string|null {

        /** @var array<string, bool|float|int|string> $data */
        $data = $this->normalizer->normalize($object, $format, $context);
        $data['in_period'] = $object->isDateTimeIncludedInPeriod(new DateTimeImmutable(), $this->strategies);

        return $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof TimePeriod;
    }
}
