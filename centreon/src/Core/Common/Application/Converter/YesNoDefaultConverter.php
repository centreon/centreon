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

namespace Core\Common\Application\Converter;

use Core\Common\Domain\YesNoDefault;


/**
 * This class purpose is to allow convertion from the legacy host event enum to a string
 */
class YesNoDefaultConverter
{
    private const CASE_NO = 0;
    private const CASE_YES = 1;
    private const CASE_DEFAULT = 2;

    /**
     * @param null|bool|int|string $value
     *
     * @return YesNoDefault
    */
    public static function fromScalar(null|bool|int|string $value): YesNoDefault
    {
        return match ($value) {
            true, '1', self::CASE_YES, (string) self::CASE_YES => YesNoDefault::Yes,
            false, '0', self::CASE_NO, (string) self::CASE_NO => YesNoDefault::No,
            null, '2', self::CASE_DEFAULT, (string) self::CASE_DEFAULT => YesNoDefault::Default,
            default => throw new \ValueError("\"{$value}\" is not a valid backing value for enum YesNoDefault")
        };
    }

    /**
     * Convert a YesNoDefault into its corresponding integer
     * ex: YesNoDefault::Default => 2
     *
     * @param YesNoDefault $enum
     *
     * @return int
     */
    public static function toInt(YesNoDefault $enum): int
    {
        return match ($enum) {
            YesNoDefault::Yes => self::CASE_YES,
            YesNoDefault::No => self::CASE_NO,
            YesNoDefault::Default => self::CASE_DEFAULT
        };
    }

    /**
     * Convert a YesNoDefault into its corresponding string
     * ex: YesNoDefault::Default => '2'
     *
     * @param YesNoDefault $enum
     *
     * @return string
     */
    public static function toString(YesNoDefault $enum): string
    {
        return (string) self::toInt($enum);
    }
}
