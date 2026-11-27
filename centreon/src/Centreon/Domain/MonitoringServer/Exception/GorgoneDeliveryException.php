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
 * Gorgone did not deliver the configuration (or the reload) to a containerized poller.
 * The message is meant for the API client as is; the code tells why.
 *
 * @package Centreon\Domain\MonitoringServer\Exception
 */
class GorgoneDeliveryException extends \Exception
{
    /** The poller is not connected to Gorgone: nothing has been sent */
    public const NODE_NOT_CONNECTED = 1;

    /** Gorgone reported a failure for the delivery */
    public const DELIVERY_FAILED = 2;

    /** Gorgone did not report the final result of the delivery in time */
    public const RESULT_TIMEOUT = 3;

    /**
     * @param int $reason one of the constants of this class
     */
    public static function isKnownReason(int $reason): bool
    {
        return in_array($reason, [self::NODE_NOT_CONNECTED, self::DELIVERY_FAILED, self::RESULT_TIMEOUT], true);
    }
}
