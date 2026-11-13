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

namespace Core\Gorgone\Application\Repository;

use Core\Gorgone\Application\Exception\GorgoneCommandException;
use Core\Gorgone\Domain\Model\JobOutcome;

interface ReadGorgoneJobRepositoryInterface
{
    /**
     * Find the outcome of a job, waiting for the job to finish for at most the given time.
     *
     * @param string $jobId
     * @param int $waitSeconds 0 to get the current state at once
     *
     * @throws GorgoneCommandException
     *
     * @return JobOutcome
     */
    public function findOutcome(string $jobId, int $waitSeconds): JobOutcome;
}
