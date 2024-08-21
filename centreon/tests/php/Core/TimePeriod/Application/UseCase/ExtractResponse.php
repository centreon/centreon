<?php

namespace Tests\Core\TimePeriod\Application\UseCase;

use Core\TimePeriod\Domain\Model\Day;
use Core\TimePeriod\Domain\Model\ExtraTimePeriod;
use Core\TimePeriod\Domain\Model\Template;
use Core\TimePeriod\Domain\Model\TimePeriod;

class ExtractResponse
{
    /**
     * @param TimePeriod $timePeriod
     * @return array
     */
    public static function daysToArray(TimePeriod $timePeriod): array
    {
        return array_map(fn(Day $day) => [
            'day' => $day->getDay(),
            'time_range' => (string)$day->getTimeRange()
        ], $timePeriod->getDays());
    }

    /**
     * @param TimePeriod $timePeriod
     * @return array
     */
    public static function templatesToArray(TimePeriod $timePeriod): array
    {
        return array_map(fn(Template $template) => [
            'id' => $template->getId(),
            'alias' => $template->getAlias(),
        ], $timePeriod->getTemplates());
    }

    /**
     * @param TimePeriod $timePeriod
     * @return array
     */
    public static function exceptionsToArray(TimePeriod $timePeriod): array
    {
        return array_map(fn(ExtraTimePeriod $exception) => [
            'id' => $exception->getId(),
            'day_range' => $exception->getDayRange(),
            'time_range' => (string)$exception->getTimeRange(),
        ], $timePeriod->getExtraTimePeriods());
    }
}
