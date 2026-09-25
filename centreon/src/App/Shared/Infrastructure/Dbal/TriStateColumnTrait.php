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

namespace App\Shared\Infrastructure\Dbal;

use App\Shared\Domain\Aggregate\TriStateEnum;

/**
 * Maps {@see TriStateEnum} to the `enum('0','1','2')` column values shared by every
 * on/off/default monitoring directive column.
 */
trait TriStateColumnTrait
{
    private function triStateToColumn(TriStateEnum $state): string
    {
        return match ($state) {
            TriStateEnum::False => '0',
            TriStateEnum::True => '1',
            TriStateEnum::UseDefault => '2',
        };
    }

    private function columnToTriState(string $value): TriStateEnum
    {
        return match ($value) {
            '0' => TriStateEnum::False,
            '1' => TriStateEnum::True,
            '2' => TriStateEnum::UseDefault,
            default => throw new \UnexpectedValueException(sprintf('Unexpected tri-state column value "%s".', $value)),
        };
    }
}
