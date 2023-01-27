<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Core\Common\Infrastructure\RequestParameters\Normalizer;

use Centreon\Infrastructure\RequestParameters\Interfaces\NormalizerInterface;

/**
 * This simple normalizer translate a value to a valid string (for mysql enum).
 */
final class BoolToEnumNormalizer implements NormalizerInterface
{
    public function __construct(
        private readonly string $falseEnum = '0',
        private readonly string $trueEnum = '1',
        private readonly bool $nullable = false,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @throws \TypeError
     */
    public function normalize($valueToNormalize): ?string
    {
        return match ($valueToNormalize) {
            true, 1, '1', 'true', 'TRUE', $this->trueEnum => $this->trueEnum,
            false, 0, '0', 'false', 'FALSE', $this->falseEnum => $this->falseEnum,
            null => $this->nullable ? null : throw $this->newTypeError($valueToNormalize),
            default => throw $this->newTypeError($valueToNormalize)
        };
    }

    private function newTypeError(string|int|null $value): \TypeError
    {
        return new \TypeError(
            sprintf(
                'The value %s is not supported.',
                '<' . get_debug_type($value) . '>' . $value
            )
        );
    }
}
