<?php

declare(strict_types=1);

namespace App\ResourceConfiguration\Domain\Exception;

use App\Shared\Domain\Exception\AggregateAlreadyExistException;

final class ServiceCategoryAlreadyExistException extends AggregateAlreadyExistException
{
}
