<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\Common\Domain\Exception;

/**
 * Class.
 *
 * @class ExceptionFormatter
 */
abstract class ExceptionFormatter
{
    /**
     * @param \Throwable $throwable
     *
     * @return array<string,mixed>
     */
    public static function format(\Throwable $throwable): array
    {
        return self::getExceptionInfos($throwable);
    }

    /**
     * @param \Throwable $throwable
     *
     * @return array<string,mixed>
     */
    private static function getExceptionInfos(\Throwable $throwable): array
    {
        return [
            'type' => $throwable::class,
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'code' => $throwable->getCode(),
            'class' => $throwable->getTrace()[0]['class'] ?? null,
            'method' => $throwable->getTrace()[0]['function'] ?? null,
            'previous' => self::getPreviousInfos($throwable),
        ];
    }

    /**
     * @param \Throwable $throwable
     *
     * @return array<string,mixed>|null
     */
    private static function getPreviousInfos(\Throwable $throwable): ?array
    {
        $previousContext = null;
        if ($throwable->getPrevious() !== null) {
            $previousContext = self::getExceptionInfos($throwable->getPrevious());
        }

        return $previousContext;
    }
}
