<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Core\Gorgone\Infrastructure\API\CollectorEnrolment;

use Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger;
use Core\Gorgone\Application\Exception\CollectorEnrolmentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers of the collector enrolment routes that change something.
 */
final class CollectorEnrolmentResponses
{
    public static function notFound(): JsonResponse
    {
        return self::error(Response::HTTP_NOT_FOUND, 'Not found');
    }

    /**
     * Runs $action: 204, or the status its refusal says (400, 404, 409), or 500.
     *
     * @param \Closure(): void $action
     * @param string $failure message of an unexpected failure
     */
    public static function run(\Closure $action, string $failure): JsonResponse
    {
        try {
            $action();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (CollectorEnrolmentException $ex) {
            $status = in_array($ex->getCode(), [
                CollectorEnrolmentException::NOT_FOUND,
                CollectorEnrolmentException::CONFLICT,
            ], true) ? $ex->getCode() : Response::HTTP_BAD_REQUEST;

            return self::error($status, $ex->getMessage());
        } catch (\Throwable $ex) {
            ExceptionLogger::create()->log($ex);

            return self::error(Response::HTTP_INTERNAL_SERVER_ERROR, $failure . ': ' . $ex->getMessage());
        }
    }

    public static function error(int $status, string $message): JsonResponse
    {
        return new JsonResponse(['code' => $status, 'message' => $message], $status);
    }
}
