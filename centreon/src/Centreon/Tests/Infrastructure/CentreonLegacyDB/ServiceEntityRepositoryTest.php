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

namespace Centreon\Tests\Infrastructure\CentreonLegacyDB;

use Centreon\Infrastructure\CentreonLegacyDB\EntityPersister;
use Centreon\Infrastructure\CentreonLegacyDB\Mapping\ClassMetadata;
use Centreon\Infrastructure\CentreonLegacyDB\ServiceEntityRepository;
use Centreon\Test\Mock\CentreonDB;
use Centreon\Test\Mock\CentreonDBManagerService;
use Centreon\Test\Traits\TestCaseExtensionTrait;
use Centreon\Tests\Resources\CheckPoint;
use Centreon\Tests\Resources\Mock;
use PHPUnit\Framework\TestCase;

/**
 * Class
 *
 * @class ServiceEntityRepositoryTest
 * @package Centreon\Tests\Infrastructure\CentreonLegacyDB
 */
class ServiceEntityRepositoryTest extends TestCase
{
    use TestCaseExtensionTrait;

    /** @var CentreonDB */
    public $db;

    /** @var CentreonDBManagerService */
    public $manager;

    /** @var Mock\RepositoryMock */
    public $repository;

    public function setUp(): void
    {
        $this->db = new CentreonDB();
        $this->manager = new CentreonDBManagerService();
        $this->repository = new Mock\RepositoryMock($this->db, $this->manager);
    }

    public function testEntityClass(): void
    {
        $this->assertEquals(
            'Centreon\\Infrastructure\\CentreonLegacyDB\\ServiceEntity',
            ServiceEntityRepository::entityClass()
        );
    }

    public function testGetEntityPersister(): void
    {
        $result = $this->repository->getEntityPersister();
        $classMetadata = $this->getProtectedProperty($result, 'classMetadata');

        $this->assertInstanceOf(EntityPersister::class, $result);
        $this->assertEquals(Mock\EntityMock::class, $this->getProtectedProperty($result, 'entityClassName'));
        $this->assertInstanceOf(ClassMetadata::class, $classMetadata);
        $this->assertEquals($this->repository->getClassMetadata()->getTableName(), $classMetadata->getTableName());
    }

    public function testUpdateRelationData(): void
    {
        $list = [1, 2];
        $id = 7;
        $tableName = $this->repository->getClassMetadata()->getTableName();
        $columnA = 'id_a';
        $columnB = 'id_b';
        $checkPoint = (new CheckPoint())
            ->add('select')
            ->add('delete')
            ->add('insert');

        $this->db
            ->addResultSet(
                'SELECT `id_b` FROM `mock_table` WHERE `id_a` = :id_a LIMIT 0, 5000',
                [
                    [
                        $columnA => '1',
                        $columnB => '10',
                    ],
                    [
                        $columnA => '4',
                        $columnB => '10',
                    ],
                ],
                null,
                function ($params) use ($id, $columnA, $checkPoint): void {
                    $checkPoint->mark('select');

                    $this->assertEquals([
                        ":{$columnA}" => $id,
                    ], $params);
                }
            )
            ->addResultSet(
                'DELETE FROM `mock_table` WHERE `id_a` = :id_a AND `id_b` = :id_b',
                [],
                null,
                function ($params) use ($id, $columnA, $columnB, $checkPoint): void {
                    $checkPoint->mark('delete');

                    $this->assertEquals([
                        ":{$columnA}" => $id,
                        ":{$columnB}" => '10',
                    ], $params);
                }
            )
            ->addResultSet(
                'INSERT INTO `mock_table` (`id_a`, `id_b`)  VALUES (:id_a, :id_b)',
                [],
                null,
                function ($params) use ($id, $columnA, $columnB, $checkPoint): void {
                    $checkPoint->mark('insert');

                    $this->assertContains($params, [
                        [
                            ":{$columnA}" => $id,
                            ":{$columnB}" => 1,
                        ],
                        [
                            ":{$columnA}" => $id,
                            ":{$columnB}" => 2,
                        ],
                    ]);
                }
            );

        $result = $this->invokeMethod($this->repository, 'updateRelationData', [
            $list,
            $id,
            $tableName,
            $columnA,
            $columnB,
        ]);

        $checkPoint->assert($this);
    }
}
