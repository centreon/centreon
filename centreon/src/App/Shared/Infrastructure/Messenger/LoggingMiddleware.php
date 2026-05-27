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

namespace App\Shared\Infrastructure\Messenger;

use App\Shared\Infrastructure\Logging\ExceptionFormatter;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final readonly class LoggingMiddleware implements MiddlewareInterface
{
    /**
     * Matched via str_contains on the lowercased payload key — intentionally
     * permissive: any field name that *contains* one of these tokens is
     * masked (e.g. `oauth_authorization_url`, `password_changed_at`,
     * `customer_token_id`). Over-masking is preferred to under-masking so a
     * variant we did not anticipate can't leak a real secret. A future
     * `#[Sensitive]` / `#[NotSensitive]` attribute layer will let callers
     * override per-property (tracked in MON-199097).
     */
    private const SENSITIVE_KEYWORDS = ['password', 'token', 'secret', 'api_key', 'authorization', 'credential', 'private_key'];
    private const MAX_DEPTH = 3;
    private const MAX_VALUE_LENGTH = 1024;

    public function __construct(
        #[Autowire(service: 'monolog.logger.bus')]
        private LoggerInterface $logger,
        private NormalizerInterface $normalizer,
    ) {
    }

    /**
     * @throws \Throwable
     * @throws ExceptionInterface
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $class = $message::class;
        $busType = $this->resolveBusType($envelope);
        $payload = $this->normalizePayload($message);
        // Correlation id only — shared across the three logs of one dispatch,
        // never a secret. uniqid() with extra entropy is time-based, fast and
        // never throws (unlike random_bytes), which is all a log correlator needs.
        $dispatchId = uniqid('', true);
        // hrtime(true) is monotonic and unaffected by NTP adjustments, the
        // only suitable clock for a duration measured around an I/O-bound
        // dispatch. Microtime would drift if the system time stepped
        // during the handle().
        $startedAt = hrtime(true);

        $this->logger->info(sprintf('Dispatching %s %s', $busType, $class), [
            'dispatch_id' => $dispatchId,
            'bus_type' => $busType,
            'handler_message' => $class,
            'payload' => $payload,
        ]);

        try {
            $result = $stack->next()->handle($envelope, $stack);

            $this->logger->info(sprintf('Handled %s %s', $busType, $class), [
                'dispatch_id' => $dispatchId,
                'bus_type' => $busType,
                'handler_message' => $class,
                'handlers' => $this->collectHandlers($result),
                'duration_ms' => $this->elapsedMs($startedAt),
                'payload' => $payload,
            ]);

            return $result;
        } catch (\Throwable $exception) {
            $this->logger->log($this->resolveFailureLevel($exception), sprintf('Failed to handle %s %s', $busType, $class), [
                'dispatch_id' => $dispatchId,
                'bus_type' => $busType,
                'handler_message' => $class,
                'duration_ms' => $this->elapsedMs($startedAt),
                'payload' => $payload,
                'exception' => ExceptionFormatter::format($exception),
            ]);

            throw $exception;
        }
    }

    /**
     * A value-object / domain rejection surfaces as \InvalidArgumentException
     * (Centreon's AssertionException extends it) and maps to a 4xx response —
     * expected client input, logged as a warning. Anything else is an
     * unexpected server-side failure (DB down, OOM, bug) and is critical,
     * mirroring LegacyHttpExceptionListener's CRITICAL-vs-WARNING split.
     */
    private function resolveFailureLevel(\Throwable $exception): string
    {
        return $exception instanceof \InvalidArgumentException
            ? LogLevel::WARNING
            : LogLevel::CRITICAL;
    }

    /**
     * @param int $startedAt monotonic hrtime(true) reading taken at dispatch
     *
     * @return float milliseconds elapsed, rounded to 3 decimals
     */
    private function elapsedMs(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1_000_000, 3);
    }

    /**
     * @return list<string> handler names produced by Messenger HandledStamps —
     *                      one entry per handler that actually ran. Single-handler
     *                      buses (command/query) yield a list of size 1; an event
     *                      bus may yield several. Empty when no handler ran (e.g.
     *                      a transport-only middleware short-circuited).
     */
    private function collectHandlers(Envelope $envelope): array
    {
        $names = [];
        foreach ($envelope->all(HandledStamp::class) as $stamp) {
            \assert($stamp instanceof HandledStamp);
            $names[] = $stamp->getHandlerName();
        }

        return $names;
    }

    private function resolveBusType(Envelope $envelope): string
    {
        $stamp = $envelope->last(BusNameStamp::class);

        if (! $stamp instanceof BusNameStamp) {
            return 'unknown';
        }

        $busName = $stamp->getBusName();

        // str_starts_with rather than str_contains to keep the classification
        // tight: a future bus named `command_audit.bus` or `query_proxy.bus`
        // should be classified by its prefix, not silently folded into the
        // base type. Unknown patterns fall through to the raw bus name.
        return match (true) {
            str_starts_with($busName, 'command') => 'command',
            str_starts_with($busName, 'query') => 'query',
            default => $busName,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePayload(object $message): array
    {
        try {
            $data = $this->normalizer->normalize($message);
        } catch (\Throwable $e) {
            // Surface the normalisation failure so an operator reading
            // bus.WARN sees that the payload was reduced to a placeholder
            // (and why), rather than an inexplicably empty payload on a
            // dispatch info line.
            $this->logger->warning('Normalizer failed for ' . $message::class, [
                'handler_message' => $message::class,
                'exception' => ExceptionFormatter::format($e),
            ]);

            return ['__class' => $message::class];
        }

        if (! \is_array($data)) {
            $this->logger->warning('Normalizer returned a non-array value for ' . $message::class, [
                'handler_message' => $message::class,
                'returned_type' => get_debug_type($data),
            ]);

            return ['__class' => $message::class];
        }

        /** @var array<string, mixed> */
        return $this->sanitize($data, 0);
    }

    private function sanitize(mixed $data, int $depth): mixed
    {
        if ($depth >= self::MAX_DEPTH) {
            return '{…}';
        }

        if (\is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                if (\is_string($key) && $this->isSensitiveKey($key)) {
                    $result[$key] = '***';

                    continue;
                }
                $result[$key] = $this->sanitize($value, $depth + 1);
            }

            return $result;
        }

        if (\is_object($data)) {
            return $this->sanitizeObject($data);
        }

        if (\is_string($data) && \mb_strlen($data) > self::MAX_VALUE_LENGTH) {
            return mb_substr($data, 0, self::MAX_VALUE_LENGTH) . '…[truncated]';
        }

        return $data;
    }

    /**
     * Defense-in-depth for objects NormalizerInterface would otherwise
     * pass through as-is (DateTime without a dedicated normalizer, Enum,
     * VO without a custom normalizer). Without this branch, a nested
     * object could carry an unmasked sensitive property all the way down
     * to the Monolog LineFormatter.
     */
    private function sanitizeObject(object $data): mixed
    {
        if ($data instanceof \BackedEnum) {
            return $data->value;
        }

        if ($data instanceof \UnitEnum) {
            return $data->name;
        }

        if ($data instanceof \DateTimeInterface) {
            return $data->format(\DateTimeInterface::ATOM);
        }

        if ($data instanceof \Stringable) {
            $string = (string) $data;

            return \mb_strlen($string) > self::MAX_VALUE_LENGTH
                ? mb_substr($string, 0, self::MAX_VALUE_LENGTH) . '…[truncated]'
                : $string;
        }

        return '{' . $data::class . '}';
    }

    private function isSensitiveKey(string $key): bool
    {
        $lower = mb_strtolower($key);

        foreach (self::SENSITIVE_KEYWORDS as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
