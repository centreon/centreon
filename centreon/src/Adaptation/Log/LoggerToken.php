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

namespace Adaptation\Log;

use Psr\Log\LoggerInterface;

final class LoggerToken
{
    private static ?self $instance = null;

    private function __construct(private readonly LoggerInterface $logger)
    {
    }

    public static function create(): self
    {
        if (! self::$instance instanceof self) {
            self::$instance = new self(Logger::create(Enum\LogChannelEnum::TOKEN));
        }

        return self::$instance;
    }

    public function success(
        string $event,
        int $userId,
        ?string $tokenType = null,
        ?string $tokenName = null,
        ?string $endpoint = null,
        ?string $httpMethod = null,
    ): void {
        $context = [
            'event' => $event,
            'status' => 'success',
            'datetime' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ];

        if ($tokenName !== null) {
            $context['token_name'] = $tokenName;
        }

        if ($tokenType !== null) {
            $context['token_type'] = $tokenType;
        }

        if ($endpoint !== null) {
            $context['endpoint'] = $endpoint;
        }

        if ($httpMethod !== null) {
            $context['http_method'] = $httpMethod;
        }

        $this->logger->info("Token {$event} succeeded", $context);
    }

    public function warning(
        string $event,
        string $reason,
        int $userId,
        ?string $tokenName = null,
        ?string $tokenType = null,
        ?\Throwable $exception = null,
    ): void {
        $context = [
            'event' => $event,
            'status' => 'failure',
            'datetime' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'reason' => mb_strtolower(str_replace(' ', '_', $reason)),
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'exception' => $exception,
        ];

        if ($tokenName !== null) {
            $context['token_name'] = $tokenName;
        }

        if ($tokenType !== null) {
            $context['token_type'] = $tokenType;
        }

        $this->logger->warning("Token {$event} failed", $context);
    }
}
