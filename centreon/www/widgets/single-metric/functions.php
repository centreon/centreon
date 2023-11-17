<?php

/**
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

/**
 * Convert a size to be human readable.
 *
 * @param float|int $value Value to convert
 * @param string $unit Unit of value
 * @param int $base Conversion base (ex: 1024)
 * @return array{0: float, 1: string}
 */
function convertSizeToHumanReadable(float|int $value, string $unit, int $base): array
{
    $accuracy = 2;
    $prefix = array('a', 'f', 'p', 'n', 'u', 'm', '', 'k', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y');
    $power = min(max(floor(log(abs($value), $base)), -6), 6);
    return [ round((float)$value / pow($base, $power), $accuracy), $prefix[$power + 6] . $unit];
}
