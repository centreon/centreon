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

namespace Core\Gorgone\Application\Repository;

/**
 * What the cron decided for each fleet enrolment (table collector_enrolment): the name of its
 * poller, written before the poller is created, then the poller id. A cron that dies between the
 * creation of the poller and its assignment finds the same poller again at its next run.
 */
interface CollectorEnrolmentRecordRepositoryInterface
{
    /**
     * Take the lock of the cron run: false when another run (another replica, a manual run) holds it.
     *
     * @throws \Throwable
     */
    public function lock(): bool;

    public function unlock(): void;

    /**
     * @throws \Throwable
     *
     * @return array<string, array{name: string, address: string, pollerId: int|null}> by gorgone
     *                                                                                  enrolment id
     */
    public function findAll(): array;

    /**
     * Record the poller chosen for an enrolment, before it is created. A record already there is kept.
     *
     * @param string $enrolmentId
     * @param string $name
     * @param string $address
     *
     * @throws \Throwable
     */
    public function add(string $enrolmentId, string $name, string $address): void;

    /**
     * @param string $enrolmentId
     * @param int $pollerId
     *
     * @throws \Throwable
     */
    public function setPoller(string $enrolmentId, int $pollerId): void;
}
