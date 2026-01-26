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

namespace Centreon\Tests\Resources\Traits;

use Centreon\ServiceProvider;
use Centreon\Tests\Resources\CheckPoint;
use Symfony\Component\Serializer\Serializer;

/**
 * Trait with extension methods to test the response from webservice
 *
 * @author Centreon
 * @version 1.0.0
 * @package centreon
 * @subpackage test
 */
trait WebServiceExecuteTestTrait
{
    /**
     * Path to fixtures
     *
     * @var string
     */
    protected $fixturePath;

    /**
     * Compare response with control value
     *
     * Require to be set property fixturePath and webservice object to be property of the test case
     *
     * <example>
     * $this->fixturePath = __DIR__ . '/../../Resource/Fixture/';
     * </example>
     *
     * @param string $method
     * @param string $controlJsonFile
     */
    protected function executeTest($method, $controlJsonFile)
    {
        $result = $this->webservice->{$method}();
        $file = realpath($this->fixturePath . $controlJsonFile);

        $this->assertInstanceOf(\JsonSerializable::class, $result);
        $this->assertStringEqualsFile(
            $file,
            json_encode($result),
            "Fixture file with path {$file}"
        );
    }

    /**
     * Mock the repository methods related with the pagination
     *
     * @param array $entities
     * @param CheckPoint $checkPoints
     * @param string $repositoryClass
     * @param array $expectedArgs
     */
    protected function mockRepository(
        array $entities,
        CheckPoint $checkPoints,
        string $repositoryClass,
        ?array $expectedArgs = null,
    ) {
        $methodGetPaginationList = 'getPaginationList';
        $methodGetPaginationListTotal = 'getPaginationListTotal';

        $checkPoints
            ->add($methodGetPaginationList)
            ->add($methodGetPaginationListTotal);

        $this->db
            ->resetResultSet()
            ->addRepositoryMock($repositoryClass, (function () use (
                $entities,
                $checkPoints,
                $repositoryClass,
                $methodGetPaginationList,
                $methodGetPaginationListTotal,
                $expectedArgs
            ) {
                $repository = $this->createMock($repositoryClass);

                $repository->method($methodGetPaginationList)
                    ->will($this->returnCallback(function () use (
                        $entities,
                        $checkPoints,
                        $methodGetPaginationList,
                        $expectedArgs
                    ) {
                        $checkPoints->mark($methodGetPaginationList);

                        if ($expectedArgs) {
                            $this->assertEquals($expectedArgs, func_get_args());
                        }

                        return $entities;
                    }));

                $repository->method($methodGetPaginationListTotal)
                    ->will($this->returnCallback(function () use (
                        $entities,
                        $checkPoints,
                        $methodGetPaginationListTotal
                    ) {
                        $checkPoints->mark($methodGetPaginationListTotal);

                        return count($entities);
                    }));

                return $repository;
            })());
    }

    /**
     * Make query method of the webservice
     *
     * @param array $filters
     */
    protected function mockQuery(array $filters = [])
    {
        $this->webservice
            ->method('query')
            ->will($this->returnCallback(function () use ($filters) {
                return $filters;
            }));
    }

    /**
     * Get the Serializer service from DI
     *
     * @return Serializer
     */
    protected static function getSerializer(): Serializer
    {
        return loadDependencyInjector()[ServiceProvider::SERIALIZER];
    }
}
