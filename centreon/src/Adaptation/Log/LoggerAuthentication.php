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
use Psr\Log\LogLevel;

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

    public function loginSuccess(string $message, int $userId, AuthProviderEnum $provider): void
    {
        $this->logger->log(
            LogLevel::INFO,
            $message,
            $this->baseContext('login.success', 'success', $userId, $provider)
        );

        $this->mirrorToLegacyLoginLog($message, $userId);
    }

    public function loginFailure(
        string $message,
        ?int $userId,
        AuthProviderEnum $provider,
        ?\Throwable $exception = null,
    ): void {
        $this->logger->log(
            LogLevel::WARNING,
            $message,
            $this->baseContext('login.failure', 'failure', $userId, $provider, $exception)
        );

        $this->mirrorToLegacyLoginLog($message, $userId);
    }

    public function logout(string $message, int $userId, AuthProviderEnum $provider): void
    {
        $this->logger->log(
            LogLevel::INFO,
            $message,
            $this->baseContext('logout', 'success', $userId, $provider)
        );
    }

    public function tokenRefreshSuccess(string $message, int $userId, AuthProviderEnum $provider): void
    {
        $this->logger->log(
            LogLevel::INFO,
            $message,
            $this->baseContext('token.refresh.success', 'success', $userId, $provider)
        );
    }

    public function tokenRefreshFailure(
        string $message,
        ?int $userId,
        AuthProviderEnum $provider,
        ?\Throwable $exception = null,
    ): void {
        $this->logger->log(
            LogLevel::WARNING,
            $message,
            $this->baseContext('token.refresh.failure', 'failure', $userId, $provider, $exception)
        );
    }

    public function unauthorized(string $message, ?int $userId = null): void
    {
        $this->logger->log(
            LogLevel::WARNING,
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
        $this->logger->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Mirror a login event to the historical login.log file.
     *
     * Login success/failure events are routed to the Monolog "authentication" channel
     * (prod.access.log). This duplicate write keeps the legacy pipe-delimited format and
     * file path so external consumers that watch /var/log/centreon/login.log (fail2ban
     * jails matching the "Authentication failed" line with the client IP, SIEM parsers)
     * keep working unchanged. It reproduces exactly the lines the legacy centreonAuth used
     * to emit through CentreonUserLog::insertLog(TYPE_LOGIN). It is transitional and will be
     * removed in a future release once those consumers read the Monolog access log instead.
     *
     * Mirroring the security feed must never break the authentication flow: a write failure
     * (unwritable directory, full disk) is reported to error_log() and swallowed rather than
     * propagated, so logging a login can never turn a successful login into an error.
     */
    private function mirrorToLegacyLoginLog(string $message, ?int $userId): void
    {
        $logDir = defined('_CENTREON_LOG_') ? _CENTREON_LOG_ : '/var/log/centreon';
        // Neutralize line breaks and the field delimiter so a crafted message (e.g. a
        // login containing CRLF) cannot forge or split records in the pipe-delimited file,
        // while keeping the historical backtick-strip / asterisk-escape behavior.
        $sanitizedMessage = str_replace(
            ["\r", "\n", '|', '`', '*'],
            [' ', ' ', ' ', '', '\*'],
            $message
        );
        $line = date('Y-m-d H:i:s') . '|' . ($userId ?? 0) . '|0|0|' . $sanitizedMessage;

        try {
            $written = @file_put_contents($logDir . '/login.log', $line . "\n", FILE_APPEND | LOCK_EX);
            if ($written === false) {
                error_log(sprintf('LoggerAuthentication: unable to mirror login event to %s/login.log', $logDir));
            }
        } catch (\Throwable $e) {
            error_log(sprintf('LoggerAuthentication: unable to mirror login event to login.log: %s', $e->getMessage()));
        }
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
        if ($provider instanceof AuthProviderEnum) {
            $context['provider'] = $provider->value;
        }
        if ($exception instanceof \Throwable) {
            $context['exception'] = $exception;
        }

        return $context;
    }
}
