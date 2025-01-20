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

namespace Core\Common\Infrastructure\Normalizer;

use Core\Common\Domain\NotEmptyString;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

final readonly class NotEmptyStringNormalizer implements NormalizerInterface
{
    public function __construct(private ObjectNormalizer $normalizer)
    {
    }

    /**
     * @param mixed $object
     * @param string|null $format
     * @param array<string, mixed> $context
     *
     * @throws ExceptionInterface
     * @return string
     */
    public function normalize(
        mixed $object,
        ?string $format = null,
        array $context = []
    ): string
    {
        /** @var array{value: string} $data */
        $data = $this->normalizer->normalize($object, $format, $context);

        return $data['value'];
    }

    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof NotEmptyString;
    }
}
