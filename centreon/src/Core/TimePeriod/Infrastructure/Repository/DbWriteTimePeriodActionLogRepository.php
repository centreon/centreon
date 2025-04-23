<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\TimePeriod\Infrastructure\Repository;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Infrastructure\DatabaseConnection;
use Core\ActionLog\Application\Repository\WriteActionLogRepositoryInterface;
use Core\ActionLog\Domain\Model\ActionLog;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\TimePeriod\Application\Repository\WriteTimePeriodRepositoryInterface;
use Core\TimePeriod\Domain\Model\Day;
use Core\TimePeriod\Domain\Model\{NewExtraTimePeriod, NewTimePeriod, Template, TimePeriod};

class DbWriteTimePeriodActionLogRepository extends AbstractRepositoryRDB implements WriteTimePeriodRepositoryInterface
{
    use LoggerTrait;
    public const TIMEPERIOD_OBJECT_TYPE = 'timeperiod';

    public function __construct(
        private readonly WriteTimePeriodRepositoryInterface $writeTimePeriodRepository,
        private readonly ReadTimePeriodRepositoryInterface $readTimePeriodRepository,
        private readonly ContactInterface $contact,
        private readonly WriteActionLogRepositoryInterface $writeActionLogRepository,
        DatabaseConnection $db
    ) {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function delete(int $timePeriodId): void
    {
        try {
            $timePeriod = $this->readTimePeriodRepository->findById($timePeriodId);
            if ($timePeriod === null) {
                throw new RepositoryException('Cannot find timeperiod to delete');
            }

            $this->writeTimePeriodRepository->delete($timePeriodId);

            $actionLog = new ActionLog(
                self::TIMEPERIOD_OBJECT_TYPE,
                $timePeriodId,
                $timePeriod->getName(),
                ActionLog::ACTION_TYPE_DELETE,
                $this->contact->getId()
            );
            $this->writeActionLogRepository->addAction($actionLog);
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function add(NewTimePeriod $timePeriod): int
    {
        try {
            $timePeriodId = $this->writeTimePeriodRepository->add($timePeriod);
            if ($timePeriodId === 0) {
                throw new RepositoryException('Timeperiod ID cannot be 0');
            }

            $actionLog = new ActionLog(
                self::TIMEPERIOD_OBJECT_TYPE,
                $timePeriodId,
                $timePeriod->getName(),
                ActionLog::ACTION_TYPE_ADD,
                $this->contact->getId()
            );

            $actionLogId = $this->writeActionLogRepository->addAction($actionLog);
            $actionLog->setId($actionLogId);

            $details = $this->getTimePeriodPropertiesAsArray($timePeriod);
            $this->writeActionLogRepository->addActionDetails($actionLog, $details);

            return $timePeriodId;
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function update(TimePeriod $timePeriod): void
    {
        try {
            $currentTimePeriod = $this->readTimePeriodRepository->findById($timePeriod->getId());
            if ($currentTimePeriod === null) {
                throw new RepositoryException('Cannot find timeperiod to update');
            }
            $currentTimePeriodDetails = $this->getTimePeriodPropertiesAsArray($currentTimePeriod);
            $updatedTimePeriodDetails = $this->getTimePeriodPropertiesAsArray($timePeriod);
            $diff = array_diff_assoc($updatedTimePeriodDetails, $currentTimePeriodDetails);

            $this->writeTimePeriodRepository->update($timePeriod);

            $actionLog = new ActionLog(
                self::TIMEPERIOD_OBJECT_TYPE,
                $timePeriod->getId(),
                $timePeriod->getName(),
                ActionLog::ACTION_TYPE_CHANGE,
                $this->contact->getId()
            );
            $actionLogId = $this->writeActionLogRepository->addAction($actionLog);
            if ($actionLogId === 0) {
                throw new RepositoryException('Action log ID cannot be 0');
            }
            $actionLog->setId($actionLogId);
            $this->writeActionLogRepository->addActionDetails($actionLog, $updatedTimePeriodDetails);
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    }

    /**
     * @param NewTimePeriod|TimePeriod $timePeriod
     *
     * @return array<string,string|int|bool>
     */
    private function getTimePeriodPropertiesAsArray(NewTimePeriod|TimePeriod $timePeriod): array
    {
        $timePeriodAsArray = [];
        $timePeriodReflection = new \ReflectionClass($timePeriod);

        foreach ($timePeriodReflection->getProperties() as $property) {
            $value = $property->getValue($timePeriod);
            if ($value === null) {
                $value = '';
            }

            if (is_array($value)) {
                if ($value === []) {
                    $value = '';
                } elseif ($value[0] instanceof Day) {
                    $days = [];
                    foreach ($value as $day) {
                        $dayAsString = match ($day->getDay()) {
                            1 => 'monday',
                            2 => 'tuesday',
                            3 => 'wednesday',
                            4 => 'thursday',
                            5 => 'friday',
                            6 => 'saturday',
                            7 => 'sunday',
                            default => throw new RepositoryException('Should never happen')
                        };
                        $days[$dayAsString] = $day->getTimeRange()->__toString();
                    }
                    $value = $days;
                } elseif ($value[0] instanceof NewExtraTimePeriod) {
                    $exceptions = [
                        'nbOfExceptions' => count($value),
                    ];
                    foreach ($value as $key => $extra) {
                        $exceptions["exceptionInput_{$key}"] = $extra->getDayRange();
                        $exceptions["exceptionTimerange_{$key}"] = $extra->getTimeRange()->__toString();
                    }
                    $value = $exceptions;
                } elseif ($value[0] instanceof Template) {
                    $value = implode(
                        ',',
                        array_map(
                            fn(Template $tpl) => $tpl->getId(),
                            $value
                        )
                    );
                } elseif (is_int($value[0])) {
                    $value = implode(
                        ',',
                        $value
                    );
                }
            }

            if (is_array($value)) {
                $timePeriodAsArray = array_merge($timePeriodAsArray, $value);
            } else {
                $timePeriodAsArray[$property->getName()] = $value;
            }
        }

        return $timePeriodAsArray;
    }
}
