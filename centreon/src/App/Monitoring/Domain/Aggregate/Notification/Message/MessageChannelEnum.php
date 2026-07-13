<?php

declare(strict_types=1);

namespace App\Monitoring\Domain\Aggregate\Notification\Message;

enum MessageChannelEnum {
    case Email;
    case Sms;
    case Slack;
}
