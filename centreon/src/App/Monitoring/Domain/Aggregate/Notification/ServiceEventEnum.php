<?php

declare(strict_types=1);

namespace App\Monitoring\Domain\Aggregate\Notification;

enum ServiceEventEnum{
    case Ok;
    case Warning;
    case Critical;
    case Unknown;
}
