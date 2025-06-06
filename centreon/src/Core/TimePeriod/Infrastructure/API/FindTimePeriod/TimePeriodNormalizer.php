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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Traversable;

class TimePeriodNormalizer implements NormalizerInterface
{
    /** @var TimePeriodRuleStrategyInterface[] */
    private array $strategies;

    /**
     * @param Traversable<TimePeriodRuleStrategyInterface> $strategies
     * @param NormalizerInterface $normalizer
     */
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer,
        Traversable $strategies,
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

    /**
     * @param array<string, mixed> $context
     * @param mixed $data
     * @param ?string $format
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof TimePeriod;
    }

    /**
     * @param ?string $format
     * @return array<class-string|'*'|'object'|string, bool|null>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            TimePeriod::class => true,
        ];
    }
}
