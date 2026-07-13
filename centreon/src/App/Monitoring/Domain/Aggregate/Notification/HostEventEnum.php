<?php

declare(strict_types=1);

namespace App\Monitoring\Domain\Aggregate\Notification;

enum HostEventEnum{
    case Up;
    case Down;
    case Unreachable;
}
