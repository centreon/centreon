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

namespace App\AiConfiguration\Domain\Llm\Exception;

use App\Shared\Domain\Exception\ExpectedFailureInterface;

/**
 * The provider could not be reached, or rejected the request.
 */
final class LlmRequestFailedException extends LlmException implements ExpectedFailureInterface
{
    public static function fromTransportError(string $message, ?\Throwable $previous = null): self
    {
        return new self(sprintf('The AI provider can not be reached: %s', $message), 0, $previous);
    }

    public static function fromTimeout(int $timeoutSeconds, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf(
                'The AI provider sent nothing for %d seconds: the model may be busy, throttled or not '
                . 'available to this account. Try again later, or choose another model.',
                $timeoutSeconds
            ),
            0,
            $previous
        );
    }

    public static function fromEmptyAnswer(bool $truncated, int $maxTokens): self
    {
        if ($truncated) {
            return new self(sprintf(
                'The model used its whole budget of %d tokens without writing an answer, probably '
                . 'reasoning. Choose another model.',
                $maxTokens
            ));
        }

        return new self('The model returned an empty answer. Try again, or choose another model.');
    }

    public static function fromRefusal(string $reason): self
    {
        return new self(sprintf('The AI provider refused to answer: %s', $reason));
    }

    public static function fromProviderError(int $statusCode, string $message): self
    {
        return new self(
            sprintf('The AI provider rejected the request (HTTP %d): %s', $statusCode, $message),
            $statusCode
        );
    }

    public static function fromInvalidResponse(string $details, ?\Throwable $previous = null): self
    {
        return new self(sprintf('The AI provider returned an unexpected response: %s', $details), 0, $previous);
    }
}
