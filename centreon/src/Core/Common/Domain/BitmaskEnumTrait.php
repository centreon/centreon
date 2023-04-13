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

namespace Core\Common\Domain;

/**
 * This trait is to be used for enums.
 * It provides methods to transform data between enum to bitmask format.
 * Example: 0b11 <=> 3 <=> [self::Down,self::Unreachable].
 */
trait BitmaskEnumTrait
{
    /**
     * Returns the int representation (2^n) of the bit of each enum.
     *
     * @return int
     */
    abstract public function toBit(): int;

    /**
     * Returns the maximum possible bitmask as an int representation (2^0 + ... + 2^n).
     *
     * @return int
     */
    abstract public static function getMaxBitmask(): int;

    /**
     * @param int $bitmask
     *
     * @throws \Throwable
     *
     * @return self[]
     */
    public static function fromBitmask(int $bitmask): array
    {
        if ($bitmask > self::getMaxBitmask() || $bitmask < 0) {
            $shortName = (new \ReflectionClass(self::class))->getShortName();

            throw new \ValueError("\"{$bitmask}\" is not a valid bitmask for enum {$shortName}");
        }

        $enums = [];
        foreach (self::cases() as $enum) {
            if ($bitmask & $enum->toBit()) {
                $enums[] = $enum;
            }
        }

        return $enums;
    }

    /**
     * @param self[] $enums
     *
     * @return int
     */
    public static function toBitmask(array $enums): int
    {
        $bitmask = 0;
        foreach ($enums as $event) {
            // Value 0 is not a bit, we consider it resets the bitmask
            if ($event->toBit() === 0) {
                return 0;
            }
            $bitmask |= $event->toBit();
        }

        return $bitmask;
    }
}
