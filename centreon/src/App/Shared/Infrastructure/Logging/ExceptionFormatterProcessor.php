<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Unauthorized reproduction, copy and distribution are not allowed.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;

#[AsMonologProcessor(channel: 'bus')]
#[AsMonologProcessor(channel: 'request')]
#[AsMonologProcessor(channel: 'app')]
final readonly class ExceptionFormatterProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;

        if (! isset($context['exception']) || ! $context['exception'] instanceof \Throwable) {
            return $record;
        }

        $context['exception'] = ExceptionFormatter::format($context['exception']);

        return $record->with(context: $context);
    }
}
