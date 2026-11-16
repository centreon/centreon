<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Core\Metric\Application\Exception;

/**
 * The time series database could not give the values of a graph. A \RuntimeException, as the
 * missing RRD file of the status graph.
 */
final class MetricsStorageException extends \RuntimeException
{
    public static function missingUrl(): self
    {
        return new self('The graphs are read from VictoriaMetrics but its URL is not configured');
    }

    public static function requestFailed(\Throwable $previous): self
    {
        return new self(sprintf('Request to VictoriaMetrics failed: %s', $previous->getMessage()), 0, $previous);
    }

    public static function unexpectedResponse(int $statusCode, string $detail): self
    {
        return new self(sprintf(
            'VictoriaMetrics answered HTTP %d%s',
            $statusCode,
            $detail !== '' ? ': ' . $detail : ''
        ));
    }

    public static function invalidResponse(string $detail): self
    {
        return new self(sprintf('Invalid answer of VictoriaMetrics: %s', $detail));
    }
}
