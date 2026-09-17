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

/**
 * Class
 *
 * @class CentreonDuration
 */
class CentreonDuration
{
    /**
     * @param $duration
     * @param $periods
     *
     * @return false|string
     */
    public static function toString($duration, $periods = null)
    {
        if (! is_array($duration)) {
            $duration = CentreonDuration::int2array($duration, $periods);
        }

        return CentreonDuration::array2string($duration);
    }

    /**
     * @param $seconds
     * @param $periods
     *
     * @return array|null
     */
    public static function int2array($seconds, $periods = null)
    {
        // Define time periods
        if (! is_array($periods)) {
            $periods = ['y' => 31556926, 'M' => 2629743, 'w' => 604800, 'd' => 86400, 'h' => 3600, 'm' => 60, 's' => 1];
        }

        // Loop
        $seconds = (int) $seconds;
        foreach ($periods as $period => $value) {
            $count = floor($seconds / $value);

            if ($count == 0) {
                continue;
            }

            $values[$period] = $count;
            $seconds = $seconds % $value;
        }

        // Return
        if ($values === []) {
            $values = null;
        }

        return $values;
    }

    /**
     * @param $duration
     *
     * @return false|string
     */
    public static function array2string($duration)
    {
        if (! is_array($duration)) {
            return false;
        }

        $i = 0;
        foreach ($duration as $key => $value) {
            if ($i < 2) {
                $segment = $value . '' . $key;
                $array[] = $segment;
                $i++;
            }
        }

        return implode(' ', $array);
    }
}

/**
 * Class
 *
 * @class DurationHoursMinutes
 */
class DurationHoursMinutes
{
    /**
     * @param $duration
     * @param $periods
     *
     * @return false|string
     */
    public static function toString($duration, $periods = null)
    {
        if (! is_array($duration)) {
            $duration = DurationHoursMinutes::int2array($duration, $periods);
        }

        return DurationHoursMinutes::array2string($duration);
    }

    /**
     * @param $seconds
     * @param $periods
     *
     * @return array|null
     */
    public static function int2array($seconds, $periods = null)
    {
        // Define time periods
        if (! is_array($periods)) {
            $periods = ['h' => 3600, 'm' => 60, 's' => 1];
        }

        // Loop
        $seconds = (int) $seconds;
        foreach ($periods as $period => $value) {
            $count = floor($seconds / $value);
            if ($count == 0) {
                continue;
            }

            $values[$period] = $count;
            $seconds = $seconds % $value;
        }

        // Return
        if ($values === []) {
            $values = null;
        }

        return $values;
    }

    /**
     * @param $duration
     *
     * @return false|string
     */
    public static function array2string($duration)
    {
        if (! is_array($duration)) {
            return false;
        }

        foreach ($duration as $key => $value) {
            $array[] = $value . '' . $key;
        }
        unset($segment);

        return implode(' ', $array);
    }
}
