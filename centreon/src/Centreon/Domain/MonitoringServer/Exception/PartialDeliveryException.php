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

namespace Centreon\Domain\MonitoringServer\Exception;

/**
 * Gorgone did not deliver the configuration to some of the monitoring servers, the others have been delivered
 * (and can still be reloaded).
 *
 * @package Centreon\Domain\MonitoringServer\Exception
 */
class PartialDeliveryException extends GorgoneDeliveryException
{
    /**
     * @param list<int> $deliveredMonitoringServerIds
     */
    public function __construct(
        string $message,
        int $code,
        private readonly array $deliveredMonitoringServerIds,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return list<int>
     */
    public function getDeliveredMonitoringServerIds(): array
    {
        return $this->deliveredMonitoringServerIds;
    }
}
