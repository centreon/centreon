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
use App\Shared\Infrastructure\Logging\LogPayloadNormalizer;
use App\Shared\Infrastructure\Logging\NonArrayNormalizationException;
use App\Shared\Infrastructure\Logging\PayloadSanitizer;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private LogPayloadNormalizer $payloadNormalizer,
        private PayloadSanitizer $sanitizer,
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $class = $message::class;
        $busType = $this->resolveBusType($envelope);
        $payload = $this->normalizePayload($message);
        $dispatchId = uniqid('', true);
        // hrtime(true): monotonic, NTP-immune — required for duration sampling.
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
     * @return list<string> handler names from HandledStamps in the envelope
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

        return $stamp instanceof BusNameStamp ? $stamp->getBusName() : 'unknown';
    }

    /**
     * @return array<string, mixed> the normalised payload, or a class
     *                              placeholder + warning if normalisation failed
     */
    private function normalizePayload(object $message): array
    {
        try {
            $payload = $this->payloadNormalizer->normalize($message);
        } catch (NonArrayNormalizationException $e) {
            $this->logger->warning('Normalizer returned a non-array value for ' . $message::class, [
                'handler_message' => $message::class,
                'returned_type' => $e->returnedType,
            ]);

            return ['__class' => $message::class];
        } catch (\Throwable $e) {
            $this->logger->warning('Normalizer failed for ' . $message::class, [
                'handler_message' => $message::class,
                'exception' => ExceptionFormatter::format($e),
            ]);

            return ['__class' => $message::class];
        }

        /** @var array<string, mixed> */
        return $this->sanitizer->sanitize($payload, $message::class);
    }
}
