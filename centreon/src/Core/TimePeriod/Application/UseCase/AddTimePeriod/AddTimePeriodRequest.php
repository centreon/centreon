<?php

namespace Core\TimePeriod\Application\UseCase\AddTimePeriod;

final class AddTimePeriodRequest
{
    public int $id = 0;
    public string $name = '';
    public string $alias = '';
    /**
     * @var DayDto[]
     */
    public array $days;
    /**
     * @var int[]
     */
    public array $templates;
    /**
     * @var array<array{day_range: string, time_range: string}>
     */
    public array $exceptions;
}
