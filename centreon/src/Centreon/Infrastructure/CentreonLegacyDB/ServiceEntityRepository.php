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

namespace Centreon\Infrastructure\CentreonLegacyDB;

use Centreon\Infrastructure\Service\CentreonDBManagerService;
use CentreonDB;

/**
 * Compatibility with Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository
 */
abstract class ServiceEntityRepository
{
    /** @var CentreonDB */
    protected $db;

    /** @var CentreonDBManagerService */
    protected $manager;

    /** @var Mapping\ClassMetadata */
    protected $classMetadata;

    /** @var EntityPersister */
    protected $entityPersister;

    /**
     * Get class name and namespace of the Entity
     *
     * <example>
     * public static function entityClass(): string
     * {
     *      return MyEntity::class;
     * }
     * </example>
     *
     * @return string
     */
    public static function entityClass(): string
    {
        return str_replace(
            '\\Domain\\Repository\\',
            '\\Domain\\Entity\\', // change namespace
            substr(static::class, 0, -10) // remove class name suffix "Repository"
        );
    }

    /**
     * Construct
     *
     * @param CentreonDB $db
     * @param CentreonDBManagerService $manager
     */
    public function __construct(CentreonDB $db, ?CentreonDBManagerService $manager = null)
    {
        $this->db = $db;
        $this->manager = $manager;
        $this->classMetadata = new Mapping\ClassMetadata();

        // load metadata for Entity implemented MetadataInterface
        $this->loadMetadata();
    }

    /**
     * Load ClassMetadata with data from the Entity
     *
     * @return void
     */
    protected function loadMetadata(): void
    {
        if (is_subclass_of(static::entityClass(), Mapping\MetadataInterface::class)) {
            (static::entityClass())::loadMetadata($this->classMetadata);

            // prepare the Entity persister
            $this->entityPersister = new EntityPersister(static::entityClass(), $this->classMetadata);
        }
    }

    public function getEntityPersister(): ?EntityPersister
    {
        return $this->entityPersister;
    }

    /**
     * Get ClassMetadata
     *
     * @return Mapping\ClassMetadata
     */
    public function getClassMetadata(): Mapping\ClassMetadata
    {
        return $this->classMetadata;
    }

    /**
     * This method will update the relation table to clean up old data and add the missing
     *
     * @param array $list
     * @param int $id
     * @param string $tableName
     * @param string $columnA
     * @param string $columnB
     */
    protected function updateRelationData(array $list, int $id, string $tableName, string $columnA, string $columnB)
    {
        $listExists = [];
        $listAdd = [];
        $listRemove = [];

        $rows = (function () use ($id, $tableName, $columnA, $columnB) {
            $sql = "SELECT `{$columnB}` FROM `{$tableName}` WHERE `{$columnA}` = :{$columnA} LIMIT 0, 5000";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(":{$columnA}", $id, \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        })();

        // to remove
        foreach ($rows as $row) {
            $pollerId = $row[$columnB];
            if (! in_array($pollerId, $list)) {
                $listRemove[] = $pollerId;
            }

            $listExists[] = $pollerId;
            unset($row, $pollerId);
        }

        // to add
        foreach ($list as $pollerId) {
            if (! in_array($pollerId, $listExists)) {
                $listAdd[] = $pollerId;
            }
            unset($pollerId);
        }

        // removing
        foreach ($listRemove as $pollerId) {
            (function () use ($id, $pollerId, $tableName, $columnA, $columnB): void {
                $sql = "DELETE FROM `{$tableName}` WHERE `{$columnA}` = :{$columnA} AND `{$columnB}` = :{$columnB}";
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(":{$columnA}", $id, \PDO::PARAM_INT);
                $stmt->bindValue(":{$columnB}", $pollerId, \PDO::PARAM_INT);
                $stmt->execute();
            })();
            unset($pollerId);
        }

        // adding
        foreach ($listAdd as $pollerId) {
            (function () use ($id, $pollerId, $tableName, $columnA, $columnB): void {
                $sql = "INSERT INTO `{$tableName}` (`{$columnA}`, `{$columnB}`)  VALUES (:{$columnA}, :{$columnB})";
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(":{$columnA}", $id, \PDO::PARAM_INT);
                $stmt->bindValue(":{$columnB}", $pollerId, \PDO::PARAM_INT);
                $stmt->execute();
            })();
            unset($pollerId);
        }
    }
}
