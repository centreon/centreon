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

use Adaptation\Log\Enum\AuthProviderEnum;
use Adaptation\Log\Enum\LogChannelEnum;
use Psr\Log\LoggerInterface;

final class LoggerAuthentication
{
    private static ?self $instance = null;

    private function __construct(private readonly LoggerInterface $logger)
    {
    }

    public static function create(): self
    {
        if (! self::$instance instanceof self) {
            self::$instance = new self(Logger::create(LogChannelEnum::AUTHENTICATION));
        }

        return self::$instance;
    }

    public function loginSuccess(int $userId, AuthProviderEnum $provider): void
    {
        $this->logger->info(
            'Login succeeded',
            $this->baseContext('login.success', 'success', $userId, $provider)
        );
    }

    public function loginFailure(
        string $message,
        ?int $userId,
        AuthProviderEnum $provider,
        ?\Throwable $exception = null,
    ): void {
        $this->logger->warning(
            $message,
            $this->baseContext('login.failure', 'failure', $userId, $provider, $exception)
        );
    }

    public function logout(int $userId, AuthProviderEnum $provider): void
    {
        $this->logger->info(
            'Logout',
            $this->baseContext('logout', 'success', $userId, $provider)
        );
    }

    public function tokenRefreshSuccess(int $userId, AuthProviderEnum $provider): void
    {
        $this->logger->info(
            'Token refresh succeeded',
            $this->baseContext('token.refresh.success', 'success', $userId, $provider)
        );
    }

    public function tokenRefreshFailure(
        string $message,
        ?int $userId,
        AuthProviderEnum $provider,
        ?\Throwable $exception = null,
    ): void {
        $this->logger->warning(
            $message,
            $this->baseContext('token.refresh.failure', 'failure', $userId, $provider, $exception)
        );
    }

    public function unauthorized(string $message, ?int $userId = null): void
    {
        $this->logger->warning(
            $message,
            $this->baseContext('unauthorized', 'failure', $userId, null)
        );
    }

    public function forbidden(string $message, int $userId, ?string $resource = null): void
    {
        $context = $this->baseContext('forbidden', 'failure', $userId, null);
        if ($resource !== null) {
            $context['resource'] = $resource;
        }
        $this->logger->warning($message, $context);
    }

    /**
     * @return array<string,mixed>
     */
    private function baseContext(
        string $event,
        string $status,
        ?int $userId,
        ?AuthProviderEnum $provider,
        ?\Throwable $exception = null,
    ): array {
        $context = [
            'event' => $event,
            'status' => $status,
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ];
        if ($provider !== null) {
            $context['provider'] = $provider->value;
        }
        if ($exception !== null) {
            $context['exception'] = $exception;
        }

        return $context;
    }
}
