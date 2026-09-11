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
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * /api/latest is a backward-compatible alias for already-migrated ApiPlatform operations
 * (see LegacyApiPrefixAliasLoader): the exact same Operation object answers both the bare /api
 * prefix and its /api/latest alias, so ApiPlatform's exceptionToStatus — global or per-operation
 * — cannot make the two prefixes disagree on a status code. New clients on bare /api get 422 for
 * a validation error (see config.new/packages/api_platform.yaml); legacy clients on /api/latest
 * must keep getting 400.
 *
 * Builds the response directly, in the same {code, message} shape as
 * LegacyValidationExceptionNormalizer (which still handles the bare /api, 422 case) — going
 * through ApiPlatform's own error pipeline for this one case would mean giving a distinct
 * exception class its own ErrorResource metadata just to be recognized by it.
 */
#[AsEventListener]
final class LegacyValidationStatusListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (! $exception instanceof ValidationException) {
            return;
        }

        if (! str_starts_with($event->getRequest()->getPathInfo(), '/api/latest/')) {
            return;
        }

        $messages = [];
        foreach ($exception->getConstraintViolationList() as $violation) {
            $messages[] = sprintf('[%s] %s', $violation->getPropertyPath(), $violation->getMessage());
        }

        $event->setResponse(new JsonResponse([
            'code' => 400,
            'message' => implode("\n", $messages) . "\n",
        ], Response::HTTP_BAD_REQUEST));
    }
}
