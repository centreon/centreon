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

namespace Core\Gorgone\Domain\Model;

/**
 * Result of the verification of the engine configuration of a poller, made by the poller itself.
 */
final class ConfigurationValidation
{
    /** The engine of the poller accepted the configuration. */
    public const STATUS_VALID = 'valid';

    /** The engine of the poller refused the configuration. */
    public const STATUS_INVALID = 'invalid';

    /** The poller did not answer in time: it verifies the configuration when it applies it. */
    public const STATUS_UNVERIFIED = 'unverified';

    /** The verification could not be requested, or its result could not be read. */
    public const STATUS_ERROR = 'error';

    /**
     * @param int $pollerId
     * @param string $status
     * @param string $output output of the engine checker, empty when it did not run
     * @param string $message summary of the result, or explanation of the error
     * @param int $exitCode exit code of the engine checker, 0 when the configuration is valid
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        private readonly int $pollerId,
        private readonly string $status,
        private readonly string $output = '',
        private readonly string $message = '',
        private readonly int $exitCode = 0,
    ) {
        if (
            ! in_array(
                $status,
                [self::STATUS_VALID, self::STATUS_INVALID, self::STATUS_UNVERIFIED, self::STATUS_ERROR],
                true
            )
        ) {
            throw new \InvalidArgumentException(sprintf('Unknown configuration validation status "%s"', $status));
        }
    }

    public function getPollerId(): int
    {
        return $this->pollerId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isValid(): bool
    {
        return $this->status === self::STATUS_VALID;
    }

    public function isInvalid(): bool
    {
        return $this->status === self::STATUS_INVALID;
    }

    public function isUnverified(): bool
    {
        return $this->status === self::STATUS_UNVERIFIED;
    }

    public function isError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    /**
     * Indicates whether the engine checker ran: its output and exit code are then meaningful.
     */
    public function isVerified(): bool
    {
        return $this->isValid() || $this->isInvalid();
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return int never 0 for a refused configuration
     */
    public function getExitCode(): int
    {
        return $this->isInvalid() && $this->exitCode === 0 ? 1 : $this->exitCode;
    }
}
