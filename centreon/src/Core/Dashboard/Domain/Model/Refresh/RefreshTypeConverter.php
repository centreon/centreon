<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Dashboard\Domain\Model\Refresh;

final class RefreshTypeConverter
{
    /**
     * @param string $type
     *
     * @throws \InvalidArgumentException
     *
     * @return RefreshType
     *
     */
    public static function fromString(string $type): RefreshType
    {
        return match($type) {
            'global' => RefreshType::Global,
            'manual' => RefreshType::Manual,
            default => throw new \InvalidArgumentException('Invalid value')
        };
    }

    public static function toString(RefreshType $type): string
    {
        return match($type) {
            RefreshType::Global => 'global',
            RefreshType::Manual => 'manual',
            default => throw new \InvalidArgumentException('Invalid value')
        };
    }
}
