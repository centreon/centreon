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
 * State of a Gorgone job, with the explanation of its failure.
 */
final class JobOutcome
{
    public const STATE_QUEUED = 'queued';
    public const STATE_DISPATCHED = 'dispatched';
    public const STATE_RUNNING = 'running';
    public const STATE_SUCCEEDED = 'succeeded';
    public const STATE_FAILED = 'failed';
    public const STATE_EXPIRED = 'expired';
    public const STATE_CANCELLED = 'cancelled';
    private const PENDING_STATES = [self::STATE_QUEUED, self::STATE_DISPATCHED, self::STATE_RUNNING];
    private const TERMINAL_STATES = [
        self::STATE_SUCCEEDED,
        self::STATE_FAILED,
        self::STATE_EXPIRED,
        self::STATE_CANCELLED,
    ];

    /**
     * @param string $jobId
     * @param string $state
     * @param string $message message of the last event of the job
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        private readonly string $jobId,
        private readonly string $state,
        private readonly string $message = '',
    ) {
        if (! in_array($state, [...self::PENDING_STATES, ...self::TERMINAL_STATES], true)) {
            throw new \InvalidArgumentException(sprintf('Unknown Gorgone job state "%s"', $state));
        }
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function isPending(): bool
    {
        return in_array($this->state, self::PENDING_STATES, true);
    }

    public function isSucceeded(): bool
    {
        return $this->state === self::STATE_SUCCEEDED;
    }

    public function isFailed(): bool
    {
        return ! $this->isPending() && ! $this->isSucceeded();
    }

    /**
     * Explanation of a failure: the message of the job when it has one, its state otherwise.
     */
    public function getFailureMessage(): string
    {
        return $this->message !== '' ? $this->message : $this->state;
    }
}
