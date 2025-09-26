<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Adaptation\Log;

use Psr\Log\LoggerInterface;

final class LoggerPassword
{
    private static ?self $instance = null;

    private function __construct(private readonly LoggerInterface $logger)
    {
    }

    public static function create(): self
    {
        if (! self::$instance instanceof self) {
            self::$instance = new self(Logger::create(Enum\LogChannelEnum::PASSWORD));
        }

        return self::$instance;
    }

    public function success(int $initiatorId, int $targetId): void
    {
        $this->logger->info(
            'Password change succeeded',
            [
                'status' => 'success',
                'initiator_user_id' => $initiatorId,
                'target_user_id' => $targetId,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]
        );
    }

    public function warning(string $reason, int $initiatorId, int $targetId, ?\Throwable $exception = null): void
    {
        $this->logger->warning(
            "Password change failed ({$reason})",
            [
                'status' => 'failure',
                'reason' => mb_strtolower(str_replace(' ', '_', $reason)),
                'initiator_user_id' => $initiatorId,
                'target_user_id' => $targetId,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'exception' => $exception,
            ]
        );
    }
}
