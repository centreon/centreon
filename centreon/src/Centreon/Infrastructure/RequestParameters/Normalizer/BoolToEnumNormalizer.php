<?php
/*
 * Copyright 2005 - 2020 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Centreon\Infrastructure\RequestParameters\Normalizer;

use Centreon\Infrastructure\RequestParameters\Interfaces\NormalizerInterface;

class BoolToEnumNormalizer implements NormalizerInterface
{
    public function __construct(
        private string $falseEnum = '0',
        private string $trueEnum = '1',
    ) {
    }

    public function normalize($valueToNormalize): string
    {
        return match ($valueToNormalize) {
            true, 1, '1', 'true', 'TRUE', $this->trueEnum => $this->trueEnum,
            false, 0, '0', 'false', 'FALSE', $this->falseEnum => $this->falseEnum,
            default => throw new \ValueError()
        };
    }
}