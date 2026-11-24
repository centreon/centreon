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

namespace App\AiConfiguration\Infrastructure\Llm;

use Psr\Log\LoggerInterface;

/**
 * Typed reads of the JSON documents a provider returns: whatever the provider
 * (or a server on the way) sends, a wrong type is ignored, never a TypeError.
 */
final class ProviderPayload
{
    private const BODY_EXCERPT_LENGTH = 200;

    public static function string(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    public static function tokenCount(mixed $value): int
    {
        return is_int($value) && $value >= 0 ? $value : 0;
    }

    /**
     * @return array<mixed>
     */
    public static function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * The message of an {"error": {"message": "..."}} or {"error": "..."}
     * document, if it has one.
     */
    public static function errorMessage(mixed $data): ?string
    {
        $error = is_array($data) ? ($data['error'] ?? null) : null;
        $message = is_array($error) ? ($error['message'] ?? null) : $error;

        return is_string($message) && trim($message) !== '' ? $message : null;
    }

    /**
     * The body of an error response is not sent back to the user (it may come from
     * any server on the way): log an excerpt server-side, to diagnose it.
     */
    public static function logErrorResponse(LoggerInterface $logger, int $statusCode, string $body): void
    {
        $logger->warning('The AI provider returned an error response', [
            'status_code' => $statusCode,
            'body_excerpt' => mb_substr($body, 0, self::BODY_EXCERPT_LENGTH),
        ]);
    }
}
