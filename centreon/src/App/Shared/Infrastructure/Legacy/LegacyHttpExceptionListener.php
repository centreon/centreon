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

namespace App\Shared\Infrastructure\Legacy;

use ApiPlatform\Validator\Exception\ValidationException;
use Centreon\Domain\Exception\EntityNotFoundException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsEventListener]
final readonly class LegacyHttpExceptionListener
{
    /**
     * @var array<class-string, int>
     */
    private const LEGACY_EXCEPTION_TO_STATUS = [
        AccessDeniedException::class => 403,
        EntityNotFoundException::class => 404,
        MethodNotAllowedHttpException::class => 404,
    ];

    /**
     * @param array<class-string, int> $exceptionToStatus
     */
    public function __construct(
        #[Autowire(param: 'api_platform.exception_to_status')]
        private array $exceptionToStatus,
        #[Autowire(service: 'monolog.logger.request')]
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // this case is handled by LegacyValidationExceptionNormalizer
        if ($exception instanceof ValidationException) {
            return;
        }

        // get status code from exception
        $statusCode = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : null;

        // try to override status code by API Platform
        foreach ($this->exceptionToStatus as $class => $status) {
            if (is_a($exception::class, $class, true)) {
                $statusCode = $status;

                break;
            }
        }

        // if still no status code, try to fetch it from legacy exception mapping
        if (! $statusCode) {
            foreach (self::LEGACY_EXCEPTION_TO_STATUS as $class => $status) {
                if (is_a($exception::class, $class, true)) {
                    $statusCode = $status;

                    break;
                }
            }
        }

        // if still no status code, compute it from the exception (legacy way)
        if (! $statusCode) {
            $errorCode = $exception->getCode();
            $statusCode = (is_int($errorCode) && $errorCode >= 100 && $errorCode < 600) ? $errorCode : 500;
        }

        // Symfony's ErrorListener::logKernelException would otherwise log the
        // exception on the `request` channel, but `RequestEvent::setResponse()`
        // stops propagation as soon as we set the response below — the
        // platform pipeline would then silently swallow the exception.
        // Log it ourselves first so it lands in `prod.web.log` through the
        // ExceptionFormatterProcessor / WebProcessor stack.
        $this->logException($exception, $statusCode);

        $event->setResponse(new JsonResponse(
            status: $statusCode,
            data: [
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
            ],
        ));
    }

    private function logException(\Throwable $exception, int $statusCode): void
    {
        $level = $statusCode >= 500 ? LogLevel::CRITICAL : LogLevel::WARNING;

        $message = sprintf(
            'Uncaught exception %s: "%s" at %s line %d',
            $exception::class,
            $exception->getMessage(),
            basename($exception->getFile()),
            $exception->getLine(),
        );

        $this->logger->log($level, $message, ['exception' => $exception]);
    }
}
