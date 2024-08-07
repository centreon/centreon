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

namespace CentreonRemote\Tests\Resources\Traits;

use CentreonRemote\ServiceProvider;
use Pimple\Container;

/**
 * Trait with extension methods for Exporter testing.
 *
 * @version 1.0.0
 */
trait ExporterTrait
{
    /**
     * Set up exporter service in container.
     *
     * <code>
     * public function setUp()
     * {
     *     $container = new \Pimple\Container;
     *     $this->setUpExporter($container);
     * }
     * </code>
     *
     * @param Container $container
     */
    public function setUpExporter(Container $container): void
    {
        $this->container[ServiceProvider::CENTREON_REMOTE_EXPORTER] = new class {
            protected $list = [];

            public function add($class, callable $factory): void
            {
                $this->list[$class] = $factory;
            }

            public function getList(): array
            {
                return $this->list;
            }
        };
    }

    /**
     * Check list of exporters if they are registered in export chain service.
     *
     * <code>
     * $this->checkExporters([
     *     \MyComponenct\Domain\Exporter\MyExporter::class,
     * ]);
     * </code>
     *
     * @param array $checkList
     */
    public function checkExporters(array $checkList): void
    {
        // check exporters
        $exporters = $this->container[ServiceProvider::CENTREON_REMOTE_EXPORTER]->getList();

        foreach ($checkList as $exporter => $factoryTest) {
            $this->assertArrayHasKey($exporter, $exporters);

            if (array_key_exists($exporter, $exporters)) {
                $factoryTest($exporters[$exporter]);
            }
        }
    }
}
