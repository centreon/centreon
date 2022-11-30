<?php

namespace Core\TimePeriod\Application\UseCase\AddTimePeriod;

final class AddTimePeriodRequest
{
    public string $name = '';
    public string $alias = '';
    /**
     * @var array<array{day: integer, time_range: string}>
     */
    public array $days = [];
    /**
     * @var int[]
     */
    public array $templates = [];
    /**
     * @var array<array{day_range: string, time_range: string}>
     */
    public array $exceptions = [];
}
