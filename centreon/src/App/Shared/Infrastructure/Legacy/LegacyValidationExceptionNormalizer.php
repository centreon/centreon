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

namespace App\Shared\Infrastructure\Legacy;

use ApiPlatform\Validator\Exception\ValidationException;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Converts ValidationException in order to have a legacy compliant shape.
 */
final class LegacyValidationExceptionNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;
    private const ALREADY_CALLED = self::class . '_called';

    /**
     * @param array<string, mixed> $context
     *
     * @return array{code: int, message: string}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $context[self::ALREADY_CALLED] = true;

        /** @var array{violations: list<array{propertyPath: string, message: string, code: string}>} $normalized */
        $normalized = $this->normalizer->normalize($data, $format, $context);

        $messages = array_map($this->convertViolationToLegacy(...), $normalized['violations']);

        return [
            'code' => 400,
            'message' => implode("\n", $messages) . "\n",
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if ($context[self::ALREADY_CALLED] ?? false) {
            return false;
        }

        return $data instanceof ValidationException;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ValidationException::class => false,
        ];
    }

    /**
     * @param array{propertyPath: string, message: string, code: string} $violation
     */
    private function convertViolationToLegacy(array $violation): string
    {
        return sprintf('[%s] %s', $violation['propertyPath'], $violation['message']);
    }
}
