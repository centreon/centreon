<?php

declare(strict_types=1);

namespace App\Monitoring\Domain\Exception;

use App\Shared\Domain\Exception\AggregateNotFoundException;

final class TimePeriodNotFoundException extends AggregateNotFoundException {

}
