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

use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use Psr\Log\LoggerInterface;

/**
 * Typed reads of the JSON documents a provider returns: whatever the provider
 * (or a server on the way) sends, a wrong type is ignored, never a TypeError.
 */
final class ProviderPayload
{
    /** Shorter tokens are placeholders (self-hosted servers): masking them would garble the message */
    private const MIN_MASKED_TOKEN_LENGTH = 8;

    public static function string(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * A count sent as a JSON number or an integral string; anything else counts as 0.
     */
    public static function tokenCount(mixed $value): int
    {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        } elseif (is_float($value) && $value === floor($value) && $value < \PHP_INT_MAX) {
            $value = (int) $value;
        }

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
     * The message of an {"error": {"message": "..."}} or {"error": "..."} document, or of
     * one with a top-level "message" (Mistral) or "detail" (FastAPI servers), if it has one,
     * without the API token a provider may echo in it. The document may come wrapped in a
     * list (Gemini).
     */
    public static function errorMessage(mixed $data, ApiToken $apiToken): ?string
    {
        if (! is_array($data)) {
            return null;
        }
        if (array_is_list($data) && is_array($data[0] ?? null)) {
            $data = $data[0];
        }

        $error = $data['error'] ?? null;
        $message = is_array($error) ? ($error['message'] ?? null) : $error;
        $message ??= $data['message'] ?? self::detailMessage($data['detail'] ?? null);

        if (! is_string($message) || trim($message) === '') {
            return null;
        }

        return mb_strlen($apiToken->value) >= self::MIN_MASKED_TOKEN_LENGTH
            ? str_replace($apiToken->value, '[API token]', $message)
            : $message;
    }

    /**
     * Only the error message of an error response is reported: the rest of the body
     * is neither sent back to the user (it may come from any server on the way) nor
     * logged (a provider may echo the API key in it), so log its status and size only.
     */
    public static function logErrorResponse(LoggerInterface $logger, int $statusCode, string $body): void
    {
        $logger->warning('The AI provider returned an error response', [
            'status_code' => $statusCode,
            'body_length' => mb_strlen($body),
        ]);
    }

    /**
     * A FastAPI "detail": a message, or the list of validation errors of a 422, each with
     * its "msg" and the "loc" of the field it is about.
     */
    private static function detailMessage(mixed $detail): ?string
    {
        if (! is_array($detail)) {
            return self::string($detail);
        }

        $messages = [];
        foreach ($detail as $error) {
            $error = self::array($error);
            $message = self::string($error['msg'] ?? null);
            if ($message === null) {
                continue;
            }
            if (trim((string) $message) === '') {
                continue;
            }
            $location = array_filter(
                self::array($error['loc'] ?? null),
                static fn (mixed $part): bool => is_string($part) || is_int($part),
            );
            $messages[] = $location !== [] ? implode('.', $location) . ': ' . $message : $message;
        }

        return $messages !== [] ? implode('; ', $messages) : null;
    }
}
