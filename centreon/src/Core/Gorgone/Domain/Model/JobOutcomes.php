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
 * Outcomes of a set of Gorgone jobs, seen as one operation.
 */
final class JobOutcomes
{
    /** @var array<string, JobOutcome> */
    private array $outcomes = [];

    /**
     * @param iterable<JobOutcome> $outcomes
     */
    public function __construct(iterable $outcomes = [])
    {
        foreach ($outcomes as $outcome) {
            $this->outcomes[$outcome->getJobId()] = $outcome;
        }
    }

    /**
     * Outcomes of some of the jobs; the ids of the jobs which were not waited for are ignored.
     *
     * @param list<string> $jobIds
     */
    public function of(array $jobIds): self
    {
        return new self(array_intersect_key($this->outcomes, array_flip($jobIds)));
    }

    /**
     * @return list<JobOutcome>
     */
    public function all(): array
    {
        return array_values($this->outcomes);
    }

    /**
     * Indicates whether at least one job failed, expired or was cancelled.
     */
    public function isFailed(): bool
    {
        return $this->getFailures() !== [];
    }

    /**
     * Indicates whether no job failed and at least one is not finished yet.
     */
    public function isPending(): bool
    {
        if ($this->isFailed()) {
            return false;
        }
        foreach ($this->outcomes as $outcome) {
            if ($outcome->isPending()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Explanation of the first failure, empty when no job failed.
     */
    public function getFailureMessage(): string
    {
        $failures = $this->getFailures();

        return $failures === [] ? '' : $failures[0]->getFailureMessage();
    }

    /**
     * @return list<JobOutcome>
     */
    private function getFailures(): array
    {
        return array_values(array_filter(
            $this->outcomes,
            static fn (JobOutcome $outcome): bool => $outcome->isFailed()
        ));
    }
}
