<?php

/*
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

declare(strict_types=1);

namespace Core\TimePeriod\Application\Exception;

class TimePeriodException extends \Exception
{
    /**
     * @param int $timePeriodId
     * @return self
     */
    public static function errorOnDelete(int $timePeriodId): self
    {
        return new self(sprintf(_('Error when deleting the time period %d'), $timePeriodId));
    }

    /**
     * @param int $timePeriodId
     * @return self
     */
    public static function errorOnUpdate(int $timePeriodId): self
    {
        return new self(sprintf(_('Error when updating the time period %d'), $timePeriodId));
    }

    /**
     * @return self
     */
    public static function errorWhenAddingTimePeriod(): self
    {
        return new self(_('Error when adding the time period'));
    }

    /**
     * @return self
     */
    public static function errorWhenSearchingForAllTimePeriods(): self
    {
        return new self(_('Error when searching for time periods'));
    }

    /**
     * @param int $timePeriodId
     * @return self
     */
    public static function errorWhenSearchingForTimePeriod(int $timePeriodId): self
    {
        return new self(sprintf(_('Error when searching for the time period %d'), $timePeriodId));
    }

    /**
     * @param string $timePeriodName
     * @return self
     */
    public static function nameAlreadyExists(string $timePeriodName): self
    {
        return new self(sprintf(_('The time period name \'%s\' already exists'), $timePeriodName));
    }
}
