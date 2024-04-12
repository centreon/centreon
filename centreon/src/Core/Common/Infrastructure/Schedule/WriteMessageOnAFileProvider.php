<?php

namespace Core\Common\Infrastructure\Schedule;

use Symfony\Component\Mime\Message;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule]
class WriteMessageOnAFileProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return $this->schedule ??= (new Schedule())
            ->with(RecurringMessage::cron(
                '*/5 * * * *',
                new WriteMessageOnAFile('vous etes completement youyou')
            ));
    }
}