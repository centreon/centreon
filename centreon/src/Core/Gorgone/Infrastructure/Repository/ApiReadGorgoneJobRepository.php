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

namespace Core\Gorgone\Infrastructure\Repository;

use Core\Gorgone\Application\Exception\GorgoneCommandException;
use Core\Gorgone\Application\Repository\ReadGorgoneJobRepositoryInterface;
use Core\Gorgone\Domain\Model\JobOutcome;

/**
 * Reads the jobs from the native REST API ("/v1") of Gorgone.
 */
final class ApiReadGorgoneJobRepository extends AbstractApiGorgoneRepository implements ReadGorgoneJobRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function findOutcome(string $jobId, int $waitSeconds): JobOutcome
    {
        $description = sprintf('status of job %s', $jobId);
        $waitSeconds = max(0, $waitSeconds);
        // Gorgone answers when the job is finished or the wait elapsed: the request lasts that long.
        $timeout = $waitSeconds + $this->getCommandTimeout();

        [$statusCode, $body] = $this->call(
            'GET',
            'jobs/' . rawurlencode($jobId),
            ['query' => ['wait' => $waitSeconds], 'timeout' => $timeout, 'max_duration' => $timeout],
            $description
        );

        $state = $body['state'] ?? null;
        if ($statusCode !== 200 || ! is_string($state)) {
            throw self::createUnexpectedResponse($description, $statusCode, $body);
        }

        try {
            return new JobOutcome($jobId, $state, self::findLastMessage($body['events'] ?? null));
        } catch (\InvalidArgumentException $ex) {
            throw GorgoneCommandException::unexpectedResponse($description, $statusCode, $ex->getMessage());
        }
    }

    /**
     * @param mixed $events
     *
     * @return string message of the last event, empty when it has none
     */
    private static function findLastMessage(mixed $events): string
    {
        if (! is_array($events) || $events === []) {
            return '';
        }
        $event = end($events);
        $message = is_array($event) && is_array($event['data'] ?? null) ? ($event['data']['message'] ?? null) : null;

        return is_string($message) ? trim($message) : '';
    }
}
