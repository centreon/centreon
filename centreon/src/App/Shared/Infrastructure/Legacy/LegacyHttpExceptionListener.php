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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

#[AsEventListener]
final readonly class LegacyHttpExceptionListener
{
    /**
     * @param array<class-string, int> $exceptionToStatus
     */
    public function __construct(
        #[Autowire(param: 'api_platform.exception_to_status')]
        private array $exceptionToStatus,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (! $exception instanceof HttpExceptionInterface) {
            return;
        }

        $statusCode = $exception->getStatusCode();
        foreach ($this->exceptionToStatus as $class => $status) {
            if (is_a($exception::class, $class, true)) {
                $statusCode = $status;

                break;
            }
        }

        // this case is handled by LegacyValidationExceptionNormalizer
        if ($exception instanceof ValidationException) {
            return;
        }

        $event->setResponse(new JsonResponse(
            status: $statusCode,
            data: [
                'code' => $statusCode,
                'message' => $exception->getMessage(),
            ],
        ));
    }
}
