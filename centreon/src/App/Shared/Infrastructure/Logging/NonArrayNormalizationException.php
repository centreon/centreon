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

namespace App\Shared\Infrastructure\Logging;

/**
 * Signals that the inner normalizer wrapped by LogPayloadNormalizer
 * returned a non-array value (scalar, null, object) when an associative
 * array was contractually required. Carries the offending type so the
 * caller can log it operationally without parsing the message string.
 */
final class NonArrayNormalizationException extends \UnexpectedValueException
{
    public function __construct(
        public readonly string $messageClass,
        public readonly string $returnedType,
    ) {
        parent::__construct(sprintf(
            'Expected the inner normalizer to return an array for %s, got %s.',
            $messageClass,
            $returnedType,
        ));
    }
}
