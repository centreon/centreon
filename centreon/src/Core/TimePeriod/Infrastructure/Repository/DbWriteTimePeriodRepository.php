<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\TimePeriod\Application\Repository\WriteTimePeriodRepositoryInterface;
use Core\TimePeriod\Domain\Model\{ExtraTimePeriod, NewExtraTimePeriod, NewTimePeriod, Template, TimePeriod};

class DbWriteTimePeriodRepository extends AbstractRepositoryRDB implements WriteTimePeriodRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function add(NewTimePeriod $newTimePeriod): int
    {
        $alreadyInTransaction = $this->db->inTransaction();
        if (! $alreadyInTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $statement = $this->db->prepare(
                $this->translateDbName(
                    <<<'SQL'
                        INSERT INTO `:db`.timeperiod
                        (tp_name, tp_alias, tp_sunday, tp_monday, tp_tuesday, tp_wednesday,
                        tp_thursday, tp_friday, tp_saturday)
                        VALUES
                        (:name, :alias, :sunday, :monday, :tuesday, :wednesday, :thursday, :friday, :saturday)
                        SQL
                )
            );
            $this->bindValueOfTimePeriod($statement, $newTimePeriod);
            $statement->execute();
            $newTimePeriodId = (int) $this->db->lastInsertId();

            $this->addTimePeriodTemplates($newTimePeriodId, $newTimePeriod->getTemplates());
            $this->addExtraTimePeriods($newTimePeriodId, $newTimePeriod->getExtraTimePeriods());

            if (! $alreadyInTransaction) {
                $this->db->commit();
            }

            return $newTimePeriodId;
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            if (! $alreadyInTransaction) {
                $this->db->rollBack();
            }

            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(int $timePeriodId): void
    {
        $statement = $this->db->prepare(
            $this->translateDbName('DELETE FROM `:db`.timeperiod WHERE tp_id = :id')
        );
        $statement->bindValue(':id', $timePeriodId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function update(TimePeriod $timePeriod): void
    {
        $alreadyInTransaction = $this->db->inTransaction();
        if (! $alreadyInTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $statement = $this->db->prepare(
                $this->translateDbName(
                    <<<'SQL'
                        UPDATE `:db`.timeperiod
                        SET tp_name = :name,
                            tp_alias = :alias,
                            tp_monday = :monday,
                            tp_tuesday = :tuesday,
                            tp_wednesday = :wednesday,
                            tp_thursday = :thursday,
                            tp_friday = :friday,
                            tp_saturday = :saturday,
                            tp_sunday = :sunday
                        WHERE tp_id = :id
                        SQL
                )
            );
            $this->bindValueOfTimePeriod($statement, $timePeriod);
            $statement->bindValue(':id', $timePeriod->getId(), \PDO::PARAM_INT);
            $statement->execute();

            $this->deleteExtraTimePeriods($timePeriod->getId());
            $this->deleteTimePeriodTemplates($timePeriod->getId());

            $templateIds = array_map(fn (Template $template): int => $template->getId(), $timePeriod->getTemplates());
            $this->addTimePeriodTemplates($timePeriod->getId(), $templateIds);
            $this->addExtraTimePeriods($timePeriod->getId(), $timePeriod->getExtraTimePeriods());

            if (! $alreadyInTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            if (! $alreadyInTransaction) {
                $this->db->rollBack();
            }

            throw $ex;
        }
    }

    /**
     * @param int $timePeriodId
     * @param list<ExtraTimePeriod|NewExtraTimePeriod> $extraTimePeriods
     *
     * @throws \PDOException
     */
    private function addExtraTimePeriods(int $timePeriodId, array $extraTimePeriods): void
    {
        if ($extraTimePeriods === []) {
            return;
        }
        $subRequest = [];
        $bindValues = [];
        foreach ($extraTimePeriods as $index => $extraTimePeriod) {
            $subRequest[$index] = "(:timeperiod_id_{$index}, :days_{$index}, :timerange_{$index})";
            $bindValues[$index]['day'] = $extraTimePeriod->getDayRange();
            $bindValues[$index]['timerange'] = $extraTimePeriod->getTimeRange();
        }
        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                    INSERT INTO `:db`.timeperiod_exceptions
                    (timeperiod_id, days, timerange)
                    VALUES
                    SQL . implode(', ', $subRequest)
            )
        );
        foreach ($bindValues as $index => $extraTimePeriod) {
            $statement->bindValue(':timeperiod_id_' . $index, $timePeriodId, \PDO::PARAM_INT);
            $statement->bindValue(':days_' . $index, $extraTimePeriod['day']);
            $statement->bindValue(':timerange_' . $index, $extraTimePeriod['timerange']);
        }
        $statement->execute();
    }

    /**
     * @param int $timePeriodId
     * @param list<int> $templateIds
     *
     * @throws \PDOException
     */
    private function addTimePeriodTemplates(int $timePeriodId, array $templateIds): void
    {
        if ($templateIds === []) {
            return;
        }
        $subRequest = [];
        $bindValues = [];
        foreach ($templateIds as $index => $templateId) {
            $subRequest[$index] = "(:timeperiod_id_{$index}, :template_id_{$index})";
            $bindValues[$index] = $templateId;
        }

        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                    INSERT INTO `:db`.timeperiod_include_relations
                    (timeperiod_id, timeperiod_include_id)
                    VALUES
                    SQL . implode(', ', $subRequest)
            )
        );
        foreach ($bindValues as $index => $templateId) {
            $statement->bindValue(':timeperiod_id_' . $index, $timePeriodId, \PDO::PARAM_INT);
            $statement->bindValue(':template_id_' . $index, $templateId, \PDO::PARAM_INT);
        }
        $statement->execute();
    }

    /**
     * @param \PDOStatement $statement
     * @param TimePeriod|NewTimePeriod $timePeriod
     */
    private function bindValueOfTimePeriod(\PDOStatement $statement, TimePeriod|NewTimePeriod $timePeriod): void
    {
        $statement->bindValue(':name', $timePeriod->getName());
        $statement->bindValue(':alias', $timePeriod->getAlias());
        $statement->bindValue(':monday', $this->extractTimeRange($timePeriod, 1));
        $statement->bindValue(':tuesday', $this->extractTimeRange($timePeriod, 2));
        $statement->bindValue(':wednesday', $this->extractTimeRange($timePeriod, 3));
        $statement->bindValue(':thursday', $this->extractTimeRange($timePeriod, 4));
        $statement->bindValue(':friday', $this->extractTimeRange($timePeriod, 5));
        $statement->bindValue(':saturday', $this->extractTimeRange($timePeriod, 6));
        $statement->bindValue(':sunday', $this->extractTimeRange($timePeriod, 7));
    }

    /**
     * @param int $timePeriodId
     *
     * @throws \PDOException
     */
    private function deleteExtraTimePeriods(int $timePeriodId): void
    {
        $statement = $this->db->prepare(
            $this->translateDbName('DELETE FROM `:db`.timeperiod_exceptions WHERE timeperiod_id = :id')
        );
        $statement->bindValue(':id', $timePeriodId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @param int $timePeriodId
     *
     * @throws \PDOException
     */
    private function deleteTimePeriodTemplates(int $timePeriodId): void
    {
        $statement = $this->db->prepare(
            $this->translateDbName('DELETE FROM `:db`.timeperiod_include_relations WHERE timeperiod_id = :id')
        );
        $statement->bindValue(':id', $timePeriodId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @param TimePeriod|NewTimePeriod $timePeriod
     * @param int $id
     *
     * @return string|null
     */
    private function extractTimeRange(TimePeriod|NewTimePeriod $timePeriod, int $id): ?string
    {
        foreach ($timePeriod->getDays() as $day) {
            if ($day->getDay() === $id) {
                return (string) $day->getTimeRange();
            }
        }

        return null;
    }
}
