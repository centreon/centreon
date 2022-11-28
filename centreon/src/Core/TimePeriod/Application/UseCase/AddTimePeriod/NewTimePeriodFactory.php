<?php

namespace Core\TimePeriod\Application\UseCase\AddTimePeriod;

use Core\TimePeriod\Domain\Model\Day;
use Core\TimePeriod\Domain\Model\NewTimePeriod;
use Core\TimePeriod\Domain\Model\Template;
use Core\TimePeriod\Domain\Model\TimePeriod;
use Core\TimePeriod\Domain\Model\TimeRange;

final class NewTimePeriodFactory
{
    /**
     * @param AddTimePeriodRequest $dto
     * @return NewTimePeriod
     * @throws \Assert\AssertionFailedException
     * @throws \Throwable
     */
    public static function create(AddTimePeriodRequest $dto): NewTimePeriod
    {
        $newTimePeriod = new NewTimePeriod(
            $dto->name,
            $dto->alias
        );
        $newTimePeriod->setDays(
            array_map(function (array $day): Day {
                return new Day(
                    $day['day'],
                    new TimeRange($day['timeRange']),
                );
            }, $dto->days)
        );
        $newTimePeriod->setTemplates(
            array_map(function (array $templateId): Template {
                return new Template($templateId);
            }, $dto->templates)
        );
        return $newTimePeriod;
    }
}
